<?php

declare(strict_types=1);

namespace App\Application\Secrets;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSecretsCles\PolitiqueSecretsCles;
use Gamad\RegistreSecretsCles\RegistreSecretsCles;

/**
 * Cas d'usage HTTP de CAP-CORE-016 (partie 4).
 *
 * Même chemin gouverné que les autres registres persistants du Core :
 * `CAP-CORE-004` (`Ctr03`) décide, `CAP-CORE-013` conserve la preuve
 * d'exploitation — sans valeur secrète, comme l'exige la fiche partie 4
 * §10-11 — et seule une décision permise et prouvée atteint l'écriture
 * gouvernée. `POL-SECRETS-CLES-V1`, comme `POL-EVENEMENTS-V1`, ne permet ces
 * actions qu'à `AUT-GAMAD-001` : autorité unique confirmée par le dirigeant,
 * pas une lacune à combler.
 *
 * Cette couche n'expose et ne manipule jamais le matériel secret lui-même :
 * elle gouverne uniquement les métadonnées via `RegistreSecretsCles`. La
 * résolution bornée (`ResolveurSecret::avecSecret()`) reste une API PHP
 * interne, jamais appelée depuis un contrôleur HTTP.
 */
final class AccesSecrets
{
    /** @return array{statut:int,corps:array<string,mixed>} */
    public function inscrireSecret(array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_INSCRIRE, (string) ($dossier['reference'] ?? ''), $acteur, $correlation,
            'SECRET_REFERENCE_INSCRITE',
            fn (array $g): array => $this->registre()->inscrireSecret(array_merge($dossier, $g)),
            201,
            ['REFERENCE_DEJA_UTILISEE' => 409, 'CHAMP_INTERDIT' => 422, 'TYPE_SECRET_INCONNU' => 422, 'ENVIRONNEMENT_REFUSE' => 422, 'CLASSIFICATION_INCONNUE' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function inscrireFournisseur(array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_FOURNISSEUR_INSCRIRE, (string) ($dossier['reference'] ?? ''), $acteur, $correlation,
            'FOURNISSEUR_SECRET_INSCRIT',
            fn (array $g): array => $this->registre()->inscrireFournisseur(array_merge($dossier, $g)),
            201,
            ['TYPE_FOURNISSEUR_INCONNU' => 422, 'CHAMP_INTERDIT' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerVersion(string $reference, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_VERSION_DECLARER, $reference, $acteur, $correlation, 'VERSION_SECRET_DECLAREE',
            fn (array $g): array => $this->registre()->declarerVersion(array_merge($dossier, ['secret_reference' => $reference], $g)),
            201,
            ['SECRET_INCONNU' => 404, 'FOURNISSEUR_INDISPONIBLE' => 422, 'VERSION_DEJA_DECLAREE' => 409, 'CLE_PRIVEE_REFUSEE' => 422, 'CHAMP_INTERDIT' => 422],
        );
    }

    /**
     * Active une version — revérifie toujours le fournisseur en direct côté
     * serveur avant d'activer, plutôt que de faire confiance à un booléen
     * `verifiee` fourni par l'appelant : un client ne peut jamais se
     * déclarer lui-même vérifié (fiche partie 3 §6.6, aucun fallback
     * silencieux).
     *
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function activerVersion(string $reference, int $id, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_VERSION_ACTIVER, $reference, $acteur, $correlation, 'VERSION_SECRET_ACTIVEE',
            function (array $g) use ($id, $dossier): array {
                $verification = $this->reverifierVersion($id);
                if (isset($verification['refus'])) {
                    return $verification;
                }

                return $this->registre()->activerVersion($id, array_merge($dossier, ['verifiee' => true], $g));
            },
            200,
            ['VERSION_INCONNUE' => 404, 'VERSION_NON_VERIFIEE' => 422, 'VERSION_INACTIVE' => 409, 'FOURNISSEUR_NON_CONFORME' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function verifierVersion(string $reference, string $version, string $acteur, ?string $correlation): array
    {
        $ligne = $this->registre()->resoudreVersion($reference, $version);
        $id = $ligne !== null ? (int) $ligne['id'] : 0;

        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_VERSION_VERIFIER, $reference, $acteur, $correlation, 'VERSION_SECRET_VERIFIEE',
            fn (array $g): array => $ligne === null ? $this->refus('VERSION_INCONNUE', 'version inconnue') : $this->reverifierVersion($id),
            200,
            ['VERSION_INCONNUE' => 404, 'FOURNISSEUR_NON_CONFORME' => 422],
        );
    }

    /** @return array<string,mixed> */
    private function reverifierVersion(int $id): array
    {
        $version = null;
        foreach ($this->registre()->listerSecrets() as $secret) {
            foreach ($this->registre()->listerVersions((string) $secret['reference']) as $v) {
                if ((int) $v['id'] === $id) {
                    $version = $v;
                }
            }
        }
        if ($version === null) {
            return $this->refus('VERSION_INCONNUE', "version #{$id} inconnue");
        }
        $fournisseur = $this->registre()->resoudreFournisseur((string) $version['fournisseur_reference']);
        $adaptateur = $fournisseur !== null
            ? \Gamad\RegistreSecretsCles\AdaptateurParType::resoudre((string) $fournisseur['type_fournisseur'])
            : null;
        if ($adaptateur === null) {
            return $this->refus('FOURNISSEUR_NON_CONFORME', 'aucun adaptateur borné disponible pour ce type de fournisseur');
        }

        return $this->registre()->verifierVersion($id, $adaptateur, []);
    }

    /** @return array<string,mixed> */
    private function refus(string $motif, string $detail): array
    {
        return ['refus' => $motif, 'detail' => $detail];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function suspendreVersion(string $reference, int $id, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_VERSION_SUSPENDRE, $reference, $acteur, $correlation, 'VERSION_SECRET_SUSPENDUE',
            fn (array $g): array => $this->registre()->suspendreVersion($id, array_merge($dossier, $g)),
            200,
            ['VERSION_INCONNUE' => 404, 'VERSION_INACTIVE' => 409, 'VERSION_COMPROMISE' => 423, 'DOSSIER_INCOMPLET' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function revoquerVersion(string $reference, int $id, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_VERSION_REVOQUER, $reference, $acteur, $correlation, 'VERSION_SECRET_REVOQUEE',
            fn (array $g): array => $this->registre()->revoquerVersion($id, array_merge($dossier, $g)),
            200,
            ['VERSION_INCONNUE' => 404, 'VERSION_INACTIVE' => 409, 'VERSION_COMPROMISE' => 423, 'DOSSIER_INCOMPLET' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerCompromission(array $dossier, string $acteur, ?string $correlation): array
    {
        $reference = 'version-' . (string) ($dossier['secret_version_id'] ?? '');

        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_VERSION_COMPROMETTRE, $reference, $acteur, $correlation, 'VERSION_SECRET_COMPROMISE',
            fn (array $g): array => $this->registre()->declarerCompromission(array_merge($dossier, $g)),
            201,
            ['VERSION_INCONNUE' => 404, 'NIVEAU_INCONNU' => 422, 'DOSSIER_INCOMPLET' => 422, 'CHAMP_INTERDIT' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function detruireVersion(string $reference, int $id, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_VERSION_DETRUIRE, $reference, $acteur, $correlation, 'VERSION_SECRET_DETRUITE',
            fn (array $g): array => $this->registre()->detruireVersion($id, $this->fournisseurAdaptateurGenerique(), array_merge($dossier, $g)),
            200,
            ['VERSION_INCONNUE' => 404, 'DESTRUCTION_REFUSEE' => 423, 'DEPENDANCE_BLOQUANTE' => 423],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerUsage(array $dossier, string $acteur, ?string $correlation): array
    {
        $reference = (string) ($dossier['secret_reference'] ?? '');

        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_USAGE_DECLARER, $reference, $acteur, $correlation, 'USAGE_SECRET_DECLARE',
            fn (array $g): array => $this->registre()->declarerUsage(array_merge($dossier, $g)),
            201,
            ['SECRET_INCONNU' => 404, 'USAGE_REFUSE' => 422, 'ENVIRONNEMENT_REFUSE' => 422, 'CHAMP_INTERDIT' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function planifierRotation(array $dossier, string $acteur, ?string $correlation): array
    {
        $reference = (string) ($dossier['secret_reference'] ?? '');

        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_ROTATION_PLANIFIER, $reference, $acteur, $correlation, 'ROTATION_SECRET_PLANIFIEE',
            fn (array $g): array => $this->registre()->planifierRotation(array_merge($dossier, $g)),
            201,
            ['SECRET_INCONNU' => 404, 'STRATEGIE_INCONNUE' => 422, 'PLAN_SANS_CONSOMMATEURS' => 422, 'PLAN_SANS_RETOUR_ARRIERE' => 422, 'CHAMP_INTERDIT' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function validerRotation(string $reference, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_ROTATION_VALIDER, $reference, $acteur, $correlation, 'ROTATION_SECRET_VALIDEE',
            fn (array $g): array => $this->registre()->validerRotation($reference, array_merge($dossier, $g)),
            200,
            ['ROTATION_INCONNUE' => 404, 'ROTATION_ETAT_INVALIDE' => 409],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function executerEtapeRotation(string $reference, string $etape, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueSecretsCles::ACTION_ROTATION_EXECUTER, $reference, $acteur, $correlation, 'ETAPE_ROTATION_SECRET_TRAITEE',
            fn (array $g): array => $this->registre()->executerEtapeRotation($reference, $etape, array_merge($dossier, $g)),
            200,
            ['ROTATION_INCONNUE' => 404, 'ROTATION_ETAT_INVALIDE' => 409, 'CHAMP_INTERDIT' => 422],
        );
    }

    // ------------------------------------------------------------------
    // Lectures — métadonnées seules

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function lister(array $filtres, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueSecretsCles::ACTION_LIRE_METADONNEES, null, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['secrets' => $this->registre()->listerSecrets($filtres)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudre(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueSecretsCles::ACTION_LIRE_METADONNEES, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $secret = $this->registre()->resoudreSecret($reference);
        if ($secret === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'SECRET_INCONNU']];
        }

        return ['statut' => 200, 'corps' => ['secret' => $secret]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerVersions(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueSecretsCles::ACTION_LIRE_METADONNEES, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['versions' => $this->masquerHandles($this->registre()->listerVersions($reference))]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreVersion(string $reference, string $version, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueSecretsCles::ACTION_LIRE_METADONNEES, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $ligne = $this->registre()->resoudreVersion($reference, $version);
        if ($ligne === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'VERSION_INCONNUE']];
        }

        return ['statut' => 200, 'corps' => ['version' => $this->masquerHandle($ligne)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerUsages(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueSecretsCles::ACTION_LIRE_METADONNEES, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['usages' => $this->registre()->listerUsages($reference)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerDependances(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueSecretsCles::ACTION_LIRE_METADONNEES, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['dependances' => $this->registre()->listerDependances($reference)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerRotations(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueSecretsCles::ACTION_LIRE_METADONNEES, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['rotations' => $this->registre()->listerRotations($reference)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerCompromissions(array $filtres, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueSecretsCles::ACTION_DIAGNOSTIC_LIRE, null, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['compromissions' => $this->registre()->listerCompromissions($filtres)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerFournisseurs(string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueSecretsCles::ACTION_LIRE_METADONNEES, null, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['fournisseurs' => $this->registre()->listerFournisseurs()]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function diagnostiquer(string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueSecretsCles::ACTION_DIAGNOSTIC_LIRE, null, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return [
            'statut' => 200,
            'corps' => [
                'registre' => $this->registre()->diagnostiquerRegistre(),
                'fournisseurs' => $this->registre()->diagnostiquerFournisseurs(),
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Internes

    private function registre(): RegistreSecretsCles
    {
        return new RegistreSecretsCles(SecretsMagasin::connecter());
    }

    private function journal(): Journal
    {
        return new Journal(JournalMagasin::connecter());
    }

    /**
     * Destruction gouvernée par HTTP : un fournisseur d'environnement de
     * transition ne détruit jamais réellement de matériel (c'est le
     * déploiement qui retire la variable) — cette façade générique renvoie
     * donc toujours un refus explicite pour ce chemin, jamais une fausse
     * confirmation. Les destructions réelles (fichier, credential) passent
     * par la CLI d'exploitation, avec le fournisseur concret injecté.
     */
    private function fournisseurAdaptateurGenerique(): \Gamad\RegistreSecretsCles\FournisseurSecret
    {
        return new class implements \Gamad\RegistreSecretsCles\FournisseurSecret {
            public function verifierDisponibilite(\Gamad\RegistreSecretsCles\DescripteurVersion $version): \Gamad\RegistreSecretsCles\DiagnosticFournisseur
            {
                return new \Gamad\RegistreSecretsCles\DiagnosticFournisseur(false, 'destruction non disponible par API — utiliser la CLI d’exploitation');
            }

            public function avecSecret(\Gamad\RegistreSecretsCles\DescripteurVersion $version, \Gamad\RegistreSecretsCles\UsageSecret $usage, callable $operation): mixed
            {
                throw new \Gamad\RegistreSecretsCles\ExceptionSecret('résolution non disponible par cette façade');
            }

            public function empreintePublique(\Gamad\RegistreSecretsCles\DescripteurVersion $version): ?string
            {
                return null;
            }

            public function detruire(\Gamad\RegistreSecretsCles\DescripteurVersion $version): \Gamad\RegistreSecretsCles\ResultatDestruction
            {
                return new \Gamad\RegistreSecretsCles\ResultatDestruction(false, 'destruction non disponible par API — utiliser la CLI d’exploitation');
            }
        };
    }

    /** @param list<array<string,mixed>> $versions @return list<array<string,mixed>> */
    private function masquerHandles(array $versions): array
    {
        return array_map(fn (array $v): array => $this->masquerHandle($v), $versions);
    }

    /** @param array<string,mixed> $version @return array<string,mixed> */
    private function masquerHandle(array $version): array
    {
        unset($version['handle_fournisseur']);

        return $version;
    }

    /** @return array{statut:int,corps:array<string,mixed>}|null */
    private function verifierLecture(string $action, ?string $ressource, string $acteur): ?array
    {
        try {
            $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser($acteur, $action, $ressource);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        if ($decision['decision'] !== 'PERMIS') {
            return ['statut' => 403, 'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision]];
        }

        return null;
    }

    /**
     * @param callable(array<string,mixed>):array<string,mixed> $operation
     * @return array{statut:int,corps:array<string,mixed>}
     */
    private function gouverner(
        string $action,
        ?string $ressource,
        string $acteur,
        ?string $correlation,
        string $typeReussite,
        callable $operation,
        int $statutReussite,
        array $codesRefus = [],
    ): array {
        try {
            $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser($acteur, $action, $ressource);
            $journal = $this->journal();
            $preuve = $journal->enregistrer([
                'categorie' => 'SECRETS_CLES', 'type' => 'DECISION_' . $typeReussite,
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => $decision['decision'] === 'PERMIS' ? 'PERMIS' : 'REFUSE',
                'motif' => $decision['motif'], 'correlation_id' => $correlation,
                'donnees' => ['politique' => $decision['politique']],
            ]);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        if ($decision['decision'] !== 'PERMIS') {
            $this->tracer($journal, [
                'categorie' => 'SECRETS_CLES', 'type' => 'OPERATION_SECRET_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => 'autorisation refusée', 'correlation_id' => $preuve['correlation_id'],
            ]);

            return ['statut' => 403, 'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision, 'preuve' => $preuve]];
        }

        $dossier = [
            'politique' => $decision['politique'] ?? PolitiqueSecretsCles::POLITIQUE,
            'source' => PolitiqueSecretsCles::SOURCE,
            'producteur' => $acteur, 'acteur' => $acteur,
            'preuve' => $preuve['reference'], 'correlation_id' => $preuve['correlation_id'],
        ];

        try {
            $resultat = $operation($dossier);
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'REGISTRE_SECRETS_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'SECRETS_CLES', 'type' => 'OPERATION_SECRET_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => $resultat['detail'] ?? $resultat['refus'],
                'correlation_id' => $preuve['correlation_id'], 'donnees' => ['refus' => $resultat['refus']],
            ]);

            return [
                'statut' => $codesRefus[$resultat['refus']] ?? 422,
                'corps' => ['erreur' => 'OPERATION_REFUSEE', 'resultat' => $resultat, 'preuve' => $preuve],
            ];
        }

        $this->tracer($journal, [
            'categorie' => 'SECRETS_CLES', 'type' => $typeReussite,
            'acteur' => $acteur, 'action' => $action,
            'ressource' => $ressource ?? (string) ($resultat['reference'] ?? ''),
            'decision' => 'EXECUTEE', 'correlation_id' => $preuve['correlation_id'],
        ]);

        return ['statut' => $statutReussite, 'corps' => ['resultat' => $resultat, 'decision' => $decision, 'preuve' => $preuve]];
    }

    /** @param array<string,mixed> $evenement */
    private function tracer(Journal $journal, array $evenement): ?array
    {
        try {
            return $journal->enregistrer($evenement);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    private function socleIndisponible(): array
    {
        return [
            'statut' => 503,
            'corps' => [
                'erreur' => 'SOCLE_INDISPONIBLE',
                'message' => 'Le registre des secrets et clés est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
