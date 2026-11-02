<?php

namespace Modules\StoreManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:1|max:10000000',
            'store_menu_category_id' => 'nullable|uuid',
            'is_available' => 'nullable|boolean',
            'sort' => 'nullable|integer',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
        ];
    }
}
