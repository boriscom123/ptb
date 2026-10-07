<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Resources\UserResource;
use App\Jobs\NotifyRoleChanged;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class UserController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
        ]);

        $users = User::query()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $search = ltrim($search, '@');
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($query) use ($search, $like) {
                    $query->where('username', 'ilike', $like)
                        ->orWhere('first_name', 'ilike', $like)
                        ->orWhere('last_name', 'ilike', $like);

                    if (ctype_digit($search)) {
                        $query->orWhere('telegram_id', (int) $search);
                    }
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->orderByRaw('last_seen_at desc nulls last')
            ->orderByDesc('id')
            ->paginate(20);

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(Request $request, User $user): UserResource
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        abort_if($user->is($request->user()), 422, __('api.cannot_change_own_role'));
        abort_if($user->telegram_id === config('bot.admin_telegram_id'), 422, __('api.cannot_change_protected_admin'));

        $role = Role::from($data['role']);

        if ($user->role !== $role) {
            $user->update(['role' => $role]);
            NotifyRoleChanged::dispatch($user);
        }

        return new UserResource($user);
    }
}
