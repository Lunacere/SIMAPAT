<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MinuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('meeting'));
    }

    public function rules(): array
    {
        return ['discussion' => ['required', 'string', 'max:20000'], 'decisions' => ['required', 'string', 'max:20000'],
            'client_progress' => ['nullable', 'string', 'max:10000'], 'recorded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']];
    }
}
