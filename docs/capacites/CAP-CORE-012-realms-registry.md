# CAP-CORE-012 — Realms Registry

**Nom :** Registre des realms
**État réel au commit de tête de ce chantier :** voir `docs/capacites/CATALOGUE.md` — ne jamais présumer `GO` à partir de ce seul document.

Cette fiche décrit le code réellement livré. Elle n'est ni une loi, ni un acte d'adoption, ni une preuve d'exécution en production.

## 1. Objectif

Représenter de manière persistante, gouvernée et vérifiable les realms de GAMAD Core : périmètres nommés, bornés et gouvernés dans lesquels des organisations, produits, capacités ou contrats peuvent être rattachés — territoriaux, institutionnels, de programme, de marché, de produit, techniques ou de coopération.

## 2. Responsabilité

`CAP-CORE-012` possède : la fiche de realm liée à une identité canonique (`CAP-CORE-001`), ses révisions en ajout seul, son cycle de vie (`PREPARATION → ACTIF → SUSPENDU → FERME → RETIRE`), sa hiérarchie acyclique (`PARENT_DE`) et ses autres relations (`CHEVAUCHE`, `EQUIVALENT_OPERATIONNEL`, `SUCCEDE_A`, `COOPERE_AVEC`), ses périmètres canoniques, ses identifiants externes gouvernés, ses rattachements d'organisations (`CAP-CORE-002`), de produits (`CAP-CORE-011`) et de contrats (`CAP-CORE-009`), ses franchissements explicites inter-realm, ses vérifications, et un moteur déterministe de contrôle de portée (`EvaluateurPortee`).

## 3. Exclusions

`CAP-CORE-012` ne possède pas l'identité canonique elle-même (`CAP-CORE-001`), le dossier d'organisation (`CAP-CORE-002`), les mandats opposables (`CAP-CORE-003`), les décisions d'autorisation (`CAP-CORE-004`), les sources (`CAP-CORE-006`), les politiques (`CAP-CORE-007`), les contrats (`CAP-CORE-009`), les codes canoniques (`CAP-CORE-010`), les produits et leurs environnements (`CAP-CORE-011`), aucun secret, aucune donnée métier de satellite, aucune cartographie lourde, aucune donnée personnelle de géolocalisation.

Un realm actif, un rattachement `RESPONSABLE` ou un franchissement `PERMET` ne donnent jamais automatiquement une autorisation : `CAP-CORE-004` reste le seul moteur de décision, et chaque réponse de `EvaluateurPortee::evaluer()` le rappelle explicitement.

## 4. Modèle de données livré

`core/registre-realms/src/SchemaRealms.php` — onze tables persistantes, distinctes de l'index reconstructible et des registres d'identités, d'organisations, de produits et de contrats :

- `realm` — fiche, référence `RLM-GAMAD-#########`, `identite_reference` et `code_canonique` uniques ;
- `realm_revision` — nom d'affichage, description, classification, organisation responsable, en ajout seul ;
- `realm_cycle` — cycle de vie, en ajout seul ;
- `realm_relation` — relations entre realms, hiérarchie `PARENT_DE` acyclique contrôlée applicativement (`ValidateurRealms`) ;
- `realm_perimetre` — dimensions canoniques (pays, région, juridiction, marché, domaine, programme, environnement, institution, classification de données) ;
- `realm_identifiant_externe` — identifiants gouvernés, unicité système/valeur pendant une période active ;
- `realm_organisation` — rattachement d'organisation, rôle canonique, mandat vérifié pour `RESPONSABLE`/`REGULATEUR` ;
- `realm_produit` — rattachement de produit, rôle canonique, environnement référencé sans recopie d'URL ni de secret ;
- `realm_contrat` — association d'un contrat actif ou déprécié ;
- `realm_franchissement` — passages explicites `PERMET`/`REFUSE` entre realms, refus prioritaire ;
- `realm_verification` — vérifications en ajout seul, auto-attestation forte interdite.

**Non créée dans ce chantier :** `realm_capacite` (fiche §21). La fiche l'autorise explicitement à rester absente tant qu'aucun consommateur réel ne l'utilise ; aucun consommateur de ce type n'existe aujourd'hui dans `main`.

## 5. Hiérarchie et relations

`RegistreRealms::declarerRelation()` / `fermerRelation()`. Seule `PARENT_DE` est enregistrée pour la hiérarchie canonique ; `INCLUS_DANS` se dérive en lecture, jamais doublée en base. `ValidateurRealms::relationCreeraitCycle()` refuse tout cycle direct ou indirect, dans la même transaction que la déclaration. `resoudreAscendance()`/`resoudreDescendance()` sont bornées (profondeur/nombre de nœuds) pour éviter une récursion non maîtrisée. Un `CHEVAUCHE` n'implique jamais une inclusion ; une `SUCCEDE_A` n'altère jamais automatiquement le cycle du realm remplacé.

## 6. Périmètres et identifiants externes

`declarerPerimetre()`/`fermerPerimetre()`, `declarerIdentifiantExterne()`/`fermerIdentifiantExterne()`. Dimension hors liste close refusée (`DIMENSION_INCONNUE`) : aucune dimension libre n'est jamais utilisée par le moteur de portée. Un couple système/valeur déjà actif est refusé (`IDENTIFIANT_DEJA_DECLARE`).

## 7. Rattachements d'organisation, de produit et de contrat

- `rattacherOrganisation()`/`detacherOrganisation()` : organisation active dans `CAP-CORE-002` obligatoire ; rôle `RESPONSABLE`/`REGULATEUR` exige un mandat vérifié via `RegistreOrganisations::verifierRepresentation()` (`CAP-CORE-003`), sauf pour l'autorité d'inscription elle-même.
- `rattacherProduit()`/`detacherProduit()` : produit actif dans `CAP-CORE-011` obligatoire ; environnement, s'il est fourni, doit exister réellement (`resoudreEnvironnementActif()`) ; aucune URL ni audience n'est jamais recopiée.
- `rattacherContrat()`/`detacherContrat()` : contrat avec version active ou dépréciée dans `CAP-CORE-009` obligatoire.

Chacune de ces trois dépendances est optionnelle à la construction de `RegistreRealms` : absente, la commande correspondante est refusée avec `DEPENDANCE_INDISPONIBLE`, jamais silencieusement acceptée.

## 8. Franchissement et contrôle de portée

`declarerFranchissement()`/`fermerFranchissement()` : refus par défaut, un `REFUSE` applicable l'emporte toujours sur un `PERMET`, aucun objet contenant un caractère `*` n'est accepté (`WILDCARD_INTERDIT`).

`RegistreRealms::verifierPortee()` rassemble les faits (état du realm, rattachements actifs, franchissement applicable, vérification expirée, dépendance indisponible) et délègue la décision à `EvaluateurPortee::evaluer()` — une fonction pure, sans connexion, testée isolément. La réponse porte toujours `dans_portee`, la liste des `motifs` canoniques et un avertissement explicite : cette réponse **ne constitue jamais une autorisation** ; la couche applicative doit ensuite demander une décision à `CAP-CORE-004`.

## 9. Vérifications

`enregistrerVerification()` : ajout seul, vérificateur et résultat canoniques, auto-attestation forte interdite sauf pour l'autorité d'inscription. Une vérification `CONFORME` expirée est signalée par le contrôle de portée (`VERIFICATION_EXPIREE`), jamais silencieusement ignorée.

## 10. Vocabulaire, politique et contrat — auto-bootstrapés

Contrairement à `CAP-CORE-002`, rien de ce que `core:realms:bootstrap` inscrit n'existait avant ce chantier :

- **`VOC-GAMAD-REALM`** (`CAP-CORE-010`) — un vocabulaire unique couvrant les types de realm, les types de relation, les dimensions de périmètre, les rôles d'organisation et de produit, et les motifs canoniques de refus, repris fidèlement de `PolitiqueRealms`. Cycle complet : version créée, termes ajoutés, soumission, analyse de compatibilité, projection JSON, conformité `CONFORME`, activation.
- **`POL-REALMS-V1`** (`CAP-CORE-007`) — vingt-trois actions, une règle par action, réservées à `AUT-GAMAD-001`.
- **`CTR-12`** (`CAP-CORE-009`) — contrat interne décrivant huit opérations de lecture minimales (résolution par référence/identité/code, état, hiérarchie, organisations, produits, contrôle de portée), analysé, conforme et actif.

## 11. Autorisation (CAP-CORE-004) et audit (CAP-CORE-013)

Chaque commande gouvernée passe par `App\Application\Realms\AccesRealms::executer()` : décision `Ctr03::autoriser()`, preuve `Journal::enregistrer()` avant toute écriture, refus tracé si la décision ou l'écriture échoue. Événements d'audit : `REALM_INSCRIT`, `REALM_MODIFIE`, `REALM_ACTIVE`, `REALM_SUSPENDU`, `REALM_FERME`, `REALM_RETIRE`, `RELATION_REALM_DECLAREE`, `RELATION_REALM_FERMEE`, `PERIMETRE_REALM_DECLARE`, `IDENTIFIANT_REALM_DECLARE`, `ORGANISATION_REALM_RATTACHEE`, `ORGANISATION_REALM_DETACHEE`, `PRODUIT_REALM_RATTACHE`, `PRODUIT_REALM_DETACHE`, `CONTRAT_REALM_RATTACHE`, `CONTRAT_REALM_DETACHE`, `FRANCHISSEMENT_REALM_DECLARE`, `FRANCHISSEMENT_REALM_FERME`, `VERIFICATION_REALM_ENREGISTREE`, `OPERATION_REALM_REFUSEE`.

## 12. Panne

Registre des realms indisponible → `503 SOCLE_INDISPONIBLE` (avant écriture) ou `503 REGISTRE_REALMS_INDISPONIBLE` (après décision et preuve, avant écriture confirmée). `CAP-CORE-002`/`CAP-CORE-011`/`CAP-CORE-009` indisponibles → rattachement refusé (`DEPENDANCE_INDISPONIBLE`), jamais un rattachement supposé. `Ctr12` referme systématiquement toute exception en réponse fermée (`dans_portee: false`), jamais une portée globale supposée.

## 13. API et console

API v1 : 34 routes sous `/api/v1/realms*` (`RealmController`), gouvernées par `AccesRealms`. Console : écran `Realms` (`RealmConsoleController`, vues `realms/{index,create,show}.blade.php`) — liste filtrable, fiche complète (hiérarchie, périmètres, identifiants, organisations, produits, contrats, franchissements, vérification, historique), confirmations JavaScript pour les actions sensibles (activation, suspension, fermeture, retrait, rattachements, franchissements).

## 14. Sauvegarde et restauration

`ops/core-foundation/backup.sh` et `restore-drill.sh` incluent la cible `realms` (dump, somme SHA-256, comptage post-restauration `SELECT count(*) FROM realm`). `apps/console-laravel/tests/Integration/postgresql_p0.sh` provisionne `gamad_realms`/`drill_realms` et exécute l'exercice complet aux côtés des dix autres magasins.

## 15. Code livré

```text
core/registre-realms/
├── README.md
├── resources/bootstrap-realms-v1.json
├── src/
│   ├── Magasin.php
│   ├── SchemaRealms.php
│   ├── PolitiqueRealms.php
│   ├── ValidateurRealms.php
│   ├── EvaluateurPortee.php
│   ├── RegistreRealms.php
│   ├── Ctr12.php
│   └── ExceptionRealm.php
└── tests/realms_p3.php

apps/console-laravel/
├── app/Application/Realms/AccesRealms.php
├── app/Http/Controllers/Api/V1/RealmController.php
├── app/Http/Controllers/RealmConsoleController.php
├── app/Console/Commands/BootstrapRealmsCommand.php
├── resources/views/realms/{index,create,show}.blade.php
└── tests/Integration/
    ├── realms_v1_p1.php
    ├── realms_console_p1.php
    └── realms_contracts_p1.php
```

## 16. Tests

- `core/registre-realms/tests/realms_p3.php` — 45 épreuves et contre-épreuves sur le registre seul (SQLite), y compris une contre-épreuve finale de non-réutilisation de référence retirée.
- `apps/console-laravel/tests/Integration/realms_v1_p1.php` — 21 épreuves du parcours HTTP complet (inscription → périmètre → rattachements → activation → contrôle de portée → franchissement → suspension → fermeture → succession → retrait).
- `apps/console-laravel/tests/Integration/realms_console_p1.php` — 9 épreuves de l'écran console.
- `apps/console-laravel/tests/Integration/realms_contracts_p1.php` — 12 épreuves : dérive OpenAPI stricte sur `/realms*` (34 routes ↔ 34 opérations), `CTR-12` bootstrapé et actif, façade `Ctr12` explicable et fermée en panne.

87 épreuves au total. Elles ne couvrent pas littéralement chacun des soixante-quatre points numérotés de la fiche §57 : elles couvrent les invariants critiques (gouvernance, cycle de vie, acyclicité hiérarchique, refus par défaut, refus prioritaire, non-opposabilité par défaut, dépendances indisponibles, non-réutilisation de référence, absence de secret). Voir le rapport de session pour la correspondance exacte.

## 17. Limites non bloquantes et réserves

- Aucune identité de type `realm` n'existait avant ce chantier : le bootstrap est honnêtement vide et le mécanisme de reprise d'identités existantes est codé et testé avec des données construites, jamais exercé sur un jeu de données réel faute d'en exister un.
- `realm_capacite` (fiche §21) n'est pas créée, conformément à l'autorisation explicite de la fiche en l'absence de consommateur réel.
- La concurrence réelle (deux processus simultanés) n'est pas éprouvée par un test multi-processus dans ce chantier : les épreuves d'idempotence (activation rejouée, rattachement rejoué) prouvent l'absence de doublon au rejeu séquentiel, pas une garantie sous charge concurrente réelle — la protection tient aux contraintes `UNIQUE` de la base et aux transactions, pas à un verrou applicatif distinct testé sous concurrence.
- L'intégration CAP-CORE-022 (fédération) n'a pas été livrée : la fiche l'autorise explicitement à rester différée tant qu'aucun consommateur réel ne l'exige (fiche §45).
- Le contrôle de portée ne vérifie pas de vocabulaire de « finalité » canonique dédié : une finalité est acceptée comme chaîne non vide, sans liste close propre à CAP-CORE-012 au-delà de son usage dans les franchissements.
