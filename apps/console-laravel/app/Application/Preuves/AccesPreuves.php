<?php

declare(strict_types=1);

namespace App\Application\Preuves;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePreuves\Magasin as PreuvesMagasin;
use Gamad\RegistrePreuves\PolitiquePreuves;
use Gamad\RegistrePreuves\RegistrePreuves;

/**
 * Cas d'usage HTTP de CAP-CORE-015 (partie 4).
 *
 * Même chemin gouverné que `AccesSecrets` (CAP-CORE-016) : `CAP-CORE-004`
 * (`Ctr03`) décide, `CAP-CORE-013` conserve la preuve d'exploitation, seule
 * une décision permise et prouvée atteint l'écriture gouvernée.
 * `POL-PREUVES-V1` ne permet ces actions qu'à `AUT-GAMAD-001` — même
 * autorité unique que les autres registres persistants du Core.
 *
 * Ce que cette façade n'expose délibérément jamais par HTTP (fiche partie 4
 * §1) : signature de contenu libre, choix de clé par l'appelant, lecture de
 * chemin arbitraire, checkpoint/manifeste/attestation construits depuis une
 * source externe non gouvernée. Ces émissions bornées restent réservées à la
 * CLI d'exploitation (`core:preuves:*`), qui seule connaît les chemins et
 * magasins réels autorisés. Seule l'empreinte d'un contenu JSON borné fourni
 * inline est émettable par API — jamais signée automatiquement.
 */
final class AccesPreuves
{
    // ------------------------------------------------------------------
    // Lectures

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function lister(array $filtres, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_LIRE, null, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['preuves' => $this->registre()->listerPreuves($filtres)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudre(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $preuve = $this->registre()->resoudrePreuve($reference);
        if ($preuve === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'PREUVE_INCONNUE']];
        }

        return ['statut' => 200, 'corps' => ['preuve' => $preuve]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function etat(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $etat = $this->registre()->resoudreEtat($reference);
        if ($etat === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'PREUVE_INCONNUE']];
        }

        return ['statut' => 200, 'corps' => ['reference' => $reference, 'etat' => $etat]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function empreintes(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['empreintes' => $this->registre()->resoudreEmpreintes($reference)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function signatures(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['signatures' => $this->masquerSignatures($this->registre()->resoudreSignatures($reference))]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function manifeste(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $manifeste = $this->registre()->resoudreManifeste($reference);
        if ($manifeste === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'MANIFESTE_INCONNU']];
        }

        return ['statut' => 200, 'corps' => ['manifeste' => $manifeste]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function attestation(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $attestation = $this->registre()->resoudreAttestation($reference);
        if ($attestation === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'ATTESTATION_INCONNUE']];
        }

        return ['statut' => 200, 'corps' => ['attestation' => $attestation]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function checkpoint(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $checkpoint = $this->registre()->resoudreCheckpoint($reference);
        if ($checkpoint === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'CHECKPOINT_INCONNU']];
        }

        return ['statut' => 200, 'corps' => ['checkpoint' => $checkpoint]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function verifications(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['verifications' => $this->registre()->resoudreVerifications($reference)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function liens(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['liens' => $this->registre()->resoudreLiens($reference)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function diagnostiquer(string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiquePreuves::ACTION_DIAGNOSTIC_LIRE, null, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['registre' => $this->registre()->diagnostiquerRegistre()]];
    }

    // ------------------------------------------------------------------
    // Émission bornée — empreinte de contenu JSON inline uniquement

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function emettreEmpreinte(array $dossier, string $acteur, ?string $correlation): array
    {
        $contenuJson = $dossier['contenu_json'] ?? null;
        $ressource = (string) ($dossier['sujet_reference'] ?? '');

        return $this->gouverner(
            PolitiquePreuves::ACTION_EMPREINTE_EMETTRE, $ressource, $acteur, $correlation, 'EMPREINTE_PREUVE_EMISE',
            function (array $g) use ($dossier, $contenuJson): array {
                $preparation = $this->registre()->preparerPreuve(array_merge($dossier, $g, [
                    'type_preuve' => 'EMPREINTE_ARTEFACT',
                    'representation' => ['format_representation' => 'JSON_CANONIQUE', 'media_type' => 'application/json'],
                ]));
                if (isset($preparation['refus'])) {
                    return $preparation;
                }
                $contenu = is_string($contenuJson) ? $contenuJson : json_encode($contenuJson, JSON_UNESCAPED_UNICODE);

                return $this->registre()->emettreEmpreinte(
                    (string) $preparation['reference'],
                    (string) ($dossier['algorithme'] ?? 'SHA-256'),
                    (string) $contenu,
                    $g,
                );
            },
            201,
            [
                'PRODUCTEUR_OBLIGATOIRE' => 422, 'REALM_INACTIF' => 422, 'SOURCE_INCONNUE' => 422,
                'FINALITE_INACTIVE' => 422, 'TYPE_PREUVE_INCONNU' => 422, 'IDEMPOTENCY_KEY_CONTRADICTOIRE' => 409,
                'CONTENU_TROP_VOLUMINEUX' => 413, 'ALGORITHME_REFUSE' => 422, 'CHAMP_INTERDIT' => 422,
            ],
        );
    }

    // ------------------------------------------------------------------
    // Vérification

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function verifier(string $reference, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiquePreuves::ACTION_VERIFIER, $reference, $acteur, $correlation, 'PREUVE_VERIFIEE',
            fn (array $g): array => $this->registre()->resoudrePreuve($reference) === null
                ? $this->refus('PREUVE_INCONNUE', "preuve `{$reference}` inconnue")
                : $this->registre()->verifierPreuve($reference, $dossier),
            200,
            ['PREUVE_INCONNUE' => 404],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function verifierPaquet(array $paquet, string $acteur, ?string $correlation): array
    {
        $reference = (string) ($paquet['preuve_reference'] ?? '');

        return $this->gouverner(
            PolitiquePreuves::ACTION_LIRE, $reference, $acteur, $correlation, 'PAQUET_PREUVE_VERIFIE',
            fn (array $g): array => $this->registre()->verifierPaquetPreuve($paquet),
            200,
            ['FORMAT_PAQUET_INCONNU' => 415, 'PREUVE_INCONNUE' => 404, 'PROFIL_INCONNU' => 422],
        );
    }

    // ------------------------------------------------------------------
    // Cycle

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function suspendre(string $reference, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiquePreuves::ACTION_SUSPENDRE, $reference, $acteur, $correlation, 'PREUVE_SUSPENDUE',
            fn (array $g): array => $this->registre()->suspendrePreuve($reference, array_merge($dossier, $g)),
            200,
            ['PREUVE_INCONNUE' => 404, 'TRANSITION_INVALIDE' => 409, 'MOTIF_OBLIGATOIRE' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function revoquer(string $reference, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiquePreuves::ACTION_REVOQUER, $reference, $acteur, $correlation, 'PREUVE_REVOQUEE',
            fn (array $g): array => $this->registre()->revoquerPreuve($reference, array_merge($dossier, $g)),
            200,
            ['PREUVE_INCONNUE' => 404, 'TRANSITION_INVALIDE' => 409, 'MOTIF_OBLIGATOIRE' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerCompromission(string $reference, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiquePreuves::ACTION_COMPROMISSION_DECLARER, $reference, $acteur, $correlation, 'COMPROMISSION_PREUVE_DECLAREE',
            fn (array $g): array => $this->registre()->declarerCompromission($reference, array_merge($dossier, $g)),
            201,
            ['PREUVE_INCONNUE' => 404, 'MOTIF_OBLIGATOIRE' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function exporter(string $reference, array $dossier, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiquePreuves::ACTION_PAQUET_EXPORTER, $reference, $acteur, $correlation, 'PAQUET_PREUVE_EXPORTE',
            fn (array $g): array => $this->registre()->exporterPaquetPreuve(
                $reference,
                (string) ($dossier['profil'] ?? 'VERIFICATION_INTERNE'),
                array_merge($dossier, $g),
            ),
            201,
            ['PREUVE_INCONNUE' => 404, 'PROFIL_INCONNU' => 422, 'PAQUET_TROP_VOLUMINEUX' => 413],
        );
    }

    // ------------------------------------------------------------------
    // Internes

    private function registre(): RegistrePreuves
    {
        return new RegistrePreuves(PreuvesMagasin::connecter());
    }

    private function journal(): Journal
    {
        return new Journal(JournalMagasin::connecter());
    }

    /** @param list<array<string,mixed>> $signatures @return list<array<string,mixed>> */
    private function masquerSignatures(array $signatures): array
    {
        // La signature elle-même est un artefact public par construction
        // (elle ne prouve rien sans le contexte signé) ; seule une future
        // extension à métadonnées de résolution de clé y ajouterait un champ
        // à filtrer. Rien à masquer aujourd'hui au-delà des champs interdits
        // déjà refusés à l'écriture par le registre.
        return $signatures;
    }

    /** @return array<string,mixed> */
    private function refus(string $motif, string $detail): array
    {
        return ['refus' => $motif, 'detail' => $detail];
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
                'categorie' => 'PREUVES_INTEGRITE', 'type' => 'DECISION_' . $typeReussite,
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
                'categorie' => 'PREUVES_INTEGRITE', 'type' => 'OPERATION_PREUVE_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => 'autorisation refusée', 'correlation_id' => $preuve['correlation_id'],
            ]);

            return ['statut' => 403, 'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision, 'preuve' => $preuve]];
        }

        $dossier = [
            'politique' => $decision['politique'] ?? PolitiquePreuves::POLITIQUE,
            'source' => PolitiquePreuves::SOURCE,
            'producteur' => $acteur, 'acteur' => $acteur,
            'preuve' => $preuve['reference'], 'correlation_id' => $preuve['correlation_id'],
        ];

        try {
            $resultat = $operation($dossier);
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'REGISTRE_PREUVES_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'PREUVES_INTEGRITE', 'type' => 'OPERATION_PREUVE_REFUSEE',
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
            'categorie' => 'PREUVES_INTEGRITE', 'type' => $typeReussite,
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
                'message' => 'Le registre des preuves d’intégrité est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
