<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateGuardianUserService
{
    public function execute(
        string $email
    ): User {

        // ======================
        // CHECK EXISTING USER
        // ======================

        $existingUser = User::query()
            ->where('email', $email)
            ->first();

        if ($existingUser) {
            return $existingUser;
        }

        // ======================
        // GENERATE PASSWORD
        // ======================

        $temporaryPassword =
            Str::random(10);

        // ======================
        // CREATE USER
        // ======================

        return User::query()
            ->create([

                'name' => $email,

                'email' => $email,

                'password' => Hash::make(
                    $temporaryPassword
                ),

                'role' => 'guardian',

            ]);
    }
}
