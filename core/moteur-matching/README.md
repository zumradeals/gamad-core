# Moteur de Matching (CAP-CORE-021)

Moteur déterministe, explicable et multi-consommateurs de qualification et d'appariement du GAMAD Core.

Voir le chantier complet : `docs/capacites/chantiers/CAP-CORE-021/` (5 parties) et les sources fondatrices `docs/03-matching-transversal.md` et `GAMAD_Core_Capacite_Matching_Note_Fondatrice_v0.1 (1).md` (conceptuelle, non normative).

Décisions de lancement du dirigeant (2026-08-05) : voir mémoire `projet-cap-core-021-matching-decisions-go` — deux prérequis de la fiche de chantier ont été explicitement écartés (l'ordre imposant 008/017/018/020 avant 021, et l'exigence nommée de Wasplex comme pilote). Le moteur est construit complet et prêt pour la production sans attendre qu'un deuxième consommateur réel soit branché ; le verdict `GO`/`NO GO` final reste honnête sur ce point.

## Fichiers

- `src/Magasin.php` — connexion au magasin persistant (`MATCHING_REGISTRY_URL` / `MATCHING_REGISTRY_PATH`).
- `src/SchemaMatching.php` — migration additive des tables (doc 02), réutilise l'outbox partagé `Gamad\EvenementsSortants\SchemaOutbox` pour la publication transactionnelle vers CAP-CORE-014 (pas de table `matching_outbox` bespoke).
- `src/PolitiqueMatching.php` — vocabulaire technique fermé (actions, opérateurs, états, obligations, codes d'erreur, limites, préfixes de référence). Même précédent que `PolitiquePreuves` (CAP-CORE-015) et `PolitiqueSecretsCles` (CAP-CORE-016) : listes fermées vérifiées en code, intégration dynamique avec CAP-CORE-010 laissée à un chantier ultérieur non bloquant — réserve documentée, pas une omission.
- `src/Apparieur.php`, `EvaluateurDeterministe.php`, `Classement.php`, `CompilateurPolitique.php`, `Segments.php`, `Explication.php`, `Activation.php`, `Mesure.php`, `Contestations.php`, `ResolutionSources.php` — fonctions pures du moteur, sans accès au magasin (doc 03).
- `src/RegistreMatching.php` — orchestration persistante : relie les classes pures ci-dessus au magasin réel. Couvre le parcours complet doc 01 §4 (1 à 10) : contexte, profil compilé et activé, demande, signal, exécution, résultat, explication, segment, appartenance, activation, mesure, contestation, réexamen. **Réserve documentée en tête de fichier** : cette classe ne contacte jamais elle-même CAP-CORE-004/006/008/011/012/017/018 — les faits qui en dépendent (autorisation, statut de source/produit/contrat, risque ou incident bloquant) sont fournis explicitement par l'appelant. Câbler ces appels réels est un chantier ultérieur non bloquant, à ne jamais présenter comme déjà fait.
- `tests/matching_p3.php` — garde des fonctions pures (91 épreuves).
- `tests/matching_p4.php` — garde de `RegistreMatching` sur un magasin SQLite en mémoire, parcours bout en bout incluant un réexamen après correction de source (34 épreuves). Vérifié seulement sur SQLite à ce stade — pas encore rejoué contre PostgreSQL réel.
- `apps/console-laravel/app/Application/Matching/AccesMatching.php` — façade HTTP gouvernée, même moule qu'`AccesPreuves` (CAP-CORE-015) : `CTR-03` décide, `CAP-CORE-013` trace, puis seulement l'écriture. **Périmètre volontairement restreint** à ce qui a un code d'action exact dans `PolitiqueMatching::ACTIONS` — voir la réserve en tête du fichier : pas de lecture, d'accusé, de suspension ou de révocation isolée d'une activation, pas d'instruction de contestation au-delà de l'ouverture et du réexamen, faute d'action dédiée dans le vocabulaire fermé actuel de la fiche (doc 04 §1 est incomplet par rapport à doc 04 §7-8). Étendre ce vocabulaire est un chantier séparé, pas fait ici pour ne pas inventer une permission.
- `apps/console-laravel/app/Http/Controllers/Api/V1/MatchingController.php` — 22 routes sous `/api/v1/matching/*` (contextes, profils, demandes, résultats, segments, activations/mesures, contestations).
- `apps/console-laravel/tests/Integration/matching_v1_p1.php` — parcours HTTP complet à travers le vrai noyau Laravel (authentification, CTR-03, audit CAP-CORE-013), 15 épreuves. Le contexte et le profil de test sont semés directement via `RegistreMatching` — il n'existe encore aucune route ni commande d'exploitation pour cette opération.
- `apps/console-laravel/app/Http/Controllers/MatchingConsoleController.php` + `resources/views/matching/*.blade.php` — console **lecture seule** : tableau de bord (contextes + demandes récentes), fiche de contexte (profil actif), fiche de demande (historique, exécutions, résultats classés avec explication dépliable, segments sans jamais lister leurs membres), fiche de segment. Aucune écriture (soumission, exécution, construction, activation) n'est exposée depuis cette console — ce sont des opérations de produit consommateur ou d'exploitation, pas des boutons d'admin.
- `apps/console-laravel/tests/Integration/matching_console_p1.php` — éprouve le rendu des trois écrans et le refus 404 propre sur une référence inconnue, 5 épreuves.

`apps/console-laravel/app/Console/Commands/BootstrapMatchingCommand.php` établit `POL-MATCHING-V1` (CAP-CORE-007) et les douze contrats `CTR-MAT-01`..`12` (CAP-CORE-009), en type `COMMANDE` (pas `HTTP_API`, qui exigerait un consommateur réel déjà déclaré). Vérifié réellement : bootstrap et rejeu idempotent sur SQLite frais, exit code 0 les deux fois. Aucun contexte n'est bootstrapé automatiquement : `RegistreMatching::inscrireContexte()` exige une spécification réelle (finalité, classification, autorisations) fournie par l'appelant, jamais inventée par ce chantier.

## Ce que ce module NE possède PAS

L'identité canonique (CAP-CORE-001), le dossier d'organisation (CAP-CORE-002), les mandats (CAP-CORE-003), les décisions d'autorisation (CAP-CORE-004), les sources (CAP-CORE-006), les politiques (CAP-CORE-007 — le Matching compile une politique active en plan d'exécution, il n'en détient jamais une version souveraine concurrente), les contrats (CAP-CORE-009), les codes canoniques (CAP-CORE-010), les produits (CAP-CORE-011), aucune clé privée, aucun modèle d'apprentissage automatique, aucune lecture directe d'un autre magasin.

Pas encore livré (voir chantier restant) : câblage réel vers CAP-CORE-004/006/008/011/012/017/018 (les faits correspondants restent fournis par l'appelant), acquisition de signaux depuis CAP-CORE-014, worker et tâches planifiées, métriques, tests de charge et de sécurité, rapport de qualité et d'équité, exercices de sauvegarde/restauration et d'incident, mise à jour d'`openapi/core-v1.yaml`, extension du vocabulaire d'action pour les routes d'activation/contestation non exposées, vérification contre PostgreSQL réel, deuxième consommateur réel. `CAP-CORE-021` reste `NO GO` au sens strict de la fiche (doc 05) tant que ces éléments ne sont pas faits et honnêtement rapportés.

## Invariants imposés au niveau schéma

- `matching_segment.export_brut_autorise` est contraint à `0` : aucun export brut de membres dans ce premier périmètre (doc 01 §4, doc 04 §6).
- `matching_resultat.non_decisionnel` est contraint à `1` : le moteur ne produit jamais une décision finale automatique (doc 02 §15).
- toute table de cycle (`matching_cycle`) est en ajout seul.

## Exécuter les gardes

```bash
php core/moteur-matching/tests/matching_p3.php
php core/moteur-matching/tests/matching_p4.php
php apps/console-laravel/tests/Integration/matching_v1_p1.php
php apps/console-laravel/tests/Integration/matching_console_p1.php
```
