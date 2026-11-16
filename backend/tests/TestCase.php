<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Testlarda tashqi xizmatlarga haqiqiy so'rov ketmasligi kafolatlanadi.
        Http::preventStrayRequests();
        $this->withHeaders(['Accept' => 'application/json', 'Referer' => 'http://localhost/']);
    }

    /**
     * Bitta test ichida turli foydalanuvchilar nomidan so'rov yuborish uchun
     * keshlangan guard'larni (sanctum) tozalab, keyin foydalanuvchini o'rnatadi.
     */
    protected function actingAsUser(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    /** Zoom API uchun standart soxta javoblar. */
    protected function fakeZoom(array $extra = []): void
    {
        Http::fake($extra + [
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'zoom-token', 'expires_in' => 3600]),
            'api.zoom.us/v2/users/*/meetings' => Http::response([
                'id' => 81234567890,
                'join_url' => 'https://us06web.zoom.us/j/81234567890?pwd=x',
                'start_url' => 'https://us06web.zoom.us/s/81234567890?zak=first',
                'password' => '654321',
            ], 201),
            'api.zoom.us/v2/meetings/*/status' => Http::response(null, 204),
            'api.zoom.us/v2/meetings/*' => Http::response(['start_url' => 'https://us06web.zoom.us/s/81234567890?zak=fresh']),
            'api.zoom.us/v2/users*' => Http::response(['users' => [['id' => 'u1', 'email' => 'zoom_1@karsu.uz', 'display_name' => 'Zoom 1', 'type' => 2]]]),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);
    }
}
