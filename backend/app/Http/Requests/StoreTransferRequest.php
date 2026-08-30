<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'file_id' => ['required', 'integer', 'exists:files,id'],
            'to_department_id' => ['required', 'integer', 'exists:departments,id'],
            'to_holder_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('is_active', true)
                        ->where('department_id', $this->input('to_department_id'));
                }),
            ],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
