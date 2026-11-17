<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use ZipArchive;

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
        // Har bir foydalanuvchining o'z sessiyasi: aks holda AuthenticateSession avvalgi
        // foydalanuvchining parol xeshini ko'rib, sessiyani yopadi.
        $this->flushSession();

        return $this->actingAs($user, 'web');
    }

    /**
     * Yuklab olingan .xlsx faylni o'qiydi: [varaq nomi => qatorlar]. Varaqlarda formula
     * (<f>) bo'lsa test yiqiladi — formula injection himoyasi. (OpenSpout o'quvchisi "="
     * bilan boshlanadigan matnni ham FormulaCell qilib qaytaradi, shuning uchun XML tekshiriladi.)
     *
     * @return array<string, list<list<mixed>>>
     */
    protected function xlsx(TestResponse $response): array
    {
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();

        $zip = new ZipArchive;
        $zip->open($path);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'xl/worksheets/')) {
                $this->assertStringNotContainsString('<f>', (string) $zip->getFromIndex($i));
            }
        }
        $zip->close();

        $reader = new Reader;
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = array_map(fn ($cell) => $cell->getValue(), $row->cells);
            }
        }
        $reader->close();

        return $sheets;
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
