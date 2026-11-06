<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuse un acces Reseau sans session Core', function () {
    $this->get('/reseau/mon-compte')->assertRedirect(route('acces.formulaire'));
});

it('ne confere pas un acces Reseau via une session Laravel seule', function () {
    $utilisateur = \App\Models\User::factory()->create();

    $this->actingAs($utilisateur)
        ->get('/reseau/mon-compte')
        ->assertRedirect(route('acces.formulaire'));
});
