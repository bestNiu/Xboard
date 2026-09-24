<?php

namespace App\Http\Controllers\V2\Client;

use App\Http\Controllers\Controller;
use App\Utils\Helper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('plan');

        return $this->success([
            'email' => $user->email,
            'subscription' => [
                'url' => Helper::getSubscribeUrl($user->token),
                'plan_id' => $user->plan_id,
                'plan_name' => $user->plan?->name,
                'expires_at' => $user->expired_at,
                'upload' => (int) $user->u,
                'download' => (int) $user->d,
                'total' => (int) $user->transfer_enable,
                'device_limit' => $user->device_limit,
                'speed_limit' => $user->speed_limit,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(true);
    }
}
