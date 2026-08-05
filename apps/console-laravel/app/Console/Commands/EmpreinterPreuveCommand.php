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
 * Empreinte un artefact borné et émet une preuve (CAP-CORE-015).
 *
 * Le chemin doit être absolu et rester dans un répertoire d'exploitation
 * fermé — jamais un chemin arbitraire fourni sans contrôle.
 */
final class EmpreinterPreuveCommand extends Command
{
    protected $signature = 'core:preuves:empreinter
        {chemin : chemin absolu de l’artefact}
        {--sujet-type=ARTEFACT_CLI}
        {--sujet=}
        {--realm=RLM-GAMAD-CORE}
        {--algorithme=SHA-256}
        {--dry-run : calcule et affiche l’empreinte sans émettre de preuve}';

    protected $description = 'Empreinte un artefact borné (chemin absolu) et émet une preuve EMPREINTE_ARTEFACT.';

    private const REPERTOIRES_AUTORISES = ['/var/backups/gamad-core', '/var/www/gamad-core'];

    public function handle(): int
    {
        $chemin = (string) $this->argument('chemin');
        $algorithme = (string) $this->option('algorithme');
        $autorise = false;
        foreach (self::REPERTOIRES_AUTORISES as $racine) {
            if (str_starts_with($chemin, $racine . '/')) {
                $autorise = true;
                break;
            }
        }
        if (!$autorise) {
            $this->error('Chemin hors des répertoires d’exploitation autorisés.');

            return self::FAILURE;
        }

        try {
            $calcul = CalculateurEmpreinte::empreinteFlux($chemin, $algorithme);
        } catch (\Throwable $e) {
            $this->error('Calcul interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }
        $this->info("{$algorithme} : {$calcul['empreinte_hex']} ({$calcul['taille_octets']} octet(s)).");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        try {
            $registre = new RegistrePreuves(PreuvesMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Émission interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }
        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $g = [
            'politique' => PolitiquePreuves::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'CLI-EMPREINTE-' . strtoupper(bin2hex(random_bytes(6))),
        ];
        $preparation = $registre->preparerPreuve(array_merge($g, [
            'type_preuve' => 'EMPREINTE_ARTEFACT', 'sujet_type' => (string) $this->option('sujet-type'),
            'sujet_reference' => (string) ($this->option('sujet') ?: basename($chemin)),
            'producteur_capacite_reference' => 'CAP-CORE-015', 'realm_reference' => (string) $this->option('realm'),
            'finalite_reference' => 'INTEGRITE_ARTEFACT_CLI', 'source_reference' => 'CLI core:preuves:empreinter',
            'classification' => 'INTERNE',
            'representation' => ['format_representation' => 'OCTETS_BRUTS', 'media_type' => 'application/octet-stream'],
        ]));
        if (isset($preparation['refus'])) {
            $this->error("Refus à la préparation : {$preparation['refus']} ({$preparation['detail']})");

            return self::FAILURE;
        }
        $contenu = file_get_contents($chemin);
        $resultat = $registre->emettreEmpreinte((string) $preparation['reference'], $algorithme, (string) $contenu, $g);
        if (isset($resultat['refus'])) {
            $this->error("Refus à l'émission : {$resultat['refus']} ({$resultat['detail']})");

            return self::FAILURE;
        }
        $this->info("Preuve {$preparation['reference']} émise ({$resultat['etat']}).");

        return self::SUCCESS;
    }
}
