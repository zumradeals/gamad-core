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
            ->assertSee(route('acces.formulaire'));
    }
}
