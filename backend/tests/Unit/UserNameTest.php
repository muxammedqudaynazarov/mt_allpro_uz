<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserNameTest extends TestCase
{
    public function test_uppercase_hemis_names_are_title_cased(): void
    {
        $this->assertSame('Qudaynazarov Muxammed', User::normalizeName('QUDAYNAZAROV  MUXAMMED'));
        $this->assertSame('Oʻrinboyev Gʻayrat', User::normalizeName('OʻRINBOYEV GʻAYRAT'));
        $this->assertSame('Already Mixed', User::normalizeName('Already Mixed'));
        $this->assertNull(User::normalizeName('   '));
    }

    public function test_short_name(): void
    {
        $user = new User(['name' => 'Jumabaev Rustem Kenesbaevich', 'first_name' => 'Rustem', 'last_name' => 'Jumabaev']);
        $this->assertSame('Jumabaev R.', $user->shortName());
        $this->assertSame('Rustem', $user->firstName());

        $single = new User(['name' => 'Ali']);
        $this->assertSame('Ali', $single->shortName());
    }
}
