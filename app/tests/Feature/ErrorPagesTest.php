<?php

namespace Tests\Feature;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_missing_pages_use_the_browsers_language_without_changing_api_errors(): void
    {
        $this->withHeader('Accept-Language', 'es')->get('/missing-error-page-9327')
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertSee('md-notes-errors.css')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->withHeader('Accept-Language', 'en')->get('/missing-error-page-9327')
            ->assertNotFound()
            ->assertSee('Page not found');

        $this->getJson('/api/missing-error-page-9327')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_server_error_page_does_not_expose_exception_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/test-server-error-page', fn () => throw new RuntimeException('private diagnostic detail'));

        $this->withHeader('Accept-Language', 'es')->get('/test-server-error-page')
            ->assertStatus(500)
            ->assertSee('Ha ocurrido un error')
            ->assertDontSee('private diagnostic detail');
    }

    public function test_rate_limit_page_preserves_retry_after_and_json_behavior(): void
    {
        Route::get('/test-rate-limit-page', fn () => throw new ThrottleRequestsException(
            'Too many requests',
            null,
            ['Retry-After' => '12'],
        ));

        $this->withHeader('Accept-Language', 'es')->get('/test-rate-limit-page')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '12')
            ->assertSee('Demasiadas solicitudes');

        $this->withHeader('Accept-Language', 'es')->getJson('/test-rate-limit-page')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '12')
            ->assertJsonPath('message', __('ui.too_many_requests', ['seconds' => 12]));

        $this->withHeader('Accept-Language', 'fr,es;q=0.8,en;q=0.6')->get('/test-rate-limit-page')
            ->assertStatus(429)
            ->assertSee('Demasiadas solicitudes')
            ->assertSee('Inténtalo de nuevo en 12 segundos.');
    }
}
