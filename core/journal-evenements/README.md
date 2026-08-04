# Journal des événements (CAP-CORE-014)

Registre central, persistant et gouverné des événements communs publiés par
les producteurs du Core et consommés par des abonnés authentifiés — Core ou
satellites. Physiquement distinct de `core/journal-operationnel`
(`CAP-CORE-013`), qui reste le journal d'audit privé de l'exploitation.
`CAP-CORE-013` et `CAP-CORE-014` ne se fusionnent jamais.

## Ce que ce module possède

- l'enveloppe canonique de l'événement commun (`evenement_commun`) et sa
  charge séparée, expirante (`evenement_charge`) ;
- la chaîne d'empreintes SHA-256 en ajout seul, vérifiable par
  `RegistreEvenements::verifierChaine()` ;
- les reçus de publication idempotents (`recu_publication`) ;
- les abonnements gouvernés, leur cycle, leurs filtres par type/producteur/
  realm (`abonnement_evenement` et tables associées) ;
- la livraison PULL par bail, avec accusés idempotents, tentatives, lettres
  mortes et rejeux bornés (`livraison_evenement`, `tentative_livraison`,
  `curseur_abonnement`, `demande_rejeu`, `lettre_morte_evenement`).

## Ce que ce module ne possède pas

- les outboxes productrices, qui vivent dans chaque magasin producteur
  (`core/evenements-sortants`, table `evenement_sortant`), jamais ici ;
- le journal d'audit privé de l'exploitation (`core/journal-operationnel`,
  `CAP-CORE-013`) ;
- une réputation humaine universelle, une facturation ou l'exécution des
  règles économiques des satellites.

## Variables dédiées

```text
EVENT_JOURNAL_URL   PostgreSQL en exploitation (obligatoire en production)
EVENT_JOURNAL_PATH  SQLite en local et en CI
```

## Commandes d'exploitation

```text
php artisan core:evenements:bootstrap        # POL-EVENEMENTS-V1 + contrats techniques
php artisan core:evenements:publier          # relaie les outboxes productrices
php artisan core:evenements:liberer-baux     # libère les baux de livraison expirés
php artisan core:evenements:traiter-rejeux   # exécute les rejeux validés, reprenable
php artisan core:evenements:purger-charges   # purge gouvernée, simulation par défaut
php artisan core:evenements:verifier         # vérifie la chaîne en lecture seule
php artisan core:evenements:diagnostiquer    # rapport structuré, aucune réparation
php artisan core:evenements:rapprocher       # détecte les écarts avec les outboxes
```

Voir `ops/core-foundation/README.md` pour les cinq unités systemd
correspondantes et `docs/capacites/CAP-CORE-014-event-journal.md` pour la
description complète du code livré.

## Tests

```text
php core/journal-evenements/tests/evenements_p3.php
php core/evenements-sortants/tests/outbox_p3.php
```
