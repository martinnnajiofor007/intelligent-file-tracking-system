<?php

namespace App\Http\Requests;

use App\Models\FileIssue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFileIssueRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'status' => ['required', 'string', Rule::in(FileIssue::STATUSES)],
        ];
    }
}
