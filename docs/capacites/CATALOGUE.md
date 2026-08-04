# Catalogue initial des capacités GAMAD Core

Ce catalogue est la carte de travail du Core. Les états sont établis par
inspection du code, des tests et de la CI ; ils ne sont jamais déduits d’un
document.

Le catalogue n’utilise désormais que deux statuts : `GO` et `NO GO`. `GO`
signifie que le comportement nécessaire à la production est codé, éprouvé et
raccordé à l’exploitation — pas seulement documenté. `NO GO` couvre tout le
reste, y compris ce qui était auparavant nuancé en `ABSENT`, `DÉMONSTRATIF`,
`PARTIEL`, `IMPLÉMENTÉ`, `EXPLOITÉ`, `CONTRADICTOIRE` ou `À VÉRIFIER`. Cette
simplification délibérée réduit l’information disponible dans ce tableau ;
elle ne réduit rien du code lui-même. La colonne « Code livré » reste la
source la plus fine pour qui veut savoir ce qui existe réellement derrière un
`NO GO`.

| Référence | Capacité | Finalité | Code livré | État réel |
|---|---|---|---|---|
| CAP-CORE-001 | Identity Registry | Reconnaître les identités canoniques et leurs relations minimales | `core/registre-identites`, console et API v1 | `GO` — garde et intégrations vertes |
| CAP-CORE-002 | Organizations Registry | Gérer les organisations et leur structure commune | `core/registre-organisations`, bootstrap idempotent, API v1 `/organisations*`, écran Organisations, `Ctr01::resoudreLiensOrganisations()` délégable à CAP-CORE-002, représentation vérifiée via CAP-CORE-003 | `GO` — registre persistant gouverné et testé (63 épreuves SQLite/HTTP/console, exercice PostgreSQL réel local le 3 août 2026, CI GitHub verte sur `claude/cap-core-002-organizations-registry-go` le 3 août 2026) ; réserve non bloquante : aucun contrat `CAP-CORE-009` encore enregistré pour ce registre, à écrire dans un chantier ultérieur — voir `docs/capacites/CAP-CORE-002-organizations-registry.md` |
| CAP-CORE-003 | Authorities & Mandates | Résoudre les fonctions, titulaires, mandats et délégations | `core/registre-autorites` | `GO` — résolution datée éprouvée |
| CAP-CORE-004 | Authorization | Décider si une action commune est permise ou refusée | `core/registre-autorisation` | `GO` — refus par défaut éprouvé |
| CAP-CORE-005 | Authentication & Access | Authentifier, ouvrir et révoquer les sessions | `core/registre-acces`, passkeys, écran Mon accès | `GO` — sessions, révocation, WebAuthn, codes de secours à usage unique et garde du dernier moyen d’accès, tous éprouvés |
| CAP-CORE-006 | Sources Registry | Référencer, gouverner et faire vivre le cycle des sources du Core | `core/registre-sources`, bootstrap idempotent, API v1 `/sources*`, écran Sources, `CTR-15` découplé du registre des normes | `GO` — registre persistant gouverné ; garde SQLite (38 épreuves) et exercice PostgreSQL réel verts en local le 2 août 2026 |
| CAP-CORE-007 | Rules / Policies Registry | Gérer les politiques techniques versionnées | `core/registre-politiques`, bootstrap idempotent (huit politiques/quarante-deux règles reprises fidèlement), `CTR-03` rebranché exclusivement sur ce magasin, correspondance exacte après normalisation, API v1 `/politiques*`, écran Politiques | `GO` — registre persistant gouverné, cycle de vie complet, simulation obligatoire avant activation, remplacement atomique d'une version active ; garde et intégrations HTTP/console vertes en local le 2 août 2026 |
| CAP-CORE-008 | Decisions Registry | Tracer les décisions opérationnelles utiles | aucun ; traces dans le journal opérationnel | `NO GO` |
| CAP-CORE-009 | Contracts Registry | Décrire et versionner les contrats intercapacités | `core/registre-contrats`, bootstrap idempotent (treize contrats déjà exploités, six internes et sept HTTP externes), analyse de compatibilité structurelle, API v1 `/contrats*`, écran Contrats | `GO` — registre persistant gouverné ; garde SQLite (51 épreuves), 25 intégrations HTTP/console et exercice PostgreSQL réel (huit magasins) verts en local le 2 août 2026 |
| CAP-CORE-010 | Canonical Vocabulary | Partager des termes et codes stables entre produits | `core/registre-vocabulaire`, bootstrap idempotent (vingt-quatre vocabulaires, cent trente-deux termes repris fidèlement depuis les constantes réelles de CAP-CORE-001/006/007/011), analyse de compatibilité structurelle, projections dérivées, API v1 `/vocabulaires*` et `/termes*`, écran Vocabulaires | `GO` — registre persistant gouverné ; garde SQLite (80 épreuves), 3 intégrations HTTP/console/dérive et exercice PostgreSQL réel (neuf magasins) verts en local le 3 août 2026 |
| CAP-CORE-011 | Products Registry | Référencer, gouverner et faire vivre le cycle des produits du Core | `core/registre-produits`, bootstrap idempotent, API v1 `/produits*`, écran Produits, `CAP-CORE-022` raccordé au registre | `GO` — registre persistant gouverné ; CI complète (11 contrôles GitHub, PR #58) verte sur `claude/cap-core-011-products-registry-go` le 2 août 2026 |
| CAP-CORE-012 | Realms Registry | Isoler les périmètres techniques et institutionnels | `core/registre-realms`, bootstrap idempotent (vocabulaire, politique et contrat auto-établis), API v1 `/realms*`, écran Realms, `EvaluateurPortee` déterministe | `GO` — registre persistant gouverné et testé (87 épreuves SQLite/HTTP/console/contrats, exercice PostgreSQL réel local le 3 août 2026 sur onze magasins) ; CI GitHub à confirmer sur `claude/cap-core-012-realms-registry-go` — voir `docs/capacites/CAP-CORE-012-realms-registry.md` |
| CAP-CORE-013 | Common Audit | Conserver les traces transversales autorisées | `core/journal-operationnel` | `GO` — chaîne append-only vérifiée, trigger PostgreSQL |
| CAP-CORE-014 | Event Journal | Publier et consommer les événements communs | `core/journal-evenements`, `core/evenements-sortants`, bootstrap idempotent (`POL-EVENEMENTS-V1`, huit contrats techniques, huit vocabulaires), API v1 `/evenements*` `/abonnements*` `/rejeux*` `/lettres-mortes*`, écran Événements | `GO` — journal persistant gouverné, chaîné, testé (115 épreuves SQLite/HTTP/console/outbox, exercice PostgreSQL réel local le 4 août 2026 sur douze magasins) ; un seul producteur réel raccordé (CAP-CORE-011) et aucun satellite consommateur réel — voir `docs/capacites/CAP-CORE-014-event-journal.md` |
| CAP-CORE-015 | Integrity Proofs | Vérifier les empreintes et preuves techniques | empreinte de baseline, chaîne du journal | `NO GO` — pas de service d’empreintes général |
| CAP-CORE-016 | Secrets & Keys | Gérer les références, rotations et usages des secrets | `core/registre-secrets-cles`, bootstrap idempotent (`POL-SECRETS-CLES-V1`, neuf contrats techniques, inventaire réel de dix-sept références sans valeur), API v1 `/secrets-cles*` `/fournisseurs-secrets*` `/rotations-secrets*`, écran Secrets & clés, trois fournisseurs bornés (fichier 0600, credential systemd, transition) | `GO` — registre de gouvernance persistant, testé (88 épreuves SQLite/HTTP, exercice PostgreSQL réel local le 4 août 2026 sur treize magasins) ; jamais de valeur secrète stockée ni exposée ; rotation `APP_KEY` non exécutée en production (mécanisme éprouvé sur un secret pilote), trois fournisseurs (GPG/SSH/externe) non livrés, aucun consommateur réel migré — voir `docs/capacites/CAP-CORE-016-secrets-keys.md` |
| CAP-CORE-017 | Risks & Exceptions | Enregistrer et suivre les risques et exceptions techniques | aucun | `NO GO` |
| CAP-CORE-018 | Incidents | Déclarer, suivre et clôturer les incidents | aucun | `NO GO` |
| CAP-CORE-019 | Backup & Restore | Sauvegarder, restaurer et prouver la continuité | `ops/core-foundation`, écran Continuité | `GO` — copie chiffrée hors machine sur destination réelle, TLS épinglé, et exercice de restauration complet exécuté depuis cette copie le 1er août 2026 |
| CAP-CORE-020 | Directory & Atlas | Produire un annuaire opérationnel des capacités et produits | aucun | `NO GO` — l’ancien module dérivait d’un corpus supprimé |
| CAP-CORE-021 | Matching Engine | Produire des correspondances contextualisées entre besoins, offres et signaux | aucun | `NO GO` |
| CAP-CORE-022 | Satellite Federation | Relier le Compte GAMAD, le Portail et les comptes produit locaux | `core/registre-federation`, API v1 `/produits*` | `GO` — parcours pilote GamaDrive éprouvé ; aucun satellite réel raccordé |

Un état `NO GO` est une information utile : il nomme un chantier ouvert plutôt
qu’il ne dissimule un manque derrière un module qui ne rendait aucun service.

## Regroupement fonctionnel

### Identité et accès

- CAP-CORE-001 — Identity Registry
- CAP-CORE-002 — Organizations Registry
- CAP-CORE-003 — Authorities & Mandates
- CAP-CORE-004 — Authorization
- CAP-CORE-005 — Authentication & Access

### Référentiels et contrats

- CAP-CORE-006 — Sources Registry
- CAP-CORE-007 — Rules / Policies Registry
- CAP-CORE-008 — Decisions Registry
- CAP-CORE-009 — Contracts Registry
- CAP-CORE-010 — Canonical Vocabulary
- CAP-CORE-011 — Products Registry
- CAP-CORE-012 — Realms Registry

### Traçabilité et sécurité

- CAP-CORE-013 — Common Audit
- CAP-CORE-014 — Event Journal
- CAP-CORE-015 — Integrity Proofs
- CAP-CORE-016 — Secrets & Keys
- CAP-CORE-017 — Risks & Exceptions
- CAP-CORE-018 — Incidents
- CAP-CORE-019 — Backup & Restore

### Écosystème

- CAP-CORE-020 — Directory & Atlas
- CAP-CORE-021 — Matching Engine
- CAP-CORE-022 — Satellite Federation

## Règles de construction

Pour chaque capacité :

1. inventorier les contrats et consommateurs réels ;
2. définir la responsabilité et la frontière Core / satellite ;
3. créer une fiche individuelle fondée sur le code ;
4. porter les données nécessaires dans une source technique versionnée ;
5. écrire une garde de comportement avec sa contre-épreuve ;
6. raccorder la garde à `core-operational-tests.yml` ;
7. mettre l’état de ce catalogue à jour d’après le résultat réel.

## Priorité proposée

Le chemin critique est livré. La première tranche de CAP-CORE-022 l’est aussi :
le Core sait ouvrir une identité sur GamaDrive, borner le jeton, le révoquer et
le prouver. CAP-CORE-011 est livré à son tour : le catalogue fédéré que
CAP-CORE-022 sert n’est plus dérivé d’un marqueur de texte, il vient d’un
registre persistant et gouverné. CAP-CORE-006 suit le même chemin : la
provenance des informations n’est plus une ligne de lecture seule dépendant
du registre des normes, mais une fiche persistante et gouvernée, avec cycle
de vie, révisions, vérifications expirables, finalités bornées et lignée
acyclique — et la dépendance entre CAP-CORE-007 et CAP-CORE-006 est enfin
dans le bon sens. CAP-CORE-007 est livré à son tour : les politiques et
règles que `CTR-03` évalue ne sont plus des lignes de l’index documentaire
modifiées en éditant un fichier, mais un registre persistant et gouverné,
versionné, avec simulation obligatoire avant toute activation et
remplacement atomique d’une version active ; les huit politiques déjà
exploitées (`POL-SOURCES-V1`, `POL-PRODUITS-V1`,
`POL-FEDERATION-SATELLITES-V1` comprises) y ont été reprises fidèlement, et
`politique`/`regle` ont quitté l’index reconstructible. CAP-CORE-009 est
livré à son tour : les échanges du Core ne sont plus dispersés entre les
classes `CTR-*`, les contrôleurs, les routes et `openapi/core-v1.yaml` sans
registre commun, mais un registre persistant et gouverné, avec parties,
opérations, schémas, erreurs et obligations par version, une analyse de
compatibilité structurelle qui détecte réellement une rupture (opération
supprimée, champ obligatoire ajouté, méthode ou chemin modifiés, consommateur
retiré…), une conformité et un plan de migration obligatoires avant toute
activation d’une rupture ; treize contrats déjà exploités (les six contrats
internes prioritaires `CTR-01`/`CTR-02`/`CTR-03`/`CTR-04`/`CTR-15`/`CTR-16`
et sept contrats HTTP externes, fédération GamaDrive comprise) y ont été
repris fidèlement, chacun relié à du code ou une route réels. CAP-CORE-010
est livré à son tour : les valeurs closes du Core ne sont plus dispersées,
sans définition commune, entre des contraintes SQL `CHECK` et des constantes
PHP propres à chaque capacité, mais un registre persistant et gouverné, avec
codes stables, définitions, libellés localisés, alias explicites, relations
sémantiques acycliques, correspondances externes qualifiées, usages déclarés
et une analyse de compatibilité qui distingue rupture, adaptation requise et
changement compatible ; vingt-quatre vocabulaires déjà exploités (cent
trente-deux termes, chacun relié à la constante PHP réelle d’une capacité
`GO`) y ont été repris fidèlement. Ce chantier décrit le vocabulaire ; il ne
migre pas encore les capacités consommatrices vers une lecture depuis ce
registre. CAP-CORE-002 est livrée à son tour : les organisations possèdent
désormais une fiche persistante, un cycle de vie, une structure hiérarchique
acyclique, des relations interorganisationnelles et des affiliations dont la
représentation opposable dépend d’un mandat réellement vérifié par
CAP-CORE-003. CAP-CORE-012 est livrée à son tour : les realms possèdent une
fiche persistante, une hiérarchie acyclique `PARENT_DE`, des périmètres
canoniques, des rattachements gouvernés d’organisations (CAP-CORE-002), de
produits (CAP-CORE-011) et de contrats (CAP-CORE-009), des franchissements
explicites à refus par défaut et prioritaire, et un moteur déterministe de
contrôle de portée qui ne constitue jamais une autorisation — `CAP-CORE-004`
reste seul décideur. CAP-CORE-014 est livrée à son tour : les événements
communs ne sont plus une extension du journal d’audit privé de
CAP-CORE-013, mais un registre persistant et gouverné, avec enveloppe
chaînée par empreintes, outbox transactionnelle dans chaque magasin
producteur, abonnements filtrés par realm à refus par défaut, livraison PULL
par bail avec accusés idempotents et lettres mortes, et rejeu borné
reprenable par curseur. Un seul producteur réel est raccordé (CAP-CORE-011)
et aucun satellite consommateur réel ne l’est encore — la preuve de
consommation repose sur le parcours HTTP de conformité livré dans ce
chantier, pas sur une intégration de production. CAP-CORE-016 est livrée à
son tour : les secrets et clés du Core possèdent désormais un registre de
gouvernance persistant — références, versions à cycle complet, usages,
plans et exécutions de rotation idempotentes, compromissions bloquantes —
sans jamais conserver le matériel secret lui-même. Trois fournisseurs
bornés (fichier `0600`, credential systemd, variable de transition) sur six
types déclarés, dix-sept références réelles inventoriées sans valeur ;
aucune rotation `APP_KEY`, PostgreSQL, GPG, SSH ou FTP n’a encore été
exécutée en production, et aucun consommateur réel (comme la sauvegarde
hors machine) n’est encore migré vers ce registre — c’était un préalable
obligatoire avant tout autre chantier, désormais posé. Les chantiers
ouverts, par ordre d’utilité :

```text
intégration réelle de GamaDrive V2 sur CAP-CORE-022
→ premier satellite ou producteur réel raccordé à CAP-CORE-014
→ premier consommateur réel raccordé à CAP-CORE-016 (par exemple la copie
  hors machine CAP-CORE-019) et rotation APP_KEY réellement exécutée
→ CAP-CORE-015 Integrity Proofs, désormais consommateur naturel des clés
  gouvernées par CAP-CORE-016
→ CAP-CORE-021 Matching, désormais consommateur naturel de CAP-CORE-006,
  CAP-CORE-009, CAP-CORE-010 et CAP-CORE-012
```

Le second chemin d’accès est livré : codes de secours à usage unique,
attachement d’une passkey sans ligne de commande, et refus de retirer le
dernier moyen d’accès durable. Reste à l’exercer — engendrer les codes depuis
l’écran « Mon accès » et enrôler une passkey. Tant que ce n’est pas fait, le
magasin d’accès ne contient toujours qu’un seul authentificateur, constaté le
1er août 2026 par l’exercice de restauration.

Cette proposition reste une proposition : elle se confirme produit par produit.
Tant qu’aucun satellite ne consomme la fédération, CAP-CORE-022 reste `GO` au
sens technique de ce catalogue, mais n’est pas encore éprouvée en exploitation
réelle.

La continuité est passée devant le reste, et elle est faite. Le 1er août 2026,
l’inspection du serveur montrait huit lots de sauvegarde, tous sur le disque
qu’ils protégeaient, et aucune copie ailleurs. Le même jour, une copie chiffrée
est partie vers une destination distincte, en a été récupérée, et a été
rechargée sur quatre bases isolées — sept identités dérivées, un
authentificateur, vingt-deux événements relus. C’est cette exécution, et non le
code qui la permet, qui fait passer CAP-CORE-019 à `GO`.