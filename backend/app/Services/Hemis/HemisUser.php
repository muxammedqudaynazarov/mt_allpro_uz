<?php

namespace App\Services\Hemis;

/** HEMIS OAuth'dan olingan xodim ma'lumotlari. */
final readonly class HemisUser
{
    public function __construct(
        public string $id,
        public ?string $uuid,
        public ?string $login,
        public ?string $type,
        public string $name,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $middleName,
        public ?string $email,
        public ?string $phone,
        public ?string $picture,
        public ?string $universityId,
        public ?string $department,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $str = static fn (mixed $value): ?string => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;

        $first = $str($data['firstname'] ?? $data['first_name'] ?? null);
        $last = $str($data['surname'] ?? $data['second_name'] ?? $data['last_name'] ?? null);
        $middle = $str($data['patronymic'] ?? $data['third_name'] ?? null);
        $name = $str($data['name'] ?? $data['full_name'] ?? null) ?? trim(implode(' ', array_filter([$last, $first, $middle])));

        $department = $data['department'] ?? null;
        if (is_array($department)) {
            $department = $department['name'] ?? null;
        }

        return new self(
            id: (string) ($str($data['id'] ?? null) ?? $str($data['employee_id_number'] ?? null) ?? ''),
            uuid: $str($data['uuid'] ?? null),
            login: $str($data['login'] ?? $data['employee_id_number'] ?? null),
            type: $str($data['type'] ?? null),
            name: $name !== '' ? $name : 'Nomaʼlum xodim',
            firstName: $first,
            lastName: $last,
            middleName: $middle,
            email: $str($data['email'] ?? null),
            phone: $str($data['phone'] ?? null),
            picture: $str($data['picture'] ?? $data['image'] ?? null),
            universityId: $str($data['university_id'] ?? (is_array($data['university'] ?? null) ? ($data['university']['id'] ?? null) : null)),
            department: $str($department),
        );
    }
}
