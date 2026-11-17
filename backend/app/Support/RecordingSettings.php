<?php

namespace App\Support;

use App\Models\Setting;

/** Video yozuv sozlamalari (Administrator → Umumiy sozlamalar). */
final class RecordingSettings
{
    public const DEFAULTS = [
        // Yangi darslar Zoom bulutiga avtomatik yoziladimi.
        'enabled' => false,
        // Serverga yuklanib, hajmi tekshirilgach Zoom bulutidan o'chirilsinmi.
        'delete_from_zoom' => true,
        // trash — Zoom savatiga (30 kun tiklash mumkin, bulut hajmiga hisoblanmaydi), delete — butunlay.
        'delete_action' => 'trash',
        // Alohida audio (M4A) fayl ham saqlansinmi (video ichida ovoz baribir bor).
        'include_audio' => false,
    ];

    /** @return array{enabled: bool, delete_from_zoom: bool, delete_action: string, include_audio: bool} */
    public static function get(): array
    {
        $saved = (array) Setting::get('recording', []);

        return [
            'enabled' => (bool) ($saved['enabled'] ?? self::DEFAULTS['enabled']),
            'delete_from_zoom' => (bool) ($saved['delete_from_zoom'] ?? self::DEFAULTS['delete_from_zoom']),
            'delete_action' => ($saved['delete_action'] ?? null) === 'delete' ? 'delete' : 'trash',
            'include_audio' => (bool) ($saved['include_audio'] ?? self::DEFAULTS['include_audio']),
        ];
    }

    /** Yuklab olinadigan fayl turlari: video, chat, subtitr/transkript (+ audio). */
    public static function wanted(string $fileType): bool
    {
        $types = ['MP4', 'CHAT', 'TRANSCRIPT', 'CC'];
        if (self::get()['include_audio']) {
            $types[] = 'M4A';
        }

        return in_array(strtoupper($fileType), $types, true);
    }
}
