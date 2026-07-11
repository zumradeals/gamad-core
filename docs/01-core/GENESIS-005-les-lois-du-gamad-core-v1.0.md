# GENESIS-005 — Les Lois du GAMAD Core

## Version 1.0

**Projet :** GAMAD Core  
**Statut :** Référence normative  
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD  
**Architecture :** SIRR — Architecte de GAMAD  
**Dépendances :**
- GENESIS-001 — Livre Blanc de GAMAD
- GENESIS-002 — Charte Fondatrice de GAMAD
- GENESIS-003 — GAMAD Core Charter
- GENESIS-004 — Lexique Canonique du GAMAD Core
- ADR-0001 — Entity is an abstract concept, not a universal domain model
- ADR-0002 — Canonical vocabulary changes require architectural decision
- ADR-0003 — The Laws of GAMAD Core are normative invariants

---

## 1. Objet du document

Les Lois du GAMAD Core définissent les règles fondamentales qui doivent rester vraies dans toute conception, toute implémentation et toute évolution valide du Core.

Elles ne décrivent ni une interface, ni une technologie, ni un framework.

Elles expriment les conditions minimales de cohérence de l’écosystème GAMAD.

Une fonctionnalité peut être utile, rentable ou séduisante. Si elle viole une Loi du Core, elle doit être refusée, repensée ou déplacée hors du Core.

---

## 2. Portée normative

Les présentes Lois s’appliquent :

- au GAMAD Core ;
- aux modules connectés au Core ;
- aux applications GAMAD ;
- aux contrats d’API ;
- aux événements et commandes ;
- aux modèles conceptuels ;
- aux migrations ;
- aux décisions architecturales futures ;
- aux outils humains ou IA participant à la construction de GAMAD.

Les Lois ne remplacent pas les politiques métier. Elles fixent le cadre dans lequel ces politiques peuvent exister.

---

# TITRE I — EXISTENCE, IDENTITÉ ET CONTINUITÉ

## Loi 1 — Toute entité reconnue possède une identité persistante

Aucune entité ne peut être référencée durablement dans GAMAD sans Identity stable.

Une adresse email, un numéro de téléphone, une URL, un nom d’hôte ou un chemin de fichier ne constituent pas, à eux seuls, une identité persistante.

**Conséquence :** les identifiants techniques changeants restent des attributs ou alias, jamais la référence canonique.

---

## Loi 2 — Une identité n’est jamais un compte utilisateur

Identity, User Account, Authentication et Session sont des concepts distincts.

Une personne, une organisation, une application ou une ressource peut posséder une Identity sans disposer d’un compte utilisateur.

**Conséquence :** désactiver un compte ne supprime pas l’identité de l’entité représentée.

---

## Loi 3 — L’identité précède l’accès

Aucun droit durable ne doit être accordé à un sujet non identifié.

L’accès temporaire anonyme, lorsqu’il est autorisé, doit être explicitement modélisé, limité, traçable et révocable.

**Conséquence :** le Core ne déduit jamais une confiance durable d’une simple possession de lien ou de jeton non gouverné.

---

## Loi 4 — La continuité identitaire est protégée

Une Identity ne doit jamais être réattribuée arbitrairement à une autre entité.

Les fusions, remplacements, révocations et successions doivent conserver un historique explicite.

**Conséquence :** l’ancien identifiant reste traçable même lorsqu’il n’est plus actif.

---

# TITRE II — ACTEURS, ACTIONS ET RESPONSABILITÉ

## Loi 5 — Toute action importante possède un acteur identifiable

Aucune action critique ne doit être attribuée à un acteur vague tel que `system`, `admin` ou `unknown` sans identité exploitable.

L’acteur peut être humain, application, service, Agent ou automatisation.

**Conséquence :** les actions techniques doivent toujours pouvoir être reliées à une identité et à un contexte.

---

## Loi 6 — L’acteur technique et l’autorité d’origine sont distingués

Lorsqu’une application, un service, un Agent ou Copilote agit pour le compte d’un humain ou d’une organisation, l’audit doit conserver :

- l’Actor technique ;
- l’autorité humaine ou organisationnelle ;
- l’Application utilisée ;
- le contexte d’exécution.

**Conséquence :** une automatisation ne peut masquer la responsabilité de l’autorité qui l’a déclenchée ou autorisée.

---

## Loi 7 — L’authentification ne vaut pas autorisation

Le fait d’être authentifié ne confère aucun droit implicite sur une Resource.

Toute autorisation doit être évaluée selon Permission, Role, Policy, contexte et état des objets concernés.

**Conséquence :** aucun endpoint sensible ne doit reposer uniquement sur la présence d’une session valide.

---

# TITRE III — ORGANISATIONS, APPARTENANCES ET CONTEXTES

## Loi 8 — Une organisation ne se confond pas avec un tenant technique

Organization représente une réalité gouvernée.

Tenant représente une partition technique d’isolation.

**Conséquence :** une application peut utiliser un tenant par organisation, plusieurs tenants pour une organisation ou un tenant pour plusieurs organisations, selon un contrat explicite.

---

## Loi 9 — L’appartenance est une relation, pas une propriété identitaire

Membership relie une Person à une Organization pour une période et un contexte déterminés.

L’appartenance ne doit pas être inscrite comme caractéristique permanente de la Person.

**Conséquence :** une personne peut appartenir successivement ou simultanément à plusieurs organisations sans multiplication de son identité.

---

## Loi 10 — Toute relation organisationnelle possède un cycle de vie

Membership, responsabilité, délégation et rattachement ne doivent jamais être considérés comme éternels par défaut.

**Conséquence :** dates, états et transitions doivent être explicites lorsque la relation est gouvernée.

---

# TITRE IV — RESSOURCES ET DONNÉES MÉTIER

## Loi 11 — Toute ressource possède un responsable identifiable

Aucune Resource gouvernée ne peut exister sans Responsible Party explicite.

Le responsable peut être une Organization, une Person, une autorité système reconnue ou un contexte conjoint gouverné.

**Conséquence :** une localisation technique ne remplace jamais la responsabilité.

---

## Loi 12 — Toute ressource possède un système métier responsable

Chaque Resource doit être rattachée au système ou module qui demeure source de vérité pour son contenu métier.

**Conséquence :** le Core sait qui gouverne la ressource, mais ne prétend pas connaître tout son contenu.

---

## Loi 13 — Le Core ne possède pas les données métier des modules

Le Core maintient les références, états, droits, relations et contrats transversaux nécessaires.

Les contenus détaillés restent dans les domaines responsables.

**Conséquence :** le Core ne devient jamais l’entrepôt universel des documents, courriels, sauvegardes, conversations, fichiers ou données sectorielles.

---

## Loi 14 — Une ressource n’est pas son contenu

Resource est une unité gouvernée.

Le contenu peut évoluer, être remplacé, migré ou versionné sans que l’identité de la Resource change nécessairement.

**Conséquence :** l’identité d’une ressource ne doit pas dépendre uniquement d’un chemin physique ou d’un emplacement de stockage.

---

## Loi 15 — La suppression n’est jamais implicite

La désactivation, suspension, expiration, révocation, archivage et suppression sont des opérations distinctes.

**Conséquence :** la perte d’un abonnement, d’un droit ou d’un accès ne doit jamais supprimer automatiquement les données sans politique explicite, délai, traçabilité et procédure de récupération.

---

# TITRE V — AUTORISATIONS, POLITIQUES ET CAPACITÉS

## Loi 16 — Toute permission est explicite et contextualisée

Une Permission doit désigner au minimum :

- une action ;
- une cible ou catégorie de cibles ;
- un contexte ;
- une source d’autorité.

**Conséquence :** les rôles vagues sans permissions définies ne constituent pas un modèle d’autorisation suffisant.

---

## Loi 17 — Les permissions sont refusées par défaut

En l’absence d’une autorisation valide, la décision est un refus.

**Conséquence :** l’accès ne doit jamais être accordé parce qu’aucune règle ne l’interdit explicitement.

---

## Loi 18 — Capability et Permission restent distinctes

Capability décrit ce qu’un Module sait fournir.

Permission décrit ce qu’un Actor peut faire.

**Conséquence :** l’activation commerciale ou institutionnelle d’une capacité ne donne pas automatiquement à tous les utilisateurs le droit de l’exécuter.

---

## Loi 19 — L’activation d’un module ne suffit pas à l’autoriser

Un Module activé pour une Organization doit encore respecter :

- les Entitlements ;
- les Permissions ;
- les Policies ;
- les états des Actors, Applications et Resources.

**Conséquence :** masquer ou afficher un menu n’est jamais une décision de sécurité.

---

## Loi 20 — Les politiques ont une portée et une priorité explicites

Toute Policy doit préciser son niveau d’application, sa période de validité, son auteur et sa priorité face aux autres politiques.

**Conséquence :** aucune règle globale, organisationnelle ou locale ne peut se substituer silencieusement à une autre.

---

# TITRE VI — MODULES, APPLICATIONS ET FRONTIÈRES

## Loi 21 — Un module possède une responsabilité délimitée

Chaque Module doit déclarer :

- ce qu’il possède ;
- ce qu’il ne possède pas ;
- les Contracts qu’il expose ;
- les Contracts qu’il consomme ;
- ses Capabilities ;
- ses Events ;
- ses données métier.

**Conséquence :** un module sans frontière claire n’est pas prêt à être construit.

---

## Loi 22 — Aucun module ne lit directement les données internes d’un autre module

Toute coopération passe par un Contract explicite.

**Conséquence :** les lectures croisées de tables, modèles internes ou fichiers privés sont interdites hors migration gouvernée.

---

## Loi 23 — Le Core n’appartient à aucune application

Drive, Hub, Docs, Mail, Copilote, Mobile et toute future Application sont des consommateurs du Core.

Aucune ne définit seule ses invariants.

**Conséquence :** le Core ne doit pas être structuré autour des besoins particuliers d’un premier produit.

---

## Loi 24 — Aucune application n’est fiable par défaut

Toute Application, Service ou Agent doit être enregistré, authentifié, limité par scopes, révocable et auditable.

**Conséquence :** l’appartenance à l’écosystème GAMAD ne confère pas une confiance illimitée.

---

## Loi 25 — La modularité logique précède la distribution physique

Les domaines doivent être séparés conceptuellement avant toute décision de microservices.

**Conséquence :** le Core commence comme monolithe modulaire, sauf preuve qu’une distribution physique est nécessaire.

---

# TITRE VII — CONTRATS, COMMANDES ET ÉVÉNEMENTS

## Loi 26 — Toute interaction inter-système repose sur un contrat versionné

Aucun comportement important ne doit dépendre d’une convention implicite connue seulement du code ou d’une équipe.

**Conséquence :** schémas, erreurs, préconditions, compatibilité et idempotence doivent être documentés.

---

## Loi 27 — Une commande demande, un événement constate

Command exprime une intention future adressée à un responsable.

Event décrit un fait passé et immuable.

**Conséquence :** un événement ne doit jamais être utilisé comme instruction cachée.

---

## Loi 28 — Un événement publié n’est pas réécrit

Une correction produit un nouvel Event ou une relation de compensation.

**Conséquence :** l’historique événementiel important reste traçable.

---

## Loi 29 — Tout contrat possède une politique de compatibilité

Toute évolution d’API, Command, Event ou Response doit préciser :

- la version ;
- les changements ;
- la durée de support ;
- la stratégie de migration ;
- les conditions de dépréciation.

**Conséquence :** un consommateur ne doit pas être cassé silencieusement.

---

## Loi 30 — Les opérations critiques sont idempotentes ou protégées contre la répétition

Une répétition due au réseau, à une reprise ou à une file d’attente ne doit pas créer de doublons ou d’effets irréversibles inattendus.

**Conséquence :** les Commands critiques doivent disposer d’une identité de corrélation ou d’idempotence.

---

# TITRE VIII — AUDIT, PREUVE ET OBSERVABILITÉ

## Loi 31 — Toute action critique est auditée

L’Audit Record doit permettre d’identifier au minimum :

- l’Actor ;
- l’autorité d’origine ;
- l’Application ;
- le contexte organisationnel ;
- la cible ;
- l’action ;
- la décision ;
- le résultat ;
- la date ;
- la corrélation.

**Conséquence :** les opérations critiques sans preuve exploitable sont invalides.

---

## Loi 32 — Audit, log technique et trace restent distincts

Audit constitue une preuve métier ou de sécurité.

Technical Log sert au diagnostic.

Trace sert à suivre un traitement distribué.

**Conséquence :** aucun de ces mécanismes ne doit remplacer les deux autres.

---

## Loi 33 — L’audit ne contient pas plus de données sensibles que nécessaire

La traçabilité ne justifie pas l’enregistrement de secrets, mots de passe, contenus privés complets ou données personnelles inutiles.

**Conséquence :** l’audit respecte minimisation, classification et politique de conservation.

---

## Loi 34 — Les opérations d’administration sont soumises aux mêmes exigences d’audit

Le pouvoir administratif ne constitue pas une exception à la traçabilité.

**Conséquence :** les actions des superadministrateurs et opérateurs du Core doivent être identifiables et vérifiables.

---

# TITRE IX — CYCLE DE VIE, RÉSILIENCE ET SÉCURITÉ

## Loi 35 — Tout objet gouverné possède un cycle de vie explicite

Identity, Organization, Membership, Application, Resource, Module, Policy et Entitlement doivent définir leurs états et transitions valides.

**Conséquence :** une simple valeur `status` modifiable librement est insuffisante.

---

## Loi 36 — Toute transition critique vérifie ses préconditions

Une transition ne peut être exécutée uniquement parce qu’un endpoint a été appelé.

**Conséquence :** le système vérifie l’état courant, l’Actor, la Permission, la Policy et les dépendances.

---

## Loi 37 — La révocation doit être réellement opposable

Révoquer une Session, Application, Device, Permission ou Entitlement doit empêcher les nouvelles actions concernées dans un délai défini.

**Conséquence :** les caches, tokens et Agents doivent respecter une stratégie de propagation et d’expiration.

---

## Loi 38 — Les données critiques doivent être récupérables

Le Core doit posséder des mécanismes vérifiés de sauvegarde, restauration, migration et reprise.

**Conséquence :** une sauvegarde non testée ne constitue pas une garantie de résilience.

---

## Loi 39 — Les secrets ne sont jamais stockés ou transmis sans protection adaptée

Les secrets, clés, tokens et données sensibles doivent être classifiés, chiffrés, rotatifs et accessibles selon le moindre privilège.

**Conséquence :** aucun secret ne doit être committé dans Git ou exposé dans les journaux.

---

## Loi 40 — Le moindre privilège prévaut

Actors, Applications, Services et Agents ne reçoivent que les accès nécessaires à leur responsabilité.

**Conséquence :** les comptes techniques universels et permanents sont interdits.

---

# TITRE X — ÉVOLUTION ET GOUVERNANCE ARCHITECTURALE

## Loi 41 — Les technologies sont remplaçables

Aucun langage, framework, moteur de base de données ou fournisseur ne définit l’identité du Core.

**Conséquence :** les choix d’implémentation sont documentés par ADR et restent derrière les Contracts.

---

## Loi 42 — Les concepts canoniques ne changent pas silencieusement

Toute création, redéfinition, fusion ou suppression d’un concept transversal exige une ADR et une mise à jour du Lexique Canonique.

**Conséquence :** le code ne crée pas le vocabulaire après coup.

---

## Loi 43 — Les invariants précèdent les fonctionnalités

Une fonctionnalité ne peut affaiblir une Loi, même pour accélérer un prototype ou satisfaire un besoin commercial immédiat.

**Conséquence :** un compromis temporaire doit être isolé, documenté et interdit dans le chemin de production du Core.

---

## Loi 44 — Toute dette architecturale est déclarée

Lorsqu’une solution transitoire est inévitable, elle doit disposer :

- d’une décision documentée ;
- d’un périmètre ;
- d’un risque ;
- d’une date ou condition de sortie ;
- d’un responsable.

**Conséquence :** aucune dette ne doit devenir permanente par oubli.

---

## Loi 45 — Git est la mémoire officielle des décisions

Les documents normatifs, ADR, contrats, schémas et migrations doivent être versionnés dans les dépôts officiels.

**Conséquence :** une décision importante présente uniquement dans une conversation, un message ou la mémoire d’une personne n’est pas encore une décision institutionnelle.

---

## Loi 46 — Le Core doit rester reconstructible

Une équipe compétente doit pouvoir comprendre et reconstruire le Core à partir :

- du Livre Blanc ;
- de la Charte Fondatrice ;
- du Core Charter ;
- du Lexique ;
- des Lois ;
- des ADR ;
- des modèles ;
- des contrats ;
- des migrations ;
- des tests.

**Conséquence :** aucune connaissance essentielle ne doit dépendre exclusivement d’un individu ou d’un outil.

---

# TITRE XI — INTELLIGENCE ARTIFICIELLE ET AUTOMATISATION

## Loi 47 — L’IA ne dépasse jamais les droits de l’autorité qu’elle assiste

Une IA ou un Copilote ne peut accéder, déduire ou agir au-delà des Permissions, Policies et Resources autorisées pour son contexte.

**Conséquence :** l’accès IA est limité par l’intersection des droits humains, organisationnels, applicatifs et documentaires.

---

## Loi 48 — L’IA prépare, l’humain engage

Les actes engageant juridiquement, financièrement, institutionnellement ou humainement une organisation exigent une validation humaine explicite, sauf automatisation strictement prédéfinie et gouvernée.

**Conséquence :** la génération libre ne déclenche pas automatiquement une signature, un envoi officiel, une suppression ou une décision sensible.

---

## Loi 49 — La connaissance officielle est validée

Une conversation, une suggestion ou un document non validé ne devient jamais automatiquement connaissance officielle d’une Organization.

**Conséquence :** la mémoire organisationnelle possède un cycle de validation, versionnement, obsolescence et archivage.

---

## Loi 50 — Toute action automatisée reste explicable et traçable

Une automatisation doit permettre de connaître :

- sa règle déclenchante ;
- son Actor technique ;
- son autorité ;
- les données utilisées ;
- le résultat ;
- la possibilité de révocation ou correction.

**Conséquence :** aucune action critique ne peut être attribuée à une IA opaque sans contexte exploitable.

---

# TITRE XII — CONTRÔLE DE CONFORMITÉ

## 51. Test de conformité d’une proposition

Toute proposition de composante, module ou fonctionnalité doit répondre aux questions suivantes :

1. Quelle Loi protège-t-elle ?
2. Risque-t-elle d’en violer une ?
3. Possède-t-elle une responsabilité claire ?
4. Ses données métier ont-elles un propriétaire explicite ?
5. Ses contrats sont-ils versionnés ?
6. Ses Actors et autorisations sont-ils identifiables ?
7. Son cycle de vie est-il défini ?
8. Son audit est-il prévu ?
9. Sa révocation est-elle possible ?
10. Peut-elle être remplacée sans casser les invariants ?

Une réponse insuffisante suspend la validation architecturale.

---

## 52. Hiérarchie en cas de conflit

En cas de contradiction documentaire, l’ordre de résolution est :

1. Livre Blanc de GAMAD — vision et finalité ;
2. Charte Fondatrice — principes invariants de l’écosystème ;
3. GAMAD Core Charter — mission et frontières du Core ;
4. Lois du GAMAD Core — règles normatives ;
5. Lexique Canonique — sens des concepts ;
6. ADR acceptées — décisions architecturales ;
7. Contracts versionnés — engagements d’interopérabilité ;
8. Spécifications ;
9. Implémentation.

Une implémentation contradictoire doit être corrigée. Elle ne redéfinit jamais rétroactivement la Loi.

---

## 53. Procédure d’amendement

Une Loi ne peut être modifiée, supprimée ou remplacée que par :

1. une proposition d’amendement documentée ;
2. une analyse d’impact ;
3. une ADR dédiée ;
4. une validation de l’Orchestrateur et de l’autorité architecturale ;
5. une nouvelle version du présent document ;
6. un plan de migration pour les contrats et systèmes affectés.

Aucun amendement silencieux n’est permis.

---

## Déclaration finale

Les Lois du GAMAD Core ne cherchent pas à ralentir la construction.

Elles empêchent la vitesse de détruire la cohérence.

Elles protègent le Core contre la confusion entre identité et compte, authentification et autorisation, ressource et contenu, capacité et permission, commande et événement, audit et log, module et application.

Elles garantissent qu’une évolution demeure compréhensible, gouvernée, traçable, révocable et reconstructible.

> Les modules créent les usages. Les contrats organisent les échanges. Les Lois protègent la cohérence.
