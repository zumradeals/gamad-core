# CAP-CORE-018 — Incidents

Note préparatoire du chantier de passage de `NO GO` à `GO`.

## Ordre de lecture

1. `01-fiche-de-codage.md` — mission, prérequis, frontières, architecture et cas d’usage.
2. `02-modele-incidents-et-cycle.md` — magasin persistant, signaux, impacts, rôles, chronologie, actions et clôture.
3. `03-commandes-triage-reponse-retablissement.md` — déclaration, qualification, confinement, éradication, rétablissement, communications et revue.
4. `04-api-console-contrats-tests.md` — API, console, politiques, contrats, audit, événements et épreuves.
5. `05-exploitation-criteres-go.md` — exploitation, sécurité, sauvegarde, métriques, CI, critères GO et rapport final.

## Prérequis de lancement

```text
CAP-CORE-010 — GO et fusionnée
CAP-CORE-002 — GO et fusionnée
CAP-CORE-012 — GO et fusionnée
CAP-CORE-014 — GO et fusionnée
CAP-CORE-016 — GO et fusionnée
CAP-CORE-015 — GO et fusionnée
CAP-CORE-008 — GO et fusionnée
CAP-CORE-017 — GO et fusionnée
```

Les capacités déjà `GO`, notamment `CAP-CORE-001`, `CAP-CORE-003`, `CAP-CORE-004`, `CAP-CORE-005`, `CAP-CORE-006`, `CAP-CORE-007`, `CAP-CORE-009`, `CAP-CORE-011`, `CAP-CORE-013`, `CAP-CORE-019` et `CAP-CORE-022`, doivent rester vertes pendant tout le chantier.

## Séparation essentielle

```text
SIGNAL
= fait ou anomalie susceptible de nécessiter une analyse

RISQUE
= événement possible et conséquences potentielles

INCIDENT
= événement réellement survenu ou compromission confirmée

PROBLÈME TECHNIQUE
= cause durable à corriger, même après rétablissement
```

Un signal ne devient jamais automatiquement un incident confirmé.

Un incident ne constitue jamais automatiquement une preuve de faute, d’attaque, de responsabilité ou de violation juridique.

`CAP-CORE-018` coordonne la réponse. Il ne remplace pas :

- `CAP-CORE-005` pour révoquer des sessions ;
- `CAP-CORE-011` pour suspendre un produit ;
- `CAP-CORE-014` pour transporter des événements ;
- `CAP-CORE-016` pour révoquer ou tourner une clé ;
- `CAP-CORE-019` pour restaurer les données ;
- la capacité métier propriétaire pour exécuter son propre changement.

## Règles absolues

- aucune clôture sans preuve de rétablissement et revue minimale ;
- aucune action d’urgence ne contourne `CAP-CORE-004` ;
- aucune décision formelle n’est antidatée ;
- aucune preuve brute, clé privée, mot de passe, jeton ou donnée personnelle complète dans le registre ;
- aucune suppression ou réécriture de la chronologie ;
- aucun changement silencieux de sévérité ;
- aucun accès automatique d’un realm parent aux incidents des realms enfants ;
- aucune communication publique automatique ;
- aucun incident restauré depuis une sauvegarde ne peut écraser un état plus récent sans rapprochement explicite.

## Règle d’exécution

Claude Code doit lire les cinq parties avant de commencer, traiter uniquement `CAP-CORE-018`, ouvrir une PR dédiée et s’arrêter lorsque cette PR est verte et prête à fusionner.
