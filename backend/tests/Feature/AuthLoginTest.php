<?php

namespace Tests\Feature;

use App\Models\AuthToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_accepts_a_gmail_address_and_issues_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validRegistration([
            'email' => 'Ava.Santos@gmail.com',
        ]));

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ava.santos@gmail.com')
            ->assertJsonPath('user.name', 'Ava Santos');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('users', ['email' => 'ava.santos@gmail.com']);
    }

    public function test_register_rejects_addresses_outside_gmail(): void
    {
        $this->postJson('/api/auth/register', $this->validRegistration([
            'email' => 'ava@yahoo.com',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_a_password_that_breaks_the_login_rules(): void
    {
        $this->postJson('/api/auth/register', $this->validRegistration([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/auth/register', $this->validRegistration([
            'password' => 'longpassword',
            'password_confirmation' => 'longpassword',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/auth/register', $this->validRegistration([
            'password' => 'lovebird1',
            'password_confirmation' => 'lovebird2',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_opens_protected_routes_and_logout_closes_them(): void
    {
        $this->postJson('/api/auth/register', $this->validRegistration())->assertCreated();

        $this->getJson('/api/birds')->assertUnauthorized();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'ava.santos@gmail.com',
            'password' => 'lovebird1',
        ])->assertOk();

        $token = $login->json('token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'ava.santos@gmail.com');

        $this->withToken($token)->getJson('/api/birds')->assertOk();

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->withToken($token)->getJson('/api/birds')->assertUnauthorized();
    }

    public function test_repeated_requests_skip_rewriting_the_token_timestamp(): void
    {
        $this->postJson('/api/auth/register', $this->validRegistration())->assertCreated();

        $token = $this->postJson('/api/auth/login', [
            'email' => 'ava.santos@gmail.com',
            'password' => 'lovebird1',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $stamp = AuthToken::query()->value('last_used_at');

        $this->travel(1)->minute();

        $this->withToken($token)->getJson('/api/auth/me')->assertOk();

        $this->assertSame((string) $stamp, (string) AuthToken::query()->value('last_used_at'));
    }

    public function test_login_rejects_unknown_credentials_without_confirming_the_account(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'missing@gmail.com',
            'password' => 'lovebird1',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validRegistration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ava Santos',
            'email' => 'ava.santos@gmail.com',
            'password' => 'lovebird1',
            'password_confirmation' => 'lovebird1',
        ], $overrides);
    }
}
