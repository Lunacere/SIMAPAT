<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('chat', $this->route('meeting'));
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000'], 'client_message_id' => ['required', 'uuid'],
            'sender_id' => ['prohibited'], 'meeting_id' => ['prohibited']];
    }
}
