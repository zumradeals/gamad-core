<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Résolution interne bornée d'un secret (fiche partie 2 §14, partie 3 §8).
 *
 * API PHP interne, jamais une route HTTP : `avecSecret()` transmet la valeur
 * uniquement au callback fourni, jamais à son appelant, jamais à une couche
 * HTTP, jamais à l'audit ni aux événements.
 *
 * La décision d'autorisation (`CAP-CORE-004`) est un intrant du contexte
 * (`UsageSecret`), établie par la couche applicative avant l'appel — comme
 * pour tous les autres registres persistants du Core, ce module `core/` ne
 * décide d'aucune autorisation lui-même et ne dépend pas de Laravel.
 */
final class ResolveurSecret
{
    /** @param array<string,FournisseurSecret> $fournisseurs référence fournisseur => adaptateur */
    public function __construct(
        private readonly RegistreSecretsCles $registre,
        private readonly array $fournisseurs,
    ) {
    }

    /**
     * @template T
     * @param callable(SensitiveValue):T $operation
     * @return T
     */
    public function avecSecret(string $reference, UsageSecret $usage, callable $operation): mixed
    {
        $secret = $this->registre->resoudreSecret($reference);
        if ($secret === null) {
            throw new ExceptionSecret("SECRET_INCONNU: `{$reference}` inconnu");
        }
        if ($secret['realm_reference'] !== null && $secret['realm_reference'] !== $usage->realmReference) {
            throw new ExceptionSecret('REALM_REFUSE: realm du contexte différent du realm du secret');
        }
        if ($secret['environnement_reference'] !== $usage->environnementReference) {
            throw new ExceptionSecret('ENVIRONNEMENT_REFUSE: environnement du contexte différent');
        }

        $ecriture = in_array($usage->modeUsage, PolitiqueSecretsCles::MODES_ECRITURE, true);
        $version = $ecriture
            ? $this->registre->resoudreVersionActiveEcriture($reference)
            : $this->versionPourLecture($reference, $usage);
        if ($version === null) {
            throw new ExceptionSecret('VERSION_INACTIVE: aucune version utilisable pour cet usage');
        }
        $etat = $this->registre->etatVersion((int) $version['id']);
        if (in_array($etat, PolitiqueSecretsCles::ETATS_TERMINAUX, true)) {
            throw new ExceptionSecret("VERSION_COMPROMISE: version `{$etat}`, usage refusé");
        }
        if ($etat === 'SUSPENDUE') {
            throw new ExceptionSecret('USAGE_REFUSE: version suspendue');
        }

        $fournisseurReference = (string) $version['fournisseur_reference'];
        $fournisseur = $this->fournisseurs[$fournisseurReference] ?? null;
        if ($fournisseur === null) {
            throw new ExceptionSecret("FOURNISSEUR_INDISPONIBLE: `{$fournisseurReference}` non enregistré côté résolveur");
        }
        $descripteur = new DescripteurVersion($reference, (string) $version['version'], (string) $version['handle_fournisseur']);
        $diagnostic = $fournisseur->verifierDisponibilite($descripteur);
        if (!$diagnostic->disponible) {
            throw new ExceptionSecret("FOURNISSEUR_INDISPONIBLE: {$diagnostic->motif}");
        }

        return $fournisseur->avecSecret($descripteur, $usage, $operation);
    }

    /** @return array<string,mixed>|null */
    private function versionPourLecture(string $reference, UsageSecret $usage): ?array
    {
        $ecriture = $this->registre->resoudreVersionActiveEcriture($reference);
        if ($ecriture !== null) {
            return $ecriture;
        }
        $lecture = $this->registre->resoudreVersionsLecture($reference);

        return $lecture[0] ?? null;
    }
}
