<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R01 : une session CTR-16 ne confère aucun droit d'administration.
 *
 * L'accès administratif exige un mandat institutionnel actif ET une politique
 * CAP-CORE-004 active permettant explicitement « consulter la console ».
 * Aucune permission implicite, aucun rôle local ni exception de bootstrap.
 * Tant que la politique n'est pas adoptée, l'accès est fermé (INV-27).
 */
final class ExigerAccesConsole
{
    public const ACTION = 'consulter la console';

    public function handle(Request $request, Closure $suivant): Response
    {
        $acteur = $request->attributes->get('gamad_entite');
        if (!is_string($acteur) || $acteur === '') {
            abort(403);
        }

        try {
            $mandat = (new Ctr02(Db::connect()))->resoudreMandat(null, $acteur, gmdate('Y-m-d'));
            if (!is_array($mandat) || !str_starts_with((string) ($mandat['etat'] ?? ''), 'ACTIF')) {
                abort(403);
            }

            $decision = (new Ctr03(PolitiquesMagasin::connecter()))
                ->autoriser($acteur, self::ACTION, 'console');
            if (($decision['decision'] ?? null) !== 'PERMIS') {
                abort(403);
            }
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable) {
            // Indisponibilité des registres : refus, jamais ouverture implicite.
            abort(403);
        }

        return $suivant($request);
    }
}
