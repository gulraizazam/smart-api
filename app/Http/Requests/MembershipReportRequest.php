<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MembershipReportRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Adjust this based on your authorization logic
    }

    public function rules()
    {
        return [
            'location_id' => 'nullable|integer',
            'membership_type_id' => 'nullable',
            'date_range' => 'nullable|string',
        ];
    }

    public function getStartDate()
    {
        if ($this->date_range) {
            $date_range = explode(' - ', $this->date_range);
            return date('Y-m-d', strtotime($date_range[0]));
        }

        return null;
    }

    public function getEndDate()
    {
        if ($this->date_range) {
            $date_range = explode(' - ', $this->date_range);
            return date('Y-m-d', strtotime($date_range[1]));
        }

        return null;
    }
}
