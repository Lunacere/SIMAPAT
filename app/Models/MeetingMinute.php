<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['meeting_id', 'discussion', 'decisions', 'client_progress', 'recorded_on'])]
class MeetingMinute extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['recorded_on' => 'date'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
