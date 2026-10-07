<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidInitDataException;
use App\Http\Resources\UserResource;
use App\Services\InitDataValidator;
use App\Services\JwtService;
use App\Services\UserSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class AuthController
{
    /**
     * Обмен initData миниприложения Telegram на JWT.
     */
    public function telegram(Request $request, InitDataValidator $validator, UserSync $userSync, JwtService $jwt): JsonResponse
    {
        $request->validate(['init_data' => ['required', 'string', 'max:4096']]);

        try {
            $data = $validator->validate($request->input('init_data'));
        } catch (InvalidInitDataException) {
            abort(401, __('api.invalid_init_data'));
        }

        abort_if($data['is_bot'] ?? false, 403, __('api.forbidden'));

        $user = $userSync->sync(
            (int) $data['id'],
            $data['first_name'],
            $data['last_name'] ?? null,
            $data['username'] ?? null,
            $data['language_code'] ?? null,
        );
        App::setLocale($user->preferredLocale());

        $token = $jwt->issue($user);

        return response()->json([
            'token' => $token['token'],
            'token_type' => 'Bearer',
            'expires_at' => $token['expires_at']->toIso8601String(),
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
