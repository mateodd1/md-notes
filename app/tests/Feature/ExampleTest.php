<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guests_can_open_the_landing_page_with_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('md-notes')
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_guests_can_open_the_markdown_documentation(): void
    {
        $this->withHeader('Accept-Language', 'es')->get(route('documentation'))
            ->assertOk()
            ->assertSee('Documentación de md-notes')
            ->assertSee('API desde terminal')
            ->assertSee('Authorization: Bearer TU_TOKEN', false);
    }

    public function test_documentation_is_shown_in_english_for_non_spanish_browsers(): void
    {
        $this->withHeader('Accept-Language', 'en')->get(route('documentation'))
            ->assertOk()
            ->assertSee('md-notes documentation')
            ->assertSee('Terminal API')
            ->assertSee('Authorization: Bearer YOUR_TOKEN', false);
    }
}
