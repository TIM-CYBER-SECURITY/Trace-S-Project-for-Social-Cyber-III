<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTraceFixtures;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTraceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTraceFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanTestingProvisioning();
        parent::tearDown();
    }

    public function test_unauthenticated_visitor_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_login_page_renders_clean_without_hardcoded_credentials(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('TRACE-S');
        $response->assertSee('TRS-XXXX-XXXX');
        $response->assertSee('data-password-toggle="password"', false);
        $response->assertDontSee('operator@traces.mil.id');
        $response->assertDontSee('operator123');
        $response->assertDontSee('Quick Switch');
        $response->assertDontSee('cdn.tailwindcss.com');
        $response->assertDontSee('/register');
        $response->assertDontSee('/platform/login');
    }

    public function test_public_health_endpoint_returns_minimal_status(): void
    {
        $response = $this->getJson('/health');
        $response->assertStatus(200);
        $response->assertExactJson(['status' => 'ok']);
    }

    public function test_removed_public_registration_and_platform_routes_return_not_found(): void
    {
        foreach (['/register', '/register/submitted', '/status', '/platform', '/platform/login', '/prototype.html'] as $uri) {
            $this->get($uri)->assertStatus(404);
        }

        $this->post('/register', ['full_name' => 'Visitor'])->assertStatus(404);
    }
}
