<?php

namespace Tests\Feature;

use App\Events\ChatMessageCreated;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake([ChatMessageCreated::class]);
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        require base_path('routes/channels.php');
    }

    private function clientAccount(Client $client): User
    {
        return User::factory()->create(['role' => 'client', 'client_id' => $client->id]);
    }

    public function test_chat_is_restricted_to_the_linked_client_and_responsible_operator(): void
    {
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $url = route('chat.index', $meeting);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'operator']))->getJson($url)->assertForbidden();
        $this->actingAs($this->clientAccount(Client::factory()->create()))->getJson($url)->assertForbidden();
        $this->actingAs($account)->getJson($url)->assertOk()->assertExactJson(['messages' => [], 'has_more' => false, 'can_send' => true]);
        $this->actingAs($meeting->creator)->getJson($url)->assertOk();
        $this->postJson($url, ['body' => 'Waktu besok sesuai?', 'client_message_id' => (string) Str::uuid()])->assertCreated()->assertJsonPath('message.sender_id', $meeting->creator_id);
    }

    public function test_messages_are_idempotent_and_do_not_change_the_meeting_schedule(): void
    {
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $data = ['body' => 'Saya setuju di chat, tetapi belum konfirmasi formal.', 'client_message_id' => (string) Str::uuid()];
        $this->actingAs($account)->postJson(route('chat.store', $meeting), $data)->assertCreated();
        $this->postJson(route('chat.store', $meeting), $data)->assertOk();
        $this->postJson(route('chat.store', $meeting), [...$data, 'body' => 'Pesan berbeda'])->assertUnprocessable()->assertJsonValidationErrors('client_message_id');
        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertDatabaseCount('schedule_proposals', 0);
        $this->assertSame(1, $meeting->refresh()->version);
        Event::assertDispatchedTimes(ChatMessageCreated::class, 1);
    }

    public function test_chat_rejects_forged_sender_blank_messages_and_oversized_text(): void
    {
        $client = Client::factory()->create();
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $this->actingAs($this->clientAccount($client));
        $url = route('chat.store', $meeting);
        $this->postJson($url, ['body' => '   ', 'client_message_id' => (string) Str::uuid()])->assertJsonValidationErrors('body');
        $this->postJson($url, ['body' => str_repeat('a', 2001), 'client_message_id' => (string) Str::uuid()])->assertJsonValidationErrors('body');
        $this->postJson($url, ['body' => 'Halo', 'client_message_id' => (string) Str::uuid(), 'sender_id' => $meeting->creator_id, 'meeting_id' => $meeting->id])->assertJsonValidationErrors(['sender_id', 'meeting_id']);
        $this->assertDatabaseCount('chat_messages', 0);
        Event::assertNothingDispatched();
    }

    public function test_private_channel_authorization_cannot_be_used_for_other_accounts_or_meetings(): void
    {
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $data = ['socket_id' => '123.456', 'channel_name' => 'private-meeting-chat.'.$meeting->id.'.user.'.$account->id];
        $this->actingAs($account)->postJson('/broadcasting/auth', $data)->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/broadcasting/auth', [...$data, 'channel_name' => 'private-meeting-chat.'.$meeting->id.'.user.'.$meeting->creator_id])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/broadcasting/auth', $data)->assertForbidden();
        $account->update(['client_id' => Client::factory()->create()->id]);
        $this->actingAs($account->refresh())->postJson('/broadcasting/auth', $data)->assertForbidden();
        $this->getJson(route('chat.index', $meeting))->assertForbidden();
    }

    public function test_broadcast_is_only_a_signal_and_rechecks_recipients_after_access_changes(): void
    {
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        $message = ChatMessage::factory()->for($meeting)->create();
        $event = new ChatMessageCreated($meeting->id, $message->id);
        $this->assertSame(['message_id' => $message->id], $event->broadcastWith());
        $channels = array_map(fn ($channel): string => $channel->name, $event->broadcastOn());
        $this->assertContains('private-meeting-chat.'.$meeting->id.'.user.'.$account->id, $channels);
        $account->update(['role' => 'user', 'client_id' => null]);
        $channels = array_map(fn ($channel): string => $channel->name, $event->broadcastOn());
        $this->assertNotContains('private-meeting-chat.'.$meeting->id.'.user.'.$account->id, $channels);
        $this->assertContains('private-meeting-chat.'.$meeting->id.'.user.'.$meeting->creator_id, $channels);
    }

    public function test_history_pagination_and_reconnect_do_not_expose_internal_tasks(): void
    {
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id]);
        FollowUp::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $account->id, 'task' => 'RAHASIA DEVELOPER INTERNAL']);
        ChatMessage::factory()->count(55)->for($meeting)->create(['sender_id' => $meeting->creator_id]);
        $first = $meeting->chatMessages()->min('id');
        $this->actingAs($account)->getJson(route('chat.index', $meeting))->assertOk()->assertJsonCount(50, 'messages')->assertJsonPath('has_more', true)->assertDontSee('RAHASIA DEVELOPER INTERNAL');
        $response = $this->getJson(route('chat.index', $meeting).'?after_id='.($first + 49))->assertOk()->assertJsonCount(5, 'messages');
        $this->assertSame(['id', 'sender_id', 'sender_name', 'body', 'created_at'], array_keys($response->json('messages.0')));
        $this->getJson(route('chat.index', $meeting).'?before_id='.($first + 5))->assertOk()->assertJsonCount(5, 'messages');
        $this->get(route('negotiations.show', $meeting))->assertOk()->assertDontSee('RAHASIA DEVELOPER INTERNAL');
    }

    public function test_closed_meetings_allow_reading_but_reject_new_messages(): void
    {
        $client = Client::factory()->create();
        $account = $this->clientAccount($client);
        $meeting = Meeting::factory()->create(['client_id' => $client->id, 'status' => 'completed']);
        $this->actingAs($account)->getJson(route('chat.index', $meeting))->assertOk()->assertJsonPath('can_send', false);
        $this->postJson(route('chat.store', $meeting), ['body' => 'Halo', 'client_message_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_rolled_back_messages_do_not_broadcast(): void
    {
        $meeting = Meeting::factory()->create(['client_id' => Client::factory()]);
        DB::beginTransaction();
        $meeting->chatMessages()->create(['sender_id' => $meeting->creator_id, 'client_message_id' => (string) Str::uuid(), 'body' => 'Belum commit']);
        ChatMessageCreated::dispatch($meeting->id, 1);
        DB::rollBack();
        Event::assertNothingDispatched();
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_chat_sending_is_rate_limited(): void
    {
        $meeting = Meeting::factory()->create(['client_id' => Client::factory()]);
        $this->actingAs($meeting->creator);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson(route('chat.store', $meeting), [])->assertUnprocessable();
        }
        $this->postJson(route('chat.store', $meeting), ['body' => 'Halo', 'client_message_id' => (string) Str::uuid()])->assertTooManyRequests();
        $this->assertDatabaseCount('chat_messages', 0);
    }
}
