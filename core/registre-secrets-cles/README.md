# Registre des secrets et clés (CAP-CORE-016)

Registre de gouvernance des secrets et clés du Core — références, versions,
usages, rotations, compromissions. **Ne conserve jamais le matériel secret
lui-même** : aucune colonne de valeur, aucune clé privée, garde explicite
refusant tout dossier portant un champ interdit (`password`, `secret`,
`private_key`…) avant toute écriture. Voir
`docs/capacites/CAP-CORE-016-secrets-keys.md` pour la description complète.

## Ce que ce module possède

- la référence canonique d'un secret ou d'une clé, sa finalité, son
  propriétaire, sa source, son realm, son environnement, sa classification ;
- ses fournisseurs (`secret_fournisseur`) ;
- ses versions (`secret_version`) et leur cycle
  (`PREPARATION → ACTIVE_ECRITURE/LECTURE → DEPRECIEE/SUSPENDUE/REVOQUEE →
  COMPROMISE/DETRUITE`) ;
- ses usages, ses dépendances historiques ;
- ses plans et exécutions de rotation ;
- ses déclarations de compromission ;
- son matériel public (clé publique, certificat, empreinte).

## Ce que ce module ne possède jamais

- un secret en clair, une clé privée, un mot de passe, une phrase secrète ;
- un code de secours, un jeton de session ou fédéré, un challenge WebAuthn
  (ces valeurs restent dans `CAP-CORE-005`) ;
- le contenu d'un fichier `.env`, d'un trousseau GPG privé, d'une clé SSH
  privée.

## Variables dédiées

```text
SECRET_REGISTRY_URL   PostgreSQL en exploitation (obligatoire en production)
SECRET_REGISTRY_PATH  SQLite en local et en CI
```

## Fournisseurs bornés

Trois adaptateurs implémentés : `FournisseurFichier0600`,
`FournisseurCredentialSystemd`, `FournisseurEnvironnementTransition`.
`TROUSSEAU_GPG`, `AGENT_SSH` et `FOURNISSEUR_EXTERNE` sont déclarés dans le
vocabulaire fermé (`PolitiqueSecretsCles::TYPES_FOURNISSEUR`) mais n'ont pas
encore d'adaptateur (`AdaptateurParType::resoudre()`).

Aucun fournisseur n'expose de méthode d'export général : la résolution
passe uniquement par `ResolveurSecret::avecSecret()`, une API PHP interne
qui transmet la valeur au seul callback fourni, jamais à son appelant.

## Commandes d'exploitation

```text
php artisan core:secrets:bootstrap             # POL-SECRETS-CLES-V1 + contrats + inventaire réel
php artisan core:secrets:diagnostiquer         # état du registre, lecture seule
php artisan core:secrets:fournisseurs-verifier # disponibilité générique des fournisseurs
php artisan core:secrets:rotation-simuler      # planifie un plan de rotation en BROUILLON
php artisan core:secrets:rotation-executer     # exécute une étape validée, idempotente
php artisan core:secrets:version-compromettre  # déclaration d'urgence, bloque immédiatement
php artisan core:secrets:version-detruire      # destruction gouvernée, confirmation obligatoire
```

## Tests

```text
php core/registre-secrets-cles/tests/secrets_cles_p3.php
php apps/console-laravel/tests/Integration/secrets_cles_v1_p1.php
ops/core-foundation/tests/secrets_analyse_depot_p1.sh
```
