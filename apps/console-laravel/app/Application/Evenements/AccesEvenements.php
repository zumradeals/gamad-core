<?php

declare(strict_types=1);

namespace App\Application\Evenements;

use Gamad\JournalEvenements\LivreurEvenements;
use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalEvenements\PolitiqueEvenements;
use Gamad\JournalEvenements\RegistreAbonnements;
use Gamad\JournalEvenements\RegistreEvenements;
use Gamad\JournalEvenements\RejoueurEvenements;
use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\RegistreRealms;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\RegistreSources;

/**
 * Cas d'usage HTTP de CAP-CORE-014 (partie 4).
 *
 * Même chemin gouverné que les autres registres persistants du Core :
 * `CAP-CORE-004` (`Ctr03`) décide, `CAP-CORE-013` conserve la preuve
 * d'exploitation — sans charge utile, comme l'exige la fiche partie 4 §10 —
 * et seule une décision permise et prouvée atteint l'écriture centrale.
 *
 * `POL-EVENEMENTS-V1`, bootstrapée par `core:evenements:bootstrap`, ne permet
 * aujourd'hui ces actions qu'à `PolitiqueInscription::AUTORITE_INSCRIPTION` :
 * élargir à des producteurs et consommateurs réels reste une décision produit
 * ultérieure (§9 du rapport de PR), pas une extension silencieuse ici.
 */
final class AccesEvenements
{
    // ------------------------------------------------------------------
    // Publications

    /** @param array<string,mixed> $intention @return array{statut:int,corps:array<string,mixed>} */
    public function publier(array $intention, string $acteur, ?string $correlation): array
    {
        $ressource = (string) ($intention['contrat_reference'] ?? '');

        return $this->gouverner(
            PolitiqueEvenements::ACTION_PUBLIER, $ressource, $acteur, $correlation, 'EVENEMENT_COMMUN_ACCEPTE',
            fn (array $dossier): array => $this->registreEvenements()->accepterEvenement($intention, $dossier),
            201,
            [
                'ENVELOPPE_INVALIDE' => 422, 'CONTRAT_INCONNU' => 404, 'CONTRAT_TYPE_INVALIDE' => 422,
                'VERSION_INCOMPATIBLE' => 409, 'PRODUCTEUR_NON_DECLARE' => 403, 'SOURCE_INACTIVE' => 404,
                'FINALITE_ABSENTE' => 422, 'REALM_INACTIF' => 404, 'CHARGE_INVALIDE' => 422,
            ],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudrePublication(string $producteur, string $idempotence, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_LIRE, $producteur, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $recu = $this->registreEvenements()->resoudrePublication($producteur, $idempotence);
        if ($recu === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'PUBLICATION_INCONNUE']];
        }

        return ['statut' => 200, 'corps' => ['reçu' => $recu]];
    }

    // ------------------------------------------------------------------
    // Événements

    /** @param array<string,mixed> $filtres @return array{statut:int,corps:array<string,mixed>} */
    public function lister(array $filtres, int $limite, int $decalage, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_LIRE, null, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $evenements = $this->registreEvenements()->listerEvenements($filtres, $limite, $decalage);

        return ['statut' => 200, 'corps' => [
            'evenements' => $evenements, 'limite' => $limite, 'decalage' => $decalage,
        ]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudre(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $evenement = $this->registreEvenements()->resoudreEvenement($reference);
        if ($evenement === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'EVENEMENT_INCONNU']];
        }

        return ['statut' => 200, 'corps' => ['evenement' => $evenement]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreCharge(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $evenement = $this->registreEvenements()->resoudreEvenement($reference);
        if ($evenement === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'EVENEMENT_INCONNU']];
        }
        $autorise = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION
            || $evenement['producteur_capacite_reference'] === $acteur
            || $evenement['producteur_produit_reference'] === $acteur
            || $this->acteurEstDestinataire($reference, $acteur);
        if (!$autorise) {
            return ['statut' => 403, 'corps' => ['erreur' => 'CHARGE_NON_AUTORISEE']];
        }

        return ['statut' => 200, 'corps' => $this->registreEvenements()->resoudreCharge($reference)];
    }

    // ------------------------------------------------------------------
    // Abonnements

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerAbonnements(string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_LIRE, null, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $consommateur = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION ? null : $acteur;

        return ['statut' => 200, 'corps' => ['abonnements' => $this->registreAbonnements()->listerAbonnements($consommateur)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreAbonnement(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $abonnement = $this->registreAbonnements()->resoudreAbonnement($reference);
        if ($abonnement === null || !$this->acteurEstProprietaire($abonnement, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'ABONNEMENT_INCONNU']];
        }

        return ['statut' => 200, 'corps' => ['abonnement' => $abonnement]];
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function creerAbonnement(array $donnees, string $acteur, ?string $correlation): array
    {
        $consommateur = (string) ($donnees['consommateur_capacite_reference'] ?? $donnees['consommateur_produit_reference'] ?? '');

        return $this->gouverner(
            PolitiqueEvenements::ACTION_ABONNEMENT_CREER, $consommateur, $acteur, $correlation, 'ABONNEMENT_EVENEMENT_CREE',
            fn (array $dossier): array => $this->registreAbonnements()->creerAbonnement($donnees + $dossier),
            201,
            ['CHAMP_MANQUANT' => 422, 'CONSOMMATEUR_ABSENT' => 422, 'CONSOMMATEUR_AMBIGU' => 422, 'MODE_INCONNU' => 422, 'REALM_INACTIF' => 404, 'REFERENCE_DEJA_UTILISEE' => 409],
        );
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function modifierAbonnement(string $reference, array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_ABONNEMENT_MODIFIER, $correlation, 'ABONNEMENT_EVENEMENT_MODIFIE',
            fn (): array => $this->registreAbonnements()->modifierAbonnement($reference, $donnees),
            200,
            ['ETAT_INCOMPATIBLE' => 409, 'AUCUNE_MODIFICATION' => 422],
        );
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function ajouterType(string $reference, array $donnees, string $acteur, ?string $correlation): array
    {
        $contrat = (string) ($donnees['contrat_reference'] ?? '');
        $type = (string) ($donnees['type_evenement'] ?? '');

        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_ABONNEMENT_MODIFIER, $correlation, 'ABONNEMENT_EVENEMENT_MODIFIE',
            fn (): array => $this->registreAbonnements()->ajouterTypeAbonnement($reference, $contrat, $type, $donnees),
            201,
            ['ETAT_INCOMPATIBLE' => 409, 'CONTRAT_INACTIF' => 404, 'CONSOMMATEUR_NON_DECLARE' => 403, 'LIMITE_ATTEINTE' => 422],
        );
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function ajouterProducteur(string $reference, array $donnees, string $acteur, ?string $correlation): array
    {
        $producteur = (string) ($donnees['producteur_reference'] ?? '');

        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_ABONNEMENT_MODIFIER, $correlation, 'ABONNEMENT_EVENEMENT_MODIFIE',
            fn (): array => $this->registreAbonnements()->ajouterProducteurAbonnement($reference, $producteur),
            201,
            ['ETAT_INCOMPATIBLE' => 409, 'PRODUCTEUR_INVALIDE' => 422, 'LIMITE_ATTEINTE' => 422],
        );
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function ajouterRealm(string $reference, array $donnees, string $acteur, ?string $correlation): array
    {
        $realm = (string) ($donnees['realm_reference'] ?? '');
        $portee = (string) ($donnees['portee'] ?? 'EXACT');

        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_ABONNEMENT_MODIFIER, $correlation, 'ABONNEMENT_EVENEMENT_MODIFIE',
            fn (): array => $this->registreAbonnements()->ajouterRealmAbonnement($reference, $realm, $portee),
            201,
            ['ETAT_INCOMPATIBLE' => 409, 'PORTEE_INCONNUE' => 422, 'REALM_INACTIF' => 404, 'LIMITE_ATTEINTE' => 422],
        );
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function activerAbonnement(string $reference, array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_ABONNEMENT_ACTIVER, $correlation, 'ABONNEMENT_EVENEMENT_ACTIVE',
            fn (array $dossier): array => $this->registreAbonnements()->activerAbonnement($reference, $donnees + $dossier),
            200,
            ['ETAT_INCOMPATIBLE' => 409, 'AUCUN_TYPE' => 422, 'AUCUN_PRODUCTEUR' => 422, 'AUCUN_REALM' => 422],
        );
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function suspendreAbonnement(string $reference, array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_ABONNEMENT_SUSPENDRE, $correlation, 'ABONNEMENT_EVENEMENT_SUSPENDU',
            fn (array $dossier): array => $this->registreAbonnements()->suspendreAbonnement($reference, $donnees + $dossier),
            200,
            ['ETAT_INCOMPATIBLE' => 409],
        );
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function retirerAbonnement(string $reference, array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_ABONNEMENT_RETIRER, $correlation, 'ABONNEMENT_EVENEMENT_RETIRE',
            fn (array $dossier): array => $this->registreAbonnements()->retirerAbonnement($reference, $donnees + $dossier),
            200,
            [],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreRetard(string $reference, string $acteur): array
    {
        $verification = $this->verifierAppartenanceAbonnement($reference, $acteur);
        if ($verification !== null) {
            return $verification;
        }

        return ['statut' => 200, 'corps' => $this->livreur()->resoudreRetard($reference)];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreCurseur(string $reference, string $acteur): array
    {
        $verification = $this->verifierAppartenanceAbonnement($reference, $acteur);
        if ($verification !== null) {
            return $verification;
        }

        return ['statut' => 200, 'corps' => $this->livreur()->resoudreCurseur($reference)];
    }

    // ------------------------------------------------------------------
    // Livraisons PULL

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function obtenirLivraisons(string $reference, int $limite, ?int $bailSecondes, string $acteur, string $correlation): array
    {
        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_LIRE, $correlation, 'LOT_LIVRAISONS_ACCORDE',
            fn (): array => $this->livreur()->obtenirLivraisons($reference, $acteur, $limite, $bailSecondes, $correlation),
            200,
            ['ABONNEMENT_INACTIF' => 409, 'CONSOMMATEUR_NON_PROPRIETAIRE' => 403],
        );
    }

    /** @param list<string> $livraisons @return array{statut:int,corps:array<string,mixed>} */
    public function accuserLivraisons(string $reference, string $bail, array $livraisons, string $acteur, string $correlation): array
    {
        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_LIVRAISON_ACCUSER, $correlation, 'LIVRAISONS_ACCUSEES',
            fn (): array => $this->livreur()->accuserLivraisons($reference, $bail, $livraisons, $correlation),
            200,
        );
    }

    /** @param list<string> $livraisons @return array{statut:int,corps:array<string,mixed>} */
    public function refuserTemporairement(string $reference, string $bail, array $livraisons, string $codeErreur, ?int $delaiSecondes, string $acteur, string $correlation): array
    {
        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_LIVRAISON_REFUSER, $correlation, 'LIVRAISONS_REFUSEES_TEMPORAIREMENT',
            function () use ($reference, $bail, $livraisons, $codeErreur, $delaiSecondes, $correlation): array {
                $resultats = [];
                foreach ($livraisons as $livraison) {
                    $resultats[$livraison] = $this->livreur()->refuserTemporairement($reference, $bail, $livraison, $codeErreur, $delaiSecondes, $correlation);
                }

                return ['abonnement' => $reference, 'resultats' => $resultats];
            },
            200,
        );
    }

    /** @param list<string> $livraisons @return array{statut:int,corps:array<string,mixed>} */
    public function refuserDefinitivement(string $reference, string $bail, array $livraisons, string $codeErreur, string $justification, string $acteur, string $correlation): array
    {
        return $this->gouvernerAbonnement(
            $reference, $acteur, PolitiqueEvenements::ACTION_LIVRAISON_REFUSER, $correlation, 'LIVRAISONS_REFUSEES_DEFINITIVEMENT',
            function () use ($reference, $bail, $livraisons, $codeErreur, $justification, $correlation): array {
                $resultats = [];
                foreach ($livraisons as $livraison) {
                    $resultats[$livraison] = $this->livreur()->refuserDefinitivement($reference, $bail, $livraison, $codeErreur, $justification, $correlation);
                }

                return ['abonnement' => $reference, 'resultats' => $resultats];
            },
            200,
        );
    }

    // ------------------------------------------------------------------
    // Rejeux

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerRejeux(?string $abonnement, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_REJEU_DEMANDER, $abonnement, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['rejeux' => $this->rejoueur()->listerDemandes($abonnement)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreRejeu(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_REJEU_DEMANDER, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $demande = $this->rejoueur()->resoudreDemande($reference);
        if ($demande === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'DEMANDE_INCONNUE']];
        }

        return ['statut' => 200, 'corps' => ['rejeu' => $demande]];
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function demanderRejeu(string $abonnement, array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueEvenements::ACTION_REJEU_DEMANDER, $abonnement, $acteur, $correlation, 'REJEU_EVENEMENTS_DEMANDE',
            fn (array $dossier): array => $this->rejoueur()->demanderRejeu($abonnement, $donnees + ['demandeur' => $acteur] + $dossier),
            201,
            ['CHAMP_MANQUANT' => 422, 'ABONNEMENT_INCONNU' => 404, 'BORNES_ABSENTES' => 422, 'BORNES_INVALIDES' => 422, 'VOLUME_EXCESSIF' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function validerRejeu(string $reference, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueEvenements::ACTION_REJEU_DEMANDER, $reference, $acteur, $correlation, 'REJEU_EVENEMENTS_VALIDE',
            fn (array $dossier): array => $this->rejoueur()->validerRejeu($reference, $dossier),
            200,
            ['DEMANDE_INCONNUE' => 404, 'ETAT_INCOMPATIBLE' => 409, 'VOLUME_EXCESSIF' => 422],
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function annulerRejeu(string $reference, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueEvenements::ACTION_REJEU_DEMANDER, $reference, $acteur, $correlation, 'REJEU_EVENEMENTS_ANNULE',
            fn (): array => $this->rejoueur()->annulerRejeu($reference),
            200,
            ['DEMANDE_INCONNUE' => 404, 'ETAT_INCOMPATIBLE' => 409],
        );
    }

    // ------------------------------------------------------------------
    // Lettres mortes

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function listerLettresMortes(?string $abonnement, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_DIAGNOSTIC_LIRE, $abonnement, $acteur);
        if ($refus !== null) {
            return $refus;
        }

        return ['statut' => 200, 'corps' => ['lettres_mortes' => $this->livreur()->listerLettresMortes($abonnement)]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreLettreMorte(string $reference, string $acteur): array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_DIAGNOSTIC_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $lettre = $this->livreur()->resoudreLettreMorte($reference);
        if ($lettre === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'LETTRE_MORTE_INCONNUE']];
        }

        return ['statut' => 200, 'corps' => ['lettre_morte' => $lettre]];
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function relancerLettreMorte(string $reference, array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueEvenements::ACTION_LETTRE_MORTE_RELANCER, $reference, $acteur, $correlation, 'LETTRE_MORTE_RELANCEE',
            fn (): array => $this->livreur()->relancerLettreMorte($reference, $donnees + ['acteur' => $acteur]),
            200,
            ['CHAMP_MANQUANT' => 422, 'LETTRE_MORTE_INCONNUE' => 404],
        );
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function cloturerLettreMorte(string $reference, array $donnees, string $acteur, ?string $correlation): array
    {
        return $this->gouverner(
            PolitiqueEvenements::ACTION_LETTRE_MORTE_CLOTURER, $reference, $acteur, $correlation, 'LETTRE_MORTE_CLOTUREE',
            fn (): array => $this->livreur()->cloturerLettreMorte($reference, $donnees + ['acteur' => $acteur]),
            200,
            ['CHAMP_MANQUANT' => 422, 'LETTRE_MORTE_INCONNUE' => 404, 'ETAT_INCOMPATIBLE' => 409],
        );
    }

    // ------------------------------------------------------------------
    // Internes — construction des registres

    private function registreEvenements(): RegistreEvenements
    {
        return new RegistreEvenements(EvenementsMagasin::connecter(), $this->contrats(), $this->sources(), $this->realms());
    }

    private function registreAbonnements(): RegistreAbonnements
    {
        return new RegistreAbonnements(EvenementsMagasin::connecter(), $this->contrats(), $this->realms());
    }

    private function livreur(): LivreurEvenements
    {
        return new LivreurEvenements(EvenementsMagasin::connecter(), $this->registreEvenements());
    }

    private function rejoueur(): RejoueurEvenements
    {
        return new RejoueurEvenements(EvenementsMagasin::connecter());
    }

    private function contrats(): RegistreContrats
    {
        return new RegistreContrats(Db::connect(), IdentiteMagasin::connecter(), ContratsMagasin::connecter(), $this->ctr01());
    }

    private function sources(): RegistreSources
    {
        return new RegistreSources(Db::connect(), IdentiteMagasin::connecter(), SourcesMagasin::connecter(), ProduitsMagasin::connecter(), $this->ctr01());
    }

    private function realms(): RegistreRealms
    {
        return new RegistreRealms(Db::connect(), IdentiteMagasin::connecter(), RealmsMagasin::connecter(), $this->ctr01());
    }

    private function ctr01(): Ctr01
    {
        return new Ctr01(Db::connect(), IdentiteMagasin::connecter());
    }

    private function journal(): Journal
    {
        return new Journal(JournalMagasin::connecter());
    }

    private function acteurEstDestinataire(string $evenementReference, string $acteur): bool
    {
        $magasin = EvenementsMagasin::connecter();
        $st = $magasin->prepare(
            'SELECT 1 FROM livraison_evenement l
             JOIN abonnement_evenement a ON a.reference = l.abonnement_reference
             WHERE l.evenement_reference = ? AND a.consommateur_reference = ? LIMIT 1'
        );
        $st->execute([$evenementReference, $acteur]);

        return $st->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $abonnement */
    private function acteurEstProprietaire(array $abonnement, string $acteur): bool
    {
        return ($abonnement['consommateur_reference'] ?? null) === $acteur || $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
    }

    /** @return array{statut:int,corps:array<string,mixed>}|null */
    private function verifierAppartenanceAbonnement(string $reference, string $acteur): ?array
    {
        $refus = $this->verifierLecture(PolitiqueEvenements::ACTION_LIRE, $reference, $acteur);
        if ($refus !== null) {
            return $refus;
        }
        $abonnement = $this->registreAbonnements()->resoudreAbonnement($reference);
        if ($abonnement === null || !$this->acteurEstProprietaire($abonnement, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'ABONNEMENT_INCONNU']];
        }

        return null;
    }

    /** @return array{statut:int,corps:array<string,mixed>}|null null si permis */
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
     * Commandes portant sur un abonnement existant : vérifie l'appartenance
     * avant de gouverner la commande elle-même — un acteur qui n'est ni
     * propriétaire ni autorité ne doit même pas apprendre pourquoi une
     * commande serait refusée sur un abonnement qui n'est pas le sien.
     *
     * @param callable(array<string,mixed>):array<string,mixed> $operation
     * @return array{statut:int,corps:array<string,mixed>}
     */
    private function gouvernerAbonnement(
        string $reference,
        string $acteur,
        string $action,
        ?string $correlation,
        string $typeReussite,
        callable $operation,
        int $statutReussite,
        array $codesRefus = [],
    ): array {
        $abonnement = $this->registreAbonnements()->resoudreAbonnement($reference);
        if ($abonnement === null || !$this->acteurEstProprietaire($abonnement, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'ABONNEMENT_INCONNU']];
        }

        return $this->gouverner($action, $reference, $acteur, $correlation, $typeReussite, $operation, $statutReussite, $codesRefus);
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
                'categorie' => 'EVENEMENTS', 'type' => 'DECISION_' . $typeReussite,
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
                'categorie' => 'EVENEMENTS', 'type' => 'OPERATION_EVENEMENT_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => 'autorisation refusée', 'correlation_id' => $preuve['correlation_id'],
            ]);

            return ['statut' => 403, 'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision, 'preuve' => $preuve]];
        }

        $dossier = [
            'politique' => $decision['politique'] ?? PolitiqueEvenements::POLITIQUE,
            'source' => PolitiqueEvenements::SOURCE,
            'producteur' => $acteur, 'acteur' => $acteur,
            'preuve' => $preuve['reference'], 'correlation_id' => $preuve['correlation_id'],
        ];

        try {
            $resultat = $operation($dossier);
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'JOURNAL_EVENEMENTS_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'EVENEMENTS', 'type' => 'OPERATION_EVENEMENT_REFUSEE',
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
            'categorie' => 'EVENEMENTS', 'type' => $typeReussite,
            'acteur' => $acteur, 'action' => $action,
            'ressource' => $ressource ?? (string) ($resultat['reference'] ?? ''),
            'decision' => 'EXECUTEE', 'correlation_id' => $preuve['correlation_id'],
        ]);

        $statut = ($resultat['idempotent'] ?? false) === true ? 200 : $statutReussite;

        return ['statut' => $statut, 'corps' => ['resultat' => $resultat, 'decision' => $decision, 'preuve' => $preuve]];
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
                'message' => 'Le journal des événements est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
