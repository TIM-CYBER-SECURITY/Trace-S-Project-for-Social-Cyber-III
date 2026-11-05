<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Unauthenticated visitors are redirected to the tactical login portal.
     */
    public function test_unauthenticated_visitor_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Tactical login gate renders correctly.
     */
    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('PUSSIBERAD');
        $response->assertSee('TRACE-S');
    }

    /**
     * Interactive tactical prototype renders successfully.
     */
    public function test_prototype_page_renders_successfully(): void
    {
        $response = $this->get('/prototype');
        $response->assertStatus(200);
        $response->assertSee('TRACE-S // Social Cyber Intelligence Platform');
        $response->assertSee('EARLY WARNING DASHBOARD');
        $response->assertSee('5-DIMENSIONAL SIMILARITY');
    }
}
