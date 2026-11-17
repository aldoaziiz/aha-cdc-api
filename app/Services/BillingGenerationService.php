<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\BillingItem;
use App\Models\PaymentStatus;
use App\Models\Registration;
use App\Models\RegistrationProgram;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingGenerationService
{
    public function previewSingle(int $registrationId): array
    {
        $registrations = $this->loadRegistrations([$registrationId]);
        $this->ensureCanGenerate($registrations->first());

        return $this->previewData($registrations);
    }

    public function previewCombined(array $registrationIds): array
    {
        $registrations = $this->loadRegistrations($registrationIds);
        $this->ensureCanCombine($registrations);

        return $this->previewData($registrations);
    }

    public function generateSingle(int $registrationId, array $discounts = []): Billing
    {
        return $this->generate([$registrationId], $discounts, false);
    }

    public function generateCombined(array $registrationIds, array $discounts = []): Billing
    {
        return $this->generate($registrationIds, $discounts, true);
    }

    private function generate(array $registrationIds, array $discounts, bool $combined): Billing
    {
        return DB::transaction(function () use ($registrationIds, $discounts, $combined) {
            $registrations = $this->loadRegistrations($registrationIds, true);

            if ($combined) {
                $this->ensureCanCombine($registrations);
            } else {
                $this->ensureCanGenerate($registrations->first());
            }

            $items = $this->flattenItems($registrations);
            $discountsByItemId = $this->validateAndIndexDiscounts($discounts, $items);

            $total = 0;
            foreach ($items as &$item) {
                $discount = (int) ($discountsByItemId[$item['registration_program_id']] ?? 0);

                if ($discount > $item['subtotal']) {
                    throw ValidationException::withMessages([
                        'discounts' => "Discount for {$item['description']} cannot exceed its subtotal.",
                    ]);
                }

                $item['discount_amount'] = $discount;
                $total += $item['subtotal'] - $discount;
            }
            unset($item);

            $today = now()->format('Ymd');
            $count = Billing::whereDate('created_at', today())->count() + 1;
            $invoiceNumber = 'INV-'.$today.'-'.str_pad($count, 4, '0', STR_PAD_LEFT);
            $paidStatusId = PaymentStatus::query()
                ->whereRaw('LOWER(name) = ?', ['paid'])
                ->value('id');

            $isFullyDiscounted = (float) $total === 0.0;

            if ($isFullyDiscounted && ! $paidStatusId) {
                throw ValidationException::withMessages([
                    'payment_status' => 'The Paid payment status is not configured.',
                ]);
            }

            $billing = Billing::create([
                'registration_id' => $registrations->first()->id,
                'invoice_number' => $invoiceNumber,
                'invoice_token' => Str::uuid(),
                'payment_status_id' => $isFullyDiscounted ? $paidStatusId : 1,
                'total_amount' => $total,
            ]);

            foreach ($registrations as $registration) {
                $registration->update(['billing_id' => $billing->id]);
            }

            foreach ($items as $item) {
                BillingItem::create([
                    'billing_id' => $billing->id,
                    'registration_id' => $item['registration_id'],
                    'program_id' => $item['program_id'],
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                    'discount_amount' => $item['discount_amount'],
                ]);
            }

            return $billing->fresh([
                'items.registration',
                'registrations.child',
                'registrations.payer',
                'paymentStatus',
            ]);
        });
    }

    private function loadRegistrations(array $registrationIds, bool $lock = false): Collection
    {
        $query = Registration::query()
            ->with(['billing', 'child:id,name'])
            ->whereIn('id', $registrationIds)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $registrations = $query->get();
        if ($registrations->count() !== count(array_unique($registrationIds))) {
            throw ValidationException::withMessages([
                'registration_ids' => 'One or more selected registrations are no longer available.',
            ]);
        }

        $programsQuery = RegistrationProgram::query()
            ->with('program')
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->orderBy('id');

        if ($lock) {
            $programsQuery->lockForUpdate();
        }

        $programsByRegistration = $programsQuery->get()->groupBy('registration_id');

        foreach ($registrations as $registration) {
            $registration->setRelation(
                'registrationPrograms',
                $programsByRegistration->get($registration->id, collect())
            );
        }

        return $registrations;
    }

    private function ensureCanGenerate(?Registration $registration): void
    {
        if (! $registration || $registration->billing_id) {
            throw ValidationException::withMessages([
                'registration' => 'This registration is already billed or no longer available.',
            ]);
        }

        if ($registration->registrationPrograms->isEmpty()) {
            throw ValidationException::withMessages([
                'registration' => 'No registration programs found.',
            ]);
        }
    }

    private function ensureCanCombine(Collection $registrations): void
    {
        if ($registrations->count() < 2) {
            throw ValidationException::withMessages([
                'registration_ids' => 'Select at least two registrations.',
            ]);
        }

        foreach ($registrations as $registration) {
            $this->ensureCanGenerate($registration);
        }

        if (
            $registrations->pluck('clinic_id')->unique()->count() !== 1
            || $registrations->pluck('payer_id')->unique()->count() !== 1
        ) {
            throw ValidationException::withMessages([
                'registration_ids' => 'Selected registrations must have the same clinic and payer.',
            ]);
        }
    }

    private function previewData(Collection $registrations): array
    {
        $items = $this->flattenItems($registrations);

        return [
            'registrations' => $registrations->map(fn ($registration) => [
                'id' => (int) $registration->id,
                'registration_number' => $registration->registration_number,
                'child_name' => $registration->child?->name,
            ])->values(),
            'items' => $items,
            'subtotal' => array_sum(array_column($items, 'subtotal')),
        ];
    }

    private function flattenItems(Collection $registrations): array
    {
        $items = [];

        foreach ($registrations as $registration) {
            foreach ($registration->registrationPrograms as $registrationProgram) {
                $items[] = [
                    'registration_program_id' => (int) $registrationProgram->id,
                    'registration_id' => (int) $registration->id,
                    'registration_number' => $registration->registration_number,
                    'child_name' => $registration->child?->name,
                    'program_id' => (int) $registrationProgram->program_id,
                    'description' => $registrationProgram->program?->name ?? '-',
                    'price' => (float) $registrationProgram->price,
                    'quantity' => 1,
                    'subtotal' => (float) $registrationProgram->price,
                    'discount_amount' => 0,
                ];
            }
        }

        return $items;
    }

    private function validateAndIndexDiscounts(array $discounts, array $items): array
    {
        $validItemIds = collect($items)
            ->pluck('registration_program_id')
            ->map(fn ($id) => (int) $id);
        $discountsByItemId = collect($discounts)->keyBy(
            fn (array $discount) => (int) $discount['registration_program_id']
        );
        $unknownItemIds = $discountsByItemId->keys()
            ->map(fn ($id) => (int) $id)
            ->diff($validItemIds);

        if ($unknownItemIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'discounts' => 'A discount was supplied for a program item outside the selected registration(s).',
            ]);
        }

        $indexed = [];
        foreach ($discountsByItemId as $itemId => $discount) {
            $amount = $discount['discount_amount'] ?? 0;
            if (! is_numeric($amount) || (float) $amount < 0 || floor((float) $amount) !== (float) $amount) {
                throw ValidationException::withMessages([
                    'discounts' => 'Discounts must be whole rupiah amounts of zero or more.',
                ]);
            }
            $indexed[(int) $itemId] = (int) $amount;
        }

        return $indexed;
    }
}
