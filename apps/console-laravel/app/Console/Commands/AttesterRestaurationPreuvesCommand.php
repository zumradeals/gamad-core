<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistrePreuves\Magasin as PreuvesMagasin;
use Gamad\RegistrePreuves\PolitiquePreuves;
use Gamad\RegistrePreuves\RegistrePreuves;
use Illuminate\Console\Command;

/**
 * Attestation réelle d'un exercice de restauration (CAP-CORE-019),
 * raccordement obligatoire de CAP-CORE-015 (fiche partie 3 §20.3).
 *
 * `CAP-CORE-019` reste propriétaire de l'exercice — `restore-drill.sh`
 * l'exécute et en imprime les comptages réels. Cette commande ne rejoue
 * jamais la restauration elle-même : elle enregistre les résultats déjà
 * produits, fournis explicitement par l'opérateur qui vient d'exécuter
 * l'exercice, et lie l'attestation au manifeste d'origine par `RESTAURE_DEPUIS`.
 */
final class AttesterRestaurationPreuvesCommand extends Command
{
    protected $signature = 'core:preuves:attester-restauration
        {manifeste : référence du manifeste de sauvegarde restauré (MNF-GAMAD-…)}
        {--resultat=CONFORME : résultat constaté de l’exercice}
        {--comptage=* : comptages clé=valeur observés après restauration, ex. identites=42}';

    protected $description = 'Enregistre une attestation réelle d’un exercice de restauration déjà exécuté par CAP-CORE-019.';

    public function handle(): int
    {
        $manifesteReference = (string) $this->argument('manifeste');
        $comptages = [];
        foreach ((array) $this->option('comptage') as $paire) {
            [$cle, $valeur] = array_pad(explode('=', (string) $paire, 2), 2, null);
            if ($cle === null || $valeur === null) {
                $this->error("--comptage doit suivre la forme cle=valeur ({$paire}).");

                return self::FAILURE;
            }
            $comptages[$cle] = is_numeric($valeur) ? (int) $valeur : $valeur;
        }

        try {
            $registre = new RegistrePreuves(PreuvesMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Attestation interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $manifesteInfo = $registre->resoudreManifeste($manifesteReference);
        if ($manifesteInfo === null) {
            // Le paramètre peut être la référence de preuve du manifeste ; accepter les deux.
            $manifesteInfo = $registre->resoudrePreuve($manifesteReference);
        }
        if ($manifesteInfo === null) {
            $this->error("Manifeste ou preuve `{$manifesteReference}` introuvable.");

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $g = [
            'politique' => PolitiquePreuves::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'CLI-ATTESTATION-RESTAURATION-' . strtoupper(bin2hex(random_bytes(6))),
        ];
        $preparation = $registre->preparerPreuve(array_merge($g, [
            'type_preuve' => 'ATTESTATION', 'sujet_type' => 'RESTAURATION', 'sujet_reference' => 'RST-' . strtoupper(bin2hex(random_bytes(6))),
            'producteur_capacite_reference' => 'CAP-CORE-019', 'realm_reference' => 'RLM-GAMAD-CORE',
            'finalite_reference' => 'PREUVE_RESTAURATION', 'source_reference' => 'CAP-CORE-019 — exercice de restauration',
            'classification' => 'CONFIDENTIEL',
            'representation' => ['format_representation' => 'DECLARATION_CANONIQUE', 'media_type' => 'application/json'],
        ]));
        if (isset($preparation['refus'])) {
            $this->error("Refus à la préparation : {$preparation['refus']} ({$preparation['detail']})");

            return self::FAILURE;
        }
        $declaration = array_merge(['manifeste_reference' => $manifesteReference], $comptages);
        $resultat = $registre->emettreAttestation((string) $preparation['reference'], array_merge($g, [
            'type_attestation' => 'RESTAURATION_VERIFIEE', 'version_schema' => '1',
            'resultat' => (string) $this->option('resultat'), 'declaration' => $declaration,
        ]));
        if (isset($resultat['refus'])) {
            $this->error("Refus à l'émission : {$resultat['refus']} ({$resultat['detail']})");

            return self::FAILURE;
        }
        $lien = $registre->declarerLien((string) $preparation['reference'], (string) ($manifesteInfo['preuve_reference'] ?? $manifesteReference), 'RESTAURE_DEPUIS');
        if (isset($lien['refus'])) {
            $this->warn("Lien RESTAURE_DEPUIS non établi : {$lien['refus']}.");
        }

        $this->info("Attestation {$resultat['reference']} enregistrée, liée à {$manifesteReference}.");

        return self::SUCCESS;
    }
}
