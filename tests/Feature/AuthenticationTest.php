<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public static function protectedEndpoints(): array
    {
        return [
            'list events' => ['get', '/api/events'],
            'book a seat' => ['post', '/api/events/1/bookings'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_endpoint_requires_authentication(string $method, string $uri): void
    {
        $this->json($method, $uri)
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_api_returns_json_401_even_without_accept_header(): void
    {
        $this->get('/api/events')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('invalid-token')
            ->getJson('/api/events')
            ->assertUnauthorized();
    }

    public function test_issue_token_command_creates_working_token(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.com']);

        $exitCode = Artisan::call('app:issue-token', ['email' => 'alice@example.com']);
        $token = trim(Artisan::output());

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $user->tokens);

        $this->withToken($token)
            ->getJson('/api/events')
            ->assertOk();
    }

    public function test_issue_token_command_fails_for_unknown_user(): void
    {
        $this->artisan('app:issue-token', ['email' => 'missing@example.com'])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }
}
