<?php

namespace App\Services\Hemis;

use App\Exceptions\ExternalServiceException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HEMIS REST API'dan talabani HEMIS ID bo'yicha topish (HEMIS_API_TOKEN bilan).
 * Faqat F.I.Sh. va rasm olinadi. Natija faqat ID aniq mos kelsa qabul qilinadi
 * (qidiruv bir nechta talabani qaytarsa ham, boshqasining ismi yozilib qolmaydi).
 */
class HemisStudentApi
{
    public function enabled(): bool
    {
        return filled(config('services.hemis.api_token'));
    }

    /**
     * @return array{hemis_id: string, name: string, first_name: ?string, last_name: ?string, middle_name: ?string, picture: ?string}|null
     */
    public function find(string $hemisId): ?array
    {
        $item = collect($this->items($this->request($hemisId)))
            ->first(fn (array $row) => (string) ($row['student_id_number'] ?? '') === $hemisId || (string) ($row['id'] ?? '') === $hemisId);

        return $item ? self::map($item, $hemisId) : null;
    }

    /** Diagnostika (artisan hemis:student): javobdagi birinchi yozuvning maydon nomlari. */
    public function probe(string $hemisId): array
    {
        $response = $this->request($hemisId);
        $items = $this->items($response);

        return [
            'status' => $response->status(),
            'count' => count($items),
            'keys' => array_keys($items[0] ?? []),
            'match' => $this->find($hemisId),
        ];
    }

    private function request(string $hemisId): Response
    {
        $url = rtrim((string) config('services.hemis.api_url'), '/').'/'.ltrim((string) config('services.hemis.student_endpoint'), '/');
        try {
            $response = Http::withToken((string) config('services.hemis.api_token'))
                ->acceptJson()
                ->timeout(20)
                ->get($url, [(string) config('services.hemis.student_search_param') => $hemisId, 'limit' => 20]);
        } catch (ConnectionException) {
            throw new ExternalServiceException(__('HEMIS serveriga ulanib boʻlmadi.'));
        }
        if (in_array($response->status(), [401, 403], true)) {
            throw new ExternalServiceException(__('HEMIS_API_TOKEN notoʻgʻri yoki unga ruxsat berilmagan (HTTP :status).', ['status' => $response->status()]), status: 422);
        }
        if ($response->failed()) {
            throw new ExternalServiceException(__('HEMIS API javob bermadi (HTTP :status).', ['status' => $response->status()]));
        }

        return $response;
    }

    /** @return list<array<string, mixed>> */
    private function items(Response $response): array
    {
        $data = $response->json('data.items') ?? $response->json('data') ?? $response->json('items') ?? [];
        if (! is_array($data)) {
            return [];
        }
        // Bitta obyekt qaytsa ham ro'yxat sifatida.
        if (isset($data['id']) || isset($data['student_id_number'])) {
            $data = [$data];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /** @param array<string, mixed> $row */
    private static function map(array $row, string $hemisId): array
    {
        $str = static fn (mixed $value): ?string => is_scalar($value) && trim((string) $value) !== '' ? User::normalizeName((string) $value) : null;
        $last = $str($row['second_name'] ?? $row['surname'] ?? $row['last_name'] ?? null);
        $first = $str($row['first_name'] ?? $row['firstname'] ?? null);
        $middle = $str($row['third_name'] ?? $row['patronymic'] ?? null);
        $full = $str($row['full_name'] ?? null) ?? trim(implode(' ', array_filter([$last, $first, $middle])));
        $picture = $row['image'] ?? $row['picture'] ?? null;

        return [
            'hemis_id' => $hemisId,
            'name' => $full,
            'first_name' => $first,
            'last_name' => $last,
            'middle_name' => $middle,
            'picture' => is_string($picture) && str_starts_with($picture, 'http') ? $picture : null,
        ];
    }
}
