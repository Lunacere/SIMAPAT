<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sender_id', 'client_message_id', 'body'])]
class ChatMessage extends Model
{
    use HasFactory;

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function externalData(): array
    {
        return ['id' => $this->id, 'sender_id' => $this->sender_id, 'sender_name' => $this->sender->name,
            'body' => $this->body, 'created_at' => $this->created_at->toIso8601String()];
    }
}
