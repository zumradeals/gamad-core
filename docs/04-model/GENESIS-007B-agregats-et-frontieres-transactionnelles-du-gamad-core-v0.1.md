# GENESIS-007B — Agrégats et frontières transactionnelles du GAMAD Core

## Version 0.1 — Modèle de cohérence initial

**Projet :** GAMAD Core  
**Statut :** Draft conceptuel validé pour construction progressive  
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD  
**Architecture :** SIRR — Architecte de GAMAD  
**Dépendances :**
- GENESIS-003 — GAMAD Core Charter
- GENESIS-004 — Lexique Canonique du GAMAD Core
- GENESIS-005 — Les Lois du GAMAD Core
- GENESIS-007A — Concepts et relations du GAMAD Core
- ADR-0006 — The Core governs relationships before data
- ADR-0007 — Aggregates protect invariants, not object collections

---

## 1. Objet du document

GENESIS-007B définit les agrégats conceptuels du GAMAD Core, les invariants qu’ils protègent et les frontières transactionnelles qui encadrent leurs modifications.

Ce document ne décrit pas :

- des classes ;
- des tables ;
- des repositories techniques ;
- des contrôleurs ;
- une base de données précise ;
- une implémentation de transaction ;
- un framework.

Il établit les frontières de cohérence à partir desquelles les futurs modèles logiques et physiques seront conçus.

---

## 2. Principe fondamental

> Un agrégat existe pour protéger un invariant.

Un agrégat n’est pas :

- une collection pratique d’objets ;
- un dossier de code ;
- un module ;
- un bounded context ;
- une table principale accompagnée de tables secondaires ;
- un prétexte pour charger un graphe complet en mémoire.

Sa taille et sa frontière sont déterminées par ce qui doit rester immédiatement cohérent lors d’une modification.

---

## 3. Définitions normatives

### 3.1 Agrégat

Ensemble conceptuel cohérent gouverné par une racine unique et protégeant un ou plusieurs invariants transactionnels.

### 3.2 Racine d’agrégat

Seul point d’entrée autorisé pour modifier l’état interne d’un agrégat.

### 3.3 Invariant transactionnel

Règle qui doit être vraie avant et après toute transaction valide concernant l’agrégat.

### 3.4 Frontière transactionnelle

Limite à l’intérieur de laquelle une modification doit être atomique.

### 3.5 Cohérence immédiate

État garanti à la fin de la transaction de l’agrégat concerné.

### 3.6 Cohérence éventuelle

État atteint après coordination explicite entre plusieurs agrégats ou systèmes, généralement par événements, commandes ou processus orchestrés.

### 3.7 Process Manager

Composant de coordination suivant un processus impliquant plusieurs agrégats, sans devenir propriétaire de leurs invariants internes.

---

## 4. Lois transactionnelles

1. Une transaction métier ne modifie directement qu’un seul agrégat.
2. Un agrégat ne modifie jamais l’état interne d’un autre agrégat.
3. Toute coordination inter-agrégats passe par des contrats explicites.
4. Les transactions distribuées implicites sont interdites.
5. Une référence vers un autre agrégat utilise son identité, jamais son état interne chargé comme dépendance obligatoire.
6. Une décision prise dans un agrégat ne présume pas du succès immédiat d’un autre agrégat.
7. Les événements publiés doivent être persistés de manière fiable avec la modification qui les produit.
8. Les opérations répétables doivent être idempotentes lorsque leur contrat l’exige.
9. Les conflits de concurrence doivent être détectés et traités explicitement.
10. Aucun invariant ne peut être partagé sans propriétaire clairement identifié.

---

# PARTIE I — AGRÉGATS CANONIQUES INITIAUX

## AGG-001 — Identity Aggregate

### Racine

`Identity`

### Mission

Protéger la continuité identitaire d’une entité reconnue par GAMAD.

### Invariants protégés

- Une identité possède une référence persistante unique dans son espace de référence.
- Une identité possède un type reconnu.
- Une identité possède un état identitaire valide.
- Une identité révoquée ne redevient pas active sans transition autorisée.
- Une fusion ne détruit jamais l’historique des identités concernées.
- Une identité remplacée conserve une relation traçable avec son successeur.
- Une identité n’est jamais physiquement supprimée par une opération métier ordinaire.

### Cohérence immédiate

- création de l’identité ;
- transition d’état ;
- révocation ;
- fusion contrôlée ;
- remplacement ;
- archivage ;
- enregistrement des alias identitaires autorisés.

### Cohérence éventuelle

- création ou mise à jour des profils spécialisés ;
- propagation vers les applications ;
- mise à jour des index de recherche ;
- notification des systèmes consommateurs.

### Événements possibles

- `IdentityRegistered`
- `IdentityActivated`
- `IdentitySuspended`
- `IdentityRevoked`
- `IdentityMerged`
- `IdentityReplaced`
- `IdentityArchived`

### Dépendances autorisées

Références vers type identitaire, autorité initiatrice et Audit.

### Dépendances interdites

Données métier complètes de Person, Organization, Application ou Resource.

---

## AGG-002 — Person Aggregate

### Racine

`Person`

### Mission

Protéger la continuité du sujet humain sans la confondre avec ses comptes, rôles ou appartenances.

### Invariants protégés

- Une Person référence une Identity humaine valide.
- Les attributs humains essentiels respectent leur classification de données.
- Une Person peut exister sans compte utilisateur.
- La désactivation d’un compte ne désactive pas la Person.
- Une fusion de profils humains doit conserver l’historique et les preuves.

### Cohérence immédiate

- création du profil humain ;
- correction d’attributs gouvernés ;
- classification ;
- état du profil humain ;
- rapprochement contrôlé.

### Cohérence éventuelle

- création de User Accounts ;
- création de Memberships ;
- synchronisation avec des applications autorisées.

### Événements possibles

- `PersonProfileCreated`
- `PersonProfileUpdated`
- `PersonProfileRestricted`
- `PersonProfilesMatched`

---

## AGG-003 — User Account Aggregate

### Racine

`UserAccount`

### Mission

Protéger l’accès applicatif d’un sujet sans absorber son identité humaine complète.

### Invariants protégés

- Un compte possède un identifiant de connexion unique dans son domaine.
- Un compte référence un sujet reconnu.
- Un compte suspendu ne peut créer de nouvelle Session.
- Les Authentication Factors suivent leur propre état de confiance.
- La révocation d’un facteur n’entraîne pas la suppression du compte.
- Une réinitialisation sensible doit être auditée.

### Cohérence immédiate

- création ;
- activation ;
- suspension ;
- verrouillage ;
- ajout ou révocation d’un facteur ;
- rotation des secrets applicables.

### Cohérence éventuelle

- invalidation de Sessions actives ;
- notification de sécurité ;
- propagation vers applications fédérées.

### Événements possibles

- `UserAccountCreated`
- `UserAccountActivated`
- `UserAccountSuspended`
- `AuthenticationFactorAdded`
- `AuthenticationFactorRevoked`
- `CredentialsRotated`

---

## AGG-004 — Session Aggregate

### Racine

`Session`

### Mission

Protéger le contexte temporaire d’accès d’un sujet authentifié.

### Invariants protégés

- Une Session possède une durée limitée.
- Une Session référence un User Account ou une Identity technique valide.
- Une Session révoquée ne redevient pas active.
- Une Session est liée à une Application.
- Les niveaux de confiance et facteurs utilisés sont traçables.

### Cohérence immédiate

- ouverture ;
- renouvellement autorisé ;
- expiration ;
- révocation ;
- élévation de niveau de confiance.

### Cohérence éventuelle

- diffusion de révocation ;
- fermeture sur d’autres nœuds ;
- analyse comportementale.

---

## AGG-005 — Organization Aggregate

### Racine

`Organization`

### Mission

Protéger l’existence, l’état et la structure officielle d’une organisation reconnue.

### Invariants protégés

- Une Organization possède une Identity organisationnelle valide.
- Une Organization possède un état de cycle de vie cohérent.
- Une Organizational Unit appartient à une seule Organization racine.
- Une unité ne crée pas de cycle structurel invalide.
- Une organisation archivée ne reçoit pas de nouvelles unités actives sans réactivation autorisée.
- Une relation parent-enfant entre organisations doit être explicitement gouvernée.

### Cohérence immédiate

- création ;
- modification des attributs structuraux ;
- création, déplacement et archivage d’unités ;
- transitions de cycle de vie ;
- désignation de responsables institutionnels.

### Cohérence éventuelle

- mise à jour des Memberships ;
- recalcul des droits ;
- propagation des politiques ;
- adaptation des Workspaces.

### Événements possibles

- `OrganizationRegistered`
- `OrganizationActivated`
- `OrganizationalUnitCreated`
- `OrganizationalUnitMoved`
- `OrganizationSuspended`
- `OrganizationArchived`

---

## AGG-006 — Membership Aggregate

### Racine

`Membership`

### Mission

Protéger la relation gouvernée entre une Person et une Organization.

### Invariants protégés

- Un Membership référence une Person et une Organization valides.
- Une période d’appartenance possède des bornes cohérentes.
- Un Membership possède un statut reconnu.
- Les changements de statut respectent le Lifecycle défini.
- Une même appartenance active ne doit pas être dupliquée dans le même contexte lorsque le contrat l’interdit.
- Une fonction organisationnelle ne devient pas automatiquement une Permission technique.

### Cohérence immédiate

- admission ;
- activation ;
- changement de statut ;
- changement d’unité ;
- suspension ;
- révocation ;
- clôture.

### Cohérence éventuelle

- attribution ou retrait de Roles ;
- ajustement des Workspaces ;
- notifications ;
- recalcul de permissions dérivées.

### Événements possibles

- `MembershipRequested`
- `MembershipActivated`
- `MembershipTransferred`
- `MembershipSuspended`
- `MembershipRevoked`
- `MembershipClosed`

---

## AGG-007 — Application Aggregate

### Racine

`Application`

### Mission

Protéger l’identité, la confiance, les contrats et le cycle de vie d’une application reconnue.

### Invariants protégés

- Une Application possède une Identity technique.
- Une Application possède un propriétaire responsable.
- Une Application ne consomme que des Contracts autorisés.
- Une Application révoquée ne peut plus s’authentifier.
- Les secrets et certificats suivent une rotation gouvernée.
- Les environnements et scopes doivent être explicitement séparés.

### Cohérence immédiate

- enregistrement ;
- activation ;
- attribution de scopes ;
- rotation de credentials ;
- révocation ;
- dépréciation ;
- retraite.

### Cohérence éventuelle

- propagation aux passerelles API ;
- invalidation des tokens ;
- notification des consommateurs ;
- mise à jour des catalogues.

---

## AGG-008 — Device Aggregate

### Racine

`Device`

### Mission

Protéger l’identité, l’état de confiance et le cycle de vie d’un appareil reconnu.

### Invariants protégés

- Un Device possède une Identity technique.
- Son état de confiance est explicite.
- Un Device révoqué ne peut héberger un Agent actif sans réenrôlement autorisé.
- Les clés d’appareil sont rotatives et révocables.
- L’association à une Organization ou Person est gouvernée et historisée.

### Cohérence immédiate

- enrôlement ;
- approbation ;
- changement de propriétaire ;
- rotation de clés ;
- suspension ;
- révocation ;
- retrait.

### Cohérence éventuelle

- arrêt des Agents ;
- invalidation des Sessions ;
- retrait des accès réseau ;
- notification des administrateurs.

---

## AGG-009 — Agent Aggregate

### Racine

`Agent`

### Mission

Protéger l’identité opérationnelle, l’association à un environnement et l’état d’un Agent GAMAD.

### Invariants protégés

- Un Agent possède une Identity technique propre.
- Un Agent est lié à une Application et, lorsqu’applicable, à un Device.
- Un Agent possède un propriétaire ou contexte organisationnel explicite.
- Un Agent suspendu ou révoqué ne peut exécuter de nouvelles commandes.
- Les capacités annoncées par l’Agent sont compatibles avec sa version et son contrat.

### Cohérence immédiate

- association ;
- activation ;
- déclaration de version ;
- déclaration de capacités ;
- rotation d’identité technique ;
- suspension ;
- révocation.

### Cohérence éventuelle

- arrêt des tâches ;
- retrait des ressources exposées ;
- mise à jour de santé ;
- notification de l’application responsable.

---

## AGG-010 — Resource Registry Aggregate

### Racine

`Resource`

### Mission

Protéger l’identité, la responsabilité, la classification et le cycle de vie transversal d’une ressource.

### Invariants protégés

- Une Resource possède une Identity persistante.
- Une Resource possède un Resource Type valide.
- Une Resource possède au moins un Responsible Party.
- Une responsabilité principale est identifiable lorsque plusieurs responsables existent.
- Une Resource possède un System of Record unique pour une version donnée de sa gouvernance.
- Une Resource ne contient pas nécessairement son contenu métier.
- Les transitions de Lifecycle sont autorisées.

### Cohérence immédiate

- enregistrement ;
- classification ;
- affectation du responsable ;
- désignation du System of Record ;
- changement d’état ;
- archivage ;
- transfert de responsabilité.

### Cohérence éventuelle

- création ou déplacement du contenu dans le module ;
- indexation ;
- synchronisation ;
- recalcul des droits ;
- affichage dans les Workspaces.

### Événements possibles

- `ResourceRegistered`
- `ResourceClassified`
- `ResourceResponsibilityChanged`
- `ResourceSystemOfRecordChanged`
- `ResourceArchived`
- `ResourceRetired`

---

## AGG-011 — Workspace Aggregate

### Racine

`Workspace`

### Mission

Protéger la cohérence d’un contexte d’usage regroupant acteurs, ressources et capacités.

### Invariants protégés

- Un Workspace possède un responsable et un contexte de gouvernance.
- Une Resource ne devient pas accessible par simple appartenance au Workspace.
- Les règles d’admission et de retrait des Actors sont explicites.
- Les capacités visibles dans le Workspace doivent être disponibles dans son contexte.
- Un Workspace archivé ne reçoit plus de nouveaux membres ou ressources actives.

### Cohérence immédiate

- création ;
- configuration ;
- admission et retrait d’Actors ;
- rattachement et retrait de références de Resources ;
- activation ou retrait de capacités visibles ;
- archivage.

### Cohérence éventuelle

- propagation des permissions ;
- mise à jour des applications clientes ;
- indexation ;
- notifications.

---

## AGG-012 — Module Registry Aggregate

### Racine

`Module`

### Mission

Protéger le catalogue officiel des modules et capacités de l’écosystème.

### Invariants protégés

- Un Module possède une identité et un responsable.
- Une Capability possède un nom canonique unique dans son espace.
- Une Capability possède une version et un fournisseur officiel.
- Les dépendances entre modules ne créent pas de cycle interdit.
- Un Module retiré ne peut fournir de nouveaux Entitlements.
- Une Capability dépréciée conserve une politique de migration.

### Cohérence immédiate

- enregistrement du module ;
- déclaration ou modification d’une Capability ;
- ajout de dépendances ;
- dépréciation ;
- retraite.

### Cohérence éventuelle

- mise à jour des Entitlements ;
- publication dans les catalogues ;
- propagation aux applications ;
- migration des consommateurs.

---

## AGG-013 — Entitlement Aggregate

### Racine

`Entitlement`

### Mission

Protéger l’attribution d’une Capability à une Organization ou à un contexte gouverné.

### Invariants protégés

- Un Entitlement référence une Capability valide.
- Le bénéficiaire est identifiable.
- La période, les quotas et limites sont cohérents.
- Un Entitlement suspendu ne rend pas la Capability disponible.
- Un Entitlement ne crée pas automatiquement des Permissions individuelles.
- L’origine commerciale ou institutionnelle est traçable.

### Cohérence immédiate

- attribution ;
- activation ;
- modification de limites ;
- suspension ;
- expiration ;
- révocation.

### Cohérence éventuelle

- activation technique dans les applications ;
- adaptation des interfaces ;
- recalcul de quotas ;
- notifications.

---

## AGG-014 — Role Aggregate

### Racine

`Role`

### Mission

Protéger la définition d’un ensemble nommé de Permissions dans un contexte.

### Invariants protégés

- Un Role possède un propriétaire de gouvernance.
- Chaque Permission incluse est valide.
- Les héritages ne créent pas de cycle interdit.
- Un Role déprécié conserve une stratégie de remplacement.
- Un Role ne vaut que dans les Contexts où il est défini.

### Cohérence immédiate

- création ;
- ajout ou retrait de Permissions ;
- modification d’héritage ;
- dépréciation ;
- retraite.

### Cohérence éventuelle

- recalcul des Access Decisions ;
- mise à jour des affectations ;
- invalidation de caches.

---

## AGG-015 — Role Assignment Aggregate

### Racine

`RoleAssignment`

### Mission

Protéger l’attribution d’un Role à un Actor dans un Context explicite.

### Invariants protégés

- L’Actor, le Role et le Context sont identifiables.
- L’affectation possède une période cohérente.
- Une affectation révoquée n’est plus utilisée.
- Une affectation ne dépasse pas l’autorité de son émetteur.
- Les affectations temporaires expirent effectivement.

### Cohérence immédiate

- attribution ;
- activation ;
- suspension ;
- expiration ;
- révocation.

### Cohérence éventuelle

- recalcul des droits ;
- invalidation des Sessions ou caches ;
- notification des systèmes consommateurs.

---

## AGG-016 — Policy Aggregate

### Racine

`Policy`

### Mission

Protéger la définition, la version et le cycle de vie d’une règle gouvernée.

### Invariants protégés

- Une Policy possède une portée explicite.
- Une Policy possède une priorité ou règle de résolution.
- Une Policy possède une version immuable une fois publiée.
- Une Policy contradictoire doit être détectée ou arbitrée.
- Une Policy retirée conserve son historique.

### Cohérence immédiate

- création du draft ;
- validation ;
- publication ;
- création d’une nouvelle version ;
- dépréciation ;
- retrait.

### Cohérence éventuelle

- diffusion aux moteurs d’autorisation ;
- invalidation de caches ;
- réévaluation de Sessions ou Resources.

---

## AGG-017 — Access Decision Aggregate

### Racine

`AccessDecision`

### Mission

Conserver le résultat traçable d’une évaluation d’autorisation significative.

### Invariants protégés

- Une décision référence Actor, Authority, Action, Target et Context.
- Le résultat appartient à un ensemble d’états reconnus.
- Les règles et versions de Policies évaluées sont référencées.
- Une décision enregistrée n’est pas modifiée ; une nouvelle évaluation crée une nouvelle décision.

### Cohérence immédiate

- création du résultat ;
- scellement des références ;
- enregistrement de justification ;
- classification du niveau d’audit.

### Cohérence éventuelle

- analyse ;
- agrégation ;
- détection d’anomalies ;
- reporting.

### Note

Toutes les vérifications ordinaires ne nécessitent pas un agrégat persistant complet. Le contrat d’implémentation distinguera les décisions éphémères des décisions significatives devant être conservées.

---

## AGG-018 — Contract Registry Aggregate

### Racine

`Contract`

### Mission

Protéger la définition versionnée des échanges officiels GAMAD.

### Invariants protégés

- Un Contract possède un identifiant canonique et une version.
- Une version publiée est immuable.
- Le producteur responsable est identifiable.
- Les schémas, erreurs et règles d’idempotence sont définis.
- Une dépréciation possède une période et une stratégie de migration.
- Une rupture de compatibilité exige une nouvelle version majeure.

### Cohérence immédiate

- création ;
- validation ;
- publication ;
- dépréciation ;
- retrait ;
- déclaration de compatibilité.

### Cohérence éventuelle

- génération de documentation ;
- publication dans les SDK ;
- validation des consommateurs ;
- mise à jour des passerelles API.

---

## AGG-019 — Subscription Aggregate

### Racine

`Subscription`

### Mission

Protéger la relation commerciale ou institutionnelle pouvant donner origine à des Entitlements.

### Invariants protégés

- Le souscripteur et l’offre sont identifiables.
- La période et l’état sont cohérents.
- Les changements d’offre sont historisés.
- Une Subscription expirée ne génère plus de nouveaux Entitlements actifs.
- La facturation ne devient pas source directe de Permission.

### Cohérence immédiate

- création ;
- activation ;
- renouvellement ;
- suspension ;
- changement d’offre ;
- résiliation ;
- expiration.

### Cohérence éventuelle

- création, mise à jour ou retrait d’Entitlements ;
- activation dans les applications ;
- notifications ;
- facturation externe.

---

## AGG-020 — Audit Record Aggregate

### Racine

`AuditRecord`

### Mission

Protéger l’intégrité d’une preuve durable concernant une action, une décision ou une modification significative.

### Invariants protégés

- Un Audit Record possède une identité unique.
- Il référence l’Actor, l’Authority, l’Application, le Context, la cible et le résultat lorsque ces éléments existent.
- Il est append-only après scellement.
- Les données sensibles inutiles en sont exclues.
- Toute correction produit un nouvel enregistrement lié, jamais une modification silencieuse.
- La corrélation entre opérations reste traçable.

### Cohérence immédiate

- création ;
- scellement ;
- établissement des références ;
- classification de conservation.

### Cohérence éventuelle

- réplication ;
- archivage longue durée ;
- export ;
- analyse ;
- scellement externe ou chaînage.

---

# PARTIE II — MATRICE DE COHÉRENCE

## 5. Cohérence immédiate et éventuelle

| Agrégat | Cohérence immédiate | Cohérence éventuelle |
|---|---|---|
| AGG-001 Identity | unicité, état, fusion, révocation | profils spécialisés, index, propagation |
| AGG-002 Person | intégrité du profil humain | comptes, memberships, synchronisation |
| AGG-003 User Account | état du compte, facteurs | sessions, notifications, fédération |
| AGG-004 Session | durée, révocation, confiance | propagation multi-nœuds |
| AGG-005 Organization | structure et lifecycle | memberships, policies, droits |
| AGG-006 Membership | statut et période | rôles, workspaces, notifications |
| AGG-007 Application | identité, scopes, secrets | API gateway, catalogues |
| AGG-008 Device | confiance, clés, lifecycle | agents, sessions, réseau |
| AGG-009 Agent | association, version, état | tâches, ressources publiées |
| AGG-010 Resource Registry | identité, responsabilité, type | contenu, index, permissions |
| AGG-011 Workspace | membres et références | caches, applications clientes |
| AGG-012 Module Registry | modules, capacités, versions | entitlements, catalogues |
| AGG-013 Entitlement | attribution, période, quota | activation technique |
| AGG-014 Role | définition et permissions | recalcul des accès |
| AGG-015 Role Assignment | attribution contextualisée | invalidation des droits |
| AGG-016 Policy | version, portée, publication | diffusion et réévaluation |
| AGG-017 Access Decision | décision significative scellée | analyse et reporting |
| AGG-018 Contract Registry | version et compatibilité | docs, SDK, gateways |
| AGG-019 Subscription | état commercial | entitlements et facturation |
| AGG-020 Audit Record | preuve append-only | archivage, analyse, scellement externe |

---

# PARTIE III — COORDINATION INTER-AGRÉGATS

## 6. Règle générale

Lorsqu’un processus implique plusieurs agrégats, chaque agrégat effectue sa propre transaction et publie le résultat par contrat.

```text
Commande
   ↓
Agrégat A modifié atomiquement
   ↓
Événement A produit durablement
   ↓
Process Manager ou consommateur
   ↓
Commande vers Agrégat B
   ↓
Agrégat B modifié atomiquement
```

Aucune transaction distribuée implicite ne doit masquer cette coordination.

---

## 7. Exemples de processus

### 7.1 Admission d’un membre

1. Vérifier que Person et Organization existent.
2. Créer Membership dans AGG-006.
3. Publier `MembershipActivated`.
4. Un Process Manager demande les Role Assignments nécessaires.
5. AGG-015 crée les affectations.
6. Les applications adaptent leurs accès.

La création du Membership et l’attribution des rôles ne sont pas une seule transaction.

### 7.2 Activation d’un module pour une organisation

1. Subscription ou décision institutionnelle est validée.
2. Un Entitlement est créé dans AGG-013.
3. `EntitlementActivated` est publié.
4. Les applications activent la Capability.
5. Les Roles et Policies nécessaires peuvent être ajoutés séparément.

### 7.3 Enregistrement d’une ressource publiée par un Agent

1. AGG-009 valide l’Agent.
2. Une commande d’enregistrement est envoyée à AGG-010.
3. AGG-010 crée la Resource avec Responsible Party et System of Record.
4. `ResourceRegistered` est publié.
5. GAMAD Drive conserve le contenu et le chemin métier.
6. Access Control calcule ensuite les droits applicables.

### 7.4 Révocation d’un appareil

1. AGG-008 révoque le Device.
2. `DeviceRevoked` est publié.
3. Les Agents associés sont suspendus par AGG-009.
4. Les Sessions concernées sont révoquées par AGG-004.
5. Les accès réseau et tokens sont retirés par les systèmes responsables.

---

## 8. Process Managers candidats

Les Process Managers suivants pourront être étudiés dans GENESIS-007C et les spécifications ultérieures :

- Member Onboarding Process
- Organization Provisioning Process
- Application Registration Process
- Device Enrollment Process
- Agent Association Process
- Resource Publication Process
- Module Activation Process
- Subscription Entitlement Process
- Access Revocation Process
- Identity Merge Process

Un Process Manager coordonne ; il ne devient pas propriétaire des invariants des agrégats participants.

---

# PARTIE IV — CONCURRENCE ET FIABILITÉ

## 9. Concurrence

Chaque agrégat doit disposer d’un mécanisme permettant de détecter les modifications concurrentes.

Le modèle logique devra prévoir au minimum :

- une version d’agrégat ;
- un contrôle optimiste ou équivalent ;
- une règle de nouvelle tentative ;
- un traitement explicite des conflits ;
- une interdiction d’écrasement silencieux.

---

## 10. Publication fiable des événements

Toute modification d’agrégat produisant un événement doit garantir que :

- la modification et l’intention de publication sont enregistrées atomiquement dans la même frontière locale ;
- un échec de transport ne perd pas l’événement ;
- une répétition de livraison ne répète pas les effets métier non idempotents ;
- les événements possèdent une identité, une version et une corrélation.

Le mécanisme concret sera décidé dans une ADR d’implémentation ultérieure.

---

## 11. Compensation

Lorsqu’un processus multi-agrégats échoue après une ou plusieurs étapes réussies :

- une compensation explicite peut être déclenchée ;
- la compensation ne doit pas prétendre effacer l’histoire ;
- chaque compensation produit ses propres Audit Records et Events ;
- certains processus peuvent rester en état `PENDING_REVIEW` au lieu d’être annulés automatiquement.

---

# PARTIE V — INTERDICTIONS

## 12. Pratiques interdites

- Agrégat universel `GamadCoreAggregate`.
- Transaction SQL englobant plusieurs agrégats métier.
- Modification directe des tables internes d’un autre agrégat.
- Références circulaires fortes entre agrégats.
- Chargement obligatoire d’un graphe complet de l’écosystème pour modifier un objet.
- Invariant partagé sans propriétaire.
- Suppression physique silencieuse d’un agrégat gouverné.
- Événement publié avant la validation de la transaction locale.
- Dépendance à l’ordre de livraison sans contrat explicite.
- Utilisation d’un cache comme source de vérité.
- Fusion d’Access Control, Organization et Resource dans un même agrégat géant.
- Confusion entre bounded context et agrégat.

---

# PARTIE VI — FRONTIÈRES ET ÉVOLUTIONS

## 13. Agrégats provisoires et agrégats stables

Les agrégats définis ici sont conceptuels et pourront être raffinés avant implémentation.

Une fusion ou séparation future exige une ADR lorsqu’elle :

- déplace un invariant ;
- change une frontière transactionnelle ;
- modifie le System of Record ;
- affecte des contrats publics ;
- introduit une nouvelle cohérence immédiate entre plusieurs concepts.

---

## 14. Critères de taille d’un agrégat

Un agrégat doit rester aussi petit que possible, mais assez grand pour protéger son invariant.

Il ne doit pas être agrandi pour :

- faciliter un écran ;
- éviter un appel API ;
- simplifier une jointure ;
- charger plus de données en une fois ;
- reproduire la structure d’un document métier.

---

## 15. Questions obligatoires avant création d’un agrégat

1. Quel invariant protège-t-il ?
2. Quelle est sa racine ?
3. Quelles modifications doivent être atomiques ?
4. Quelles données ne lui appartiennent pas ?
5. Quels agrégats référence-t-il uniquement par identité ?
6. Quels événements produit-il ?
7. Quelles commandes accepte-t-il ?
8. Comment gère-t-il la concurrence ?
9. Quel est son cycle de vie ?
10. Peut-il être reconstruit à partir de sa source de vérité ?

---

# PARTIE VII — SUITE DU CHANTIER

## 16. GENESIS-007C

**Bounded Contexts et responsabilités de domaine**

Il devra :

- regrouper les agrégats dans des contextes cohérents ;
- définir le langage propre à chaque contexte ;
- formaliser les frontières de dépendance ;
- identifier les contrats entre contextes ;
- empêcher la création d’un monolithe couplé.

## 17. GENESIS-007D

**Projection logique et validation finale**

Il devra :

- produire une vue logique complète ;
- vérifier les scénarios critiques ;
- préparer les schémas, API et événements ;
- identifier les contraintes de persistance ;
- consolider GENESIS-007 v1.0.

---

## 18. Déclaration finale

Les agrégats du GAMAD Core ne regroupent pas ce qui se ressemble.

Ils enferment ce qui doit rester vrai.

Chaque frontière transactionnelle protège un invariant précis, tandis que les coordinations plus larges restent explicites, traçables et reconstructibles.
