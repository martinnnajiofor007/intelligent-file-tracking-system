<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileIssueRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'issue_type' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'],
        ];
    }
}
