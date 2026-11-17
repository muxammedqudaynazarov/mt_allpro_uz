<?php

namespace Tests\Unit;

use App\Support\AttendanceCalculator;
use App\Support\StudentName;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StudentNameTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function sameStudent(): array
    {
        return [
            'tartib va registr' => ['Ali Valiyev', 'VALIYEV ALI'],
            'kirill' => ['Ali Valiyev', 'Валиев Али'],
            'qurilma qo‘shimchasi' => ['Ali Valiyev', 'ali valiev (iPhone)'],
            'raqam' => ['Ali Valiyev', 'Ali Valiyev 2'],
            'apostrof va h/x' => ['Oʻrinboy Hasanov', 'Ўринбой Хасанов'],
            'qoraqalpoq lotin' => ['Dáwlet Qudaybergenov', 'Давлет Кудайбергенов'],
            'dj va ikkilangan harf' => ['Abdullayev Jamshid', 'Djamshid Abdulaev'],
        ];
    }

    #[DataProvider('sameStudent')]
    public function test_variants_of_one_name_share_key(string $a, string $b): void
    {
        $this->assertSame(StudentName::key($a), StudentName::key($b));
    }

    public function test_different_names_stay_different(): void
    {
        $this->assertNotSame(StudentName::key('Ali'), StudentName::key('Alim'));
        $this->assertNotSame(StudentName::key('Ali Valiyev'), StudentName::key('Ali'));
        $this->assertSame('?', StudentName::key('  '));
    }

    public function test_overlapping_intervals_are_counted_once(): void
    {
        $at = fn (string $time) => Carbon::parse('2026-11-17 '.$time);

        $this->assertSame(75 * 60, AttendanceCalculator::uniqueSeconds([
            [$at('14:33'), $at('15:00')],
            [$at('14:55'), $at('15:48')],
        ]));
        $this->assertSame(20 * 60, AttendanceCalculator::uniqueSeconds([
            [$at('14:30'), $at('14:40')],
            [$at('15:00'), $at('15:10')],
            [$at('15:02'), $at('15:05')],
        ]));
        $this->assertSame(0, AttendanceCalculator::uniqueSeconds([]));
    }
}
