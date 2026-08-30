<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('manage-organization-data');
    }

    public function rules()
    {
        $department = $this->route('department');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department->id),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
                function ($attribute, $value, $fail) use ($department) {
                    if ($value === null) {
                        return;
                    }

                    if ((int) $value === (int) $department->id) {
                        $fail('A department cannot be its own parent.');
                        return;
                    }

                    if ($this->isDescendant($department, (int) $value)) {
                        $fail('A department cannot be a parent of its own parent.');
                    }
                },
            ],
        ];
    }

    private function isDescendant(Department $department, int $candidateParentId): bool
    {
        $current = Department::find($candidateParentId);

        while ($current !== null && $current->parent_id !== null) {
            if ((int) $current->parent_id === (int) $department->id) {
                return true;
            }

            $current = Department::find($current->parent_id);
        }

        return false;
    }
}
