<?php

namespace App\Models;

use Database\Factories\ScheduleProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['proposed_by', 'proposed_party', 'responded_by', 'meeting_version', 'starts_at', 'ends_at', 'message', 'status', 'responded_at'])]
class ScheduleProposal extends Model
{
    /** @use HasFactory<ScheduleProposalFactory> */
    use HasFactory;

    public const STATUSES = ['pending' => 'Menunggu tanggapan', 'accepted' => 'Disepakati', 'superseded' => 'Digantikan'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'responded_at' => 'datetime', 'meeting_version' => 'integer'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
