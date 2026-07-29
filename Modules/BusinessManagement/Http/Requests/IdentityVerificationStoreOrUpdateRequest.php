<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IdentityVerificationStoreOrUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'initiate_face_verification' => 'required|array|min:1',
            'initiate_face_verification.*' => 'in:during_sign_up,at_intervals',

            'choose_verification_when_to_trigger' => [
                Rule::requiredIf(fn () =>
                in_array('at_intervals', $this->input('initiate_face_verification', []), true)
                ),
                Rule::in([
                    'within_a_time_period',
                    'before_going_online',
                    'randomly_during_trips',
                ]),
            ],

            'trigger_frequency_time_within_a_time_period' => [
                'nullable',
                Rule::requiredIf(fn () =>
                    $this->choose_verification_when_to_trigger === 'within_a_time_period'
                    && in_array('at_intervals', $this->input('initiate_face_verification', []), true)
                ),
                'integer',
                'min:1',
            ],

            'trigger_frequency_time_type_within_a_time_period' => [
                Rule::requiredIf(fn () =>
                    $this->choose_verification_when_to_trigger === 'within_a_time_period'
                ),
                Rule::in(['day', 'hour', 'minute']),
            ],

            'trips_required_before_random_verification' => [
                'nullable',
                Rule::requiredIf(fn () =>
                    $this->choose_verification_when_to_trigger === 'randomly_during_trips'
                    && in_array('at_intervals', $this->input('initiate_face_verification', []), true)
                ),
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
