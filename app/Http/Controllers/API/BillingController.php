<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Registration;
use App\Services\BillingGenerationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    public function previewGeneration($id, BillingGenerationService $billingGenerationService)
    {
        $this->forbidNonAdmin();

        return response()->json([
            'data' => $billingGenerationService->previewSingle((int) $id),
        ]);
    }

    public function generateBilling(Request $request, $id, BillingGenerationService $billingGenerationService)
    {
        $this->forbidNonAdmin();

        $validated = $request->validate($this->discountValidationRules());
        $billing = $billingGenerationService->generateSingle(
            (int) $id,
            $validated['discounts'] ?? []
        );

        return response()->json([
            'message' => 'Billing generated successfully.',
            'data' => $billing,
        ]);
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

    public function previewCombined(Request $request, BillingGenerationService $billingGenerationService)
    {
        $this->forbidNonAdmin();

        $validated = $request->validate([
            'registration_ids' => 'required|array|min:2',
            'registration_ids.*' => 'required|integer|distinct|exists:registrations,id',
        ]);

        return response()->json([
            'data' => $billingGenerationService->previewCombined($validated['registration_ids']),
        ]);
    }

    public function generateCombined(Request $request, BillingGenerationService $billingGenerationService)
    {
        $this->forbidNonAdmin();

        $validated = $request->validate([
            'registration_ids' => 'required|array|min:2',
            'registration_ids.*' => 'required|integer|distinct|exists:registrations,id',
        ] + $this->discountValidationRules());

        $billing = $billingGenerationService->generateCombined(
            $validated['registration_ids'],
            $validated['discounts'] ?? []
        );

        return response()->json([
            'message' => 'Combined billing generated successfully.',
            'data' => $billing,
        ], 201);
    }

    private function discountValidationRules(): array
    {
        return [
            'discounts' => ['sometimes', 'array'],
            'discounts.*.registration_program_id' => ['required', 'integer', 'distinct'],
            'discounts.*.discount_amount' => ['required', 'integer', 'min:0', 'max:9999999999999'],
        ];
    }

    public function cancel($id)
    {
        $this->forbidNonAdmin();

        return DB::transaction(function () use ($id) {

            $billing = Billing::with('items')
                ->findOrFail($id);

            $isPaidZeroAmountInvoice =
                (int) $billing->payment_status_id === 3
                && (float) $billing->total_amount === 0.0
                && ! $billing->payment_receipt;

            if ($billing->payment_status_id != 1 && ! $isPaidZeroAmountInvoice) {

                return response()->json([
                    'message' => 'Only unpaid billing or a zero-total auto-paid billing without a receipt can be cancelled.',
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
