<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Hemis\HemisOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class HemisLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeHemis(array $user = []): void
    {
        Http::fake([
            'hemis.test/oauth/access-token' => Http::response(['access_token' => 'hemis-token', 'token_type' => 'Bearer']),
            'hemis.test/oauth/api/user*' => Http::response(array_merge([
                'id' => 7777,
                'uuid' => 'a1b2c3',
                'type' => 'employee',
                'name' => 'JUMABAEV RUSTEM KENESBAEVICH',
                'firstname' => 'RUSTEM',
                'surname' => 'JUMABAEV',
                'patronymic' => 'KENESBAEVICH',
                'login' => '3011911000',
                'email' => 'rustem@karsu.uz',
                'phone' => '998901112233',
                'picture' => 'https://hemis.test/static/p.jpg',
                'university_id' => 42,
            ], $user)),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
    }

    private function hemisCallback(string $state = 'state-123'): TestResponse
    {
        return $this->withSession(['hemis_oauth_state' => 'state-123'])
            ->get('/auth/hemis/callback?code=abc&state='.$state);
    }

    public function test_redirect_sends_user_to_hemis_with_state(): void
    {
        $response = $this->get('/auth/hemis/redirect');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://hemis.test/oauth/authorize?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('hemis-client', $query['client_id']);
        $this->assertSame('http://localhost/auth/hemis/callback', $query['redirect_uri']);
        $response->assertSessionHas('hemis_oauth_state', $query['state']);
    }

    public function test_new_employee_is_registered_as_pending(): void
    {
        $this->fakeHemis();

        $this->hemisCallback()->assertRedirect('http://localhost/pending');

        $user = User::where('hemis_id', '7777')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame('Jumabaev Rustem Kenesbaevich', $user->name);
        $this->assertSame('Rustem', $user->first_name);
        $this->assertSame(['teacher'], $user->roles);
        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_active_user_logs_in_and_profile_is_refreshed(): void
    {
        $user = User::factory()->create(['hemis_id' => '7777', 'name' => 'Eski Ism', 'phone' => '998900000000']);
        $this->fakeHemis();

        $this->hemisCallback()->assertRedirect('http://localhost/');

        $user->refresh();
        $this->assertSame('Jumabaev Rustem Kenesbaevich', $user->name);
        $this->assertNull($user->phone, 'HEMIS’dan telefon so‘ralmaydi, eskisi ham saqlanmaydi');
        $this->assertNotNull($user->last_login_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_legacy_user_is_matched_by_hemis_login(): void
    {
        $legacy = User::factory()->create(['hemis_id' => '3011911000']);
        $this->fakeHemis();

        $this->hemisCallback()->assertRedirect('http://localhost/');

        $this->assertSame(1, User::count());
        $this->assertSame('7777', $legacy->refresh()->hemis_id);
    }

    public function test_login_match_cannot_take_over_an_already_linked_account(): void
    {
        // HEMIS orqali allaqachon kirgan xodimning hemis_id si boshqa birovning logini bilan mos kelsa ham, uni egallab bo'lmaydi.
        $linked = User::factory()->create(['hemis_id' => '3011911000', 'hemis_uuid' => 'other-uuid', 'last_login_at' => now()]);
        $this->fakeHemis();

        $this->hemisCallback()->assertRedirect('http://localhost/pending');

        $this->assertSame('3011911000', $linked->refresh()->hemis_id);
        $this->assertSame(2, User::count());
    }

    public function test_blocked_user_cannot_log_in(): void
    {
        User::factory()->blocked()->create(['hemis_id' => '7777']);
        $this->fakeHemis();

        $this->hemisCallback()->assertRedirect('http://localhost/login?error=blocked');
        $this->assertGuest();
    }

    public function test_deleted_user_cannot_log_in(): void
    {
        User::factory()->create(['hemis_id' => '7777'])->delete();
        $this->fakeHemis();

        $this->hemisCallback()->assertRedirect('http://localhost/login?error=blocked');
        $this->assertGuest();
    }

    public function test_state_mismatch_is_rejected(): void
    {
        $this->fakeHemis();

        $this->hemisCallback('forged')->assertRedirect('http://localhost/login?error=state');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_students_are_rejected(): void
    {
        $this->fakeHemis(['type' => 'student']);

        $this->hemisCallback()->assertRedirect('http://localhost/login?error=not_employee');
        $this->assertSame(0, User::count());
    }

    public function test_other_university_is_rejected(): void
    {
        $this->fakeHemis(['university_id' => 99]);

        $this->hemisCallback()->assertRedirect('http://localhost/login?error=university');
        $this->assertGuest();
    }

    public function test_registration_can_be_closed(): void
    {
        config(['services.hemis.auto_register' => false]);
        $this->app->forgetInstance(HemisOAuth::class);
        $this->fakeHemis();

        $this->hemisCallback()->assertRedirect('http://localhost/login?error=registration_closed');
        $this->assertSame(0, User::count());
    }

    public function test_hemis_failure_shows_friendly_error(): void
    {
        Http::fake(['hemis.test/*' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->hemisCallback()->assertRedirect('http://localhost/login?error=hemis_failed');
        $this->assertGuest();
    }
}
