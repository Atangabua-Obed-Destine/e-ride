<?php

namespace Modules\StoreManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\StoreManagement\Entities\Store;

/** Admin: create or edit a partner store and its partner login. */
class StoreSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        /** @var Store|null $store */
        $store = $this->route('store');
        $ownerId = $store?->user_id;
        $image = 'nullable|image|mimes:jpg,jpeg,png,webp|max:' . convertBytesToKiloBytes(maxUploadSize('image'));

        return array_merge([
            'name' => 'required|string|max:120',
            'type' => ['required', Rule::in(Store::TYPES)],
            'phone' => 'required|string|max:25',
            'whatsapp' => 'nullable|string|max:25',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'description' => 'nullable|string|max:1000',
            'default_prep_minutes' => 'required|integer|min:5|max:180',
            'min_order_amount' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'is_open' => 'nullable|boolean',
            'sort' => 'nullable|integer',
            'payout_method' => 'nullable|string|max:30',
            'payout_number' => 'nullable|string|max:40',
            'payout_name' => 'nullable|string|max:120',
            'logo' => $image,
            'cover' => $image,
            'owner_name' => 'required|string|max:120',
            'owner_email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($ownerId)],
            'password' => [$store ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
        ], StoreHoursRules::rules());
    }

    public function messages(): array
    {
        return [
            'owner_email.unique' => translate('This email already belongs to another account. Use a different email for the partner login.'),
        ];
    }
}
