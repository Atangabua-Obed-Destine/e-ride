<?php

namespace Modules\BusinessManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SupportSavedReplyStoreOrUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->id;
        return [
            'topic' => 'required|string|max:151|unique:support_saved_replies,topic,' . $id,
            'answer' => 'required|string|max:251',
        ];
    }


    public function authorize(): bool
    {
        return Auth::check();
    }
}
