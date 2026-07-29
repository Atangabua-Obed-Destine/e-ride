<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class QuestionAnswerStoreOrUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->id;
        return [
            'question' => 'required|string|max:151|unique:question_answers,question,' . $id,
            'answer' => 'required|string|max:251',
            'question_answer_for' => 'sometimes|string|in:'.DRIVER.',' . CUSTOMER,
        ];
    }


    public function authorize(): bool
    {
        return Auth::check();
    }
}
