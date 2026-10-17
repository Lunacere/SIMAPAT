<?php

namespace App\Http\Requests;

use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;

class MeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('meeting') ? $this->user()->can('update', $this->route('meeting')) : $this->user()->can('create', Meeting::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:starts_at'],
            'location' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'participants' => ['required', 'array', 'min:1', 'max:100'],
            'participants.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'agenda_title' => ['required', 'string', 'max:255'],
            'agenda_description' => ['required', 'string', 'max:10000'],
            'version' => [$this->route('meeting') ? 'required' : 'nullable', 'integer', 'min:1'],
            'status' => ['prohibited'],
            'creator_id' => ['prohibited'],
        ];
    }
}
