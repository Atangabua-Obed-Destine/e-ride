<?php

namespace Modules\StoreManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Partner portal: what a partner may change about their own store. */
class StoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $image = 'nullable|image|mimes:jpg,jpeg,png,webp|max:' . convertBytesToKiloBytes(maxUploadSize('image'));

        return array_merge([
            'phone' => 'required|string|max:25',
            'whatsapp' => 'nullable|string|max:25',
            'description' => 'nullable|string|max:1000',
            'default_prep_minutes' => 'required|integer|min:5|max:180',
            'min_order_amount' => 'nullable|numeric|min:0',
            'payout_method' => 'nullable|string|max:30',
            'payout_number' => 'nullable|string|max:40',
            'payout_name' => 'nullable|string|max:120',
            'logo' => $image,
            'cover' => $image,
        ], StoreHoursRules::rules());
    }
}
