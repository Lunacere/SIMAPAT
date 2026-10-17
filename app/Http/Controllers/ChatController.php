<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageCreated;
use App\Http\Requests\ChatMessageRequest;
use App\Models\ChatMessage;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function index(Request $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('chat', $meeting);
        $data = $request->validate(['after_id' => ['nullable', 'integer', 'min:0', 'prohibits:before_id'], 'before_id' => ['nullable', 'integer', 'min:1']]);
        $query = ChatMessage::where('meeting_id', $meeting->id)->with('sender');
        if (isset($data['after_id'])) {
            $messages = $query->where('id', '>', $data['after_id'])->orderBy('id')->limit(51)->get();
        } else {
            $messages = $query->when($data['before_id'] ?? null, fn ($query, $id) => $query->where('id', '<', $id))->orderByDesc('id')->limit(51)->get();
        }
        $hasMore = $messages->count() > 50;
        $messages = $messages->take(50);
        if (! isset($data['after_id'])) {
            $messages = $messages->reverse();
        }

        return response()->json(['messages' => $messages->values()->map(fn (ChatMessage $message): array => $message->externalData()),
            'has_more' => $hasMore, 'can_send' => $meeting->status === 'scheduled']);
    }

    public function store(ChatMessageRequest $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validated();
        $message = DB::transaction(function () use ($request, $meeting, $data): ChatMessage {
            $meeting = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $user = $request->user()->fresh();
            Gate::forUser($user)->authorize('chat', $meeting);
            $existing = ChatMessage::where('sender_id', $user->id)->where('client_message_id', $data['client_message_id'])->first();
            if ($existing) {
                if ($existing->meeting_id !== $meeting->id || $existing->body !== $data['body']) {
                    throw ValidationException::withMessages(['client_message_id' => 'ID kiriman sudah digunakan. Muat ulang sebelum mengirim pesan baru.']);
                }

                return $existing;
            }
            if ($meeting->status !== 'scheduled') {
                throw ValidationException::withMessages(['body' => 'Rapat sudah ditutup. Riwayat chat tetap dapat dibaca.']);
            }
            $message = $meeting->chatMessages()->create(['sender_id' => $user->id, ...$data]);
            ChatMessageCreated::dispatch($meeting->id, $message->id);

            return $message;
        }, attempts: 5);

        return response()->json(['message' => $message->load('sender')->externalData()], $message->wasRecentlyCreated ? 201 : 200);
    }
}
