# CAP-CORE-002 — Organizations Registry

**Nom :** Registre des organisations
**État réel au commit de tête de ce chantier :** voir `docs/capacites/CATALOGUE.md` — ne jamais présumer `GO` à partir de ce seul document.

Cette fiche décrit le code réellement livré. Elle n'est ni une loi, ni un acte d'adoption, ni une preuve d'exécution en production.

## 1. Objectif

Représenter de manière persistante, gouvernée et vérifiable les organisations reconnues par GAMAD Core : leur fiche organisationnelle, leur cycle de vie, leur structure interne, leurs relations avec d'autres organisations, les affiliations de personnes ou d'organisations, leurs fonctions internes descriptives et leurs identifiants externes.

## 2. Responsabilité

`CAP-CORE-002` possède : la fiche organisationnelle liée à une identité canonique (`CAP-CORE-001`), ses révisions en ajout seul, son cycle de vie (`PREPARATION → ACTIVE → SUSPENDUE/DISSOUTE/RETIREE`), ses identifiants externes gouvernés, ses unités et leur hiérarchie acyclique, ses relations organisation-à-organisation, ses affiliations et leur cycle, ses fonctions internes descriptives et leurs classifications.

## 3. Exclusions

`CAP-CORE-002` ne possède pas l'identité canonique elle-même (`CAP-CORE-001`), les mandats opposables (`CAP-CORE-003`), les décisions d'autorisation (`CAP-CORE-004`), les sources (`CAP-CORE-006`), les politiques (`CAP-CORE-007`), les contrats (`CAP-CORE-009`), les codes canoniques (`CAP-CORE-010`), aucun secret, aucune donnée RH détaillée, aucune donnée métier de satellite.

Une affiliation `DIRIGEANT` ou `REPRESENTANT` reste strictement descriptive : elle ne devient une représentation opposable qu'après vérification d'un mandat actif par `CAP-CORE-003`.

## 4. Modèle de données livré

`core/registre-organisations/src/SchemaOrganisations.php` — onze tables persistantes, distinctes de l'index reconstructible et du registre d'identités :

- `organisation` — fiche, référence `ORG-GAMAD-######`, `identite_reference` unique ;
- `organisation_revision` — dénominations et description, en ajout seul ;
- `organisation_cycle` — cycle de vie, en ajout seul ;
- `organisation_identifiant_externe` — identifiants gouvernés, unicité système/type/valeur ;
- `organisation_unite` — unités, hiérarchie acyclique contrôlée applicativement ;
- `organisation_unite_cycle` — cycle des unités, en ajout seul ;
- `organisation_relation` — relations organisation-à-organisation, acyclicité hiérarchique contrôlée pour `PARENTE_DE`/`FILIALE_DE` ;
- `organisation_affiliation` — rattachement d'une identité à une organisation ;
- `organisation_affiliation_cycle` — cycle des affiliations (`PROPOSEE → ACTIVE → SUSPENDUE/CLOSE/REJETEE`), en ajout seul ;
- `organisation_fonction_interne` — fonctions descriptives, avec `mandat_fonction_reference` optionnelle vers `CAP-CORE-003` ;
- `organisation_mandat_projection` — projection facultative, schéma créé mais non alimentée dans ce chantier (aucun consommateur réel ne l'exige encore).

## 5. Structure et unités

`RegistreOrganisations::creerUnite()`, `deplacerUnite()`, `fermerUnite()`. Une unité appartient à une seule organisation ; sa parente appartient à la même organisation. `ValidateurStructure::uniteCreeraitCycle()` refuse tout déplacement qui créerait un cycle. La fermeture d'une unité avec des descendants actifs est refusée explicitement (`DESCENDANTS_ACTIFS`), jamais en cascade silencieuse.

**Limite documentée :** le rattachement hiérarchique courant (`organisation_unite.unite_parente_reference`) est un pointeur mutable, journalisé à chaque déplacement dans `organisation_unite_cycle` (motif détaillé), plutôt qu'une table de révision structurelle dédiée. Une reconstruction historique de « qui était sous qui à quelle date » exigerait une table de révision de rattachement, non livrée dans ce chantier.

## 6. Relations interorganisationnelles

`RegistreOrganisations::declarerRelationOrganisationnelle()` / `fermerRelationOrganisationnelle()`. Auto-relation refusée. Pour `PARENTE_DE`/`FILIALE_DE`, `ValidateurStructure::relationCreeraitCycle()` refuse toute relation qui bouclerait le graphe hiérarchique. Pourcentage borné 0–100.

## 7. Affiliations

`RegistreOrganisations::proposerAffiliation()` / `activerAffiliation()` / `suspendreAffiliation()` / `fermerAffiliation()`. Cycle `PROPOSEE → ACTIVE → SUSPENDUE/CLOSE`, ou `PROPOSEE → REJETEE`. Une affiliation ne crée jamais de mandat, de session ni de rôle applicatif.

## 8. Mandats et représentation — intégration CAP-CORE-003

`ProjectionIdentites::verifierRepresentation()` combine : affiliation active (`CAP-CORE-002`), identité active (`CAP-CORE-001`), fonction interne liée à une fonction réelle de `CAP-CORE-003` (`organisation_fonction_interne.mandat_fonction_reference`), et mandat actif vérifié par `Gamad\RegistreAutorites\Ctr02::resoudreMandat()`. Quatre motifs distincts jamais fusionnés : `AFFILIATION_ABSENTE`, `IDENTITE_NON_ACTIVE`, `MANDAT_ABSENT`, `MANDAT_INDISPONIBLE`. L'absence de réponse de `CAP-CORE-003` vaut toujours non opposable.

**Limite documentée :** `CAP-CORE-003` (`core/registre-autorites`) ne connaît aujourd'hui que les fonctions institutionnelles fixes de l'index baseline (`FCT-CORE-*`), pas un mandat générique pour une organisation métier arbitraire. `organisation_fonction_interne.mandat_fonction_reference` permet de relier une fonction interne à une de ces fonctions institutionnelles quand c'est pertinent ; en dehors de ce cas, la représentation reste honnêtement non opposable (`MANDAT_ABSENT`), ce qui est le comportement correct par défaut, pas une lacune de sécurité.

## 9. Correction de frontière avec CAP-CORE-001

`Gamad\RegistreIdentites\Ctr01` accepte désormais un quatrième paramètre optionnel `?\PDO $organisations` :

- absent (comportement historique) : `resoudreLiensOrganisations()` lit `relation_organisation` comme avant ce chantier — aucun appelant existant du dépôt n'est cassé ;
- fourni : `resoudreLiensOrganisations()` délègue exclusivement à `organisation_affiliation`/`organisation_affiliation_cycle`, projetée avec les mêmes noms de champs que l'ancienne lecture ; `relation_organisation` n'est plus jamais lue ; `rattacherOrganisation()` refuse explicitement (`DEPRECIE_CAP_CORE_002`), en pointant vers `RegistreOrganisations::proposerAffiliation()`.

Ce raccordement est actif dans `AccesOrganisations`, `OrganisationConsoleController` et `BootstrapOrganisationsCommand`. Les ~45 autres instanciations historiques de `Ctr01` dans le dépôt (contrats, produits, sources, politiques, vocabulaire, fédération…) restent à deux arguments et continuent de lire `relation_organisation` : elles ne consomment jamais de relations organisationnelles et ce chantier ne les a délibérément pas touchées, pour ne pas élargir son rayon d'impact au-delà de CAP-CORE-002. C'est une migration de frontière **amorcée et prouvée** (voir `organisations_ctr01_compat_p1.php`), pas une bascule totale du dépôt.

## 10. Classification et minimisation

Les listes closes (`PolitiqueOrganisations`) reprennent fidèlement les vocabulaires déjà bootstrapés côté `CAP-CORE-001`/`CAP-CORE-010` pour les affiliations (`VOC-GAMAD-RELATION-ORGANISATION`), les classifications (`VOC-GAMAD-IDENTITE-CLASSIFICATION`) et les niveaux d'assurance (`VOC-GAMAD-IDENTITE-ASSURANCE`). Neuf vocabulaires propres à CAP-CORE-002 ont été ajoutés à `core/registre-vocabulaire/resources/bootstrap-vocabulaire-v1.json` : types, formes et états d'organisation, types et états d'unité, types de relation, états d'affiliation, types d'identifiant externe, types de fonction interne.

La liste des affiliations d'une organisation n'est jamais publique par défaut (`AccesOrganisations::resoudreAffiliations()` la réserve à l'autorité et au propriétaire).

## 11. Contrats CAP-CORE-009

**Non livré dans ce chantier.** Aucune entrée `CTR-*` n'a été enregistrée dans le registre des contrats pour les opérations de `RegistreOrganisations` ou pour les routes `/api/v1/organisations*`. C'est une réserve explicite, pas un oubli silencieux : voir section « Réserves » du rapport de session. Les autres registres analogues (produits, contrats, vocabulaire) ont chacun un contrat `CTR-*` correspondant ; celui de CAP-CORE-002 reste à créer.

## 12. Autorisation (CAP-CORE-004) et audit (CAP-CORE-013)

`POL-ORGANISATIONS-V1` — vingt actions, une règle par action, réservées à `AUT-GAMAD-001`, bootstrapées par `core:organisations:bootstrap`. Chaque commande gouvernée passe par `App\Application\Organisations\AccesOrganisations::executer()` : décision `Ctr03::autoriser()`, preuve `Journal::enregistrer()` avant toute écriture, refus tracé si la décision ou l'écriture échoue. Événements d'audit : `ORGANISATION_INSCRITE`, `ORGANISATION_MODIFIEE`, `ORGANISATION_ACTIVEE`, `ORGANISATION_SUSPENDUE`, `ORGANISATION_DISSOUTE`, `ORGANISATION_RETIREE`, `IDENTIFIANT_ORGANISATION_DECLARE`, `IDENTIFIANT_ORGANISATION_FERME`, `UNITE_ORGANISATION_CREEE`, `UNITE_ORGANISATION_DEPLACEE`, `UNITE_ORGANISATION_FERMEE`, `RELATION_ORGANISATION_DECLAREE`, `RELATION_ORGANISATION_FERMEE`, `AFFILIATION_ORGANISATION_PROPOSEE`, `AFFILIATION_ORGANISATION_ACTIVEE`, `AFFILIATION_ORGANISATION_SUSPENDUE`, `AFFILIATION_ORGANISATION_FERMEE`, `FONCTION_ORGANISATION_CREEE`, `OPERATION_ORGANISATION_REFUSEE`.

## 13. Panne

Registre indisponible → `503 SOCLE_INDISPONIBLE` (avant écriture) ou `503 REGISTRE_ORGANISATIONS_INDISPONIBLE` (après décision et preuve, avant écriture confirmée). `CAP-CORE-003` indisponible → représentation non opposable (`MANDAT_INDISPONIBLE`), jamais un refus généralisé de la lecture.

## 14. Sauvegarde et restauration

`ops/core-foundation/backup.sh` et `restore-drill.sh` incluent désormais la cible `organisations` (dump, somme SHA-256, comptage post-restauration `SELECT count(*) FROM organisation`). `apps/console-laravel/tests/Integration/postgresql_p0.sh` provisionne `gamad_organizations` / `drill_organizations` et exécute l'exercice complet.

## 15. Code livré

```text
core/registre-organisations/
├── resources/bootstrap-organisations-v1.json
├── src/
│   ├── Magasin.php
│   ├── SchemaOrganisations.php
│   ├── PolitiqueOrganisations.php
│   ├── RegistreOrganisations.php
│   ├── ProjectionIdentites.php
│   ├── ValidateurStructure.php
│   └── ExceptionOrganisation.php
└── tests/organisations_p3.php

apps/console-laravel/
├── app/Application/Organisations/AccesOrganisations.php
├── app/Http/Controllers/Api/V1/OrganisationController.php
├── app/Http/Controllers/OrganisationConsoleController.php
├── app/Console/Commands/BootstrapOrganisationsCommand.php
├── resources/views/organisations/{index,create,show}.blade.php
└── tests/Integration/
    ├── organisations_v1_p1.php
    ├── organisations_console_p1.php
    └── organisations_ctr01_compat_p1.php
```

## 16. Tests

- `core/registre-organisations/tests/organisations_p3.php` — 35 épreuves et contre-épreuves sur le registre seul (SQLite).
- `apps/console-laravel/tests/Integration/organisations_v1_p1.php` — 15 épreuves du parcours HTTP complet.
- `apps/console-laravel/tests/Integration/organisations_console_p1.php` — 9 épreuves de l'écran console.
- `apps/console-laravel/tests/Integration/organisations_ctr01_compat_p1.php` — 4 épreuves de la correction de frontière CAP-CORE-001.
- Exercice PostgreSQL réel (`postgresql_p0.sh`) exécuté localement le 3 août 2026 : dix magasins PostgreSQL réels, sauvegarde, somme de contrôle, restauration isolée, tous verts.

Ces 63 épreuves ne couvrent pas littéralement chacun des soixante points numérotés de la fiche §27 : elles couvrent les invariants critiques (gouvernance, cycle de vie, acyclicité, non-opposabilité par défaut, rollback, non-réutilisation de référence, absence de secret). Voir le rapport de session pour la correspondance exacte.

## 17. Limites non bloquantes et réserves

- **Réserve non bloquante, tranchée explicitement par le dirigeant le 3 août 2026** : aucun contrat `CTR-*` (CAP-CORE-009) enregistré pour ce registre (section 11). CAP-CORE-002 passe `GO` dans `docs/capacites/CATALOGUE.md` malgré cette absence ; l'enregistrement des contrats `CTR-*` reste dû dans un chantier ultérieur.
- Aucune entrée OpenAPI (`openapi/core-v1.yaml`) pour les routes `/api/v1/organisations*` : `openapi_contracts_p1.php`/`vocabulaire_drift_p1.php` ne couvrent pas ce registre et ne l'exigent donc pas, mais la documentation externe du contrat HTTP reste à écrire.
- `organisation_mandat_projection` : table créée, jamais alimentée (facultative selon la fiche §11.11).
- Aucune donnée réelle à bootstrapper au moment de ce chantier : zéro identité de type `organisation` dans l'index baseline, donc zéro organisation et zéro relation historique migrée en exploitation réelle. Le mécanisme de reprise et de migration est codé et testé avec des données construites, pas exercé sur un jeu de données réel faute d'en exister un.
- Le déplacement d'unité n'historise pas une table de révision de rattachement dédiée (section 5).
