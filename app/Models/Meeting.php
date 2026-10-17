<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['creator_id', 'client_id', 'title', 'starts_at', 'ends_at', 'location', 'status', 'version'])]
class Meeting extends Model
{
    use HasFactory;

    public const STATUSES = ['scheduled' => 'Terjadwal', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'version' => 'integer'];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role === 'client') {
            $query->whereNotNull('client_id')->where('client_id', $user->client_id);

            return;
        }
        if ($user->role !== 'admin') {
            $query->where(function (Builder $query) use ($user): void {
                $query->whereHas('participants', fn (Builder $query) => $query->where('user_id', $user->id));
                if ($user->role === 'operator') {
                    $query->orWhere('creator_id', $user->id);
                }
            });
        }
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function agenda(): HasOne
    {
        return $this->hasOne(Agenda::class);
    }

    public function minute(): HasOne
    {
        return $this->hasOne(MeetingMinute::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function scheduleProposals(): HasMany
    {
        return $this->hasMany(ScheduleProposal::class);
    }

    public function latestProposal(): HasOne
    {
        return $this->hasOne(ScheduleProposal::class)->latestOfMany();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
