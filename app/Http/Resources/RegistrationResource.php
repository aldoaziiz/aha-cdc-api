<?php

namespace App\Http\Resources;

use App\Models\GuardianRole;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'registration_number' => $this->registration_number,
            'created_at' => $this->created_at,

            'child' => [
                'id' => $this->child->id,
                'name' => $this->child->name,
                'birth_date' => $this->child->birth_date,

                'guardians' => $this->child->guardians->map(function ($g) {
                    $role = GuardianRole::find($g->pivot->guardian_role_id);

                    return [
                        'id' => $g->id,
                        'name' => $g->name,
                        'phone' => $g->phone,
                        'guardian_role' => [
                            'id' => $role?->id,
                            'name' => $role?->name,
                        ],
                    ];
                }),
            ],

            'program' => $this->program ? [
                'id' => $this->program->id,
                'name' => $this->program->name,
                'price' => $this->program->price ?? 0,
            ] : null,

            'payer' => $this->payer ? [
                'id' => $this->payer->id,
                'name' => $this->payer->name,
            ] : null,

            'payment_status' => $this->paymentStatus ? [
                'id' => $this->paymentStatus->id,
                'name' => $this->paymentStatus->name,
            ] : null,

            'payment_receipt' => $this->payment_receipt,
            'complaint' => $this->complaint,
        ];
    }
}
