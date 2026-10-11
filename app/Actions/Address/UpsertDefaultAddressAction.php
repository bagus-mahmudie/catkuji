<?php

namespace App\Actions\Address;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;

class UpsertDefaultAddressAction
{
    public function execute(User $user, array $data): UserAddress
    {
        return DB::transaction(function () use ($user, $data) {
            $fullAddress = trim(
                collect([
                    $data['address_line'] ?? null,
                    $data['address_details'] ?? null,
                ])->filter(fn ($value) => filled($value))->implode("\n")
            );

            $existingDefault = UserAddress::query()
                ->where('user_id', $user->id)
                ->where('is_default', true)
                ->lockForUpdate()
                ->first();

            if ($existingDefault) {
                $existingDefault->update([
                    'label' => 'Alamat Utama',
                    'recipient_name' => $data['recipient_name'],
                    'recipient_phone' => $data['recipient_phone'],
                    'province' => $data['province'],
                    'city' => $data['city'],
                    'district' => $data['district'],
                    'subdistrict' => $data['subdistrict'] ?? null,
                    'postal_code' => $data['postal_code'],
                    'full_address' => $fullAddress,
                    'notes' => $data['notes'] ?? null,
                    'is_default' => true,
                ]);

                UserAddress::query()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $existingDefault->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);

                return $existingDefault->fresh();
            }

            UserAddress::query()
                ->where('user_id', $user->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            return UserAddress::create([
                'user_id' => $user->id,
                'label' => 'Alamat Utama',
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'],
                'province' => $data['province'],
                'city' => $data['city'],
                'district' => $data['district'],
                'subdistrict' => $data['subdistrict'] ?? null,
                'postal_code' => $data['postal_code'],
                'full_address' => $fullAddress,
                'notes' => $data['notes'] ?? null,
                'is_default' => true,
            ]);
        });
    }
}