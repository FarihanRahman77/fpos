<?php

namespace Tests\Feature;

use Tests\AppTestCase;

/** Updated: the public home page is the branch-wise login form (needs the settings + branches fixtures). */
class ExampleTest extends AppTestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertStatus(200)->assertSee('Main Branch');
    }
}
