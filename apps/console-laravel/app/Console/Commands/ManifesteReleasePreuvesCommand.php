<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistrePreuves\CalculateurEmpreinte;
use Gamad\RegistrePreuves\Magasin as PreuvesMagasin;
use Gamad\RegistrePreuves\PolitiquePreuves;
use Gamad\RegistrePreuves\RegistrePreuves;
use Illuminate\Console\Command;

/**
 * Manifeste borné des artefacts d'une release (CAP-CORE-015, fiche
 * partie 3 §23). Non signé par défaut : une signature de release exige une
 * clé de release dédiée, jamais une clé disponible aux pull requests non
 * approuvées — hors du périmètre CI de ce chantier.
 */
final class ManifesteReleasePreuvesCommand extends Command
{
    protected $signature = 'core:preuves:manifeste-release {--commit= : commit à consigner ; par défaut HEAD}';

    protected $description = 'Émet un manifeste borné des artefacts de release (commit, OpenAPI, bootstraps).';

    private const ARTEFACTS = [
        'apps/console-laravel/openapi/core-v1.yaml',
        'core/registre-vocabulaire/resources/bootstrap-vocabulaire-v1.json',
        'core/registre-secrets-cles/resources/bootstrap-secrets-cles-v1.json',
        'core/registre-normes/resources/index-baseline-v1.json',
    ];

    public function handle(): int
    {
        $racine = dirname(__DIR__, 5);
        $commit = (string) ($this->option('commit') ?: trim((string) shell_exec('git -C ' . escapeshellarg($racine) . ' rev-parse HEAD 2>/dev/null')));
        if ($commit === '') {
            $this->error('Commit introuvable.');

            return self::FAILURE;
        }

        $membres = [];
        foreach (self::ARTEFACTS as $relatif) {
            $chemin = $racine . '/' . $relatif;
            if (!is_file($chemin)) {
                continue;
            }
            $calcul = CalculateurEmpreinte::empreinteFlux($chemin, 'SHA-256');
            $membres[] = [
                'chemin_logique' => $relatif, 'taille_octets' => $calcul['taille_octets'],
                'algorithme_empreinte' => 'SHA-256', 'empreinte' => $calcul['empreinte_hex'],
                'media_type' => 'text/plain', 'obligatoire' => false,
            ];
        }
        if ($membres === []) {
            $this->error('Aucun artefact de release trouvé.');

            return self::FAILURE;
        }

        try {
            $registre = new RegistrePreuves(PreuvesMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Manifeste interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }
        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $g = [
            'politique' => PolitiquePreuves::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'CLI-MANIFESTE-RELEASE-' . strtoupper(bin2hex(random_bytes(6))),
        ];
        $preparation = $registre->preparerPreuve(array_merge($g, [
            'type_preuve' => 'MANIFESTE', 'sujet_type' => 'RELEASE', 'sujet_reference' => substr($commit, 0, 12),
            'producteur_capacite_reference' => 'CAP-CORE-015', 'realm_reference' => 'RLM-GAMAD-CORE',
            'finalite_reference' => 'INTEGRITE_RELEASE', 'source_reference' => 'CLI core:preuves:manifeste-release',
            'classification' => 'INTERNE',
            'representation' => ['format_representation' => 'MANIFESTE_CANONIQUE', 'media_type' => 'application/json'],
        ]));
        if (isset($preparation['refus'])) {
            $this->error("Refus à la préparation : {$preparation['refus']} ({$preparation['detail']})");

            return self::FAILURE;
        }
        $resultat = $registre->emettreManifeste((string) $preparation['reference'], $membres, array_merge($g, [
            'type_manifeste' => 'ARTEFACTS_CI', 'nom' => "Release {$commit}",
        ]));
        if (isset($resultat['refus'])) {
            $this->error("Refus à l'émission : {$resultat['refus']} ({$resultat['detail']})");

            return self::FAILURE;
        }

        $this->info("Manifeste de release {$resultat['reference']} : {$resultat['membres']} artefact(s), racine {$resultat['racine_empreinte']}.");

        return self::SUCCESS;
    }
}
