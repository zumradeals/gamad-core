# GENESIS-004 — Lexique Canonique du GAMAD Core

## Version 1.0

**Projet :** GAMAD Core  
**Statut :** Référence normative  
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD  
**Architecture :** SIRR — Architecte de GAMAD  
**Dépendances :**
- GENESIS-001 — Livre Blanc de GAMAD
- GENESIS-002 — Charte Fondatrice de GAMAD
- GENESIS-003 — GAMAD Core Charter
- ADR-0001 — Entity is an abstract concept, not a universal domain model
- ADR-0002 — Canonical vocabulary changes require architectural decision

---

## 1. Mission du Lexique

Le présent Lexique constitue le vocabulaire officiel du GAMAD Core.

Il définit le sens normatif des concepts utilisés dans :

- le Core ;
- les modules ;
- les applications ;
- les API ;
- les contrats ;
- les événements ;
- les cahiers des charges ;
- les ADR ;
- la documentation ;
- les outils d’administration ;
- les futurs composants de l’écosystème.

Aucun document officiel ne doit employer un terme canonique dans un sens contradictoire avec ce Lexique.

Lorsqu’un terme métier spécialisé est nécessaire, il doit rester dans son module et ne peut entrer dans le Canon du Core qu’après décision architecturale explicite.

---

## 2. Règles d’usage

1. Un terme canonique possède une seule définition normative.
2. Un synonyme courant peut être utilisé dans une interface, mais ne remplace pas le terme canonique dans les contrats et documents d’architecture.
3. Un concept nouveau doit démontrer qu’il ne recouvre pas un concept existant.
4. Une ambiguïté doit être résolue dans le Lexique avant d’être encodée dans le logiciel.
5. Les noms d’API, événements, permissions et capacités doivent utiliser les concepts du Canon.
6. Les concepts métier restent dans leurs modules responsables.

---

# LIVRE I — CONCEPTS FONDATEURS

## 3. Entity — Entité

**Définition normative :** Toute réalité humaine, organisationnelle, numérique ou technique reconnue par GAMAD et susceptible de recevoir une identité persistante.

**Rôle :** Fournir le concept abstrait permettant de parler de ce qui existe dans l’univers GAMAD.

**Inclut :** Personne, organisation, application, service, appareil, ressource et autres catégories reconnues.

**N’inclut jamais :** Un modèle métier universel, une table générale obligatoire ou un objet parent contenant toutes les données de tous les domaines.

**Relations :** Une Entity peut recevoir une Identity et être décrite par un domaine spécialisé.

**Exemple :** KIMBO AFRICA SA est une entité organisationnelle reconnue.

**Contre-exemple :** Une classe `Entity` qui contient à la fois des données RH, techniques, documentaires et financières.

---

## 4. Identity — Identité

**Définition normative :** Représentation persistante permettant au Core de reconnaître une entité dans le temps et à travers les systèmes.

**Rôle :** Assurer continuité, unicité de référence, révocation, fusion contrôlée et audit transversal.

**Inclut :** Identifiant stable, catégorie identitaire, état identitaire, relations de continuité.

**N’inclut jamais :** Mot de passe, session, profil métier complet ou toutes les données de l’entité.

**Relations :** Une Identity référence une Entity ; elle peut être liée à une Person, Organization, Application, Resource ou autre domaine spécialisé.

**Exemple :** `GAM-ORG-000041` identifie durablement une organisation.

**Contre-exemple :** Utiliser l’adresse email comme identité permanente.

---

## 5. Person — Personne

**Définition normative :** Être humain reconnu par GAMAD indépendamment de ses comptes, rôles et appartenances.

**Rôle :** Représenter l’humain réel dans la continuité.

**Inclut :** Référence identitaire humaine et attributs propres au domaine Person.

**N’inclut jamais :** Un compte utilisateur, une session, un rôle ou une permission.

**Relations :** Une Person peut posséder une Identity, plusieurs User Accounts et plusieurs Memberships.

**Exemple :** Franck est une Person même si son compte est suspendu.

**Contre-exemple :** Considérer chaque compte créé comme une nouvelle personne.

---

## 6. Organization — Organisation

**Définition normative :** Structure reconnue qui possède une gouvernance, une responsabilité et une continuité propres.

**Rôle :** Fournir un contexte institutionnel aux appartenances, politiques, ressources et capacités.

**Inclut :** Entreprise, association, branche, organisme, unité juridique ou structure reconnue.

**N’inclut jamais :** Un simple groupe informel, un tenant technique ou un abonnement commercial.

**Relations :** Une Organization possède une Identity, des Memberships, des Policies, des Resources et des Entitlements.

**Exemple :** IKOMA GROUP est une Organization.

**Contre-exemple :** Un dossier partagé appelé « Équipe commerciale » n’est pas nécessairement une Organization.

---

## 7. User Account — Compte utilisateur

**Définition normative :** Compte permettant à une personne ou à une identité autorisée d’accéder à une application.

**Rôle :** Porter les moyens d’accès applicatifs.

**Inclut :** Identifiant de connexion, état du compte, facteurs d’authentification associés, préférences techniques.

**N’inclut jamais :** L’identité complète de la personne, ses appartenances ou ses permissions définitives.

**Relations :** Un User Account peut représenter une Person et créer des Sessions après Authentication.

**Exemple :** `franck@entreprise.com` est un compte rattaché à Franck.

**Contre-exemple :** Utiliser le compte comme preuve suffisante de toutes les permissions.

---

## 8. Authentication — Authentification

**Définition normative :** Processus permettant de vérifier qu’un sujet contrôle les moyens d’accès associés à une identité ou à un compte.

**Rôle :** Établir une preuve d’accès avant toute décision d’autorisation.

**Inclut :** Mot de passe, clé, certificat, MFA, jeton, mécanisme d’identité fédérée.

**N’inclut jamais :** La décision finale de droit sur une ressource.

**Relations :** L’Authentication peut créer une Session ; l’Access Control décide ensuite des autorisations.

**Exemple :** Validation d’un mot de passe et d’un second facteur.

**Contre-exemple :** Être authentifié ne signifie pas pouvoir lire tous les documents.

---

## 9. Session — Session

**Définition normative :** Contexte temporaire d’accès établi après authentification et lié à un sujet, une application et une durée.

**Rôle :** Maintenir un accès contrôlé sans répéter constamment l’authentification.

**Inclut :** Durée, appareil, application, jetons, état de révocation.

**N’inclut jamais :** Une identité permanente ou une permission éternelle.

**Relations :** Une Session est créée par Authentication et utilisée par un Actor.

**Exemple :** Session Web de Franck valable huit heures.

**Contre-exemple :** Un jeton non révocable sans date d’expiration.

---

## 10. Membership — Appartenance

**Définition normative :** Relation gouvernée entre une Person et une Organization dans un contexte et une période déterminés.

**Rôle :** Représenter l’appartenance sans la confondre avec l’identité.

**Inclut :** Statut, dates, unité, rôle organisationnel, source d’autorité, restrictions.

**N’inclut jamais :** Une permission technique universelle ou une propriété de la personne elle-même.

**Relations :** Relie Person et Organization ; peut contribuer au calcul des Roles et Policies.

**Exemple :** Franck est membre actif de KIMBO dans le service informatique.

**Contre-exemple :** Inscrire définitivement « employé KIMBO » dans l’identité de Franck.

---

## 11. Actor — Acteur

**Définition normative :** Identité autorisée à accomplir une action dans un contexte donné.

**Rôle :** Porter la responsabilité opérationnelle et l’audit d’une action.

**Inclut :** Humain, application, service, agent, automatisation ou Copilote agissant sous une autorité explicite.

**N’inclut jamais :** Toute identité inactive ou non autorisée par défaut.

**Relations :** Un Actor agit via une Application, dans une Session ou avec une identité technique, sur une Resource selon une Permission.

**Exemple :** GAMAD Copilote agissant pour Franck dans KIMBO.

**Contre-exemple :** Attribuer une action uniquement à « système » sans identité ni autorité.

---

# LIVRE II — OBJETS GOUVERNÉS

## 12. Resource — Ressource

**Définition normative :** Unité gouvernée reconnue par le Core, possédant une identité, un responsable, un contexte de gouvernance et un système métier responsable.

**Rôle :** Fournir un objet commun d’autorisation, de relation et de cycle de vie.

**Inclut :** Référence, type, responsable, état, relations, politique d’accès, système propriétaire.

**N’inclut jamais :** Le contenu métier détaillé lorsqu’il appartient à un module spécialisé.

**Relations :** Une Resource possède une Identity, un Resource Type, un Lifecycle et un Responsible Party.

**Exemple :** Une ressource publiée « Projets 2026 » gouvernée par GAMAD Drive.

**Contre-exemple :** Stocker tout le contenu d’un document Word directement dans le registre Core des ressources.

---

## 13. Resource Type — Type de ressource

**Définition normative :** Classification contractuelle décrivant la nature gouvernée d’une ressource et les capacités applicables.

**Rôle :** Permettre des règles cohérentes sans confondre les contenus métier.

**Inclut :** Identifiant de type, version, système propriétaire, capacités déclarées.

**N’inclut jamais :** Une implémentation technique précise ou une classe de framework.

**Relations :** Qualifie une Resource et peut être déclaré par un Module.

**Exemple :** `drive.published-resource.v1`.

**Contre-exemple :** `LaravelFolderModel` comme type canonique.

---

## 14. Responsible Party — Responsable

**Définition normative :** Identité ou organisation explicitement responsable de la gouvernance d’une ressource.

**Rôle :** Éviter toute ressource sans responsabilité claire.

**Inclut :** Organisation, personne, projet conjoint ou autorité système reconnue.

**N’inclut jamais :** Une simple localisation technique.

**Relations :** Lié à Resource, Organization, Policy et Lifecycle.

**Exemple :** KIMBO est responsable de sa ressource documentaire interne.

**Contre-exemple :** Déclarer « serveur 01 » comme responsable juridique de la donnée.

---

## 15. Lifecycle — Cycle de vie

**Définition normative :** Ensemble explicite des états et transitions autorisés d’un objet gouverné.

**Rôle :** Contrôler l’évolution sans suppression implicite ni états ambigus.

**Inclut :** États, transitions, préconditions, effets, responsables.

**N’inclut jamais :** Une simple colonne `status` sans règles.

**Relations :** S’applique aux Identity, Organization, Resource, Application, Module et autres objets gouvernés.

**Exemple :** Draft → Active → Suspended → Archived → Retired.

**Contre-exemple :** Modifier librement un statut par mise à jour directe en base.

---

# LIVRE III — AUTORISATIONS ET CAPACITÉS

## 16. Permission — Permission

**Définition normative :** Autorisation explicite d’effectuer une action déterminée sur un objet ou une catégorie d’objets dans un contexte précis.

**Rôle :** Exprimer ce qu’un acteur peut faire.

**Inclut :** Action, cible, scope, conditions, durée et source de décision.

**N’inclut jamais :** Une capacité commerciale ou un simple affichage de menu.

**Relations :** Peut être accordée par Role, Policy ou Grant ; évaluée par Access Control.

**Exemple :** `resource.read` sur la ressource X dans l’organisation Y.

**Contre-exemple :** « admin » sans définition des actions autorisées.

---

## 17. Role — Rôle

**Définition normative :** Ensemble nommé de responsabilités ou permissions attribuables dans un contexte donné.

**Rôle :** Simplifier l’attribution cohérente de permissions.

**Inclut :** Nom, contexte, permissions, règles d’héritage éventuelles.

**N’inclut jamais :** L’identité d’une personne ou une fonction RH universelle.

**Relations :** Attribué à un Actor ou Membership ; évalué avec Policies.

**Exemple :** Gestionnaire de ressource.

**Contre-exemple :** Confondre « Directeur général » avec un droit technique absolu sur tout GAMAD.

---

## 18. Capability — Capacité

**Définition normative :** Fonction déclarée qu’un module ou une application sait fournir et que le Core peut autoriser pour une organisation.

**Rôle :** Décrire ce qui peut être utilisé ou activé.

**Inclut :** Nom canonique, version, dépendances, limites, module fournisseur.

**N’inclut jamais :** La permission d’un acteur précis.

**Relations :** Fournie par Module, accordée par Entitlement, consommée par Application.

**Exemple :** `docs.convert.pdf`.

**Contre-exemple :** `resource.read`, qui est une Permission.

---

## 19. Policy — Politique

**Définition normative :** Règle gouvernée influençant une décision, une contrainte ou un cycle de vie dans un contexte déterminé.

**Rôle :** Exprimer des règles transversales au-delà des permissions simples.

**Inclut :** Conditions, portée, priorité, durée, auteur, version.

**N’inclut jamais :** Du code métier caché sans représentation gouvernée.

**Relations :** Peut affecter Access Control, Lifecycle, Modules, Sessions, Devices et Resources.

**Exemple :** Interdire les téléchargements externes sur les ressources confidentielles.

**Contre-exemple :** Une condition dispersée dans plusieurs contrôleurs sans politique identifiable.

---

## 20. Entitlement — Droit de capacité

**Définition normative :** Attribution d’une capacité à une organisation ou un contexte, avec ses limites et son état d’usage.

**Rôle :** Séparer l’offre disponible de l’autorisation commerciale ou institutionnelle d’utiliser une capacité.

**Inclut :** Capacité, bénéficiaire, état, quota, période, origine.

**N’inclut jamais :** La permission opérationnelle d’un utilisateur spécifique.

**Relations :** Lie Organization, Capability, Module et Subscription éventuelle.

**Exemple :** KIMBO dispose de `backup.restore` jusqu’à une limite définie.

**Contre-exemple :** Donner automatiquement à tous les membres le droit de restaurer une sauvegarde.

---

# LIVRE IV — SYSTÈMES ET CONTEXTES

## 21. Module — Module

**Définition normative :** Ensemble cohérent de capacités partageant une responsabilité fonctionnelle clairement délimitée.

**Rôle :** Étendre GAMAD sans alourdir le Core.

**Inclut :** Capacités, contrats, événements, politiques, données métier propres.

**N’inclut jamais :** Les invariants transversaux appartenant au Core.

**Relations :** Fournit des Capabilities ; est utilisé par des Applications ; respecte les Contracts du Core.

**Exemple :** GAMAD Share.

**Contre-exemple :** Une classe interne de conversion PDF appelée « module ».

---

## 22. Application — Application

**Définition normative :** Système exécutable reconnu qui offre une expérience ou accomplit des actions en consommant des contrats GAMAD.

**Rôle :** Servir d’interface ou de client actif de l’écosystème.

**Inclut :** Web, mobile, desktop, Hub, Drive, service d’administration.

**N’inclut jamais :** Un simple ensemble de permissions ou un module conceptuel.

**Relations :** Possède une Identity, utilise des Contracts, peut devenir Actor.

**Exemple :** L’application Web GAMAD Drive.

**Contre-exemple :** `docs.convert`, qui est une Capability.

---

## 23. Service — Service

**Définition normative :** Composant exécutable spécialisé fournissant une fonction technique ou fonctionnelle par contrat.

**Rôle :** Exécuter une responsabilité précise sans interface utilisateur obligatoire.

**Inclut :** Conversion, OCR, notification, indexation, authentification, audit.

**N’inclut jamais :** Une application complète sans frontière claire.

**Relations :** Possède une Identity technique, expose ou consomme des Contracts, peut agir comme Actor.

**Exemple :** Service de conversion documentaire.

**Contre-exemple :** Toute la plateforme GAMAD appelée « service ».

---

## 24. Agent — Agent

**Définition normative :** Application ou service installé dans un environnement contrôlé pour exécuter localement des capacités au nom de GAMAD.

**Rôle :** Relier le Core et les ressources ou infrastructures locales.

**Inclut :** Agent serveur, Agent poste utilisateur, Agent appareil.

**N’inclut jamais :** Une personne ou un assistant conversationnel par défaut.

**Relations :** Possède une Application Identity, agit comme Actor et peut être lié à un Device.

**Exemple :** Agent Windows GAMAD Drive.

**Contre-exemple :** Appeler Copilote « Agent » sans contrat technique autonome.

---

## 25. Device — Appareil

**Définition normative :** Équipement physique ou instance matérielle reconnue et gouvernée dans l’écosystème.

**Rôle :** Porter une identité technique, un état de confiance et des relations d’usage.

**Inclut :** PC, serveur, téléphone, appliance, machine virtuelle lorsqu’elle est gouvernée comme appareil.

**N’inclut jamais :** L’utilisateur qui l’emploie.

**Relations :** Peut héberger une Application ou un Agent ; peut être autorisé, suspendu ou révoqué.

**Exemple :** Laptop de Franck enregistré dans GAMAD.

**Contre-exemple :** Confondre la révocation du compte de Franck avec la révocation de son appareil.

---

## 26. Tenant — Tenant

**Définition normative :** Partition technique d’isolation utilisée par une application pour séparer les données et traitements de plusieurs contextes.

**Rôle :** Assurer l’isolation opérationnelle dans une implémentation multi-tenant.

**Inclut :** Identifiant technique, règles d’isolation, contexte de stockage.

**N’inclut jamais :** Une Organization par définition.

**Relations :** Peut correspondre à une ou plusieurs Organizations selon le contrat de l’application.

**Exemple :** Partition technique dédiée à KIMBO dans GAMAD Drive.

**Contre-exemple :** Employer Tenant comme synonyme universel d’entreprise.

---

## 27. Workspace — Espace de travail

**Définition normative :** Contexte d’usage organisé permettant à des acteurs d’accéder à un ensemble cohérent de ressources et capacités.

**Rôle :** Structurer l’expérience sans modifier les identités fondamentales.

**Inclut :** Membres, ressources visibles, configuration, règles d’accès contextualisées.

**N’inclut jamais :** Une Organization obligatoire ou une ressource unique.

**Relations :** Peut appartenir à une Organization et regrouper des Resources.

**Exemple :** Espace de travail « Projet Hôtel ».

**Contre-exemple :** Utiliser Workspace comme remplacement du Core ou du Hub.

---

# LIVRE V — ÉCHANGES ET CONTRATS

## 28. API — Interface de programmation

**Définition normative :** Surface d’accès versionnée permettant à un consommateur d’interagir avec un système selon des contrats explicites.

**Rôle :** Protéger les frontières et découpler les implémentations.

**Inclut :** Endpoints, opérations, schémas, authentification, erreurs, versions.

**N’inclut jamais :** Un accès direct à la base ou une convention implicite.

**Relations :** Expose des Contracts et est gouvernée par API Governance.

**Exemple :** API de vérification d’une permission.

**Contre-exemple :** Un module lisant directement une table interne d’un autre module.

---

## 29. Contract — Contrat

**Définition normative :** Définition versionnée d’un échange autorisé entre producteurs et consommateurs.

**Rôle :** Constituer la source de vérité des interactions.

**Inclut :** Schéma, version, préconditions, postconditions, erreurs, idempotence, compatibilité et exigences d’audit.

**N’inclut jamais :** Un comportement tacite connu seulement du code.

**Relations :** Utilisé par API, Command, Event et Response.

**Exemple :** `ResourceRegistered.v1`.

**Contre-exemple :** « Le client sait qu’il faut envoyer ce champ même s’il n’est pas documenté. »

---

## 30. Command — Commande

**Définition normative :** Demande explicite d’exécuter une action future.

**Rôle :** Porter une intention adressée à un responsable identifié.

**Inclut :** Verbe, cible, acteur, contexte, idempotence, résultat attendu.

**N’inclut jamais :** Un fait déjà accompli.

**Relations :** Peut produire une Response et un ou plusieurs Events.

**Exemple :** `RegisterResource`.

**Contre-exemple :** `ResourceRegistered`, qui est un Event.

---

## 31. Event — Événement

**Définition normative :** Fait immuable déclaré comme ayant eu lieu dans le passé.

**Rôle :** Informer les systèmes sans dépendance directe.

**Inclut :** Type, version, producteur, date, identité de corrélation, données contractuelles.

**N’inclut jamais :** Une instruction d’action ou une intention future.

**Relations :** Peut résulter d’une Command et être consommé par plusieurs systèmes.

**Exemple :** `MembershipRevoked.v1`.

**Contre-exemple :** `PleaseRevokeMembership`.

---

## 32. Response — Réponse

**Définition normative :** Résultat contractuel retourné à la suite d’une requête ou d’une commande.

**Rôle :** Exprimer succès, refus, erreur ou état obtenu.

**Inclut :** Statut, données, erreurs, références de corrélation.

**N’inclut jamais :** Un événement métier durable par simple convention.

**Relations :** Associée à une API Operation ou Command.

**Exemple :** Résultat d’une vérification de permission.

**Contre-exemple :** Utiliser la réponse HTTP comme seul audit d’une action critique.

---

## 33. Notification — Notification

**Définition normative :** Message destiné à attirer l’attention d’un destinataire sur une information ou un événement.

**Rôle :** Informer sans constituer la source de vérité.

**Inclut :** Destinataire, canal, contenu, statut de remise.

**N’inclut jamais :** La décision métier ou l’événement d’origine.

**Relations :** Peut être déclenchée par un Event.

**Exemple :** Alerte de révocation d’un appareil.

**Contre-exemple :** Considérer l’email envoyé comme preuve unique de révocation.

---

# LIVRE VI — GOUVERNANCE ET PREUVES

## 34. Audit — Audit

**Définition normative :** Capacité organisée de reconstituer et vérifier les actions importantes de l’écosystème.

**Rôle :** Fournir responsabilité, preuve, enquête et conformité.

**Inclut :** Règles d’enregistrement, intégrité, conservation, accès et corrélation.

**N’inclut jamais :** L’ensemble brut des logs techniques.

**Relations :** Produit des Audit Records à partir d’actions, décisions et événements significatifs.

**Exemple :** Vérifier qui a partagé une ressource et sous quelle autorité.

**Contre-exemple :** Chercher une preuve métier uniquement dans les logs serveur.

---

## 35. Audit Record — Enregistrement d’audit

**Définition normative :** Enregistrement durable d’une action ou décision importante avec son acteur, son contexte, sa cible et son résultat.

**Rôle :** Constituer une preuve exploitable.

**Inclut :** Actor, autorité, Application, Organization, Resource, action, décision, date, résultat, corrélation.

**N’inclut jamais :** Secrets, mots de passe ou contenu sensible inutile.

**Relations :** Produit par Audit ; peut référencer Event, Command ou Permission Decision.

**Exemple :** Franck a révoqué l’accès externe à 14:32 via l’application Web.

**Contre-exemple :** « Request completed » sans acteur ni cible.

---

## 36. Technical Log — Log technique

**Définition normative :** Enregistrement opérationnel destiné au diagnostic, à l’observabilité et au débogage.

**Rôle :** Aider à comprendre le fonctionnement technique.

**Inclut :** Erreurs, métriques, traces de performance, messages de diagnostic.

**N’inclut jamais :** La preuve métier suffisante d’une action critique.

**Relations :** Complète Audit sans le remplacer.

**Exemple :** Temps de réponse d’un endpoint.

**Contre-exemple :** Utiliser uniquement le log HTTP pour prouver une validation contractuelle.

---

## 37. Trace — Trace

**Définition normative :** Suite corrélée d’opérations techniques permettant de suivre un traitement distribué.

**Rôle :** Observer le parcours d’une requête entre composants.

**Inclut :** Trace ID, spans, durées, erreurs techniques.

**N’inclut jamais :** Une décision métier complète ou une autorisation durable.

**Relations :** Liée aux Logs techniques et peut référencer un Audit Record.

**Exemple :** Parcours Core → Service OCR → Docs.

**Contre-exemple :** Confondre Trace ID et identité d’une ressource.

---

## 38. Invariant — Invariant

**Définition normative :** Règle fondamentale qui doit rester vraie dans tout état valide du système.

**Rôle :** Protéger la cohérence structurelle.

**Inclut :** Condition non négociable, portée et mécanisme de vérification.

**N’inclut jamais :** Une préférence d’interface ou une règle temporaire.

**Relations :** Défini par Charter, Laws et ADR ; vérifié par tests et contraintes.

**Exemple :** Toute action importante possède un acteur identifiable.

**Contre-exemple :** « Les boutons doivent être bleus. »

---

## 39. ADR — Architecture Decision Record

**Définition normative :** Document versionné enregistrant une décision architecturale, son contexte, les options examinées et ses conséquences.

**Rôle :** Préserver le raisonnement derrière les choix structurants.

**Inclut :** Contexte, décision, statut, conséquences, options rejetées.

**N’inclut jamais :** Une simple tâche de développement.

**Relations :** Peut modifier le Canon, les contrats ou les règles de conception.

**Exemple :** ADR-0001 sur le concept abstrait Entity.

**Contre-exemple :** « Corriger le bouton de connexion ».

---

## 40. Version — Version

**Définition normative :** Identifiant ordonné représentant l’état publié d’un contrat, document, module ou composant.

**Rôle :** Permettre évolution, compatibilité et traçabilité.

**Inclut :** Numéro, statut, date, notes de changement, politique de support.

**N’inclut jamais :** Une date implicite non documentée ou un contenu mutable sans historique.

**Relations :** S’applique aux Contracts, APIs, Events, Documents, Modules et Policies.

**Exemple :** `ResourceRegistered.v1`.

**Contre-exemple :** Modifier silencieusement un schéma existant sans changer de version.

---

# LIVRE VII — DISTINCTIONS NORMATIVES

## 41. Distinctions obligatoires

Les couples suivants ne sont jamais synonymes :

- Entity ≠ Identity
- Identity ≠ User Account
- User Account ≠ Person
- Authentication ≠ Authorization
- Identity ≠ Actor
- Organization ≠ Tenant
- Module ≠ Application
- Capability ≠ Permission
- Role ≠ Membership
- Resource ≠ Content
- Event ≠ Command
- Audit ≠ Technical Log
- Device ≠ Agent
- Workspace ≠ Organization
- Contract ≠ Implementation

---

## 42. Termes à usage contrôlé

Les termes suivants ne sont pas interdits dans tout GAMAD, mais leur usage dans le Core doit être contextualisé.

### Client

Dans l’architecture, préférer :

- Application consommatrice ;
- API Consumer ;
- Organization cliente, si le sens commercial est voulu.

### Objet

Préciser : Entity, Resource, Value Object ou objet technique selon le contexte.

### Groupe

Préciser : Organization, Team, Role Group, Workspace ou audience.

### Utilisateur

Préciser : Person, User Account ou Actor.

### Fichier et dossier

Ces termes sont légitimes dans GAMAD Drive et les modules documentaires. Dans le Core, ils ne doivent pas remplacer automatiquement Resource.

### Cloud

Terme légitime en communication et infrastructure. Dans les contrats du Core, préciser la notion technique : Storage Provider, Hosting Environment, Resource Location ou Infrastructure.

---

## 43. Hiérarchie canonique

### Niveau A — Concepts invariants

Entity, Identity, Person, Organization, Actor, Resource, Contract, Event.

### Niveau B — Concepts fondamentaux

Membership, User Account, Application, Module, Permission, Capability, Policy, Lifecycle, Audit.

### Niveau C — Concepts techniques gouvernés

Service, Agent, Device, Tenant, Workspace, Session, Trace, Technical Log.

### Niveau D — Concepts métier

Ils appartiennent aux modules et ne sont pas automatiquement intégrés au Canon du Core.

Exemples : Share Link, Backup Job, Mail Thread, Document Revision, Sync Conflict.

---

## 44. Procédure d’évolution du Canon

Tout nouveau concept candidat doit :

1. être proposé dans une ADR ;
2. démontrer qu’aucun concept canonique existant ne le couvre ;
3. fournir une définition normative ;
4. préciser ce qu’il inclut et exclut ;
5. définir ses relations ;
6. fournir exemple et contre-exemple ;
7. préciser son niveau canonique ;
8. obtenir validation architecturale ;
9. mettre à jour le Lexique avant utilisation dans les contrats ou le code.

---

## 45. Formule finale

Le Lexique Canonique n’est pas une liste de mots.

Il constitue la langue officielle par laquelle GAMAD décrit ce qui existe, ce qui agit, ce qui est gouverné et ce qui peut évoluer.

Une architecture durable commence par des mots stables.
