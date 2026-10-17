<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function emptyMeetingRoles(): array
    {
        return [
            'operator creates meetings' => ['operator', 'Jadwalkan rapat pertama dan tentukan pesertanya.'],
            'admin waits for operator' => ['admin', 'Rapat akan tampil setelah Operator membuat jadwal rapat.'],
            'user waits for invitation' => ['user', 'Rapat akan tampil setelah Anda ditambahkan sebagai peserta.'],
            'client waits for company invitation' => ['client', 'Rapat akan tampil setelah akun Anda ditautkan ke perusahaan dan Operator mengundang perusahaan tersebut.'],
        ];
    }

    #[DataProvider('emptyMeetingRoles')]
    public function test_empty_meeting_list_explains_the_next_step_allowed_for_each_role(string $role, string $hint): void
    {
        $account = User::factory()->create(['role' => $role, 'client_id' => $role === 'client' ? Client::factory() : null]);

        $response = $this->actingAs($account)->get(route('meetings.index'));

        $response->assertOk()->assertSeeText('Belum ada rapat')->assertSeeText($hint);
        if ($role === 'operator') {
            $response->assertSee(route('meetings.create'));
        } else {
            $response->assertDontSee(route('meetings.create'));
        }
    }

    public function test_empty_search_guides_the_user_to_clear_filters(): void
    {
        $account = User::factory()->create(['role' => 'user']);

        $this->actingAs($account)->get(route('meetings.index', ['q' => 'tidak ditemukan']))
            ->assertOk()->assertSeeText('Tidak ada rapat yang cocok')
            ->assertSeeText('Ubah pencarian atau hapus filter untuk melihat rapat lainnya.')
            ->assertDontSeeText('Jadwalkan rapat pertama');
    }

    public function test_invalid_date_filter_keeps_values_available_for_correction(): void
    {
        $account = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($account)->followingRedirects()->from(route('meetings.index'))
            ->get(route('meetings.index', ['q' => 'Rapat penting', 'from' => '2026-10-30', 'to' => '2026-10-20']))->assertOk();

        $response->assertSee('data-error-field="to"', false)
            ->assertSee('value="Rapat penting"', false)
            ->assertSee('value="2026-10-30"', false)->assertSee('value="2026-10-20"', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $this->assertTrue((new \DOMXPath($document))->query('//details[@class="filter-options"]')->item(0)->hasAttribute('open'));
    }

    public static function meetingDates(): array
    {
        return [
            'same day' => ['2026-10-25 09:00:00', '2026-10-25 10:00:00', ['25 Oktober 2026', '09:00', '10:00']],
            'crosses midnight' => ['2026-10-25 23:00:00', '2026-10-26 01:00:00', ['25 Oktober 2026', '23:00', '26 Oktober 2026', '01:00']],
        ];
    }

    #[DataProvider('meetingDates')]
    public function test_meeting_list_shows_readable_complete_dates(string $startsAt, string $endsAt, array $dateParts): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        Meeting::factory()->create(['creator_id' => $operator->id, 'starts_at' => $startsAt, 'ends_at' => $endsAt]);

        $this->actingAs($operator)->get(route('meetings.index'))->assertOk()->assertSeeTextInOrder($dateParts);
    }

    public function test_active_task_filter_excludes_completed_cancelled_and_other_users_tasks(): void
    {
        $account = User::factory()->create(['role' => 'user']);
        $active = FollowUp::factory()->create(['user_id' => $account->id]);
        $completedMeetingTask = FollowUp::factory()->create(['user_id' => $account->id, 'meeting_id' => Meeting::factory()->state(['status' => 'completed'])]);
        FollowUp::factory()->create(['user_id' => $account->id, 'status' => 'completed']);
        FollowUp::factory()->create(['user_id' => $account->id, 'meeting_id' => Meeting::factory()->state(['status' => 'cancelled'])]);
        FollowUp::factory()->create();

        $this->actingAs($account)->get(route('tasks.index', ['status' => 'active']))->assertOk()
            ->assertViewHas('tasks', fn ($tasks): bool => $tasks->getCollection()->modelKeys() === [$active->id, $completedMeetingTask->id])
            ->assertSeeText($active->task)->assertSeeText($completedMeetingTask->task);
    }

    public function test_client_cannot_access_the_active_task_filter(): void
    {
        $client = User::factory()->create(['role' => 'client', 'client_id' => Client::factory()]);

        $this->actingAs($client)->get(route('tasks.index', ['status' => 'active']))->assertForbidden();
    }
}
