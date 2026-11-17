<?php

namespace App\Support;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Excel'da to'g'ri ochiladigan CSV (UTF-8 BOM, ";" ajratgich). */
final class Csv
{
    /**
     * Excel formulasi sifatida bajarilmasligi uchun (CSV injection): =, +, -, @ bilan
     * boshlanadigan qiymat oldiga apostrof qo'yiladi.
     */
    public static function safe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * @param  list<string>  $header
     * @param  Closure(Closure(array<int, mixed>): void): void  $rows  qatorlarni yozuvchi funksiya oladi
     */
    public static function download(string $filename, array $header, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            $rows(fn (array $row) => fputcsv($out, $row, ';'));
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
