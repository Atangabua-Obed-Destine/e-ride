<?php

namespace Modules\PlaceManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PlaceManagement\Entities\Place;

/** Admin: create or edit a verified place to stay. */
class PlaceSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        /** @var Place|null $place */
        $place = $this->route('place');
        $image = 'image|mimes:jpg,jpeg,png,webp|max:' . convertBytesToKiloBytes(maxUploadSize('image'));
        $time = ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'];

        return [
            'name' => 'required|string|max:120',
            'category' => ['required', Rule::in(Place::CATEGORIES)],
            'google_place_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('places', 'google_place_id')->ignore($place?->id)->whereNull('deleted_at')],
            'description' => 'nullable|string|max:1500',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'phone' => 'nullable|string|max:25',
            'whatsapp' => 'nullable|string|max:25',
            'website' => 'nullable|url|max:255',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0|gte:price_min',
            'amenities' => 'nullable|array',
            'amenities.*' => [Rule::in(Place::AMENITIES)],
            'check_in' => $time,
            'check_out' => $time,
            'reception_24h' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort' => 'nullable|integer',
            'new_photos' => 'nullable|array|max:' . Place::MAX_PHOTOS,
            'new_photos.*' => $image,
            'remove_photos' => 'nullable|array',
            'remove_photos.*' => 'string',
            'cover_photo' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'google_place_id.unique' => translate('Another listing is already linked to this Google place.'),
            'price_max.gte' => translate('The highest price must be at least the lowest price.'),
            'check_in.regex' => translate('Use the 24-hour format, for example 14:00.'),
            'check_out.regex' => translate('Use the 24-hour format, for example 11:00.'),
        ];
    }
}
