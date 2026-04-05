<?php

namespace App\Http\Controllers\Admin;

use App\Events\MessageSent;
use App\Events\NewMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SupportChatController extends Controller
{
    private const ADMIN_NAME = 'Поддержка no alone';

    public function index(): Response
    {
        $conversations = Conversation::where('is_support', true)
            ->with(['participants.user', 'lastMessage'])
            ->latest('updated_at')
            ->get()
            ->map(fn(Conversation $c) => $this->formatConversation($c));

        return Inertia::render('Admin/Support/Index', [
            'conversations' => $conversations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        $user = User::findOrFail($request->user_id);

        $conversation = Conversation::create(['is_support' => true]);
        $conversation->participants()->create(['user_id' => $user->id]);

        return response()->json($this->formatConversation($conversation->load(['participants.user', 'lastMessage'])));
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $query = $conversation->messages()->with('sender')->latest();

        if ($request->before_id) {
            $query->where('id', '<', $request->before_id);
        }

        $messages = $query->take(50)->get()->reverse()->values();

        $hasMore = $messages->isNotEmpty() &&
            $conversation->messages()->where('id', '<', $messages->first()->id)->exists();

        return response()->json([
            'messages' => $messages->map(fn($m) => $this->formatMessage($m)),
            'has_more' => $hasMore,
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $admin = auth('admin')->user();

        $msg = $conversation->messages()->create([
            'sender_id' => null,
            'body'      => $request->body,
            'type'      => 'support',
            'metadata'  => ['admin_id' => $admin->id, 'admin_name' => self::ADMIN_NAME],
        ]);

        $conversation->touch();
        $msg->load('sender');

        $this->broadcastToAll($conversation, $msg);

        return response()->json($this->formatMessage($msg));
    }

    public function upload(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $path = $request->file('file')->store("chat/{$conversation->id}", 'public');

        return response()->json(['url' => Storage::url($path)]);
    }

    public function sendImage(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['image_url' => ['required', 'string']]);

        $admin = auth('admin')->user();

        $msg = $conversation->messages()->create([
            'sender_id' => null,
            'body'      => '',
            'type'      => 'image',
            'metadata'  => [
                'admin_id'  => $admin->id,
                'admin_name' => self::ADMIN_NAME,
                'image_url' => $request->image_url,
            ],
        ]);

        $conversation->touch();
        $msg->load('sender');

        $this->broadcastToAll($conversation, $msg);

        return response()->json($this->formatMessage($msg));
    }

    public function close(Conversation $conversation): JsonResponse
    {
        $conversation->update(['closed_at' => now()]);

        $msg = $conversation->messages()->create([
            'sender_id' => null,
            'body'      => '',
            'type'      => 'system',
            'metadata'  => ['event' => 'chat_closed'],
        ]);
        $msg->load('sender');
        $this->broadcastToAll($conversation, $msg);

        return response()->json(['closed_at' => $conversation->closed_at->toISOString()]);
    }

    public function open(Conversation $conversation): JsonResponse
    {
        $conversation->update(['closed_at' => null]);

        $msg = $conversation->messages()->create([
            'sender_id' => null,
            'body'      => '',
            'type'      => 'system',
            'metadata'  => ['event' => 'chat_opened'],
        ]);
        $msg->load('sender');
        $this->broadcastToAll($conversation, $msg);

        return response()->json(['closed_at' => null]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formatConversation(Conversation $c): array
    {
        $participant = $c->participants->first();
        $user = $participant?->user;

        return [
            'id'         => $c->id,
            'closed_at'  => $c->closed_at?->toISOString(),
            'updated_at' => $c->updated_at->toISOString(),
            'user'       => $user ? [
                'id'     => $user->id,
                'name'   => $user->name,
                'avatar' => $user->avatar_url,
            ] : null,
            'last_message' => $c->lastMessage ? [
                'body'       => $c->lastMessage->type === 'image' ? '[фото]' : $c->lastMessage->body,
                'type'       => $c->lastMessage->type,
                'created_at' => $c->lastMessage->created_at->toISOString(),
            ] : null,
        ];
    }

    private function formatMessage($m): array
    {
        return [
            'id'            => $m->id,
            'body'          => $m->body,
            'type'          => $m->type ?? 'user',
            'metadata'      => $m->metadata,
            'sender_id'     => $m->sender_id,
            'sender_name'   => $m->sender?->name,
            'sender_avatar' => $m->sender?->avatar_url,
            'created_at'    => $m->created_at->toISOString(),
        ];
    }

    private function broadcastToAll(Conversation $conversation, $msg): void
    {
        try {
            broadcast(new MessageSent($msg));
        } catch (\Throwable $e) {
            \Log::warning('Broadcast failed: ' . $e->getMessage());
        }

        // Notify all user participants
        foreach ($conversation->participants as $participant) {
            try {
                broadcast(new NewMessageReceived($participant->user_id, $conversation->id));
            } catch (\Throwable $e) {
                \Log::warning('Broadcast NewMessageReceived failed: ' . $e->getMessage());
            }
        }
    }
}
