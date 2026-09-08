# DEC-2026-09-08 — GAMAD devient l’identité active de PRD-GAMAD-005

**Statut :** ADOPTÉE  
**Date :** 2026-09-08  
**Portée :** CAP-CORE-011 — registre des produits

## Décision

Le produit déjà inscrit sous la référence immuable `PRD-GAMAD-005` porte désormais l’identité produit active **GAMAD**.

- `reference` : `PRD-GAMAD-005` — inchangée ;
- `identite_reference` : inchangée ;
- `nom_canonique` : `GAMAD` ;
- `nom_affichage` : `GAMAD` ;
- `type_produit` : inchangé ;
- cycle de vie : inchangé ;
- permissions, authentificateur et délégations existantes : inchangés.

La formulation fonctionnelle complète du produit est **le réseau social d’action GAMAD**. `GAMAD Core` désigne le moteur de confiance et ne constitue pas la marque publique du produit.

## Invariant de migration

Cette décision est une **mutation de métadonnées d’un produit existant**, jamais une nouvelle inscription. La référence `PRD-GAMAD-005` ne peut être réattribuée ni remplacée par un nouvel identifiant pour matérialiser le changement de nom.

L’identité canonique déjà liée au produit est une trace persistante distincte de la fiche CAP-CORE-011. Elle n’est pas réécrite par cette décision : le contrat du registre des identités ne fournit pas d’opération de renommage équivalente et aucune mutation hors contrat n’est autorisée.

## Exécution

L’opération gouvernée est portée par `core:produit-gamad:normaliser`.

La commande :

1. refuse d’inventer `PRD-GAMAD-005` s’il est absent ;
2. lit sa fiche et son état avant mutation ;
3. modifie uniquement `nom_canonique` et `nom_affichage` ;
4. vérifie après mutation que `reference`, `identite_reference`, cycle, type, propriétaire et autorisation de fédération n’ont pas dérivé ;
5. est idempotente si les deux noms valent déjà `GAMAD` ;
6. exige un geste explicite `--force` en environnement `production`.

## Raccordement portail

Le portail `dgafrique-core` doit se présenter à GAMAD Core avec :

- `GAMAD_CORE_PRODUCT_REF=PRD-GAMAD-005` ;
- un authentificateur/secret de raccordement propre à ce produit, conservé hors Git ;
- l’URL versionnée du Core.

La référence produit est un identifiant technique stable. Le secret n’est jamais inscrit dans ce document ni dans le dépôt.
