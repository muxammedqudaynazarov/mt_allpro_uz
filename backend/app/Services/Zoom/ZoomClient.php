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
            throw new ExternalServiceException(__('Zoom serveriga ulanib boʻlmadi. Internet aloqasini tekshiring.'), ['error' => $e->getMessage()]);
        }

        if ($response->failed() || ! $response->json('access_token')) {
            $account->forceFill(['last_error' => $this->describe($response), 'last_checked_at' => now()])->saveQuietly();
            Log::warning('Zoom token olinmadi', ['account' => $account->id, 'status' => $response->status(), 'body' => $response->json()]);
            throw new ExternalServiceException(__('“:name” Zoom akkauntining kalitlari notoʻgʻri yoki eskirgan. Bosh administratorga xabar bering.', ['name' => $account->name]));
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
            throw new ExternalServiceException(__('Zoom’da :email foydalanuvchisi topilmadi. Guruh sozlamalarini tekshiring.', ['email' => $hostEmail]), status: 422);
        }
        if ($response->failed() || ! $response->json('id')) {
            $this->fail(__('Zoom uchrashuvini yaratib boʻlmadi'), $response, ['host' => $hostEmail]);
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

        $this->fail(__('Zoom uchrashuvini yakunlab boʻlmadi'), $delete, ['meeting' => $meetingId]);
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
                $this->fail(__('Zoom foydalanuvchilari roʻyxatini olib boʻlmadi'), $response);
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

    /**
     * Uchrashuvning hozirgi holati: "waiting" (boshlanmagan yoki tugagan) yoki "started" (efirda).
     * Zoom'da uchrashuv topilmasa (o'chirilgan) — null.
     */
    public function meetingStatus(ZoomAccount $account, string $meetingId): ?string
    {
        $response = $this->send($account, 'get', '/meetings/'.rawurlencode($meetingId));
        if ($response->status() === 404) {
            return null;
        }
        if ($response->failed()) {
            throw $this->error(__('Zoom uchrashuvi holatini olib boʻlmadi'), $response);
        }

        return (string) $response->json('status', 'waiting');
    }

    /**
     * O'tgan uchrashuv nusxalari. Host uchrashuvni tugatib, qayta boshlasa — bir nechta bo'ladi.
     *
     * @return list<array{uuid: string, start_time: string}>
     */
    public function pastInstances(ZoomAccount $account, string $meetingId): array
    {
        $response = $this->send($account, 'get', '/past_meetings/'.rawurlencode($meetingId).'/instances');
        if ($response->status() === 404) {
            return [];
        }
        if ($response->failed()) {
            throw $this->error(__('Zoom’dan oʻtgan uchrashuvlarni olib boʻlmadi'), $response);
        }

        $instances = [];
        foreach ((array) $response->json('meetings', []) as $meeting) {
            if (! empty($meeting['uuid'])) {
                $instances[] = ['uuid' => (string) $meeting['uuid'], 'start_time' => (string) ($meeting['start_time'] ?? '')];
            }
        }

        return $instances;
    }

    /**
     * Uchrashuv nusxasining aniq boshlanish/tugash vaqti. Ixtiyoriy ma'lumot:
     * olinmasa (ruxsat yo'q va h.k.) — null, vaqtlar qatnashchilardan hisoblanadi.
     *
     * @return array{start_time: ?string, end_time: ?string, host_id: ?string, participants_count: ?int}|null
     */
    public function pastMeeting(ZoomAccount $account, string $uuid): ?array
    {
        $response = $this->send($account, 'get', '/past_meetings/'.$this->encodeUuid($uuid));
        if ($response->failed()) {
            return null;
        }

        return [
            'start_time' => $response->json('start_time'),
            'end_time' => $response->json('end_time'),
            'host_id' => $response->json('host_id'),
            'participants_count' => $response->json('participants_count'),
        ];
    }

    /**
     * Uchrashuv nusxasi qatnashchilari — har bir kirish-chiqish alohida qator.
     * Avval hisobot API (Pro va undan yuqori tarif), unga ruxsat bo'lmasa — past_meetings.
     * Hisobot hali tayyor bo'lmasa — null.
     *
     * @return list<array{id: string, name: string, email: string, join_time: string, leave_time: ?string}>|null
     */
    public function meetingParticipants(ZoomAccount $account, string $uuid): ?array
    {
        $encoded = $this->encodeUuid($uuid);
        $rows = $this->paginate($account, '/report/meetings/'.$encoded.'/participants', 'participants');

        if ($rows instanceof Response && $this->missingScope($rows)) {
            $rows = $this->paginate($account, '/past_meetings/'.$encoded.'/participants', 'participants');
        }
        if ($rows instanceof Response) {
            if (in_array($rows->status(), [404, 400], true) && ! $this->missingScope($rows)) {
                return null;
            }
            throw $this->error(__('Zoom’dan qatnashchilar roʻyxatini olib boʻlmadi'), $rows);
        }

        $participants = [];
        foreach ($rows as $row) {
            if (($row['status'] ?? 'in_meeting') === 'in_waiting_room' || empty($row['join_time'])) {
                continue;
            }
            $participants[] = [
                'id' => (string) (($row['participant_user_id'] ?? '') ?: ($row['id'] ?? '')),
                'name' => (string) ($row['name'] ?? ''),
                'email' => (string) ($row['user_email'] ?? ''),
                'join_time' => (string) $row['join_time'],
                'leave_time' => isset($row['leave_time']) && $row['leave_time'] !== '' ? (string) $row['leave_time'] : null,
            ];
        }

        return $participants;
    }

    /**
     * Sahifalab o'qish. Xato bo'lsa — javobning o'zi qaytariladi.
     *
     * @return list<array<string, mixed>>|Response
     */
    private function paginate(ZoomAccount $account, string $path, string $key): array|Response
    {
        $rows = [];
        $next = null;
        do {
            $response = $this->send($account, 'get', $path, array_filter(['page_size' => 300, 'next_page_token' => $next]));
            if ($response->failed()) {
                return $response;
            }
            array_push($rows, ...array_values((array) $response->json($key, [])));
            $next = $response->json('next_page_token') ?: null;
        } while ($next);

        return $rows;
    }

    /** Ilovada kerakli ruxsat (scope) yo'q: Zoom 4711 kodini qaytaradi. */
    private function missingScope(Response $response): bool
    {
        return (int) $response->json('code') === 4711 || $response->status() === 403;
    }

    /** UUID "/" bilan boshlansa yoki "//" bo'lsa, Zoom uni ikki marta kodlashni talab qiladi. */
    private function encodeUuid(string $uuid): string
    {
        $encoded = rawurlencode($uuid);

        return str_starts_with($uuid, '/') || str_contains($uuid, '//') ? rawurlencode($encoded) : $encoded;
    }

    /** Logga yozmasdan xato yaratadi (har daqiqalik kuzatuv logni to'ldirmasligi uchun). */
    private function error(string $message, Response $response): ExternalServiceException
    {
        $reason = $response->json('message');

        return new ExternalServiceException($message.($reason ? ": {$reason}" : ' (HTTP '.$response->status().').'));
    }

    /** @param array<string, mixed> $payload */
    private function send(ZoomAccount $account, string $method, string $path, array $payload = []): Response
    {
        $attempt = function (bool $fresh) use ($account, $method, $path, $payload): Response {
            try {
                return $this->request($this->token($account, $fresh))->{$method}($this->apiUrl.$path, $payload);
            } catch (ConnectionException $e) {
                throw new ExternalServiceException(__('Zoom serveriga ulanib boʻlmadi. Birozdan soʻng qayta urinib koʻring.'), ['error' => $e->getMessage()]);
            }
        };

        $response = $attempt(false);
        // Token muddatidan oldin bekor qilingan bo'lsa — yangisini olib, bir marta qaytaramiz.
        if ($response->status() === 401) {
            $response = $attempt(true);
        }
        if ($response->status() === 429) {
            throw new ExternalServiceException(__('Zoom soʻrovlar chegarasiga yetildi. Bir daqiqadan soʻng qayta urinib koʻring.'), status: 503);
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
