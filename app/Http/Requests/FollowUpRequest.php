<?php

namespace App\Http\Requests;

use App\Models\FollowUp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('meeting'));
    }

    public function rules(): array
    {
        return ['task' => ['required', 'string', 'max:10000'], 'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNot('role', 'client')],
            'deadline' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->route('meeting')->starts_at->toDateString()],
            'status' => ['required', Rule::in(array_keys(FollowUp::STATUSES))]];
    }
}
