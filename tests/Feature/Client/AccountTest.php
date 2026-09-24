<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        config()->set('app.url', 'https://panel.example.com');
        Queue::fake();
    }

    public function test_account_returns_the_client_subscription_contract(): void
    {
        [$user, $token] = $this->userAndToken();

        $response = $this->withToken($token)->getJson('/api/v2/client/account');

        $response->assertOk()->assertJsonPath('data.email', $user->email);
        $response->assertJsonPath('data.subscription.plan_id', null);
        $response->assertJsonPath('data.subscription.total', 1024);
        $response->assertJsonPath(
            'data.subscription.url',
            "https://panel.example.com/s/{$user->token}"
        );
        $response->assertJsonMissingPath('data.subscription.token');
        $response->assertJsonMissingPath('data.subscription.uuid');
    }

    public function test_logout_revokes_the_current_session(): void
    {
        [$user, $token] = $this->userAndToken();

        $this->withToken($token)
            ->postJson('/api/v2/client/session/logout')
            ->assertOk()
            ->assertJsonPath('data', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => hash('sha256', $token),
        ]);
        auth()->forgetGuards();
        $this->withToken($token)
            ->getJson('/api/v2/client/account')
            ->assertStatus(403);
    }

    private function userAndToken(): array
    {
        $user = User::create([
            'email' => 'client@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'uuid' => (string) Str::uuid(),
            'token' => Str::random(32),
            'transfer_enable' => 1024,
            'u' => 100,
            'd' => 200,
            'expired_at' => now()->addMonth()->timestamp,
        ]);
        $plainTextToken = $user->createToken('client-test')->plainTextToken;
        $token = explode('|', $plainTextToken, 2)[1];

        return [$user, $token];
    }
}
