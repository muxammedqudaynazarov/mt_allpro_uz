<?php

namespace Tests\Unit;

use App\Support\PersonName;
use PHPUnit\Framework\TestCase;

/** Ismlar katta harflarda; qoraqalpoq lotin alifbosida "ı" ning bosh harfi — "Í". */
class PersonNameTest extends TestCase
{
    public function test_karakalpak_and_uzbek_letters_are_uppercased_correctly(): void
    {
        $this->assertSame('TURSÍNBAEVA MADINA', PersonName::upper('Tursınbaeva Madina'));
        $this->assertSame('ǴÁRESIZLIK ŃÓKIS ÚLKEN', PersonName::upper('ǵáresizlik ńókis úlken'));
        $this->assertSame('OʻRINBOYEV GʻAYRAT', PersonName::upper('Oʻrinboyev Gʻayrat'));
        $this->assertSame('ҚУДАЙНАЗАРОВ', PersonName::upper('Қудайназаров'));
        $this->assertNull(PersonName::upper(null));
    }
}
