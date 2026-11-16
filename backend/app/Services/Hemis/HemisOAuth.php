<?php

namespace App\Services\Hemis;

use App\Exceptions\ExternalServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HEMIS OAuth 2.0 (authorization code) mijozi.
 *
 * Xodimlar uchun endpointlar universitetning HEMIS manzilida joylashgan:
 *   {base}/oauth/authorize, {base}/oauth/access-token, {base}/oauth/api/user
 */
class HemisOAuth
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public static function make(): self
    {
        return new self((array) config('services.hemis'));
    }

    public function enabled(): bool
    {
        return filled($this->config['base_url'] ?? null)
            && filled($this->config['client_id'] ?? null)
            && filled($this->config['client_secret'] ?? null);
    }

    public function authorizeUrl(string $state): string
    {
        return $this->url('/oauth/authorize').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): string
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->post($this->url('/oauth/access-token'), [
                'grant_type' => 'authorization_code',
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'redirect_uri' => $this->redirectUri(),
                'code' => $code,
            ]);
        } catch (ConnectionException $e) {
            throw new ExternalServiceException('HEMIS serveriga ulanib boʻlmadi.', ['error' => $e->getMessage()]);
        }

        $token = $response->json('access_token');
        if ($response->failed() || ! is_string($token) || $token === '') {
            Log::warning('HEMIS token olinmadi', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);
            throw new ExternalServiceException('HEMIS kirish kodini tasdiqlamadi.');
        }

        return $token;
    }

    public function fetchUser(string $accessToken): HemisUser
    {
        try {
            $response = Http::withToken($accessToken)->acceptJson()->timeout(15)
                ->get($this->url('/oauth/api/user'), ['fields' => $this->config['user_fields'] ?? 'id,uuid,type,name,firstname,surname,patronymic,login,picture,email,phone,university_id']);
        } catch (ConnectionException $e) {
            throw new ExternalServiceException('HEMIS serveriga ulanib boʻlmadi.', ['error' => $e->getMessage()]);
        }

        $data = $response->json('data') ?? $response->json();
        if ($response->failed() || ! is_array($data)) {
            Log::warning('HEMIS foydalanuvchi maʼlumoti olinmadi', ['status' => $response->status()]);
            throw new ExternalServiceException('HEMIS foydalanuvchi maʼlumotlarini qaytarmadi.');
        }

        $user = HemisUser::fromArray($data);
        if ($user->id === '') {
            throw new ExternalServiceException('HEMIS javobida xodim identifikatori yoʻq.');
        }

        return $user;
    }

    public function universityId(): ?string
    {
        $value = $this->config['university_id'] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /** @return list<string> */
    public function allowedTypes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) ($this->config['allowed_types'] ?? 'employee')))));
    }

    public function autoRegister(): bool
    {
        return (bool) ($this->config['auto_register'] ?? true);
    }

    private function redirectUri(): string
    {
        return (string) ($this->config['redirect_uri'] ?: url('/auth/hemis/callback'));
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->config['base_url'], '/').$path;
    }
}
