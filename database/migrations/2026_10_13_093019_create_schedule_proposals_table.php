<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('responded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('meeting_version');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'accepted', 'superseded'])->default('pending');
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();
            $table->index(['meeting_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_proposals');
    }
};
