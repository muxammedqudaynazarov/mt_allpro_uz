<?php

namespace App\Support;

use Normalizer;

/**
 * Zoom'dagi ism bo'yicha talabani tanish uchun kalit.
 *
 * Talabalar Zoom'ga hisobsiz kiradi va ismini o'zi yozadi, shuning uchun bir
 * odamning ismi turli ko'rinishda kelishi mumkin. Kalit quyidagi farqlarni yo'qotadi:
 *  - harf registri, kirill/lotin yozuvi ("Валиев" = "Valiev");
 *  - apostroflar va diakritika ("Oʻrinboy" = "Orinboy", "Dáwlet" = "Dawlet");
 *  - so'zlar tartibi ("Ali Valiyev" = "Valiyev Ali");
 *  - qavs ichidagi qo'shimchalar, raqamlar va qurilma nomlari ("Ali (iPhone)", "Ali 2");
 *  - keng tarqalgan yozilish farqlari: -yev/-ev, h/x, q/k, w/v, ikkilangan harflar.
 *
 * Bu taxminiy usul: ikki xil talabaning ismi bir xil bo'lsa, ular bitta bo'lib
 * qoladi; bir talaba butunlay boshqa ism bilan kirsa — alohida. Bunday holatlarni
 * administrator "Talabalar" sahifasida qo'lda tuzatadi.
 */
final class StudentName
{
    private const CYRILLIC = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'j',
        'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'x', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'i', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya', 'ў' => 'o', 'қ' => 'q', 'ғ' => 'g', 'ҳ' => 'h', 'ә' => 'a', 'ө' => 'o', 'ү' => 'u',
        'ң' => 'n', 'ұ' => 'u', 'һ' => 'h', 'і' => 'i', 'ї' => 'i', 'є' => 'e',
    ];

    /** Normalizer (intl) bo'lmagan serverlar uchun ham asosiy harflar. */
    private const LATIN = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ǵ' => 'g', 'ğ' => 'g', 'ı' => 'i', 'í' => 'i',
        'ì' => 'i', 'î' => 'i', 'ń' => 'n', 'ñ' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ş' => 'sh',
        'ç' => 'ch',
    ];

    /** Zoom qurilma nomini ism o'rniga qo'yganda ("iPhone (7)") — boshqa so'z bo'lsa, tashlab yuboriladi. */
    private const DEVICE_WORDS = [
        'iphone', 'ipad', 'android', 'samsung', 'galaxy', 'redmi', 'xiaomi', 'huawei', 'honor', 'tecno',
        'infinix', 'realme', 'oppo', 'vivo', 'poco', 'nokia', 'pixel', 'oneplus', 'motorola', 'lenovo',
        'asus', 'acer', 'dell', 'macbook', 'laptop', 'noutbuk', 'telefon', 'phone', 'mobile', 'pc',
        'zoom', 'user', 'guest', 'mehmon',
    ];

    /** Ko'rsatish uchun tozalangan ism. */
    public static function clean(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return $name === '' ? '?' : mb_substr($name, 0, 120);
    }

    /** Talabani tanish kaliti (bir guruh ichida). */
    public static function key(string $name): string
    {
        $text = mb_strtolower($name);
        $text = (string) preg_replace('/[(\[{][^)\]}]*[)\]}]/u', ' ', $text);
        $text = strtr($text, self::CYRILLIC);
        $text = strtr($text, self::LATIN);
        if (class_exists(Normalizer::class)) {
            $text = (string) preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($text, Normalizer::FORM_D));
        }
        $text = (string) preg_replace("/['`ʻʼ‘’´]/u", '', $text);
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);

        $tokens = array_values(array_filter(explode(' ', $text), fn (string $token) => $token !== ''));
        $words = array_values(array_filter($tokens, fn (string $token) => ! ctype_digit($token) && ! in_array($token, self::DEVICE_WORDS, true)));
        if ($words) {
            $tokens = $words;
        }

        $tokens = array_values(array_unique(array_map(self::canonical(...), $tokens)));
        sort($tokens);
        $key = implode(' ', $tokens);

        return $key === '' ? '?' : substr($key, 0, 191);
    }

    private static function canonical(string $token): string
    {
        $token = str_replace(['dj', 'yev', 'iy'], ['j', 'ev', 'i'], $token);
        $token = strtr($token, ['h' => 'x', 'q' => 'k', 'w' => 'v']);

        return (string) preg_replace('/(.)\1+/', '$1', $token);
    }
}
