<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'file_number' => ['required', 'string', 'max:255', 'unique:files,file_number'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:file_categories,id'],
            'confirmed_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'confirmed_holder_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('is_active', true)
                        ->where('department_id', $this->input('confirmed_department_id'));
                }),
            ],
        ];
    }
}
