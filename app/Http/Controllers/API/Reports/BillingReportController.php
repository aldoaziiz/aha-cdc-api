<?php

namespace App\Http\Controllers\API\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingReportController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'start_month' => ['nullable', 'integer', 'between:1,12'],
            'end_month' => ['nullable', 'integer', 'between:1,12'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'child_name' => ['nullable', 'string', 'max:150'],
            'program_category_id' => ['nullable', 'integer', 'exists:program_categories,id'],
            'payment_status_id' => ['nullable', 'integer', 'exists:payment_statuses,id'],
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        $startMonth = (int) ($validated['start_month'] ?? 1);
        $endMonth = (int) ($validated['end_month'] ?? 12);

        if ($startMonth > $endMonth) {
            return response()->json([
                'message' => 'Start month cannot be greater than end month.',
            ], 422);
        }

        $startDate = Carbon::create($year, $startMonth, 1)
            ->startOfMonth();
        $endDate = Carbon::create($year, $endMonth, 1)
            ->endOfMonth();

        $registrationBilling = DB::table('billing_items as bi')
            ->leftJoin('programs as p', 'p.id', '=', 'bi.program_id')
            ->leftJoin('program_categories as pc', 'pc.id', '=', 'p.program_category_id')
            ->whereNotNull('bi.registration_id')
            ->select('bi.registration_id')
            ->selectRaw('SUM(bi.subtotal - COALESCE(bi.discount_amount, 0)) as registration_total')
            ->selectRaw('SUM(COALESCE(bi.discount_amount, 0)) as registration_discount')
            ->selectRaw("GROUP_CONCAT(DISTINCT pc.name ORDER BY pc.name SEPARATOR ', ') as program_categories")
            ->groupBy('bi.registration_id');

        $query = DB::table('registrations as r')
            ->join('billings as b', 'b.id', '=', 'r.billing_id')
            ->join('children as c', 'c.id', '=', 'r.child_id')
            ->leftJoin('payment_statuses as ps', 'ps.id', '=', 'b.payment_status_id')
            ->leftJoinSub($registrationBilling, 'rb', function ($join) {
                $join->on('rb.registration_id', '=', 'r.id');
            })
            ->whereBetween('b.created_at', [$startDate, $endDate])
            ->select([
                'r.id as registration_id',
                'r.registration_number',
                'b.id as billing_id',
                'b.invoice_number',
                'c.name as child_name',
                'ps.id as payment_status_id',
                'ps.name as payment_status',
                'b.created_at as invoice_date',
            ])
            ->selectRaw('COALESCE(rb.registration_total, 0) as total_billing')
            ->selectRaw('COALESCE(rb.registration_discount, 0) as discount_amount')
            ->selectRaw("COALESCE(rb.program_categories, '') as program_categories");

        if (! empty($validated['registration_number'])) {
            $query->where('r.registration_number', 'like', '%'.$validated['registration_number'].'%');
        }

        if (! empty($validated['invoice_number'])) {
            $query->where('b.invoice_number', 'like', '%'.$validated['invoice_number'].'%');
        }

        if (! empty($validated['child_name'])) {
            $query->where('c.name', 'like', '%'.$validated['child_name'].'%');
        }

        if (! empty($validated['payment_status_id'])) {
            $query->where('b.payment_status_id', $validated['payment_status_id']);
        }

        if (! empty($validated['program_category_id'])) {
            $query->whereExists(function ($subquery) use ($validated) {
                $subquery->selectRaw('1')
                    ->from('billing_items as filter_bi')
                    ->join('programs as filter_p', 'filter_p.id', '=', 'filter_bi.program_id')
                    ->whereColumn('filter_bi.registration_id', 'r.id')
                    ->where('filter_p.program_category_id', $validated['program_category_id']);
            });
        }

        $rows = $query
            ->orderByDesc('b.created_at')
            ->orderBy('r.registration_number')
            ->get()
            ->map(function ($row) {
                return [
                    'registration_id' => (int) $row->registration_id,
                    'registration_number' => $row->registration_number,
                    'billing_id' => (int) $row->billing_id,
                    'invoice_number' => $row->invoice_number,
                    'child_name' => $row->child_name,
                    'program_categories' => $row->program_categories,
                    'total_billing' => (float) $row->total_billing,
                    'discount_amount' => (float) $row->discount_amount,
                    'payment_status_id' => (int) $row->payment_status_id,
                    'payment_status' => $row->payment_status,
                    'invoice_date' => $row->invoice_date,
                ];
            });

        $totalBilling = (float) $rows->sum('total_billing');
        $totalPaid = (float) $rows
            ->filter(fn ($row) => strtolower((string) $row['payment_status']) === 'paid')
            ->sum('total_billing');

        return response()->json([
            'filters' => [
                'year' => $year,
                'start_month' => $startMonth,
                'end_month' => $endMonth,
            ],
            'summary' => [
                'total_billing' => $totalBilling,
                'total_paid' => $totalPaid,
                'total_unpaid' => $totalBilling - $totalPaid,
                'total_registrations' => $rows->count(),
            ],
            'program_categories' => DB::table('program_categories')
                ->orderBy('name')
                ->get(['id', 'name']),
            'payment_statuses' => DB::table('payment_statuses')
                ->orderBy('id')
                ->get(['id', 'name']),
            'data' => $rows->values(),
        ]);
    }
}
