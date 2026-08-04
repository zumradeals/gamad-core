<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistrePreuves\Magasin as PreuvesMagasin;
use Gamad\RegistrePreuves\PolitiquePreuves;
use Gamad\RegistrePreuves\RegistrePreuves;
use Illuminate\Console\Command;

/**
 * Checkpoint réel de la tête du journal d'audit (CAP-CORE-013), raccordement
 * obligatoire de CAP-CORE-015 (fiche partie 3 §17).
 *
 * Lit la tête et le nombre d'événements directement dans le magasin
 * `CAP-CORE-013` — jamais recopiés depuis une supposition — et enregistre un
 * checkpoint non signé par défaut. Planifié ou déclenché explicitement,
 * jamais créé à chaque audit de preuve (pas de boucle checkpoint → audit →
 * checkpoint).
 */
final class CheckpointJournalPreuvesCommand extends Command
{
    protected $signature = 'core:preuves:checkpoint-journal';

    protected $description = 'Émet un checkpoint réel de la tête du journal opérationnel (CAP-CORE-013).';

    public function handle(): int
    {
        try {
            $journalPdo = JournalMagasin::connecter();
            $registre = new RegistrePreuves(PreuvesMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Checkpoint interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $ligne = $journalPdo->query(
            'SELECT empreinte, sequence_id FROM evenement_operationnel ORDER BY sequence_id DESC LIMIT 1'
        )->fetch();
        $nombre = (int) $journalPdo->query('SELECT count(*) FROM evenement_operationnel')->fetchColumn();
        if ($ligne === false) {
            $this->error('Aucun événement dans le journal opérationnel — rien à checkpointer.');

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $g = [
            'politique' => PolitiquePreuves::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'CLI-CHECKPOINT-JOURNAL-' . strtoupper(bin2hex(random_bytes(6))),
        ];
        $preparation = $registre->preparerPreuve(array_merge($g, [
            'type_preuve' => 'CHECKPOINT', 'sujet_type' => 'JOURNAL_OPERATIONNEL', 'sujet_reference' => 'journal-operationnel',
            'producteur_capacite_reference' => 'CAP-CORE-013', 'realm_reference' => 'RLM-GAMAD-CORE',
            'finalite_reference' => 'INTEGRITE_JOURNAL_AUDIT', 'source_reference' => 'CAP-CORE-013 — journal opérationnel',
            'classification' => 'INTERNE',
            'representation' => ['format_representation' => 'CHECKPOINT_CANONIQUE', 'media_type' => 'application/json'],
        ]));
        if (isset($preparation['refus'])) {
            $this->error("Refus à la préparation : {$preparation['refus']} ({$preparation['detail']})");

            return self::FAILURE;
        }
        $resultat = $registre->emettreCheckpoint((string) $preparation['reference'], array_merge($g, [
            'type_checkpoint' => 'JOURNAL_AUDIT', 'structure_reference' => 'journal-operationnel',
            'tete_empreinte' => (string) $ligne['empreinte'], 'sequence' => (int) $ligne['sequence_id'],
            'nombre_elements' => $nombre,
        ]));
        if (isset($resultat['refus'])) {
            $this->error("Refus à l'émission : {$resultat['refus']} ({$resultat['detail']})");

            return self::FAILURE;
        }

        $this->info("Checkpoint {$resultat['reference']} : séquence {$ligne['sequence_id']}, tête {$ligne['empreinte']}, {$nombre} événement(s).");

        return self::SUCCESS;
    }
}
