# CAP-CORE-017 — Fiche de codage

## 1. Mission

Construire un registre persistant et gouverné permettant à GAMAD Core de :

- identifier un risque avant qu’il ne devienne un incident ;
- décrire le scénario redouté, ses causes et ses conséquences ;
- évaluer le risque inhérent et le risque résiduel ;
- attribuer un propriétaire et des responsables de traitement ;
- planifier, suivre et vérifier les mesures de réduction ;
- enregistrer une demande d’exception à une exigence dérogeable ;
- faire approuver ou refuser cette exception par `CAP-CORE-008` ;
- activer une exception seulement lorsque toutes ses conditions sont réunies ;
- faire expirer automatiquement toute exception ;
- fournir aux capacités consommatrices une résolution exacte, datée et vérifiable ;
- rapprocher les incidents réels des risques déjà connus lorsque `CAP-CORE-018` sera livré.

La capacité doit devenir la source canonique pour les risques techniques transversaux et les exceptions gouvernées du Core.

Elle ne doit pas devenir :

- un tableur libre ;
- un moteur d’autorisation parallèle ;
- un mécanisme d’urgence permettant de contourner les politiques ;
- un registre juridique universel ;
- un gestionnaire d’incidents ;
- un espace de stockage de secrets ou de pièces sensibles.

---

## 2. État actuel constaté

Le catalogue de capacités indique :

```text
CAP-CORE-017 — Risks & Exceptions
Code livré : aucun
État réel : NO GO
```

Le dépôt porte déjà des mentions de risques dans :

- les rapports de chantier ;
- les procédures de continuité ;
- les commentaires d’exploitation ;
- les avertissements liés aux transports ou aux sauvegardes ;
- les limites déclarées dans certaines fiches de capacité.

Ces mentions ne forment pas un registre exploitable :

- pas de référence canonique ;
- pas de propriétaire ;
- pas d’évaluation homogène ;
- pas de révision ;
- pas de date de prochaine revue ;
- pas de traitement suivi ;
- pas de seuil d’acceptation ;
- pas d’exception bornée ;
- pas d’expiration automatique ;
- pas de rattachement formel à une décision ;
- pas de preuve signée ;
- pas de résolution consommable par les autres capacités.

Le chantier ne doit pas prétendre migrer automatiquement chaque mot « risque » du dépôt en risque actif. Seuls les éléments explicitement confirmés par un inventaire et un responsable compétent pourront être repris.

---

## 3. Responsabilité souveraine du Core

`CAP-CORE-017` possède :

- la référence canonique d’un risque transverse ;
- ses révisions ;
- ses classifications ;
- son scénario ;
- les actifs, capacités, produits, organisations et realms concernés ;
- ses causes et conséquences ;
- ses évaluations datées ;
- la méthode de cotation utilisée ;
- son niveau inhérent ;
- ses contrôles existants ;
- son niveau résiduel ;
- son propriétaire ;
- son responsable de traitement ;
- ses plans et actions de traitement ;
- son calendrier de revue ;
- son cycle de vie ;
- les demandes d’exception ;
- le périmètre exact de chaque exception ;
- l’exigence visée ;
- les mesures compensatoires ;
- la décision d’approbation, de refus, de renouvellement ou de révocation ;
- la période de validité ;
- la résolution historique d’une exception à une date donnée ;
- les références de preuves ;
- les projections opérationnelles minimales.

---

## 4. Ce qui reste aux autres capacités

### CAP-CORE-004 — Authorization

Décide si l’action demandée est permise au moment de l’appel.

Une exception active peut être un fait d’entrée pour une politique qui l’autorise explicitement. Elle ne produit jamais elle-même une réponse `PERMIS`.

### CAP-CORE-007 — Rules / Policies Registry

Déclare :

- quelles exigences existent ;
- lesquelles sont dérogeables ;
- lesquelles sont non dérogeables ;
- les conditions minimales d’exception ;
- les actions d’autorisation requises ;
- les seuils ou politiques de tolérance.

`CAP-CORE-017` ne modifie aucune règle active.

### CAP-CORE-008 — Decisions Registry

Conserve l’acte formel qui :

- accepte un risque résiduel ;
- approuve ou refuse une exception ;
- renouvelle une exception ;
- révoque une exception ;
- impose un traitement ;
- accepte une clôture.

`CAP-CORE-017` vérifie et rattache cette décision ; il ne se substitue pas à elle.

### CAP-CORE-013 — Common Audit

Conserve les traces des opérations réalisées sur le registre.

### CAP-CORE-014 — Event Journal

Diffuse les changements minimaux autorisés : risque créé, niveau changé, exception activée, expiration proche, exception expirée, mesure en retard.

### CAP-CORE-015 — Integrity Proofs

Signe et vérifie les paquets de risque, évaluations et exceptions lorsque la politique l’exige.

### CAP-CORE-016 — Secrets & Keys

Fournit les opérations cryptographiques et références de clés ; aucune clé privée n’entre dans le registre des risques.

### CAP-CORE-018 — Incidents

Gérera les événements réellement survenus, les enquêtes et la clôture d’incident.

Un incident pourra révéler :

- un risque déjà connu qui s’est matérialisé ;
- un risque mal évalué ;
- un risque entièrement nouveau ;
- une exception ayant contribué à l’incident.

### Satellites

Chaque satellite conserve ses risques purement métier et locaux.

Le Core ne centralise que les risques :

- partagés par plusieurs capacités ou produits ;
- touchant une responsabilité souveraine ;
- nécessaires à une décision du Core ;
- liés à une exception de contrôle commun ;
- requis pour la continuité ou la sécurité de l’écosystème.

---

## 5. Définitions opérationnelles

### Risque

Combinaison d’un scénario possible, de sa vraisemblance et de ses impacts potentiels sur un périmètre défini.

Un risque doit être formulé de manière testable :

```text
Étant donné [cause ou menace],
il est possible que [événement],
ce qui pourrait produire [conséquences]
sur [actifs, produits, capacités, organisations ou realms].
```

### Risque inhérent

Niveau évalué avant prise en compte des contrôles et traitements existants.

### Risque résiduel

Niveau évalué après prise en compte des contrôles réellement en place et vérifiés.

### Traitement

Réponse organisée au risque :

```text
EVITER
REDUIRE
TRANSFERER
ACCEPTER
SURVEILLER
```

`ACCEPTER` exige une décision formelle lorsque le niveau dépasse un seuil défini.

### Exception

Autorisation temporaire et bornée de ne pas satisfaire entièrement une exigence dérogeable, sous conditions et mesures compensatoires.

Une exception ne signifie jamais :

- suppression de l’exigence ;
- modification de la politique ;
- permission globale ;
- absence de contrôle ;
- dispense permanente ;
- droit de réutilisation dans un autre périmètre.

### Mesure compensatoire

Contrôle alternatif, temporaire et vérifiable qui réduit le risque créé par l’exception.

### Tolérance

Seuil défini par politique au-delà duquel une acceptation explicite, un traitement supplémentaire ou un refus est requis.

---

## 6. Architecture attendue

Créer :

```text
core/registre-risques/
├── composer.json
├── README.md
├── src/
│   ├── Magasin.php
│   ├── SchemaRisques.php
│   ├── RegistreRisques.php
│   ├── RegistreExceptions.php
│   ├── EvaluateurRisques.php
│   ├── ResolveurExceptions.php
│   ├── PolitiqueRisques.php
│   ├── CanonicalisateurRisque.php
│   └── Exceptions/
├── resources/
│   ├── bootstrap-risques-v1.json
│   └── schemas/
└── tests/
    └── risques_exceptions_p3.php
```

Ajouter les couches applicatives Laravel :

```text
apps/console-laravel/app/Application/Risques/
apps/console-laravel/app/Http/Controllers/Api/V1/RisqueController.php
apps/console-laravel/app/Http/Controllers/Api/V1/ExceptionController.php
apps/console-laravel/app/Http/Controllers/RisqueConsoleController.php
apps/console-laravel/app/Console/Commands/BootstrapRisquesCommand.php
apps/console-laravel/app/Console/Commands/VerifierEcheancesRisquesCommand.php
```

---

## 7. Magasin dédié

Variables attendues :

```text
RISK_REGISTRY_URL=postgresql://...
RISK_REGISTRY_PATH=/chemin/registre-risques.sqlite
GAMAD_RISKS_DRIVER=pgsql
```

Règles :

- PostgreSQL obligatoire en production ;
- SQLite uniquement pour développement et CI ;
- aucun fallback silencieux ;
- aucune lecture dans l’index reconstructible ;
- aucun partage de tables avec le journal, les décisions ou les politiques ;
- migrations idempotentes ;
- contraintes cohérentes sur PostgreSQL et SQLite ;
- magasin ajouté aux scripts de sauvegarde et restauration après livraison.

---

## 8. Principes de sécurité

### Refus par défaut

Une exception absente, expirée, révoquée, non encore active ou non vérifiable ne permet rien.

### Références exactes

L’exigence ciblée doit être une référence canonique issue de :

- `CAP-CORE-007` pour une règle ou un contrôle ;
- `CAP-CORE-009` pour une obligation contractuelle ;
- `CAP-CORE-010` pour un terme ou code partagé ;
- la capacité propriétaire pour une exigence enregistrée et contractuelle.

Aucun ciblage par sous-chaîne, expression approchée ou texte libre.

### Périmètre minimal

Une exception doit préciser au minimum :

- sujet ou consommateur ;
- organisation ;
- realm ;
- produit ou capacité ;
- environnement ;
- ressource ;
- opération ;
- exigence ;
- date de début ;
- date d’expiration.

Les valeurs génériques comme `*`, `TOUS`, `GLOBAL` ou `ANY` sont interdites par défaut.

### Non-rétroactivité

Une exception ne peut jamais rendre conforme une opération déjà effectuée.

### Non-propagation

Une exception accordée à un produit ne s’étend pas :

- à ses sous-produits ;
- à ses partenaires ;
- à ses autres environnements ;
- à ses autres realms ;
- à une autre version de contrat ;
- à une autre opération.

### Non-dérogation

Le système doit permettre de marquer certaines exigences comme non dérogeables.

Exemples de bornes à confirmer dans les politiques actives :

- exposition d’une clé privée ;
- stockage d’un secret en clair ;
- suppression du refus par défaut ;
- accès direct à la base privée d’un autre satellite ;
- falsification ou réécriture d’une preuve ;
- réutilisation d’un jeton fédéré expiré ;
- contournement d’une séparation de realm obligatoire.

Le bootstrap ne doit pas inventer cette liste : il doit reprendre les exigences réellement actives et explicitement déclarées non dérogeables.

---

## 9. Préconditions du chantier

Claude Code commence par :

1. mettre `main` à jour ;
2. vérifier les statuts réels de tous les prérequis ;
3. confirmer que la PR documentaire contenant cette note est fusionnée ou accessible ;
4. inventorier les références d’exigences déjà actives ;
5. inventorier les risques explicitement connus dans les rapports et procédures ;
6. distinguer les simples avertissements des risques à enregistrer ;
7. identifier les consommateurs réels ;
8. produire un compte rendu d’audit initial ;
9. arrêter le chantier si `CAP-CORE-008` ou `CAP-CORE-015` n’est pas réellement `GO`.

Branche cible :

```text
claude/cap-core-017-risks-exceptions-go
```

Un seul worktree, une seule capacité, une seule PR.

---

## 10. Résultat attendu

À la fin du chantier :

- le registre est persistant ;
- les risques ont des références, propriétaires, évaluations et revues ;
- les traitements sont suivis ;
- les exceptions sont précises, temporaires et prouvables ;
- aucune exception ne s’active sans décision valide ;
- les expirations sont automatiques ;
- les autres capacités peuvent résoudre une exception exacte à une date donnée ;
- aucune exception ne donne directement une permission ;
- la console et l’API utilisent les mêmes services ;
- les opérations sensibles sont auditées ;
- les événements minimaux sont publiés ;
- les sauvegardes et restaurations sont exercées ;
- toute la CI est verte ;
- la fiche finale `docs/capacites/CAP-CORE-017-risks-exceptions.md` décrit uniquement le code réellement livré ;
- le catalogue passe à `GO` seulement après réussite complète.
