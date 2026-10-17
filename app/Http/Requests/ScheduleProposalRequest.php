<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('negotiate', $this->route('meeting'));
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:now'],
            'ends_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:starts_at'],
            'message' => ['nullable', 'string', 'max:2000'],
            'version' => ['required', 'integer', 'min:1'],
            'latest_proposal_id' => ['required', 'integer', 'min:0'],
            'proposed_by' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
