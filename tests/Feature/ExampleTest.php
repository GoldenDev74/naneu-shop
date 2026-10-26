<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_racine_mene_au_tableau_de_bord(): void
    {
        // La racine n'affiche pas de page vitrine : Naneu Shop est un outil
        // interne, on renvoie directement vers l'application.
        $this->get('/')->assertRedirect('/tableau-de-bord');
    }

    public function test_la_sonde_de_sante_repond(): void
    {
        $this->get('/up')->assertOk();
    }
}
