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
            ->assertSee('assets/md-notes-google.css', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('<meta property="og:title"', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"@type":"WebApplication"', false)
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
            ->assertSee('<link rel="canonical" href="'.route('documentation').'">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('"@type":"TechArticle"', false)
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

    public function test_public_sitemap_lists_the_indexable_pages(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
            ->assertSee('<loc>'.route('home').'</loc>', false)
            ->assertSee('<loc>'.route('documentation').'</loc>', false)
            ->assertSee('<lastmod>', false);

        $this->assertStringContainsString(
            'Sitemap: https://mdnotes.net/sitemap.xml',
            (string) file_get_contents(public_path('robots.txt')),
        );
    }
}
