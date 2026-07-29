<?php

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class WalletBonusStoreOrUpdateRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = $this->id;
        $data = $this;
        return [
            'bonus_title' => 'required|string|max:80',
            'short_desc' => 'required|string|max:255',
            'bonus_amount' => [
                'required',
                function ($attribute, $value, $fail) use ($data) {
                    $amountType = $data['amount_type'];
                    $minBonusAmount = $data['minimum_add_amount'];
                    $bonusAmount = $data['bonus_amount'];
                    if ($amountType == AMOUNT and $value <= 0) {
                        $fail('The bonus amount  value must be gather than 0 ');
                    }
                    if ($amountType === PERCENTAGE && $value <= 0) {
                        $fail('The bonus percent value must be gather than 0 ');
                    }
                    if ($amountType === PERCENTAGE && $value > 100) {
                        $fail('The bonus percent value must be less than 100% ');
                    }
                    if ($amountType !== PERCENTAGE && $bonusAmount >= $minBonusAmount) {
                        $fail('Bonus amount is more than or not equal to minimum bonus amount');
                    }
                }
            ],
            'amount_type' => 'required|string|in:'. AMOUNT. ',' . PERCENTAGE,
            'minimum_add_amount' => 'required|numeric|gt:0',
            'maximum_bonus' => $this->amount_type === PERCENTAGE ? 'required|numeric|gt:0' : 'nullable',
            'start_date' => 'required|after_or_equal:today,' . $id,
            'end_date' => 'required|after_or_equal:start_date,' . $id,
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function failedValidation(Validator $validator)
    {
        if ($this->isMethod('PUT') || $this->wantsJson()) {
            throw new HttpResponseException(response()->json(['errors' => errorProcessor($validator)]));
        }
        parent::failedValidation($validator);
    }
}
