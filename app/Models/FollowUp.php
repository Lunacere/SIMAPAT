<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['meeting_id', 'user_id', 'task', 'deadline', 'status'])]
class FollowUp extends Model
{
    use HasFactory;

    public const STATUSES = ['pending' => 'Belum mulai', 'in_progress' => 'Berjalan', 'completed' => 'Selesai'];

    protected function casts(): array
    {
        return ['deadline' => 'date'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
