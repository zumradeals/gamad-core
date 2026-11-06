<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Normalizer;
use PHPUnit\Framework\TestCase;

final class ReseauNormalizerTest extends TestCase
{
    public function test_cle_de_recherche_ignore_accents_casse_et_espaces(): void
    {
        self::assertSame('comptabilite', Normalizer::key('  COMPTABILITÉ  '));
    }

    public function test_normalisation_multiligne_preserve_une_seule_ligne_vide(): void
    {
        self::assertSame("Bonjour\n\nMonde", Normalizer::multiline(" Bonjour  \r\n\r\n\r\n Monde "));
    }

    public function test_motif_like_ne_permet_pas_les_jokers_saisis(): void
    {
        self::assertSame('%a\\%b\\_c%', Normalizer::likeContains('a%b_c'));
    }
}
