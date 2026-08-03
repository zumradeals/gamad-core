# CAP-CORE-021 — Matching Engine

Note préparatoire du chantier de passage de `NO GO` à `GO`.

## Ordre de lecture

1. `01-fiche-de-codage.md` — mission, sources fondatrices, prérequis, frontières, architecture et première admission.
2. `02-modele-matching-et-segments.md` — demandes, critères, signaux, exécutions, résultats, segments, activations et contestations.
3. `03-moteur-deterministe-et-raccordements.md` — qualification, scoring, confiance, explication, collecte autorisée et intégrations.
4. `04-api-console-contrats-tests.md` — API, console, politiques, contrats, audit, événements, sécurité et épreuves.
5. `05-exploitation-criteres-go.md` — exploitation, performance, équité, sauvegarde, CI, pilotes et critères GO.

## Sources fondatrices à préserver

Claude Code doit lire avant toute conception :

```text
docs/03-matching-transversal.md
GAMAD_Core_Capacite_Matching_Note_Fondatrice_v0.1 (1).md
```

La note fondatrice est conceptuelle, non normative et non adoptée. Elle préserve néanmoins l’intention du dirigeant :

```text
Le Matching appartient au GAMAD Core.
Wasplex peut être un consommateur majeur, mais jamais son propriétaire exclusif.
Le moteur transforme une connaissance autorisée en correspondances utiles.
```

La présente fiche convertit cette intention en chantier technique testable. Elle ne transforme pas rétroactivement la note fondatrice en code livré.

## Prérequis de lancement

Toutes les capacités suivantes doivent être `GO` et fusionnées dans `main` :

```text
CAP-CORE-001 — Identity Registry
CAP-CORE-002 — Organizations Registry
CAP-CORE-003 — Authorities & Mandates
CAP-CORE-004 — Authorization
CAP-CORE-005 — Authentication & Access
CAP-CORE-006 — Sources Registry
CAP-CORE-007 — Rules / Policies Registry
CAP-CORE-008 — Decisions Registry
CAP-CORE-009 — Contracts Registry
CAP-CORE-010 — Canonical Vocabulary
CAP-CORE-011 — Products Registry
CAP-CORE-012 — Realms Registry
CAP-CORE-013 — Common Audit
CAP-CORE-014 — Event Journal
CAP-CORE-015 — Integrity Proofs
CAP-CORE-016 — Secrets & Keys
CAP-CORE-017 — Risks & Exceptions
CAP-CORE-018 — Incidents
CAP-CORE-019 — Backup & Restore
CAP-CORE-020 — Directory & Atlas
CAP-CORE-022 — Satellite Federation
```

`CAP-CORE-021` arrive en dernier parce qu’il consomme les références, règles, contrats, événements, preuves et périmètres établis par les autres capacités.

## Séparation essentielle

```text
IDENTITÉ
= établit qui existe

SOURCE MÉTIER
= reste propriétaire du dossier détaillé

MATCHING
= évalue une correspondance pour une finalité précise

SATELLITE CONSOMMATEUR
= applique ses règles métier, présente l’offre et exécute l’action finale
```

Le Matching ne crée pas l’identité, ne donne pas de mandat, ne facture pas, ne diffuse pas seul une campagne, n’attribue pas une aide, ne décide pas d’une embauche et ne remplace pas la décision humaine compétente.

## Première admission technique

La première version candidate au statut `GO` doit être :

- déterministe ;
- reproductible ;
- explicable ;
- multi-consommateurs ;
- sans apprentissage autonome ;
- sans modèle externe opaque ;
- sans score humain universel ;
- sans export brut des membres d’un segment ;
- sans lecture directe des bases des satellites ;
- sans utilisation d’une donnée hors de sa finalité autorisée.

Les fonctions minimales sont :

```text
QUALIFIER
APPARIER
CLASSER
SEGMENTER
EXPLIQUER
ACTIVER
MESURER
COMPARER DES POLITIQUES
```

La prévision probabiliste et l’apprentissage contrôlé restent hors de ce chantier. Ils nécessiteront une décision distincte, un dossier de risque, des contrats supplémentaires et une nouvelle admission.

## Règles absolues

- aucun matching sans consommateur, finalité, realm et politique active ;
- aucune donnée utilisée uniquement parce qu’elle existe ;
- aucune politique de Matching hors de `CAP-CORE-007` ;
- aucune source hors de `CAP-CORE-006` et de son contrat actif ;
- aucune relation déduite par similitude de noms ;
- aucun critère interdit ou non déclaré ;
- aucun profil universel de personne ou d’organisation ;
- aucun score réutilisable hors de son contexte ;
- aucune transformation d’un score en vérité ou décision obligatoire ;
- aucun membre de segment exporté par défaut ;
- aucune conservation indéfinie des signaux, résultats ou segments ;
- aucun accès automatique d’un realm parent aux membres des realms enfants ;
- aucune activation sans autorisation, obligations et expiration ;
- aucune évolution de politique sensible sans simulation, comparaison et décision formelle ;
- aucune clôture de contestation sans réexamen des données et preuve du résultat.

## Règle d’exécution

Claude Code doit lire les cinq parties et les deux sources fondatrices avant de commencer.

Il traite uniquement `CAP-CORE-021`, crée une branche et un worktree dédiés, ouvre une seule PR, attend toute la CI et s’arrête lorsque la PR est verte et prête à fusionner.

Branche recommandée :

```text
claude/cap-core-021-matching-engine-go
```

Aucun autre chantier de capacité ne doit être commencé dans la même session.
