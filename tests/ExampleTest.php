<?php

namespace Io238\ISOCountries\Tests;

class ExampleTest extends TestCase {

    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }


    /** @test */
    public function can_load_config()
    {
        $this->assertNotNull(config('iso-countries'));
        $this->assertTrue(config('iso-countries.test'));
    }

}
