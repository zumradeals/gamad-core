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
 * Manifeste réel du dernier lot de sauvegarde (CAP-CORE-019), raccordement
 * obligatoire de CAP-CORE-015 (fiche partie 3 §20.1, partie 5 §5).
 *
 * Lit réellement `SHA256SUMS` dans le lot le plus récent de
 * `GAMAD_BACKUP_DIR` — jamais une supposition — et empreinte chaque membre
 * une seconde fois de façon indépendante (au-delà de ce que `backup.sh` a
 * déjà calculé), pour que le manifeste ne se contente pas de recopier un
 * fichier de contrôle produit par le même processus. Le lot de sauvegarde
 * contenant le registre de preuves lui-même n'est protégé que par le lot
 * *suivant* — décalage documenté, pas une boucle infinie.
 */
final class ManifesteSauvegardePreuvesCommand extends Command
{
    protected $signature = 'core:preuves:manifeste-sauvegarde {--lot= : horodatage du lot ; par défaut le plus récent de GAMAD_BACKUP_DIR}';

    protected $description = 'Émet un manifeste réel du dernier lot de sauvegarde PostgreSQL (CAP-CORE-019).';

    public function handle(): int
    {
        $racine = (string) (getenv('GAMAD_BACKUP_DIR') ?: '');
        if ($racine === '' || !is_dir($racine)) {
            $this->error('GAMAD_BACKUP_DIR absent ou introuvable.');

            return self::FAILURE;
        }
        $lot = (string) $this->option('lot');
        if ($lot === '') {
            $lots = glob(rtrim($racine, '/') . '/*', GLOB_ONLYDIR) ?: [];
            rsort($lots);
            $lotChemin = $lots[0] ?? null;
        } else {
            $lotChemin = rtrim($racine, '/') . '/' . $lot;
        }
        if ($lotChemin === null || !is_dir($lotChemin)) {
            $this->error('Aucun lot de sauvegarde trouvé.');

            return self::FAILURE;
        }
        $fichierSommes = $lotChemin . '/SHA256SUMS';
        if (!is_file($fichierSommes)) {
            $this->error("SHA256SUMS introuvable dans {$lotChemin}.");

            return self::FAILURE;
        }

        $membres = [];
        foreach (file($fichierSommes, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ligne) {
            if (!preg_match('/^([0-9a-f]{64})\s+(\S+)$/', trim($ligne), $m)) {
                continue;
            }
            [, $empreinteAttendue, $nom] = $m;
            $cheminMembre = $lotChemin . '/' . $nom;
            if (!is_file($cheminMembre)) {
                $this->error("Membre {$nom} absent du lot.");

                return self::FAILURE;
            }
            $calcul = CalculateurEmpreinte::empreinteFlux($cheminMembre, 'SHA-256');
            if (!hash_equals($empreinteAttendue, $calcul['empreinte_hex'])) {
                $this->error("Divergence d'empreinte pour {$nom} — le lot ne concorde pas avec SHA256SUMS.");

                return self::FAILURE;
            }
            $membres[] = [
                'chemin_logique' => $nom, 'taille_octets' => $calcul['taille_octets'],
                'algorithme_empreinte' => 'SHA-256', 'empreinte' => $calcul['empreinte_hex'],
                'media_type' => 'application/octet-stream',
            ];
        }
        if ($membres === []) {
            $this->error('SHA256SUMS ne contient aucune entrée exploitable.');

            return self::FAILURE;
        }

        try {
            $registre = new RegistrePreuves(PreuvesMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Manifeste interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $horodatage = basename($lotChemin);
        $g = [
            'politique' => PolitiquePreuves::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'CLI-MANIFESTE-SAUVEGARDE-' . strtoupper(bin2hex(random_bytes(6))),
        ];
        $preparation = $registre->preparerPreuve(array_merge($g, [
            'type_preuve' => 'MANIFESTE', 'sujet_type' => 'SAUVEGARDE', 'sujet_reference' => "BKP-{$horodatage}",
            'producteur_capacite_reference' => 'CAP-CORE-019', 'realm_reference' => 'RLM-GAMAD-CORE',
            'finalite_reference' => 'INTEGRITE_SAUVEGARDE', 'source_reference' => 'CAP-CORE-019 — sauvegarde PostgreSQL',
            'classification' => 'CONFIDENTIEL',
            'representation' => ['format_representation' => 'MANIFESTE_CANONIQUE', 'media_type' => 'application/json'],
        ]));
        if (isset($preparation['refus'])) {
            $this->error("Refus à la préparation : {$preparation['refus']} ({$preparation['detail']})");

            return self::FAILURE;
        }
        $resultat = $registre->emettreManifeste((string) $preparation['reference'], $membres, array_merge($g, [
            'type_manifeste' => 'SAUVEGARDE', 'nom' => "Lot {$horodatage}",
        ]));
        if (isset($resultat['refus'])) {
            $this->error("Refus à l'émission : {$resultat['refus']} ({$resultat['detail']})");

            return self::FAILURE;
        }

        $this->info("Manifeste {$resultat['reference']} : {$resultat['membres']} membre(s), racine {$resultat['racine_empreinte']}.");
        $this->line('Preuve de sauvegarde : ' . $preparation['reference'] . " (lot {$horodatage}).");

        return self::SUCCESS;
    }
}
