# CAP-CORE-018 — Fiche de codage

## 1. Mission

Construire un registre persistant et exploitable permettant à GAMAD Core de :

- recevoir des signaux manuels ou techniques ;
- distinguer une anomalie, une alerte, un incident confirmé et un doublon ;
- qualifier la sévérité et l’étendue ;
- désigner une organisation responsable et une équipe de réponse ;
- coordonner le confinement, l’éradication, le rétablissement et la surveillance ;
- conserver une chronologie append-only ;
- référencer les preuves sans recopier les données sensibles ;
- lier l’incident aux risques, exceptions, décisions, produits, realms et contrats concernés ;
- suivre les actions exécutées par les capacités propriétaires ;
- informer les publics autorisés avec des communications minimisées ;
- clôturer seulement après rétablissement, vérification et revue ;
- produire des leçons et actions correctives vérifiables.

La capacité doit fonctionner en PostgreSQL en production, disposer d’une garde SQLite pour les tests rapides, être sauvegardable, restaurable, observable et intégrée à la console Laravel.

## 2. État actuel à constater avant codage

Avant toute modification, Claude Code doit vérifier et consigner :

```text
CAP-CORE-018 dans docs/capacites/CATALOGUE.md
core/registre-incidents/ absent
routes /api/v1/incidents absentes
écran Incidents absent
contrats CTR-INC-* absents
politique POL-INCIDENTS-V1 absente
magasin incidents absent
```

Le journal opérationnel existant conserve des événements append-only avec chaîne SHA-256 et expurgation des secrets. Il constitue une source de traces, pas un dossier de coordination d’incident.

Les diagnostics existants de continuité, d’authentification, de fédération, de contrats, de politiques ou de produits peuvent produire des signaux, mais ils ne doivent pas être requalifiés rétroactivement comme incidents sans procédure de triage.

## 3. Branche et worktree

Créer :

```text
branche : claude/cap-core-018-incidents-go
worktree : /var/www/worktrees/gamad-cap018
```

La branche part du `main` réellement à jour après fusion de `CAP-CORE-017`.

Interdictions :

- ne pas travailler directement dans le checkout principal ;
- ne pas réutiliser un worktree d’une autre capacité ;
- ne pas commencer `CAP-CORE-020` ;
- ne pas modifier une capacité précédente pour masquer une dépendance absente ;
- ne pas déclarer `GO` avant CI complète et exercice PostgreSQL réel.

## 4. Module à créer

Créer :

```text
core/registre-incidents/
├── README.md
├── composer.json
├── src/
│   ├── Magasin.php
│   ├── Schema.php
│   ├── RegistreIncidents.php
│   ├── PolitiqueIncidents.php
│   ├── QualificationIncident.php
│   ├── ResolutionIncident.php
│   ├── ProjectionIncident.php
│   ├── Exceptions/
│   └── Contrats/
├── resources/
│   ├── severites-v1.json
│   ├── types-incidents-v1.json
│   ├── roles-reponse-v1.json
│   └── bootstrap-incidents-v1.json
└── tests/
    └── incidents_p3.php
```

Raccorder le module au monorepo Composer sans introduire une dépendance circulaire.

## 5. Configuration

Variables minimales :

```text
INCIDENT_REGISTRY_URL=postgresql://...
INCIDENT_REGISTRY_PATH=/chemin/incidents.sqlite
GAMAD_INCIDENT_DRIVER=pgsql|sqlite
GAMAD_INCIDENT_SIGNAL_RETENTION_DAYS=365
GAMAD_INCIDENT_TIMELINE_RETENTION_DAYS=3650
GAMAD_INCIDENT_PUBLIC_PROJECTION_ENABLED=false
GAMAD_INCIDENT_AUTO_CANDIDATE_ENABLED=true
GAMAD_INCIDENT_MAX_OPEN_PER_REALM=1000
```

Règles :

- production exige PostgreSQL ;
- SQLite est réservé au local, aux tests et à la migration contrôlée ;
- aucune bascule silencieuse PostgreSQL vers SQLite ;
- aucune valeur secrète dans les variables propres au registre ;
- les paramètres de délai et de rétention sont validés ;
- la configuration mise en cache par Laravel doit être éprouvée.

## 6. Frontière souveraine

### CAP-CORE-018 possède

- la référence canonique d’un incident ;
- son organisation responsable ;
- son realm principal et ses realms affectés explicitement ;
- son type ;
- sa sévérité courante et son historique ;
- ses signaux rattachés ;
- ses impacts constatés ;
- ses actifs, produits, capacités, contrats et sources concernés ;
- son équipe de réponse et les rôles attribués ;
- sa chronologie opérationnelle ;
- ses actions de réponse et leurs états ;
- ses communications ;
- ses décisions, risques, exceptions et preuves liés ;
- son rétablissement, sa résolution, sa clôture et sa réouverture ;
- sa revue post-incident ;
- ses leçons et actions correctives référencées.

### CAP-CORE-018 ne possède pas

- les comptes, authentificateurs ou sessions ;
- les politiques d’autorisation ;
- les mandats ;
- les produits ;
- les organisations ;
- les realms ;
- les contrats ;
- les clés privées ou valeurs secrètes ;
- les preuves cryptographiques ;
- les sauvegardes ;
- les données métier des satellites ;
- les journaux techniques complets ;
- les tickets de support client ;
- les enquêtes disciplinaires ou judiciaires ;
- les décisions formelles ;
- les risques et exceptions.

Il ne conserve que des références vers les capacités souveraines.

## 7. Répartition des responsabilités

```text
CAP-CORE-004
= autorise ou refuse chaque action de réponse

CAP-CORE-008
= enregistre les décisions formelles nécessaires

CAP-CORE-013
= conserve les traces transversales

CAP-CORE-014
= transporte les signaux et changements

CAP-CORE-015
= produit et vérifie les preuves techniques

CAP-CORE-016
= gouverne les clés et secrets

CAP-CORE-017
= conserve risques et exceptions

CAP-CORE-018
= coordonne le traitement de l’incident

CAP-CORE-019
= exécute sauvegarde et restauration
```

Une action comme « révoquer toutes les sessions d’une identité » est enregistrée comme action d’incident, mais exécutée par `CAP-CORE-005` selon un contrat exact.

## 8. Définitions canoniques

### Signal

Fait minimal indiquant qu’une analyse est nécessaire.

Exemples :

- plusieurs échecs de connexion ;
- divergence d’empreinte ;
- lot de sauvegarde incomplet ;
- clé déclarée compromise ;
- lettre morte répétée ;
- contrat devenu incompatible ;
- produit indisponible ;
- déclaration manuelle d’un opérateur.

Un signal ne prouve pas l’existence d’un incident.

### Alerte

Signal ayant franchi une règle de détection ou un seuil. L’alerte peut provenir d’un outil externe ou d’une capacité, mais `CAP-CORE-018` ne devient pas un moteur général de supervision.

### Incident candidat

Dossier ouvert pour triage, sans confirmation.

### Incident confirmé

Événement réel ayant produit ou menaçant immédiatement de produire un impact sur la confidentialité, l’intégrité, la disponibilité, la sûreté, la conformité, la continuité ou la confiance opérationnelle.

### Problème

Cause technique ou organisationnelle durable pouvant survivre au rétablissement. La première version de `CAP-CORE-018` peut référencer un problème et une action corrective, mais ne doit pas créer un second registre ITSM complet.

## 9. Types initiaux

Le bootstrap fournit des références versionnées, au minimum :

```text
INC-TYPE-SECURITE-ACCES
INC-TYPE-COMPROMISSION-SECRET
INC-TYPE-INTEGRITE-DONNEES
INC-TYPE-INDISPONIBILITE
INC-TYPE-DEGRADATION
INC-TYPE-PERTE-DONNEES
INC-TYPE-FUITE-DONNEES-SUSPECTEE
INC-TYPE-CONTRAT-INCOMPATIBLE
INC-TYPE-EVENEMENT-NON-LIVRE
INC-TYPE-SAUVEGARDE-RESTAURATION
INC-TYPE-ERREUR-CONFIGURATION
INC-TYPE-FEDERATION
INC-TYPE-DEPENDANCE-EXTERNE
INC-TYPE-AUTRE
```

`AUTRE` exige un motif et ne doit pas devenir le type majoritaire sans diagnostic.

Les codes viennent de `CAP-CORE-010` après bootstrap et sont refusés s’ils sont inconnus ou retirés.

## 10. Dimensions d’impact

Au minimum :

```text
CONFIDENTIALITE
INTEGRITE
DISPONIBILITE
CONTINUITE
CONFORMITE
FINANCIER
REPUTATION
UTILISATEURS
SATELLITES
```

Chaque impact indique :

- dimension ;
- niveau constaté ;
- périmètre ;
- début estimé ;
- fin éventuelle ;
- confiance de l’évaluation ;
- source ;
- preuve ;
- données encore inconnues.

Aucune donnée personnelle complète n’est nécessaire pour compter des personnes affectées.

## 11. Sévérité

Référentiel initial :

```text
SEV-1 — CRITIQUE
SEV-2 — MAJEUR
SEV-3 — SIGNIFICATIF
SEV-4 — MINEUR
```

La sévérité est calculée depuis une méthode versionnée combinant :

- impact maximal constaté ;
- étendue ;
- environnement ;
- exposition externe ;
- durée ;
- perte de contrôle ;
- possibilité de propagation ;
- réversibilité ;
- obligations contractuelles ;
- dépendances critiques.

Une sévérité peut être rehaussée immédiatement avec motif. Une baisse exige des faits nouveaux, une trace, l’identité de l’auteur et une justification vérifiable.

Aucune modification directe de la colonne courante sans événement de cycle.

## 12. Cas d’usage prioritaires

### Compromission de clé

1. `CAP-CORE-016` publie un signal minimal.
2. `CAP-CORE-018` ouvre un candidat.
3. Le triage confirme ou rejette.
4. Une équipe est désignée.
5. Les actions de révocation et rotation sont demandées à `CAP-CORE-016`.
6. Les sessions ou jetons affectés sont traités par `CAP-CORE-005` et `CAP-CORE-022`.
7. `CAP-CORE-015` vérifie les preuves.
8. `CAP-CORE-017` réévalue le risque.
9. La clôture exige preuve de rotation et de surveillance.

### Échec de restauration

1. `CAP-CORE-019` produit un diagnostic et un signal.
2. L’incident est lié au lot, au magasin et à l’environnement, sans copier les dumps.
3. Les décisions de restauration ou de bascule passent par `CAP-CORE-008` lorsque requises.
4. Les actions restent exécutées par `CAP-CORE-019`.
5. La clôture exige un exercice réussi et une preuve `CAP-CORE-015`.

### Rupture d’un contrat intercapacité

1. `CAP-CORE-009` détecte l’incompatibilité.
2. Le signal mentionne contrat, version, producteur et consommateur.
3. L’incident n’est confirmé que si un impact réel existe ou est immédiat.
4. La correction du contrat reste dans `CAP-CORE-009`.
5. Les événements non livrés restent dans `CAP-CORE-014`.

### Échecs d’authentification répétés

1. `CAP-CORE-005` ou la couche d’accès produit un agrégat minimal.
2. Aucun mot de passe, code, IP brute non nécessaire ou jeton n’est transporté.
3. Le triage distingue erreur utilisateur, automatisation défectueuse et attaque suspectée.
4. Les actions de verrouillage ou révocation restent dans `CAP-CORE-005`.

## 13. Détection et ouverture

Sources autorisées :

```text
MANUEL
CAPACITE
SATELLITE
SUPERVISION_EXTERNE
EXERCICE
AUDIT
```

Toute source doit être inscrite dans `CAP-CORE-006` et toute opération d’ingestion décrite dans `CAP-CORE-009`.

L’ouverture automatique est limitée à un **candidat**.

Interdit :

```text
signal reçu
→ incident automatiquement confirmé
```

Autorisé :

```text
signal reçu
→ déduplication
→ candidat créé ou signal rattaché
→ triage humain ou règle gouvernée
→ confirmation ou rejet
```

## 14. Déduplication

Un même événement peut arriver plusieurs fois via l’outbox, une reprise ou plusieurs outils.

La déduplication repose sur :

- `signal_reference` stable ;
- producteur ;
- contrat et version ;
- empreinte de contenu minimal ;
- fenêtre temporelle gouvernée ;
- sujet technique ;
- realm ;
- environnement.

Le rapprochement ne doit jamais fusionner automatiquement deux incidents confirmés ayant des impacts ou responsables différents.

Une fusion d’incidents exige une opération explicite, un motif, une preuve et conserve les deux références.

## 15. Confidentialité et classification

Classifications minimales :

```text
PUBLIC
INTERNE
RESTREINT
CRITIQUE
```

Par défaut :

```text
incident candidat : RESTREINT
incident de sécurité : RESTREINT ou CRITIQUE
projection publique : désactivée
```

Les champs sensibles sont séparés des projections opérationnelles.

Le registre ne stocke jamais :

- mots de passe ;
- secrets ;
- clés privées ;
- jetons ;
- cookies ;
- codes de secours ;
- contenu complet d’un fichier utilisateur ;
- dumps de bases ;
- journaux bruts non minimisés ;
- données médicales ou disciplinaires ;
- pièces d’identité ;
- contenu complet d’une communication privée.

## 16. Autorité et rôles

Rôles opérationnels minimaux :

```text
DECLARANT
TRIEUR
COMMANDANT_INCIDENT
RESPONSABLE_TECHNIQUE
RESPONSABLE_CONTINUITE
RESPONSABLE_SECURITE
RESPONSABLE_COMMUNICATION
SCRIBE
OBSERVATEUR
VALIDATEUR_CLOTURE
```

Les rôles ne créent aucune identité ni mandat. Ils référencent `CAP-CORE-001`, `CAP-CORE-002` et `CAP-CORE-003`.

Le commandant d’incident coordonne, mais n’obtient pas un droit universel dans les autres capacités.

## 17. Décisions et urgence

Certaines actions sont préautorisées par politique et runbook :

- isoler un consommateur défectueux ;
- suspendre temporairement une livraison ;
- révoquer un jeton précis ;
- désactiver une clé compromise pour les nouveaux usages ;
- basculer un service en lecture seule.

Chaque action reste soumise à `CAP-CORE-004`.

Les mesures extraordinaires peuvent exiger `CAP-CORE-008` :

- arrêt global d’un produit ;
- restauration de production ;
- destruction d’une clé ;
- notification publique ;
- acceptation d’une perte de données ;
- maintien d’un mode dégradé prolongé.

Une revue postérieure ne rend jamais rétroactivement autorisée une action qui ne l’était pas.

## 18. Intégrations obligatoires

### CAP-CORE-006

Toutes les sources des signaux, preuves et communications sont référencées.

### CAP-CORE-009

Tous les échanges et actions vers les capacités propriétaires sont contractuels.

### CAP-CORE-010

Types, états, sévérités, rôles, dimensions d’impact et résultats sont canoniques.

### CAP-CORE-012

L’accès et le routage sont bornés par realm. Un parent ne lit pas automatiquement les incidents de ses enfants.

### CAP-CORE-013

Toutes les opérations sensibles produisent une trace minimisée.

### CAP-CORE-014

Les signaux et changements d’état sont publiés en mode au moins une fois, avec déduplication.

### CAP-CORE-015

Les paquets de clôture, preuves de rétablissement et exports sont vérifiables.

### CAP-CORE-016

Les compromissions et rotations de clés sont référencées, jamais copiées.

### CAP-CORE-017

Les risques liés sont réévalués et les exceptions affectées peuvent être suspendues ou révoquées.

### CAP-CORE-019

Les actions de sauvegarde, restauration et continuité sont exécutées par cette capacité.

## 19. Non-objectifs de la première version

Ne pas construire :

- un SIEM complet ;
- un collecteur universel de logs ;
- un antivirus ;
- un EDR ;
- un centre d’appel ;
- un outil de ticketing général ;
- une messagerie instantanée ;
- une plateforme judiciaire ;
- un moteur de conformité réglementaire par pays ;
- une intelligence artificielle décidant seule de la sévérité ou de la clôture ;
- un système exécutant des commandes shell arbitraires.

## 20. Livrables attendus

À la fin du chantier :

```text
core/registre-incidents/
apps/console-laravel/app/Application/Incidents/
apps/console-laravel/app/Http/Controllers/Api/V1/*Incident*
apps/console-laravel/app/Http/Controllers/IncidentConsoleController.php
apps/console-laravel/resources/views/incidents/
apps/console-laravel/routes/api.php
apps/console-laravel/routes/web.php
apps/console-laravel/openapi/core-v1.yaml
docs/capacites/CAP-CORE-018-incidents.md
```

Le rapport final doit citer :

- branche ;
- commits ;
- PR ;
- migrations ;
- tests ;
- exercice PostgreSQL ;
- sauvegarde/restauration ;
- limites ;
- risques résiduels ;
- état réel `GO` ou `NO GO`.
