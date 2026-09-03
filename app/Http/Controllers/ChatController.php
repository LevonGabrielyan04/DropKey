<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\ChatShowData;
use App\Http\Resources\ConversationCollection;
use App\Models\User;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(
        protected ConversationRepositoryInterface $conversations,
        protected ChatMessageRepositoryInterface $chatMessages,
    ) {}

    public function index(Request $request): View
    {
        $conversations = $this->conversations->getConversationsForUser($request->user());

        return view('chat.index', [
            'conversations' => $conversations,
            'conversationsPayload' => (new ConversationCollection($conversations))->resolve(),
        ]);
    }

    public function openByName(string $name): RedirectResponse
    {
        $user = User::query()->where('name', $name)->firstOrFail();

        abort_unless(auth()->id() !== $user->id, 404);

        return redirect()->route('chat.show', $user);
    }

    public function show(User $user): View
    {
        abort_unless(auth()->id() !== $user->id, 404);

        $conversation = $this->chatMessages->findConversationBetweenUsers(auth()->user(), $user);

        return view('chat.show', [
            'recipient' => $user,
            'conversation' => $conversation,
        ]);
    }

    public function settings(User $user): View
    {
        abort_unless(auth()->id() !== $user->id, 404);

        $conversation = $this->chatMessages->findConversationBetweenUsers(auth()->user(), $user);

        return view('chat.settings', ChatShowData::from($user, $conversation)->toArray());
    }
}
