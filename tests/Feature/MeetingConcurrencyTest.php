<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\ScheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MeetingConcurrencyTest extends TestCase
{
    public function test_simultaneous_overlapping_requests_save_only_one_meeting(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'simapat-test-');
        $original = config('database.connections.sqlite.database');
        try {
            config(['database.connections.sqlite.database' => $database]);
            DB::purge('sqlite');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $operator = User::factory()->create(['role' => 'operator']);
            $participant = User::factory()->create();
            $data = [
                'title' => 'Permintaan bersamaan',
                'starts_at' => now()->addDays(2)->setTime(9, 0)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s'),
                'location' => 'Ruang A', 'client_id' => null, 'participants' => [$participant->id],
                'agenda_title' => 'Agenda uji', 'agenda_description' => 'Uji persamaan waktu',
            ];
            $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1]]);
Illuminate\Support\Facades\DB::purge('sqlite');
while (microtime(true) < (float) $argv[4]) { usleep(1000); }
try {
    app(App\Services\MeetingScheduler::class)->save(App\Models\User::findOrFail($argv[2]), json_decode($argv[3], true));
    exit(0);
} catch (Illuminate\Validation\ValidationException $exception) {
    fwrite(STDOUT, json_encode($exception->errors()));
    exit(2);
}
PHP;
            $startAt = (string) (microtime(true) + 1.5);
            $processes = [];
            foreach ([1, 2] as $attempt) {
                $process = new Process([PHP_BINARY, '-r', $code, $database, (string) $operator->id, json_encode($data), $startAt], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $exitCodes = [];
            foreach ($processes as $process) {
                $exitCodes[] = $process->wait();
                $this->assertContains($process->getExitCode(), [0, 2], $process->getErrorOutput());
                if ($process->getExitCode() === 2) {
                    $this->assertArrayHasKey('participants', json_decode($process->getOutput(), true));
                }
            }
            sort($exitCodes);
            $this->assertSame([0, 2], $exitCodes);
            $this->assertSame(1, Meeting::count());
            $this->assertDatabaseCount('participants', 1);
            $this->assertDatabaseCount('agendas', 1);
            $this->assertDatabaseCount('reminders', 1);
        } finally {
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $original]);
            foreach ([$database, $database.'-wal', $database.'-shm', $database.'-journal'] as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
        }
    }

    public function test_simultaneous_admin_and_operator_acceptance_only_commits_once(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'simapat-negotiation-');
        $original = config('database.connections.sqlite.database');
        try {
            config(['database.connections.sqlite.database' => $database]);
            DB::purge('sqlite');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $client = Client::factory()->create();
            $account = User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
            $admin = User::factory()->create(['role' => 'admin']);
            $meeting = Meeting::factory()->create(['client_id' => $client->id]);
            $proposal = ScheduleProposal::factory()->create(['meeting_id' => $meeting->id,
                'proposed_by' => $account->id, 'proposed_party' => 'client', 'meeting_version' => 1]);
            $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1]]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Notification::fake();
while (microtime(true) < (float) $argv[5]) { usleep(1000); }
try {
    app(App\Services\MeetingNegotiation::class)->accept(App\Models\User::findOrFail($argv[2]), App\Models\Meeting::findOrFail($argv[3]), App\Models\ScheduleProposal::findOrFail($argv[4]));
    exit(0);
} catch (Illuminate\Validation\ValidationException $exception) {
    fwrite(STDOUT, json_encode($exception->errors()));
    exit(2);
}
PHP;
            $startAt = (string) (microtime(true) + 1.5);
            $processes = [];
            foreach ([$admin->id, $meeting->creator_id] as $actorId) {
                $process = new Process([PHP_BINARY, '-r', $code, $database, (string) $actorId, (string) $meeting->id, (string) $proposal->id, $startAt], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $codes = [];
            foreach ($processes as $process) {
                $codes[] = $process->wait();
                $this->assertContains($process->getExitCode(), [0, 2], $process->getErrorOutput());
            }
            sort($codes);
            $this->assertSame([0, 2], $codes);
            $this->assertSame('accepted', $proposal->refresh()->status);
            $this->assertContains($proposal->responded_by, [$admin->id, $meeting->creator_id]);
            $this->assertTrue($meeting->refresh()->starts_at->equalTo($proposal->starts_at));
        } finally {
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $original]);
            foreach ([$database, $database.'-wal', $database.'-shm', $database.'-journal'] as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
        }
    }
}
