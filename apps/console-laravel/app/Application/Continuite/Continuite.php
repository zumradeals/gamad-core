<?php

declare(strict_types=1);

namespace App\Application\Continuite;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreNormes\Db;

/**
 * Pilotage de la continuité depuis la console (CAP-CORE-019).
 *
 * La console tourne en `www-data`, la sauvegarde en `postgres`. Cette classe
 * n'exécute JAMAIS une commande système : elle écrit des réglages et dépose un
 * fichier-signal dans un répertoire partagé qu'une unité systemd surveille.
 * Aucun sudo, aucune escalade de privilège — deux processus se parlent par un
 * répertoire, et chacun garde ses droits.
 *
 * Le mot de passe de la destination est le premier secret REJOUABLE du Core :
 * il doit être relu chaque nuit, donc conservé déchiffrable. C'est un choix
 * assumé, inscrit ici pour qu'il ne se découvre pas par surprise. Il vit hors
 * du dépôt, dans un fichier du groupe de continuité, et n'est jamais réaffiché.
 */
final class Continuite
{
    public const ACTION_CONFIGURER = 'configurer la continuité des sauvegardes';
    public const ACTION_DECLENCHER = 'déclencher une opération de continuité';
    public const POLITIQUE = 'POL-CONTINUITE-V1';

    /** @var list<string> */
    public const OPERATIONS = ['sauvegarde', 'exercice'];

    /**
     * Modes de protection du transfert, du plus sûr au plus faible.
     *
     * `epingle` est le défaut : beaucoup d'hébergements mutualisés présentent
     * un certificat valide mais émis pour un autre nom que celui du serveur
     * FTP. L'épinglage garde le chiffrement ET l'authentification là où la
     * vérification par nom d'hôte échouerait.
     *
     * @var list<string>
     */
    public const MODES_TLS = ['epingle', 'exige', 'opportuniste', 'aucun'];

    private string $partage;

    public function __construct(?string $partage = null)
    {
        $this->partage = rtrim(
            $partage ?? (string) (getenv('GAMAD_CONTINUITE_DIR')
                ?: '/var/lib/gamad-core/continuite'),
            '/',
        );
    }

    /**
     * État lisible de la continuité. Ne lève jamais : un diagnostic doit rendre
     * compte d'une panne, pas s'y ajouter.
     *
     * @return array<string,mixed>
     */
    public function etat(): array
    {
        $installe = is_dir($this->partage) && is_writable($this->partage);
        $etat = [];
        $fichier = $this->partage.'/etat.json';
        if (is_readable($fichier)) {
            $brut = file_get_contents($fichier);
            if (is_string($brut)) {
                $decode = json_decode($brut, true);
                $etat = is_array($decode) ? $decode : [];
            }
        }

        $reglages = $this->reglages();
        $demandes = [];
        foreach (self::OPERATIONS as $operation) {
            if (is_file($this->cheminDemande($operation))) {
                $demandes[] = $operation;
            }
        }

        return [
            'installe' => $installe,
            'partage' => $this->partage,
            'configuree' => ($reglages['GAMAD_OFFSITE_DEST'] ?? '') !== '',
            'destination' => $reglages['GAMAD_OFFSITE_DEST'] ?? null,
            'utilisateur' => $reglages['GAMAD_OFFSITE_FTP_USER'] ?? null,
            'tls' => $reglages['GAMAD_OFFSITE_FTP_TLS'] ?? 'epingle',
            'empreinte_tls' => is_file($this->partage.'/ftp.pin')
                ? trim((string) @file_get_contents($this->partage.'/ftp.pin'))
                : null,
            'retention' => (int) ($reglages['GAMAD_OFFSITE_RETENTION'] ?? 14),
            'secret_present' => is_file($this->partage.'/ftp.secret'),
            'chiffrement_present' => is_file($this->partage.'/chiffrement.secret'),
            'demandes_en_attente' => $demandes,
            'rapport' => $etat,
        ];
    }

    /**
     * Écrit les réglages de destination. La phrase de chiffrement est engendrée
     * par le Core et retournée une seule fois : sans elle, les copies sont
     * illisibles le jour où le serveur est perdu.
     *
     * @param  array<string,mixed>  $reglages
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function configurer(array $reglages, string $acteur, ?string $correlation = null): array
    {
        $decision = $this->decider(self::ACTION_CONFIGURER, $acteur, $reglages['hote'] ?? null, $correlation);
        if ($decision['statut'] !== 200) {
            return $decision;
        }

        if (! is_dir($this->partage) || ! is_writable($this->partage)) {
            return $this->erreur(503, 'CONTINUITE_NON_INSTALLEE', [
                'message' => 'Le répertoire partagé est absent ou non inscriptible. '
                    .'Exécuter ops/core-foundation/installer-continuite.sh en root, une fois.',
                'partage' => $this->partage,
            ], $decision['preuve']);
        }

        $hote = trim((string) ($reglages['hote'] ?? ''));
        $chemin = trim((string) ($reglages['chemin'] ?? ''), '/');
        $utilisateur = trim((string) ($reglages['utilisateur'] ?? ''));
        $secret = (string) ($reglages['secret'] ?? '');
        $tls = (string) ($reglages['tls'] ?? 'opportuniste');
        $retention = (int) ($reglages['retention'] ?? 14);

        if ($hote === '' || ! preg_match('/^[A-Za-z0-9._-]+(:[0-9]{1,5})?$/', $hote)) {
            return $this->erreur(422, 'HOTE_INVALIDE', [
                'message' => 'L’adresse du serveur ne ressemble pas à un nom d’hôte.',
            ], $decision['preuve']);
        }
        if ($utilisateur === '') {
            return $this->erreur(422, 'UTILISATEUR_ABSENT', [
                'message' => 'L’identifiant de connexion est obligatoire.',
            ], $decision['preuve']);
        }
        if (! in_array($tls, self::MODES_TLS, true)) {
            return $this->erreur(422, 'TLS_INVALIDE', [
                'message' => 'Mode TLS inconnu.',
            ], $decision['preuve']);
        }
        if ($retention < 1 || $retention > 365) {
            return $this->erreur(422, 'RETENTION_INVALIDE', [
                'message' => 'La rétention doit tenir entre 1 et 365 lots.',
            ], $decision['preuve']);
        }
        // Un secret déjà en place n'est pas exigé de nouveau : on ne demande pas
        // à quelqu'un de retaper un mot de passe qu'il n'a plus sous les yeux.
        if ($secret === '' && ! is_file($this->partage.'/ftp.secret')) {
            return $this->erreur(422, 'SECRET_ABSENT', [
                'message' => 'Le mot de passe de connexion est obligatoire la première fois.',
            ], $decision['preuve']);
        }

        try {
            if ($secret !== '') {
                $this->ecrireSecret('ftp.secret', $secret);
            }
            $phrase = null;
            if (! is_file($this->partage.'/chiffrement.secret')) {
                $phrase = 'GAMAD-'.strtoupper(bin2hex(random_bytes(24)));
                $this->ecrireSecret('chiffrement.secret', $phrase);
            }

            // Changer d'hôte invalide l'empreinte retenue : la conserver
            // ferait échouer le transport sans dire pourquoi.
            $ancien = $this->reglages()['GAMAD_OFFSITE_DEST'] ?? '';
            $destination = sprintf('ftp://%s/%s', $hote, $chemin);
            if ($ancien !== '' && $ancien !== $destination) {
                @unlink($this->partage.'/ftp.pin');
            }
            $this->ecrireFichier('offsite.env', implode("\n", [
                '# Écrit par la console GAMAD Core. Ne pas modifier à la main.',
                'GAMAD_OFFSITE_DEST='.$destination,
                'GAMAD_OFFSITE_FTP_USER='.$utilisateur,
                'GAMAD_OFFSITE_FTP_SECRET_FILE='.$this->partage.'/ftp.secret',
                'GAMAD_OFFSITE_FTP_TLS='.$tls,
                'GAMAD_OFFSITE_PIN_FILE='.$this->partage.'/ftp.pin',
                'GAMAD_OFFSITE_PASSPHRASE_FILE='.$this->partage.'/chiffrement.secret',
                'GAMAD_OFFSITE_RETENTION='.$retention,
                '',
            ]), 0o660);
        } catch (\Throwable) {
            return $this->erreur(503, 'ECRITURE_IMPOSSIBLE', [
                'message' => 'Les réglages n’ont pas pu être écrits dans le répertoire partagé.',
            ], $decision['preuve']);
        }

        // Ni le mot de passe ni la phrase n'entrent au journal : seule la
        // destination, qui n'est pas un secret, y figure.
        $this->tracer([
            'categorie' => 'CONTINUITE',
            'type' => 'DESTINATION_CONFIGUREE',
            'acteur' => $acteur,
            'action' => self::ACTION_CONFIGURER,
            'ressource' => $destination,
            'decision' => 'EXECUTEE',
            'correlation_id' => $decision['preuve']['correlation_id'] ?? null,
            'donnees' => [
                'transport' => 'FTP',
                'tls' => $tls,
                'retention' => $retention,
                'chiffrement_engendre' => $phrase !== null,
            ],
        ]);

        return [
            'statut' => 200,
            'corps' => [
                'destination' => $destination,
                'phrase_chiffrement' => $phrase,
                'preuve' => $decision['preuve'],
            ],
        ];
    }

    /**
     * Dépose une demande. La console ne lance rien : elle demande, et l'unité
     * systemd exécute avec ses propres droits.
     *
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function demander(string $operation, string $acteur, ?string $correlation = null): array
    {
        if (! in_array($operation, self::OPERATIONS, true)) {
            return $this->erreur(422, 'OPERATION_INCONNUE', [
                'message' => 'Opération de continuité inconnue.',
            ], null);
        }

        $decision = $this->decider(self::ACTION_DECLENCHER, $acteur, $operation, $correlation);
        if ($decision['statut'] !== 200) {
            return $decision;
        }

        $demandes = $this->partage.'/demandes';
        if (! is_dir($demandes) || ! is_writable($demandes)) {
            return $this->erreur(503, 'CONTINUITE_NON_INSTALLEE', [
                'message' => 'Le répertoire des demandes est absent ou non inscriptible. '
                    .'Exécuter ops/core-foundation/installer-continuite.sh en root, une fois.',
                'partage' => $this->partage,
            ], $decision['preuve']);
        }

        $fichier = $this->cheminDemande($operation);
        if (@file_put_contents($fichier, gmdate('c')."\n") === false) {
            return $this->erreur(503, 'DEMANDE_IMPOSSIBLE', [
                'message' => 'La demande n’a pas pu être déposée.',
            ], $decision['preuve']);
        }
        @chmod($fichier, 0o660);

        $this->tracer([
            'categorie' => 'CONTINUITE',
            'type' => 'OPERATION_DEMANDEE',
            'acteur' => $acteur,
            'action' => self::ACTION_DECLENCHER,
            'ressource' => $operation,
            'decision' => 'DEMANDEE',
            'correlation_id' => $decision['preuve']['correlation_id'] ?? null,
            'donnees' => ['operation' => $operation],
        ]);

        return [
            'statut' => 202,
            'corps' => ['operation' => $operation, 'preuve' => $decision['preuve']],
        ];
    }

    // ------------------------------------------------------------------

    /**
     * @return array{statut:int,corps:array<string,mixed>,preuve?:array<string,mixed>}
     */
    private function decider(
        string $action,
        string $acteur,
        ?string $ressource,
        ?string $correlation,
    ): array {
        try {
            $decision = (new Ctr03(Db::connect()))->autoriser($acteur, $action, $ressource);
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'CONTINUITE',
                'type' => 'DECISION_CONTINUITE',
                'acteur' => $acteur,
                'action' => $action,
                'ressource' => $ressource,
                'decision' => $decision['decision'] === 'PERMIS' ? 'PERMIS' : 'REFUSE',
                'motif' => $decision['motif'],
                'correlation_id' => $correlation,
                'donnees' => ['politique' => $decision['politique']],
            ]);
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'SOCLE_INDISPONIBLE',
                    'message' => 'La continuité est fermée car sa décision et sa preuve '
                        .'ne peuvent pas être établies.',
                ],
            ];
        }

        if ($decision['decision'] !== 'PERMIS') {
            return [
                'statut' => 403,
                'corps' => [
                    'erreur' => 'AUTORISATION_REFUSEE',
                    'decision' => $decision,
                    'preuve' => $preuve,
                ],
            ];
        }

        return ['statut' => 200, 'corps' => [], 'preuve' => $preuve];
    }

    /**
     * @param  array<string,mixed>  $corps
     * @param  array<string,mixed>|null  $preuve
     * @return array{statut:int,corps:array<string,mixed>}
     */
    private function erreur(int $statut, string $code, array $corps, ?array $preuve): array
    {
        return [
            'statut' => $statut,
            'corps' => ['erreur' => $code] + $corps + ($preuve === null ? [] : ['preuve' => $preuve]),
        ];
    }

    /** @param array<string,mixed> $evenement */
    private function tracer(array $evenement): void
    {
        try {
            (new Journal(JournalMagasin::connecter()))->enregistrer($evenement);
        } catch (\Throwable) {
            // Le fait est accompli ; l'absence de seconde trace est signalée
            // par la preuve amont, déjà chaînée.
        }
    }

    /** @return array<string,string> */
    private function reglages(): array
    {
        $fichier = $this->partage.'/offsite.env';
        if (! is_readable($fichier)) {
            return [];
        }
        $lignes = file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $reglages = [];
        foreach ($lignes === false ? [] : $lignes as $ligne) {
            if (str_starts_with(trim($ligne), '#') || ! str_contains($ligne, '=')) {
                continue;
            }
            [$cle, $valeur] = explode('=', $ligne, 2);
            $reglages[trim($cle)] = trim($valeur);
        }

        return $reglages;
    }

    private function cheminDemande(string $operation): string
    {
        return $this->partage.'/demandes/'.$operation.'.demande';
    }

    private function ecrireSecret(string $nom, #[\SensitiveParameter] string $valeur): void
    {
        $this->ecrireFichier($nom, $valeur."\n", 0o660);
    }

    private function ecrireFichier(string $nom, string $contenu, int $mode): void
    {
        $chemin = $this->partage.'/'.$nom;
        if (file_put_contents($chemin, $contenu, LOCK_EX) === false) {
            throw new \RuntimeException("Écriture impossible : {$chemin}");
        }
        @chmod($chemin, $mode);
    }
}
