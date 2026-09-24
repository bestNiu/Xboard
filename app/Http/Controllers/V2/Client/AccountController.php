<?php

namespace App\Http\Controllers\V2\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\Client\ClientController;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('plan');

        return $this->success([
            'account_id' => hash_hmac('sha256', (string) $user->id, config('app.key')),
            'email' => $user->email,
            'subscription' => [
                'available' => app(UserService::class)->isAvailable($user),
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

    public function subscription(
        Request $request,
        ClientController $clientController
    ): Response {
        return $clientController->subscribe($request);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(true);
    }
}
