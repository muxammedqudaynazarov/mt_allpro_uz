<?php

namespace App\Services\Hemis;

/**
 * HEMIS OAuth'dan olingan xodim ma'lumotlari. Faqat kerakli maydonlar o'qiladi:
 * F.I.Sh., HEMIS ID va rasm saqlanadi; type, university_id (kirish huquqi) va login
 * (eski hisobni topish) faqat tekshirish uchun ishlatiladi, saqlanmaydi.
 */
final readonly class HemisUser
{
    public function __construct(
        public string $id,
        public ?string $login,
        public ?string $type,
        public string $name,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $middleName,
        public ?string $picture,
        public ?string $universityId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $str = static fn (mixed $value): ?string => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;

        $first = $str($data['firstname'] ?? $data['first_name'] ?? null);
        $last = $str($data['surname'] ?? $data['second_name'] ?? $data['last_name'] ?? null);
        $middle = $str($data['patronymic'] ?? $data['third_name'] ?? null);
        $name = $str($data['name'] ?? $data['full_name'] ?? null) ?? trim(implode(' ', array_filter([$last, $first, $middle])));

        return new self(
            id: (string) ($str($data['id'] ?? null) ?? $str($data['employee_id_number'] ?? null) ?? ''),
            login: $str($data['login'] ?? $data['employee_id_number'] ?? null),
            type: $str($data['type'] ?? null),
            name: $name !== '' ? $name : 'Nomaʼlum xodim',
            firstName: $first,
            lastName: $last,
            middleName: $middle,
            picture: $str($data['picture'] ?? $data['image'] ?? null),
            universityId: $str($data['university_id'] ?? (is_array($data['university'] ?? null) ? ($data['university']['id'] ?? null) : null)),
        );
    }
}
