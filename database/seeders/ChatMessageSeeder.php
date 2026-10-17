<?php

namespace Database\Seeders;

use App\Models\ChatMessage;
use App\Models\Meeting;
use Illuminate\Database\Seeder;

class ChatMessageSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        Meeting::whereNotNull('client_id')->whereDoesntHave('chatMessages')->orderBy('id')->each(function (Meeting $meeting): void {
            ChatMessage::factory()->for($meeting)->create(['sender_id' => $meeting->creator_id, 'body' => 'Silakan diskusikan waktu yang sesuai. Persetujuan tetap melalui ajuan jadwal.']);
        });
    }
}
