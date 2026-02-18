<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCodesRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'membership_type_id' => 'required|exists:membership_types,id',
            'start_code' => 'required|string|max:45',
            'end_code' => 'required|string|max:45',
        ];
    }

    public function messages()
    {
        return [
            'membership_type_id.required' => 'Please select a membership type.',
            'membership_type_id.exists' => 'Selected membership type does not exist.',
            'start_code.required' => 'Start code is required.',
            'start_code.max' => 'Start code cannot exceed 45 characters.',
            'end_code.required' => 'End code is required.',
            'end_code.max' => 'End code cannot exceed 45 characters.',
        ];
    }
}
