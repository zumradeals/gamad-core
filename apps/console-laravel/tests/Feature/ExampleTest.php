<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_console_requires_a_gamad_session(): void
    {
        $response = $this->get('/console');

        $response->assertRedirect('/connexion');
    }

    public function test_the_network_home_is_public(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Des personnes qui inspirent.')
            ->assertSee(route('reseau.decouvrir'))
            ->assertSee(route('reseau.connexion'));
    }

    public function test_network_login_is_distinct_from_console_login(): void
    {
        $this->get('/fil?page=2')
            ->assertRedirect(route('reseau.connexion'))
            ->assertSessionHas('reseau.url.intended', '/fil?page=2')
            ->assertSessionMissing('url.intended');

        $this->get(route('reseau.connexion'))
            ->assertOk()
            ->assertSee('Se connecter à GAMAD Réseau')
            ->assertSee(route('reseau.connecter'))
            ->assertSee(route('inscription.formulaire'));
    }

    public function test_discover_links_to_existing_public_catalogues(): void
    {
        $this->get('/decouvrir')
            ->assertOk()
            ->assertSee(route('personnes.index'))
            ->assertSee(route('projets.index'))
            ->assertSee(route('entreprises.index'))
            ->assertSee(route('zumras.index'))
            ->assertSee(route('opportunites.index'));

        $this->get('/agir')->assertRedirect(route('reseau.connexion'));
    }
}
