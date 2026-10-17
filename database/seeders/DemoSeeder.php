<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\MeetingMinute;
use App\Models\User;
use App\Services\MeetingScheduler;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Data demo hanya dibuat di lingkungan local/testing.');

            return;
        }
        $this->call(DemoAccountSeeder::class);
        $accounts = User::whereIn('username', ['admin', 'operator', 'peserta'])->get()->keyBy('username');
        if ($accounts['operator']->role !== 'operator' || $accounts['peserta']->role !== 'user' || $accounts['operator']->createdMeetings()->exists()) {
            return;
        }
        $client = Client::firstOrCreate(['name' => 'Klien Contoh'], ['contact' => 'kontak@example.com', 'information' => 'Data fiktif untuk demo', 'progress' => 'Evaluasi kebutuhan']);
        $common = ['location' => 'Ruang Kolaborasi', 'participants' => [$accounts['operator']->id, $accounts['peserta']->id],
            'agenda_title' => 'Evaluasi kebutuhan dan langkah berikutnya', 'agenda_description' => "1. Tinjau progres tim\n2. Diskusikan kendala\n3. Sepakati tindak lanjut", 'client_id' => $client->id];
        $scheduler = app(MeetingScheduler::class);
        $scheduler->save($accounts['operator'], array_merge($common, ['title' => 'Koordinasi mingguan tim', 'starts_at' => now()->addDays(2)->setTime(9, 0)->format('Y-m-d H:i:s'), 'ends_at' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s')]));
        $scheduler->save($accounts['operator'], array_merge($common, ['title' => 'Review kebutuhan klien', 'starts_at' => now()->addDays(3)->setTime(13, 0)->format('Y-m-d H:i:s'), 'ends_at' => now()->addDays(3)->setTime(14, 0)->format('Y-m-d H:i:s')]));
        $past = $scheduler->save($accounts['operator'], array_merge($common, ['title' => 'Diskusi awal implementasi', 'starts_at' => now()->subDay()->setTime(9, 0)->format('Y-m-d H:i:s'), 'ends_at' => now()->subDay()->setTime(10, 0)->format('Y-m-d H:i:s')]));
        $past->update(['status' => 'completed']);
        MeetingMinute::create(['meeting_id' => $past->id, 'discussion' => 'Tim meninjau kebutuhan website dan alur rapat.', 'decisions' => 'Bangun alur rapat dan tindak lanjut secara bertahap.', 'client_progress' => 'Kebutuhan awal telah dipetakan.', 'recorded_on' => $past->starts_at->toDateString()]);
        FollowUp::create(['meeting_id' => $past->id, 'user_id' => $accounts['peserta']->id, 'task' => 'Susun daftar kebutuhan halaman rapat', 'deadline' => today()->addDays(3), 'status' => 'in_progress']);
        FollowUp::create(['meeting_id' => $past->id, 'user_id' => $accounts['operator']->id, 'task' => 'Validasi alur konfirmasi dengan tim', 'deadline' => today()->addDays(2), 'status' => 'pending']);
    }
}
