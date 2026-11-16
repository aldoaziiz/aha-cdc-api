<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\BillingItem;
use App\Models\Registration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    private function forbidNonAdmin()
    {
        if (
            auth()->user()->role !==
            'admin'
        ) {

            abort(
                403,
                'Forbidden'
            );

        }
    }

    private function forbidTherapist()
    {
        if (
            auth()->user()->role ===
            'therapist'
        ) {

            abort(
                403,
                'Forbidden'
            );

        }
    }

    public function show($id)
    {
        $billing = Billing::with([

            'items',

            'registration.child',

            'registration.payer',

            'registrations.child',

            'registrations.payer',

            'items.registration',

            'paymentStatus',

        ])->findOrFail($id);

        return response()->json([

            'data' => $billing,

        ]);
    }

    public function generateBilling($id)
    {
        $this->forbidNonAdmin();

        return DB::transaction(function () use ($id) {

            $registration = Registration::with([
                'billing',
                'registrationPrograms.program',
            ])->findOrFail($id);

            // ======================
            // ALREADY HAS BILLING
            // ======================

            if ($registration->billing) {

                return response()->json([
                    'message' => 'Billing already exists.',
                ], 422);
            }

            // ======================
            // MUST HAVE PROGRAMS
            // ======================

            if ($registration->registrationPrograms->isEmpty()) {

                return response()->json([
                    'message' => 'No registration programs found.',
                ], 422);
            }

            // ======================
            // GENERATE INVOICE NUMBER
            // ======================

            $today = now()->format('Ymd');

            $count =
                Billing::whereDate(
                    'created_at',
                    today()
                )->count() + 1;

            $invoiceNumber =
                'INV-'.
                $today.
                '-'.
                str_pad(
                    $count,
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            // ======================
            // CREATE BILLING
            // ======================

            $billing = Billing::create([

                'registration_id' => $registration->id,

                'invoice_number' => $invoiceNumber,

                'invoice_token' => Str::uuid(),

                'payment_status_id' => 1,

                'total_amount' => 0,

            ]);

            $registration->update(['billing_id' => $billing->id]);

            // ======================
            // CREATE BILLING ITEMS
            // ======================

            $total = 0;

            foreach (
                $registration->registrationPrograms as $item
            ) {

                $subtotal =
                    $item->price;

                BillingItem::create([

                    'billing_id' => $billing->id,

                    'registration_id' => $registration->id,

                    'program_id' => $item->program_id,

                    'description' => $item->program?->name ?? '-',

                    'price' => $item->price,

                    'quantity' => 1,

                    'subtotal' => $subtotal,

                ]);

                $total += $subtotal;
            }

            // ======================
            // UPDATE TOTAL
            // ======================

            $billing->update([

                'total_amount' => $total,

            ]);

            return response()->json([

                'message' => 'Billing generated successfully.',

                'data' => $billing->fresh(),

            ]);
        });
    }

    public function eligibleRegistrations(Request $request)
    {
        $this->forbidNonAdmin();

        $query = Registration::with(['child:id,name', 'payer:id,name', 'clinic:id,name', 'programs:id,name'])
            ->whereNull('billing_id')
            ->whereHas('registrationPrograms')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('registration_number', 'like', '%'.$search.'%')
                        ->orWhereHas('child', fn ($child) => $child->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest();

        return response()->json($query->paginate($request->integer('per_page', 10)));
    }

    public function generateCombined(Request $request)
    {
        $this->forbidNonAdmin();

        $validated = $request->validate([
            'registration_ids' => 'required|array|min:2',
            'registration_ids.*' => 'required|integer|distinct|exists:registrations,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $registrations = Registration::with('registrationPrograms.program')
                ->whereIn('id', $validated['registration_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($registrations->count() !== count($validated['registration_ids'])) {
                return response()->json(['message' => 'Some registrations could not be found.'], 422);
            }

            if ($registrations->contains(fn ($registration) => $registration->billing_id || $registration->registrationPrograms->isEmpty())) {
                return response()->json(['message' => 'All selected registrations must be unbilled and have programs.'], 422);
            }

            if ($registrations->pluck('clinic_id')->unique()->count() !== 1 || $registrations->pluck('payer_id')->unique()->count() !== 1) {
                return response()->json(['message' => 'Selected registrations must have the same clinic and payer.'], 422);
            }

            $today = now()->format('Ymd');
            $count = Billing::whereDate('created_at', today())->count() + 1;
            $invoiceNumber = 'INV-'.$today.'-'.str_pad($count, 4, '0', STR_PAD_LEFT);
            $billing = Billing::create([
                'registration_id' => $registrations->first()->id,
                'invoice_number' => $invoiceNumber,
                'invoice_token' => Str::uuid(),
                'payment_status_id' => 1,
                'total_amount' => 0,
            ]);

            $total = 0;
            foreach ($registrations as $registration) {
                $registration->update(['billing_id' => $billing->id]);

                foreach ($registration->registrationPrograms as $programItem) {
                    $subtotal = $programItem->price;
                    BillingItem::create([
                        'billing_id' => $billing->id,
                        'registration_id' => $registration->id,
                        'program_id' => $programItem->program_id,
                        'description' => $programItem->program?->name ?? '-',
                        'price' => $programItem->price,
                        'quantity' => 1,
                        'subtotal' => $subtotal,
                    ]);
                    $total += $subtotal;
                }
            }

            $billing->update(['total_amount' => $total]);

            return response()->json([
                'message' => 'Combined billing generated successfully.',
                'data' => $billing->load(['items.registration', 'registrations.child', 'registrations.payer', 'paymentStatus']),
            ], 201);
        });
    }

    public function cancel($id)
    {
        $this->forbidNonAdmin();

        return DB::transaction(function () use ($id) {

            $billing = Billing::with('items')
                ->findOrFail($id);

            if ($billing->payment_status_id != 1) {

                return response()->json([
                    'message' => 'Only unpaid billing can be cancelled.',
                ], 422);
            }

            $billing->items()->delete();

            $billing->delete();

            return response()->json([
                'message' => 'Billing cancelled successfully.',
            ]);
        });
    }

    public function publicShowByToken($token)
    {
        $billing = Billing::with([

            'items',

            'registration.child',

            'registration.payer',

            'registrations.child',

            'registrations.payer',

            'items.registration',

            'paymentStatus',

        ])
            ->where(
                'invoice_token',
                $token
            )
            ->firstOrFail();

        return response()->json([

            'data' => $billing,

        ]);
    }

    public function uploadReceiptByToken(Request $request, $token)
    {
        return DB::transaction(function () use ($request, $token) {

            $billing = Billing::where(
                'invoice_token',
                $token
            )->firstOrFail();

            // ======================
            // ONLY UNPAID
            // ======================

            if ($billing->payment_status_id != 1) {

                return response()->json([
                    'message' => 'Receipt cannot be uploaded.',
                ], 422);
            }

            // ======================
            // VALIDATION
            // ======================

            $request->validate([
                'receipt' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png',
                    'max:5120', // 5 MB
                ],
            ]);

            // ======================
            // DELETE OLD FILE
            // ======================

            if ($billing->payment_receipt) {

                Storage::delete($billing->payment_receipt);
            }

            // ======================
            // STORE FILE
            // ======================

            $path = $request
                ->file('receipt')
                ->store(
                    'payment-receipts',
                );

            // ======================
            // UPDATE BILLING
            // ======================

            $billing->update([

                'payment_receipt' => $path,

                'payment_status_id' => 2, // Waiting

                'admin_note' => null,

            ]);

            return response()->json([

                'message' => 'Receipt uploaded successfully.',

            ]);
        });
    }

    public function approve($id)
    {
        $this->forbidNonAdmin();

        $billing = Billing::findOrFail($id);

        if ($billing->payment_status_id != 2) {

            return response()->json([
                'message' => 'Only waiting billing can be approved.',
            ], 422);
        }

        $billing->update([

            'payment_status_id' => 3, // Paid

            'admin_note' => null,

        ]);

        return response()->json([

            'message' => 'Payment approved successfully.',

        ]);
    }

    public function reject(Request $request, $id)
    {
        $this->forbidNonAdmin();

        $request->validate([

            'admin_note' => [
                'required',
                'string',
                'max:1000',
            ],

        ]);

        $billing = Billing::findOrFail($id);

        if ($billing->payment_status_id != 2) {

            return response()->json([
                'message' => 'Only waiting billing can be rejected.',
            ], 422);
        }

        if ($billing->payment_receipt) {

            Storage::delete($billing->payment_receipt);
        }

        $billing->update([

            'payment_status_id' => 1, // Unpaid

            'admin_note' => $request->admin_note,

            'payment_receipt' => null,

        ]);

        return response()->json([

            'message' => 'Payment rejected successfully.',

        ]);
    }

    public function downloadPdf($id)
    {
        $billing = Billing::with([
            'items',
            'registration.child',
            'registration.payer',

            'registrations.child',

            'registrations.payer',

            'items.registration',
            'paymentStatus',
        ])->findOrFail($id);

        $downloadedBy = auth()->user();

        $generatedAt = now()->locale('id');

        $pdf = Pdf::loadView(
            'pdf.invoice',
            compact(
                'billing',
                'downloadedBy',
                'generatedAt'
            )
        );

        return $pdf->download(
            "{$billing->invoice_number}.pdf"
        );
    }
}
