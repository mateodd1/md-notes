<?php

namespace Tests\Feature;

use Tests\TestCase;

class CanonicalDomainTest extends TestCase
{
    public function test_the_legacy_domain_permanently_redirects_to_the_canonical_domain_with_path_and_query(): void
    {
        config()->set('md-notes.canonical_url', 'https://mdnotes.net');
        config()->set('md-notes.legacy_hosts', ['md.mateo.ovh']);

        $this->get('https://md.mateo.ovh/share/A2BCD?source=old-link')
            ->assertStatus(308)
            ->assertRedirect('https://mdnotes.net/share/A2BCD?source=old-link');
    }

    public function test_the_canonical_domain_is_served_normally(): void
    {
        config()->set('md-notes.canonical_url', 'https://mdnotes.net');
        config()->set('md-notes.legacy_hosts', ['md.mateo.ovh']);

        $this->get('https://mdnotes.net/')->assertOk();
    }
}
