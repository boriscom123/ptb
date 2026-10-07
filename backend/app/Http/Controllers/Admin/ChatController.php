<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\ChatResource;
use App\Http\Resources\ModerationEventResource;
use App\Models\Chat;
use App\Models\ChatRule;
use App\Moderation\RuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChatController
{
    public function __construct(private readonly RuleRegistry $registry) {}

    public function index(): AnonymousResourceCollection
    {
        $chats = Chat::query()
            ->withCount('events')
            ->orderByRaw("bot_status in ('left', 'kicked')")
            ->orderBy('title')
            ->get();

        return ChatResource::collection($chats);
    }

    public function show(Chat $chat): array
    {
        return [
            'data' => new ChatResource($chat),
            'rules' => $this->rules($chat),
        ];
    }

    public function update(Request $request, Chat $chat): ChatResource
    {
        $chat->update($request->validate([
            'moderation_enabled' => ['required', 'boolean'],
        ]));

        return new ChatResource($chat);
    }

    public function updateRule(Request $request, Chat $chat, string $rule): array
    {
        $definition = $this->registry->get($rule);
        abort_if($definition === null, 404);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            ...$definition->validationRules(),
        ]);

        $chat->rules()->updateOrCreate(['rule' => $rule], [
            'enabled' => $data['enabled'],
            'settings' => $definition->settings($data['settings'] ?? []),
        ]);

        return ['rules' => $this->rules($chat)];
    }

    public function events(Chat $chat): AnonymousResourceCollection
    {
        return ModerationEventResource::collection(
            $chat->events()->with('user')->latest('id')->paginate(30),
        );
    }

    /**
     * Все доступные правила с настройками этого чата (или значениями по умолчанию).
     */
    private function rules(Chat $chat): array
    {
        $stored = $chat->rules()->get()->keyBy('rule');

        return array_values(array_map(function ($rule) use ($stored) {
            /** @var ChatRule|null $chatRule */
            $chatRule = $stored->get($rule->key());

            return [
                'key' => $rule->key(),
                'enabled' => (bool) $chatRule?->enabled,
                'settings' => $rule->settings($chatRule->settings ?? []),
                'fields' => $rule->fields(),
            ];
        }, $this->registry->all()));
    }
}
