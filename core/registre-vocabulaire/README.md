# CAP-CORE-010 — Registre du vocabulaire canonique

Avant ce module, les valeurs closes du Core (états, types, rôles, niveaux)
étaient dispersées entre des contraintes SQL `CHECK` et des constantes PHP
propres à chaque capacité, sans définition partagée, sans libellé localisé
gouverné et sans mécanisme capable de détecter qu'un même code portait un
sens différent — ou qu'un contrat consommait un code que sa capacité venait
de retirer. Ce module donne à ces valeurs une fiche persistante, versionnée
et gouvernée.

## Ce que ce module possède

- la référence du vocabulaire, son namespace unique, son domaine, sa portée
  (`CORE`, `ECOSYSTEME`, `CONTRAT`, `CAPACITE`, `PRODUIT_PARTAGE`) ;
- ses versions immuables une fois soumises (`X.Y.Z`, empreinte figée), en
  cycle d'ajout seul (`BROUILLON → EN_VALIDATION → ACTIVE →
  DEPRECIEE/REMPLACEE → RETIREE`), au plus une version active à la fois ;
- ses termes : code stable (`MAJUSCULES_SOULIGNEES`), définition
  obligatoire, type sémantique dans une liste close ;
- les libellés localisés de chaque terme, les alias explicites (anciens
  codes, abréviations, codes externes), les relations sémantiques
  acycliques et les correspondances externes qualifiées ;
- les usages déclarés d'un terme par une capacité, un contrat, une
  politique ou un produit — base du calcul d'impact d'un retrait ;
- les analyses de compatibilité structurelle, les projections dérivées
  (JSON, constantes PHP, contrainte SQL) et les conformités enregistrées par
  consommateur.

## Ce que ce module ne possède pas

Les identités, les produits, les sources, les politiques, les contrats, les
décisions individuelles, les données métier réelles, les secrets, les clés,
les règles exécutables. Ajouter un terme au vocabulaire ne crée jamais
automatiquement une permission (CAP-CORE-004), une transition métier ou un
comportement nouveau chez un consommateur qui ne le supporte pas encore :
`ValidateurTerme` refuse toute définition ressemblant à une expression
exécutable ou à un secret réel, `RegistreVocabulaire` refuse toute écriture
utilisant un terme inconnu, et aucune résolution floue ni alias humain
n'intervient dans une décision de sécurité.

## Ce module ne décide rien lui-même

Chaque commande gouvernée exige `politique`, `producteur`/`acteur`, `source`
et `preuve` dans son dossier, comme les autres registres du Core. La
décision d'autoriser une commande donnée vient de CAP-CORE-004, dans la
couche applicative (`App\Application\Vocabulaire\AccesVocabulaire`) ; ce
module conserve ses propres bornes structurelles — codes uniques, alias non
ambigus, relations acycliques, activation impossible sans analyse, sans
projection et sans conformité `CONFORME`.

## Magasin

`Magasin::connecter()` ouvre `VOCABULARY_REGISTRY_URL` (PostgreSQL,
obligatoire en production) ou `VOCABULARY_REGISTRY_PATH` (SQLite, local et
CI). Aucun repli silencieux vers SQLite en production. `MAGASIN_URL`,
`IDENTITY_REGISTRY_URL` et les autres variables des huit autres magasins
appartiennent à d'autres registres et ne sont jamais consultées ici.
`php artisan core:fondation:migrer` applique la migration ; la readiness
(`/api/v1/health/ready`) la vérifie.

## Bootstrap des vocabulaires existants

`php artisan core:vocabulaire:bootstrap` reprend, sans en inventer, les
vingt-quatre vocabulaires (cent trente-deux termes) réellement trouvés dans
le code fusionné du Core — chacun relié par `source_reference` à la
constante PHP réelle d'où il vient (`PolitiqueInscription`,
`PolitiquePolitiques`, `PolitiqueSources`, `PolitiqueContrats`,
`PolitiqueProduits`). La commande est idempotente.

## Limite assumée

Ce chantier décrit le vocabulaire ; il ne migre pas les capacités
consommatrices (CAP-CORE-001, 006, 007, 009, 011) vers une lecture depuis ce
registre plutôt que depuis leurs propres constantes. Leurs contraintes
`CHECK` et classes `Politique*` restent la source d'application réelle ;
`vocabulaire_projection` ne fait qu'exposer une projection dérivée à
comparer manuellement. C'est un manque documenté, pas un oubli : voir
`docs/capacites/CAP-CORE-010-canonical-vocabulary.md`.

## Tests

```text
php core/registre-vocabulaire/tests/vocabulaire_p3.php
```
