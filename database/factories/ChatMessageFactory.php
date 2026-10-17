<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ChatMessage> */
class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return ['meeting_id' => Meeting::factory(), 'sender_id' => fn (array $attributes): int => Meeting::findOrFail($attributes['meeting_id'])->creator_id,
            'client_message_id' => (string) Str::uuid(), 'body' => fake()->sentence()];
    }
}
