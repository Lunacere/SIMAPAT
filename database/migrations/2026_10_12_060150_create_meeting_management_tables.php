<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->text('information')->nullable();
            $table->text('progress')->nullable();
            $table->timestamps();
        });
        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('location');
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('attendance_status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->enum('reminder_preference', ['day_before', 'morning', 'none'])->default('day_before');
            $table->unique(['meeting_id', 'user_id']);
            $table->timestamps();
        });
        Schema::create('agendas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->timestamps();
        });
        Schema::create('meeting_minutes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained()->restrictOnDelete();
            $table->text('discussion');
            $table->text('decisions');
            $table->text('client_progress')->nullable();
            $table->date('recorded_on');
            $table->timestamps();
        });
        Schema::create('follow_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('task');
            $table->date('deadline');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->timestamps();
        });
        Schema::create('reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('meeting_version');
            $table->dateTime('scheduled_at')->index();
            $table->dateTime('sent_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'cancelled', 'skipped'])->default('pending');
            $table->unique(['meeting_id', 'user_id', 'meeting_version', 'scheduled_at'], 'reminders_delivery_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reminders', 'follow_ups', 'meeting_minutes', 'agendas', 'participants', 'meetings', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
