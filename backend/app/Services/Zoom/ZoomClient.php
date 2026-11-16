<?php

namespace App\Services\Zoom;

use App\Exceptions\ExternalServiceException;
use App\Models\ZoomAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Zoom Server-to-Server OAuth mijozi.
 *
 * Eski tizimdan farqli ravishda tokenlar bazada saqlanmaydi va tashqi cron'ga
 * bog'liq emas: kerak bo'lganda olinadi va muddati tugaguncha keshda turadi.
 */
class ZoomClient
{
    public function __construct(
        private readonly string $apiUrl = 'https://api.zoom.us/v2',
        private readonly string $oauthUrl = 'https://zoom.us/oauth/token',
        private readonly int $timeout = 15,
    ) {}

    public static function make(): self
    {
        return new self(
            rtrim((string) config('services.zoom.api_url'), '/'),
            (string) config('services.zoom.oauth_url'),
            (int) config('services.zoom.timeout', 15),
        );
    }

    public function token(ZoomAccount $account, bool $fresh = false): string
    {
        $key = $this->cacheKey($account);
        if ($fresh) {
            Cache::forget($key);
        }

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->withBasicAuth($account->client_id, $account->client_secret)
                ->post($this->oauthUrl.'?'.http_build_query([
                    'grant_type' => 'account_credentials',
                    'account_id' => $account->account_id,
                ]));
        } catch (ConnectionException $e) {
            throw new ExternalServiceException('Zoom serveriga ulanib boʻlmadi. Internet aloqasini tekshiring.', ['error' => $e->getMessage()]);
        }

        if ($response->failed() || ! $response->json('access_token')) {
            $account->forceFill(['last_error' => $this->describe($response), 'last_checked_at' => now()])->saveQuietly();
            Log::warning('Zoom token olinmadi', ['account' => $account->id, 'status' => $response->status(), 'body' => $response->json()]);
            throw new ExternalServiceException("“{$account->name}” Zoom akkauntining kalitlari notoʻgʻri yoki eskirgan. Bosh administratorga xabar bering.");
        }

        $token = (string) $response->json('access_token');
        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
        Cache::put($key, $token, $ttl);

        if ($account->last_error) {
            $account->forceFill(['last_error' => null])->saveQuietly();
        }

        return $token;
    }

    /**
     * @return array{id: string, join_url: string, start_url: string, password: string|null}
     */
    public function createMeeting(ZoomAccount $account, string $hostEmail, string $topic, Carbon $startsAt, int $duration, string $passcode): array
    {
        $response = $this->send($account, 'post', '/users/'.rawurlencode($hostEmail).'/meetings', [
            'topic' => mb_substr($topic, 0, 200),
            'type' => 2,
            'start_time' => $startsAt->copy()->timezone(config('app.timezone'))->format('Y-m-d\TH:i:s'),
            'timezone' => config('app.timezone'),
            'duration' => $duration,
            'password' => $passcode,
            'settings' => [
                'host_video' => false,
                'participant_video' => false,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'waiting_room' => false,
                'approval_type' => 2,
                'audio' => 'both',
                'auto_recording' => 'none',
                'meeting_authentication' => false,
                'email_notification' => false,
            ],
        ]);

        if ($response->status() === 404) {
            throw new ExternalServiceException("Zoom’da {$hostEmail} foydalanuvchisi topilmadi. Guruh sozlamalarini tekshiring.", status: 422);
        }
        if ($response->failed() || ! $response->json('id')) {
            $this->fail('Zoom uchrashuvini yaratib boʻlmadi', $response, ['host' => $hostEmail]);
        }

        return [
            'id' => (string) $response->json('id'),
            'join_url' => (string) $response->json('join_url'),
            'start_url' => (string) $response->json('start_url'),
            'password' => $response->json('password'),
        ];
    }

    /** Hozirgi (yangi ZAK bilan) host havolasini qaytaradi. */
    public function freshStartUrl(ZoomAccount $account, string $meetingId): ?string
    {
        $response = $this->send($account, 'get', '/meetings/'.rawurlencode($meetingId));

        return $response->successful() ? $response->json('start_url') : null;
    }

    /**
     * Uchrashuvni yakunlaydi. Uchrashuv hali boshlanmagan bo'lsa, uni bekor qiladi.
     * Zoom'da allaqachon yo'q bo'lgan uchrashuv xato hisoblanmaydi.
     */
    public function endMeeting(ZoomAccount $account, string $meetingId): void
    {
        $end = $this->send($account, 'put', '/meetings/'.rawurlencode($meetingId).'/status', ['action' => 'end']);
        if ($end->successful() || $end->status() === 404) {
            return;
        }

        $delete = $this->send($account, 'delete', '/meetings/'.rawurlencode($meetingId));
        if ($delete->successful() || $delete->status() === 404) {
            return;
        }

        $this->fail('Zoom uchrashuvini yakunlab boʻlmadi', $delete, ['meeting' => $meetingId]);
    }

    /** @return list<array{id: string, email: string, name: string, type: int}> */
    public function users(ZoomAccount $account): array
    {
        $users = [];
        $next = null;
        do {
            $response = $this->send($account, 'get', '/users', array_filter([
                'status' => 'active',
                'page_size' => 300,
                'next_page_token' => $next,
            ]));
            if ($response->failed()) {
                $this->fail('Zoom foydalanuvchilari roʻyxatini olib boʻlmadi', $response);
            }
            foreach ($response->json('users', []) as $user) {
                $users[] = [
                    'id' => (string) ($user['id'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'name' => trim(($user['display_name'] ?? '') ?: (($user['first_name'] ?? '').' '.($user['last_name'] ?? ''))),
                    'type' => (int) ($user['type'] ?? 1),
                ];
            }
            $next = $response->json('next_page_token') ?: null;
        } while ($next);

        return $users;
    }

    /** @param array<string, mixed> $payload */
    private function send(ZoomAccount $account, string $method, string $path, array $payload = []): Response
    {
        $attempt = function (bool $fresh) use ($account, $method, $path, $payload): Response {
            try {
                return $this->request($this->token($account, $fresh))->{$method}($this->apiUrl.$path, $payload);
            } catch (ConnectionException $e) {
                throw new ExternalServiceException('Zoom serveriga ulanib boʻlmadi. Birozdan soʻng qayta urinib koʻring.', ['error' => $e->getMessage()]);
            }
        };

        $response = $attempt(false);
        // Token muddatidan oldin bekor qilingan bo'lsa — yangisini olib, bir marta qaytaramiz.
        if ($response->status() === 401) {
            $response = $attempt(true);
        }
        if ($response->status() === 429) {
            throw new ExternalServiceException('Zoom soʻrovlar chegarasiga yetildi. Bir daqiqadan soʻng qayta urinib koʻring.', status: 503);
        }

        return $response;
    }

    private function request(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->asJson()->timeout($this->timeout);
    }

    /** @param array<string, mixed> $context */
    private function fail(string $message, Response $response, array $context = []): never
    {
        Log::error($message, $context + ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);
        $reason = $response->json('message');
        throw new ExternalServiceException($message.($reason ? ": {$reason}" : '.'));
    }

    private function describe(Response $response): string
    {
        return mb_substr(trim(($response->json('reason') ?? $response->json('error') ?? 'HTTP '.$response->status()).''), 0, 490);
    }

    private function cacheKey(ZoomAccount $account): string
    {
        return 'zoom:token:'.$account->id.':'.substr(hash('sha256', $account->account_id.$account->client_id.$account->client_secret), 0, 16);
    }
}
