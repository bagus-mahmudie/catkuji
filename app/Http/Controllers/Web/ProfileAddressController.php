<?php

namespace App\Http\Controllers\Web;

use App\Actions\Address\UpsertDefaultAddressAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpsertAddressRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileAddressController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load(['wallet', 'defaultAddress']);

        $walletBalance = (int) ($user->wallet?->balance ?? 0);
        $catKoinBalance = $walletBalance;
        $tokenBalance = 0;

        $address = $user->defaultAddress;

        $addressLine = '';
        $addressDetails = '';

        if ($address?->full_address) {
            $parts = preg_split("/\r\n|\n|\r/", $address->full_address, 2);
            $addressLine = $parts[0] ?? '';
            $addressDetails = $parts[1] ?? '';
        }

        return Inertia::render('Profile/Address', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar
                    ? Storage::disk('public')->url($user->avatar)
                    : null,
            ],
            'tokenBalance' => $tokenBalance,
            'catKoinBalance' => $catKoinBalance,
            'walletBalance' => $walletBalance,
            'address' => $address ? [
                'id' => $address->id,
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'recipient_phone' => $address->recipient_phone,
                'province' => $address->province,
                'city' => $address->city,
                'district' => $address->district,
                'subdistrict' => $address->subdistrict,
                'postal_code' => $address->postal_code,
                'address_line' => $addressLine,
                'address_details' => $addressDetails,
                'full_address' => $address->full_address,
                'notes' => $address->notes,
            ] : null,
        ]);
    }

    public function update(
        UpsertAddressRequest $request,
        UpsertDefaultAddressAction $upsertDefaultAddressAction
    ): RedirectResponse {
        $upsertDefaultAddressAction->execute(
            $request->user(),
            $request->validated()
        );

        return redirect()
            ->route('profile.address.edit')
            ->with('success', 'Alamat pengiriman berhasil disimpan.');
    }
}