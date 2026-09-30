<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NoteSpace;
use DOMDocument;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        file_put_contents('/tmp/md-notes-testing.sqlite', '');
        parent::setUp();
        $this->artisan('migrate:fresh --force')->assertSuccessful();
    }

    public function test_pages_authorize_only_their_own_scripts_and_never_inline_attributes(): void
    {
        $user = User::factory()->create();
        app(NoteSpace::class)->write($user, 'Note.md', '# Note');
        $nonces = [];
        $pages = [route('home'), route('login'), route('register'), route('offline.shell'), '/missing-404'];
        foreach ($pages as $url) {
            $this->assertPolicy($this->get($url), $nonces);
        }
        $this->actingAs($user);
        foreach ([route('notes.show', ['path' => 'Note.md']), route('profile.edit'), route('shares.index'), route('trash.index')] as $url) {
            $this->assertPolicy($this->get($url), $nonces);
        }

        $this->assertCount(count($nonces), array_unique($nonces));
    }

    private function assertPolicy(TestResponse $response, array &$nonces): void
    {
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($policy);
        $this->assertStringNotContainsString('unsafe-inline', $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        $this->assertStringContainsString("script-src-attr 'none'", $policy);
        $this->assertStringContainsString("style-src-attr 'none'", $policy);
        $this->assertSame(1, preg_match("/'nonce-([^']+)'/", $policy, $match));
        $nonces[] = $match[1];
        $dom = new DOMDocument;
        @$dom->loadHTML($response->getContent());
        $this->assertGreaterThan(0, $dom->getElementsByTagName('script')->length);
        foreach ($dom->getElementsByTagName('script') as $script) {
            $this->assertSame($match[1], $script->getAttribute('nonce'));
        }
        foreach ($dom->getElementsByTagName('*') as $element) {
            $this->assertFalse($element->hasAttribute('style'), $element->tagName);
        }
    }
}
