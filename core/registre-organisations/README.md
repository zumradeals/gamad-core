# Registre des organisations (CAP-CORE-002)

Registre opérationnel des organisations de GAMAD Core : fiche organisationnelle, cycle de vie, structure (unités et relations), affiliations et fonctions internes descriptives.

Voir la fiche finale : `docs/capacites/CAP-CORE-002-organizations-registry.md`.

## Fichiers

- `src/Magasin.php` — connexion au magasin persistant (`ORGANIZATION_REGISTRY_URL` / `ORGANIZATION_REGISTRY_PATH`).
- `src/SchemaOrganisations.php` — migration additive des onze tables.
- `src/PolitiqueOrganisations.php` — vocabulaire technique fermé (actions, listes closes).
- `src/RegistreOrganisations.php` — commandes gouvernées et lectures.
- `src/ProjectionIdentites.php` — appartenance et représentation, intégration CAP-CORE-003.
- `src/ValidateurStructure.php` — contrôles d'acyclicité (unités, relations hiérarchiques).
- `src/ExceptionOrganisation.php` — erreur interne (jamais un refus métier gouverné).
- `resources/bootstrap-organisations-v1.json` — inventaire initial, vérifié par empreinte SHA-256.
- `tests/organisations_p3.php` — garde de comportement.

## Ce que ce module NE possède PAS

L'identité canonique (CAP-CORE-001), les mandats opposables (CAP-CORE-003), les décisions d'autorisation (CAP-CORE-004), les sources (CAP-CORE-006), les politiques (CAP-CORE-007), les contrats (CAP-CORE-009), les codes canoniques (CAP-CORE-010), aucun secret, aucune donnée RH détaillée.

Une affiliation `DIRIGEANT` ou `REPRESENTANT` reste descriptive tant que `CAP-CORE-003` ne confirme pas un mandat actif — voir `ProjectionIdentites::verifierRepresentation()`.

## Exécuter la garde

```bash
php core/registre-organisations/tests/organisations_p3.php
```
