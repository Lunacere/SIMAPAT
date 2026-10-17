<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['meeting_id', 'user_id', 'attendance_status', 'reminder_preference'])]
class Participant extends Model
{
    use HasFactory;

    public const STATUSES = ['pending' => 'Belum konfirmasi', 'accepted' => 'Akan hadir', 'declined' => 'Tidak hadir'];

    public const REMINDERS = ['day_before' => 'Satu hari sebelumnya', 'morning' => 'Pagi hari pukul 07.00', 'none' => 'Tanpa pengingat'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
