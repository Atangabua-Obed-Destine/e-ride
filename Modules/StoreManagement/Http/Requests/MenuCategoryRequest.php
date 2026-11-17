<?php

namespace Modules\StoreManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MenuCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:80',
            'sort' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'schedule' => 'nullable|boolean',
            'served_days' => 'nullable|array',
            'served_days.*' => 'in:mon,tue,wed,thu,fri,sat,sun',
            'served_from' => 'nullable|date_format:H:i|required_with:served_until',
            'served_until' => 'nullable|date_format:H:i|required_with:served_from|after:served_from',
        ];
    }

    public function messages(): array
    {
        return [
            'served_days.required' => translate('Pick at least one day.'),
            'served_days.min' => translate('Pick at least one day.'),
            'served_until.after' => translate('The end time must be after the start time.'),
            'served_from.required_with' => translate('Give both a start and an end time, or leave both empty.'),
            'served_until.required_with' => translate('Give both a start and an end time, or leave both empty.'),
        ];
    }

    public function withValidator($validator): void
    {
        // A form with serving times must keep at least one day ticked.
        $validator->sometimes('served_days', 'required|array|min:1', fn() => $this->boolean('schedule'));
    }
}
