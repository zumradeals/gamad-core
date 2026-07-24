# SECURITY-GOVERNANCE-0001 — GOUVERNANCE DES ACCÈS, SECRETS, INCIDENTS ET DE LA CONTINUITÉ

## Genesis II — Version 0.1

**Statut : PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

- **Périmètre :** sécurité des identités et comptes, authentification, autorisation, privilèges, secrets, clés, systèmes, données sensibles, détection, vulnérabilités, incidents, sauvegardes, restaurations, continuité et reprise de GAMAD Core
- **Autorité de proposition :** Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD
- **Rédaction :** chantier GAMAD Core — Genesis II, avec assistance d’intelligence artificielle
- **Dépendances normatives :** ACTE-0001 ; SOURCES-0001 ; GOVERNANCE-0001 ; GOVERNANCE-0002 ; GOVERNANCE-0003 ; ENGINEERING-GOVERNANCE-0001
- **Principe directeur :** aucun accès, secret, compte, appareil, fournisseur ou automatisation ne doit pouvoir devenir un pouvoir incontrôlé sur GAMAD Core ; toute capacité sensible doit être attribuable, limitée, surveillée, révocable, restaurable et transmissible

---

## Préambule

GAMAD Core maintiendra des identités, des autorisations, des contrats, des preuves et des capacités communes dont dépendront les futurs produits de l’écosystème. Une faiblesse du Core peut donc devenir une faiblesse héritée par plusieurs applications, organisations ou générations.

La sécurité du Core ne peut être réduite à un pare-feu, à un mot de passe fort ou à la compétence d’un administrateur. Elle doit être gouvernée comme une chaîne complète :

> **Une identité est reconnue. Un mandat justifie l’accès. Une authentification établit l’acteur. Une autorisation limite l’action. La protection empêche ou réduit le dommage. La journalisation établit la preuve. L’incident est contenu. La restauration est vérifiée. L’enseignement devient une amélioration durable.**

Le présent texte ne suppose pas qu’un système puisse être rendu absolument invulnérable. Il exige que les risques soient identifiés, réduits, surveillés, assumés par une autorité compétente et traités sans falsification lorsqu’ils se réalisent.

Il protège également GAMAD Core contre les formes non techniques de capture : dépendance à une seule personne, possession privée des clés, comptes non transmissibles, secrets inconnus, sauvegardes jamais restaurées, fournisseurs irremplaçables, fausses preuves de sécurité et pouvoirs d’urgence devenus permanents.

---

# TITRE I — OBJET, CHAMP ET DÉFINITIONS

## Article 1 — Objet

Le présent texte définit les règles de gouvernance de sécurité applicables à GAMAD Core, depuis la reconnaissance d’une identité et l’attribution d’un accès jusqu’à la gestion des incidents, la restauration et la continuité.

## Article 2 — Champ d’application

Il s’applique aux personnes, organes, produits, realms, applications, services, agents artificiels, appareils, dépôts, pipelines, environnements, données, secrets, clés, sauvegardes et fournisseurs participant au fonctionnement du Core.

## Article 3 — Sécurité

La **sécurité** est l’ensemble gouverné de principes, décisions, contrôles et preuves visant à protéger la mission, les personnes, identités, données, capacités, services et archives du Core contre les actions ou événements non autorisés, accidentels ou malveillants.

## Article 4 — Actif

Un **actif** est toute réalité ayant une valeur ou une fonction à protéger, notamment une identité, une donnée, un contrat, un système, une clé, une preuve, un dépôt, une sauvegarde, un savoir ou une capacité institutionnelle.

## Article 5 — Acteur de sécurité

Un **acteur de sécurité** est une personne, un organe, une application, un service, un agent ou un appareil identifiable pouvant demander, exercer, contrôler ou subir une action de sécurité.

## Article 6 — Identité, compte et moyen d’accès

L’identité désigne la continuité de l’acteur reconnu. Le compte est un moyen d’interaction avec un système. Le moyen d’accès est un mécanisme permettant de prouver ou exercer certaines permissions.

Ces trois notions ne doivent pas être confondues.

## Article 7 — Authentification

L’**authentification** est le processus établissant avec un niveau d’assurance déterminé qu’un acteur est bien celui qu’il prétend être ou contrôle le moyen qui lui a été attribué.

## Article 8 — Autorisation

L’**autorisation** détermine si un acteur authentifié peut accomplir une action sur une ressource, dans un périmètre, pour une finalité, pendant une période et sous des conditions déterminées.

## Article 9 — Privilège

Un **privilège** est une permission dont l’usage peut modifier la sécurité, les identités, les droits, les données, les secrets, les environnements, les preuves ou la continuité du Core.

## Article 10 — Secret et clé

Un **secret** est une information dont la divulgation ou l’usage non autorisé peut créer un pouvoir, un dommage ou une usurpation.

Une **clé cryptographique** est un secret ou matériel spécialisé utilisé pour assurer notamment confidentialité, intégrité, authenticité, signature ou dérivation.

## Article 11 — Menace, vulnérabilité et risque

Une **menace** est une cause possible de dommage. Une **vulnérabilité** est une faiblesse exploitable ou susceptible de provoquer une défaillance. Un **risque** combine vraisemblance, exposition, impact et capacités de prévention ou de récupération.

## Article 12 — Contrôle de sécurité

Un **contrôle** est une mesure préventive, détective, corrective, dissuasive ou de récupération destinée à réduire un risque déterminé.

## Article 13 — Incident de sécurité

Un **incident de sécurité** est un événement confirmé ou fortement suspecté compromettant, menaçant ou contournant la confidentialité, l’intégrité, la disponibilité, l’authenticité, l’autorité, la preuve ou la continuité du Core.

## Article 14 — Sauvegarde, restauration et continuité

Une **sauvegarde** est une copie ou capacité de récupération protégée. La **restauration** est la reconstruction vérifiée d’un état utilisable. La **continuité** est la capacité de maintenir ou reprendre les fonctions essentielles malgré une défaillance, une attaque, une perte ou une indisponibilité.

---

# TITRE II — PRINCIPES DIRECTEURS DE SÉCURITÉ

## Article 15 — Primauté de la mission

La sécurité protège la mission de GAMAD et du Core. Elle ne doit ni devenir un prétexte à l’appropriation du système ni empêcher arbitrairement les usages légitimes.

## Article 16 — Protection des personnes et des identités

La sécurité du Core vise en premier lieu à éviter que les personnes, identités et communautés soient exposées, usurpées, surveillées, discriminées ou privées de leurs droits sans autorité et finalité légitimes.

## Article 17 — Défense en profondeur

Les fonctions critiques ne reposent pas sur un contrôle unique. Des mesures indépendantes et complémentaires réduisent la probabilité qu’une seule défaillance compromette tout le Core.

## Article 18 — Refus par défaut

Tout accès, flux, privilège, exception ou usage de secret non explicitement autorisé est réputé interdit.

## Article 19 — Moindre pouvoir nécessaire

Chaque acteur reçoit seulement les permissions nécessaires à sa mission, pour le périmètre, la finalité et la durée nécessaires.

## Article 20 — Besoin de connaître et besoin d’agir

L’accès à une information ou capacité sensible exige un besoin légitime de la connaître ou de l’utiliser. La curiosité, le rang général ou la possession technique d’un compte ne suffisent pas.

## Article 21 — Séparation des fonctions

La demande, l’approbation, l’attribution, l’usage, la surveillance, la révocation et l’audit des privilèges sont des fonctions distinctes.

## Article 22 — Autorité explicite

Tout contrôle ou pouvoir de sécurité doit être relié à une norme, une décision, un mandat ou une délégation identifiable.

## Article 23 — Traçabilité des actions sensibles

Toute action critique permet de distinguer l’identité, le compte, la fonction, le mandat, le moyen technique, la décision d’autorisation, la ressource, le résultat et les preuves.

## Article 24 — Sécurité par défaut et par conception

Les systèmes, produits et contrats doivent être conçus avec des valeurs initiales sûres, des privilèges limités et des chemins d’erreur qui réduisent le dommage.

## Article 25 — Hypothèse de compromission

La gouvernance ne suppose pas qu’un réseau, un appareil, un compte ou un fournisseur est fiable de manière permanente. Les accès sensibles sont vérifiés, limités et surveillés continuellement selon le risque.

## Article 26 — Proportionnalité

Les contrôles sont proportionnés à la criticité, à la sensibilité, à l’impact, à l’exposition, à l’irréversibilité et à la capacité de récupération.

## Article 27 — Réversibilité et récupération

Une mesure de sécurité ne peut être considérée suffisante si le Core ne sait pas révoquer l’accès, remplacer le secret, isoler la capacité, restaurer les données ou continuer le service.

## Article 28 — Minimisation

Le Core collecte, expose, copie, conserve et journalise seulement les informations nécessaires à une finalité légitime et déclarée.

## Article 29 — Agilité cryptographique

Les algorithmes, formats, certificats, clés et fournisseurs cryptographiques doivent pouvoir être remplacés sans réécrire la mission ni perdre la continuité des preuves.

## Article 30 — Non-capture

Aucune personne, entreprise, équipe, clé, compte, coffre, fournisseur ou agent ne doit contrôler seul et durablement les accès maîtres, les secrets, les sauvegardes, les preuves et les moyens de succession.

## Article 31 — Sécurité vérifiable

Une affirmation de sécurité doit être reliée à un périmètre, une exigence, un contrôle et une preuve. Les labels, certifications ou tableaux de bord ne doivent pas être présentés au-delà de ce qu’ils démontrent réellement.

---

# TITRE III — AUTORITÉS, RESPONSABILITÉS ET SÉPARATION DES POUVOIRS

## Article 32 — Autorité de sécurité

L’Autorité de sécurité définit les politiques et contrôles, gouverne les risques, supervise les secrets et incidents, contrôle la restauration et peut déclencher les mesures conservatoires prévues dans son mandat.

## Article 33 — Limites de l’Autorité de sécurité

L’Autorité de sécurité ne peut modifier seule la mission, créer une autorité institutionnelle, dissimuler un incident, effacer une preuve ou prolonger indéfiniment des pouvoirs d’urgence.

## Article 34 — Responsable de sécurité

Le Responsable de sécurité coordonne les activités quotidiennes, rend compte des risques et contrôles non satisfaits et maintient les dossiers nécessaires aux décisions de sécurité.

## Article 35 — Autorité des identités et accès

Une fonction compétente gouverne le cycle des comptes, authentificateurs, permissions, rôles, politiques d’accès et privilèges, en coordination avec l’Identity Registry, l’autorité institutionnelle et les responsables de capacités.

## Article 36 — Gardien des secrets et clés

Le Gardien des secrets ou clés assure la conservation, l’usage contrôlé, la rotation, la récupération et la révocation des matériels confiés, sans devenir propriétaire des systèmes ou données protégés.

## Article 37 — Commandant d’incident

Le Commandant d’incident coordonne temporairement la réponse, la stabilisation, les décisions urgentes, la collecte des preuves et la restauration dans les limites de son mandat.

## Article 38 — Gardien de continuité

Le Gardien de continuité veille aux analyses d’impact, plans de reprise, sauvegardes, tests de restauration, suppléances et dossiers de transmission.

## Article 39 — Autorité d’exploitation

L’Autorité d’exploitation applique les contrôles, surveille les environnements, exécute les actions autorisées et conserve les preuves opérationnelles sans choisir seule les normes applicables.

## Article 40 — Autorité d’ingénierie

L’Autorité d’ingénierie traduit les exigences de sécurité dans les architectures, contributions, tests, pipelines, versions et procédures de déploiement.

## Article 41 — Autorité des données

L’Autorité des données gouverne la classification, les finalités, la minimisation, la rétention, la portabilité et les échanges, en coordination avec la sécurité sans lui transférer la propriété de toutes les données.

## Article 42 — Responsables de capacités souveraines

Chaque capacité souveraine, notamment Identity Registry, authentification, autorisation, audit ou gestion des secrets, possède une responsabilité de sécurité et de continuité identifiable.

## Article 43 — Autorités métier des produits

Les produits protègent leurs règles et données spécialisées, respectent les contrôles communs et signalent les incidents susceptibles d’affecter le Core ou d’autres produits.

## Article 44 — Audit indépendant

L’Audit vérifie les mandats, accès, secrets, contrôles, incidents, sauvegardes et restaurations sans devenir opérateur permanent des systèmes contrôlés.

## Article 45 — Délégations et cumuls

Toute délégation de sécurité est expresse, limitée, datée et révocable. Les cumuls transitoires reçoivent des contrôles compensatoires, notamment double validation, journalisation renforcée ou revue postérieure.

---

# TITRE IV — ACTIFS, CLASSIFICATION, MENACES, RISQUES ET CONTRÔLES

## Article 46 — Inventaire des actifs

Les actifs critiques sont inventoriés avec leur identité, finalité, propriétaire institutionnel, responsable opérationnel, localisation, dépendances, classification et conditions de récupération.

## Article 47 — Propriétaire institutionnel et gardien technique

Le propriétaire institutionnel décide de la finalité et de la criticité. Le gardien technique applique les protections. La garde technique ne transfère pas la propriété institutionnelle.

## Article 48 — Criticité

Chaque capacité est classée selon l’impact de sa compromission ou indisponibilité sur la mission, les personnes, identités, données, produits, obligations et continuité.

## Article 49 — Classes d’information

Les informations sont classées au minimum comme :

- `PUBLIQUE` ;
- `INTERNE` ;
- `CONFIDENTIELLE` ;
- `SENSIBLE` ;
- `CRITIQUE`.

## Article 50 — Information publique

Une information publique peut être diffusée, mais son intégrité, son authenticité et sa version peuvent rester critiques.

## Article 51 — Information interne

Une information interne est accessible aux acteurs autorisés de l’écosystème pour une finalité professionnelle ou institutionnelle définie.

## Article 52 — Information confidentielle

Une information confidentielle exige une limitation des accès, une protection en transit et au repos selon le risque, ainsi qu’une rétention contrôlée.

## Article 53 — Information sensible

Une information sensible peut affecter significativement une personne, une identité, une organisation, une autorisation, une sécurité ou une réputation et reçoit des contrôles renforcés.

## Article 54 — Information critique

Une information critique comprend notamment les clés maîtresses, secrets de récupération, preuves racines, données de sécurité ou éléments dont la compromission menace l’ensemble du Core.

## Article 55 — Règles de manipulation

Chaque classe définit les canaux autorisés, personnes éligibles, conditions de copie, journalisation, durée, destruction et procédure de déclassement.

## Article 56 — Modélisation des menaces

Les capacités structurantes font l’objet d’une analyse des actifs, acteurs, frontières, abus possibles, dépendances, impacts et contrôles avant leur adoption ou mise en production.

## Article 57 — Évaluation du risque

Une évaluation de risque identifie la menace, la vulnérabilité, l’exposition, les impacts, les contrôles existants, le risque résiduel, l’autorité compétente et la date de réexamen.

## Article 58 — Niveaux de risque de sécurité

Le risque est classé de `S0` à `S4`, où `S0` représente un risque négligeable ou local et `S4` un risque systémique, irréversible, générationnel ou menaçant la souveraineté du Core.

## Article 59 — Catalogue de contrôles

Genesis II maintient un catalogue reliant les risques aux contrôles, responsables, preuves, fréquences de test, exceptions et dépendances applicables.

---

# TITRE V — IDENTITÉS, COMPTES ET CYCLE DE VIE

## Article 60 — Identité persistante

Tout acteur significatif reçoit une identité persistante appropriée à sa nature : personne, organe, organisation, application, service, agent, appareil ou autre catégorie reconnue.

## Article 61 — Séparation personne-compte

La suspension, suppression ou remplacement d’un compte ne supprime pas l’identité de la personne ni son histoire institutionnelle.

## Article 62 — Identités de service

Chaque service ou automatisation critique utilise une identité distincte, attribuable à un propriétaire, une mission et un environnement.

## Article 63 — Identités d’agents artificiels

Un agent artificiel significatif possède une identité technique distincte de son parrain, de l’utilisateur qui le déclenche et du fournisseur qui l’exécute.

## Article 64 — Identités d’appareils

Les appareils capables d’accéder à des fonctions sensibles sont identifiables, enregistrés et évalués selon leur état, leur propriétaire et leur niveau de confiance.

## Article 65 — Interdiction de l’autorité anonyme

Les comptes `admin`, `root`, `system`, `owner` ou équivalents ne suffisent jamais à identifier l’autorité humaine ou organisationnelle responsable.

## Article 66 — Comptes partagés

Les comptes humains partagés sont interdits pour les actions sensibles, sauf procédure exceptionnelle assurant attribution individuelle, durée limitée et revue postérieure.

## Article 67 — Création de compte

La création d’un compte exige une identité, une finalité, un propriétaire, un périmètre, une durée ou condition de réexamen et une source d’autorité.

## Article 68 — Preuve d’identité

Le niveau de vérification d’identité est proportionné aux pouvoirs, données et risques associés au compte.

## Article 69 — Comptes multiples

Une même personne peut utiliser des comptes distincts pour les activités ordinaires, privilégiées, de test ou d’urgence afin de réduire l’exposition et clarifier les actions.

## Article 70 — Cycle arrivée-mobilité-départ

Les changements d’arrivée, fonction, équipe, mandat, suspension, départ ou décès déclenchent la réévaluation des comptes, authentificateurs, permissions, secrets et responsabilités.

## Article 71 — Compte dormant

Un compte inactif au-delà de la période définie est signalé, suspendu ou soumis à réactivation contrôlée selon sa criticité.

## Article 72 — Compte orphelin

Un compte sans propriétaire, mandat ou usage légitime identifiable est suspendu et traité comme une anomalie de sécurité.

## Article 73 — Suspension

La suspension réduit immédiatement les capacités nécessaires à la maîtrise du risque sans effacer l’identité, les journaux, les décisions ou les preuves.

## Article 74 — Fin de fonction

La fin d’une fonction entraîne la révocation ou transformation des accès, la rotation des secrets concernés, le transfert des responsabilités et la conservation de la trace historique.

## Article 75 — Récupération de compte

La récupération vérifie l’identité, le contexte, les risques d’usurpation et les moyens de contact sans reposer sur un unique canal facilement capturable.

## Article 76 — Registre des identités et comptes sensibles

Les comptes privilégiés, de service, d’agents et de secours sont reliés au Registre des autorités, aux mandats, aux propriétaires et aux environnements concernés.

---

# TITRE VI — AUTHENTIFICATION, MOYENS D’ACCÈS ET SESSIONS

## Article 77 — Politique d’authentification

Chaque capacité définit le niveau d’assurance requis selon le risque, le type d’acteur, l’environnement, la sensibilité et l’action demandée.

## Article 78 — Authentification multifacteur

Les comptes sensibles exigent plusieurs facteurs indépendants lorsque cela réduit réellement le risque d’usurpation.

## Article 79 — Authentification privilégiée

Les comptes administratifs, de sécurité, de publication, de secrets ou de production utilisent des moyens renforcés, résistants autant que possible au hameçonnage et séparés des usages ordinaires.

## Article 80 — Authentification renforcée contextuelle

Une action critique peut exiger une réauthentification ou un facteur supplémentaire selon le montant du risque, l’appareil, la localisation, l’heure, l’anomalie ou la sensibilité.

## Article 81 — Mots de passe

Lorsque des mots de passe sont utilisés, ils sont stockés avec des mécanismes adaptés, ne sont pas révélés aux opérateurs et ne doivent pas être réutilisés ou transmis de manière non contrôlée.

## Article 82 — Moyens matériels et passkeys

Des moyens matériels, certificats, passkeys ou mécanismes équivalents peuvent être privilégiés pour les privilèges élevés, sans créer une dépendance exclusive à un fournisseur.

## Article 83 — Identifiants de service

Les services utilisent des identifiants dédiés, de préférence temporaires, limités à une audience, une action et un environnement.

## Article 84 — Délivrance

Tout authentificateur est délivré à une identité connue, selon une procédure enregistrée précisant sa finalité, son niveau d’assurance et sa récupération.

## Article 85 — Conservation

Les authentificateurs et secrets de récupération sont conservés de manière à empêcher leur lecture ou copie non autorisée.

## Article 86 — Rotation

Les authentificateurs sont renouvelés selon leur nature, criticité, durée, exposition, changement de titulaire ou suspicion de compromission.

## Article 87 — Révocation

La révocation doit pouvoir être exécutée rapidement et propagée aux systèmes dépendants dans un délai proportionné au risque.

## Article 88 — Compromission présumée

Un moyen d’accès perdu, partagé, exposé, dupliqué ou utilisé de manière anormale est suspendu ou révoqué jusqu’à vérification.

## Article 89 — Sessions

Chaque session possède une identité, une date de création, une durée, un contexte, un niveau d’assurance et une capacité de révocation.

## Article 90 — Durée et inactivité

Les sessions sensibles expirent après une durée ou une période d’inactivité proportionnée au risque et ne deviennent pas permanentes par commodité.

## Article 91 — Changement de contexte

Un changement significatif d’appareil, de réseau, de localisation, de privilège ou de comportement peut provoquer une nouvelle vérification ou une suspension.

## Article 92 — Journal d’authentification

Les succès, échecs, récupérations, changements de facteurs, élévations et révocations sont journalisés sans exposer les secrets utilisés.

---

# TITRE VII — AUTORISATIONS, PRIVILÈGES ET ACCÈS D’URGENCE

## Article 93 — Politique explicite

Toute autorisation importante identifie l’acteur, la ressource, l’action, le périmètre, la finalité, les conditions, la durée et l’autorité d’origine.

## Article 94 — Rôle et permission

Un rôle regroupe des responsabilités. Une permission autorise une action. Les systèmes doivent pouvoir distinguer le titre humain, la fonction, le mandat, le rôle technique et les permissions effectives.

## Article 95 — Demande d’accès

La demande indique la finalité, les ressources, actions, données, environnement, durée, niveau de privilège et responsable de la mission.

## Article 96 — Approbation

L’approbateur vérifie la compétence, le besoin, les conflits, la séparation des fonctions et les risques avant l’attribution.

## Article 97 — Attribution

L’attribution technique correspond exactement à l’autorisation approuvée. Tout écart est bloqué ou enregistré comme exception.

## Article 98 — Activation

Un accès n’est activé qu’après vérification de l’identité, du mandat, des approbations, des contrôles et de la journalisation nécessaires.

## Article 99 — Accès limité dans le temps

Les privilèges sensibles sont temporaires ou soumis à une échéance et un réexamen explicites.

## Article 100 — Accès juste-à-temps

Lorsque possible, les privilèges élevés sont activés seulement pour l’action autorisée et retirés automatiquement après la durée ou l’achèvement prévu.

## Article 101 — Finalité et environnement

Une permission accordée pour le test, l’audit, la récupération ou la préproduction ne peut être utilisée en production ou pour une autre finalité sans nouvelle autorisation.

## Article 102 — Niveaux de privilège

Les privilèges sont classés au minimum comme :

- `P0` — usage ordinaire ;
- `P1` — accès interne limité ;
- `P2` — accès élevé à un domaine ;
- `P3` — administration critique ;
- `P4` — pouvoir souverain, racine ou de récupération générale.

## Article 103 — Privilège P4

Un privilège `P4` est exceptionnel, limité, fortement journalisé, soumis à double contrôle lorsque possible et ne doit pas être détenu durablement par une seule personne.

## Article 104 — Accès aux données

L’autorisation distingue la consultation, la création, la modification, l’export, le partage, la suppression, la restauration et l’administration des règles de données.

## Article 105 — Accès inter-domaines

Un produit, service ou realm n’accède aux données ou capacités d’un autre domaine que par un contrat et une autorisation explicites.

## Article 106 — Accès de service à service

Les échanges entre services utilisent des identités, audiences, contrats, permissions et durées vérifiables sans se fier uniquement à la localisation réseau.

## Article 107 — Séparation des tâches

Les opérations critiques peuvent exiger que l’auteur, l’approbateur, l’exécutant et le contrôleur soient différents.

## Article 108 — Double autorisation

Les suppressions irréversibles, rotations racines, restaurations souveraines, changements de politiques maîtresses et autres opérations définies comme critiques peuvent exiger deux autorisations indépendantes.

## Article 109 — Révision périodique

Les accès sont périodiquement recertifiés par les autorités et propriétaires compétents, avec priorité aux privilèges, comptes de service et accès externes.

## Article 110 — Dérive d’accès

Les différences entre les permissions approuvées et les permissions effectives sont détectées, signalées et corrigées.

## Article 111 — Accès de secours

Un accès de secours ou `break-glass` est protégé, testé, surveillé, réservé aux situations prévues et ne doit pas devenir un raccourci administratif ordinaire.

## Article 112 — Usage de secours

Tout usage de secours indique le motif, l’autorité, l’utilisateur, les actions, les systèmes affectés, la durée et les preuves, puis reçoit une revue postérieure.

## Article 113 — Révocation et registre

La fin du besoin, du mandat, de l’urgence ou de la durée entraîne la révocation. Les privilèges `P2` à `P4` sont inscrits dans un Registre des accès privilégiés.

---

# TITRE VIII — SECRETS, CLÉS, CERTIFICATS ET MATÉRIEL CRYPTOGRAPHIQUE

## Article 114 — Inventaire des secrets

Tout secret critique possède une référence, un propriétaire institutionnel, un gardien, une finalité, des consommateurs, un niveau, une durée, une rotation et une procédure de récupération.

## Article 115 — Classification des secrets

Les secrets sont classés selon le pouvoir qu’ils confèrent, les systèmes affectés, leur capacité de délégation et les conséquences de leur divulgation ou perte.

## Article 116 — Génération

Les secrets et clés sont générés avec des sources et procédures adaptées à leur usage, sans valeurs prévisibles, réutilisées ou choisies pour la commodité humaine.

## Article 117 — Algorithmes et paramètres

Les algorithmes, longueurs, formats et paramètres autorisés sont définis par des normes techniques révisables selon l’état des risques et besoins de compatibilité.

## Article 118 — Conservation protégée

Les secrets ne sont conservés que dans des systèmes ou supports adaptés à leur classification, avec contrôle des accès, journalisation et récupération.

## Article 119 — Matériel de protection

Les clés racines ou souveraines peuvent exiger un matériel cryptographique dédié, un coffre hors ligne ou un mécanisme équivalent selon le risque et les capacités disponibles.

## Article 120 — Connaissance partagée et double contrôle

Lorsque le risque l’exige, aucun titulaire ne doit connaître ou utiliser seul la totalité d’un secret racine. La séparation des fragments, rôles ou autorisations est documentée et testée.

## Article 121 — Accès aux secrets

L’accès humain direct à un secret applicatif est évité lorsque le système peut l’utiliser sans l’exposer. Tout accès exceptionnel reste attribuable et justifié.

## Article 122 — Interdiction dans les dépôts et journaux

Aucun secret actif, clé privée, mot de passe, jeton ou donnée de récupération ne doit apparaître dans les dépôts, tickets, messages, images, artefacts ou journaux ordinaires.

## Article 123 — Injection contrôlée

Les secrets sont injectés à l’exécution par des mécanismes limités à l’identité, au service, à l’environnement et à la durée nécessaires.

## Article 124 — Transmission

La transmission d’un secret utilise des canaux appropriés et évite les copies persistantes non nécessaires.

## Article 125 — Rotation planifiée

Chaque secret possède une politique de rotation adaptée à sa criticité, sa durée, son exposition, son usage et la capacité des consommateurs à supporter le changement.

## Article 126 — Déclencheurs de rotation

Une rotation est notamment déclenchée par compromission, départ, changement de gardien, exposition, changement d’algorithme, fin de contrat ou doute sur l’intégrité.

## Article 127 — Révocation

La révocation d’une clé ou d’un certificat doit pouvoir être propagée aux systèmes dépendants et accompagnée d’un remplacement ou d’un mode dégradé sûr.

## Article 128 — Destruction

La destruction d’un secret obsolète est contrôlée, proportionnée au support et compatible avec les obligations de preuve, d’archive et de vérification historique.

## Article 129 — Sauvegarde des secrets

La sauvegarde des secrets de récupération est séparée des données qu’ils protègent, chiffrée, contrôlée et testée.

## Article 130 — Récupération

La récupération d’un secret critique exige une autorité, une identité, une procédure, des preuves et, selon le niveau, plusieurs participants.

## Article 131 — Clés racines

Les clés racines, maîtresses ou de signature institutionnelle reçoivent une gouvernance dédiée, des détenteurs identifiés, une continuité et une procédure de remplacement.

## Article 132 — Cérémonie de clé

La création, rotation, récupération ou destruction d’une clé racine peut faire l’objet d’une cérémonie documentant participants, rôles, matériel, empreintes, étapes, résultats et anomalies.

## Article 133 — Certificats

Les certificats possèdent une identité, une finalité, une durée, une chaîne de confiance, une procédure de renouvellement, une révocation et un responsable.

## Article 134 — Compromission

Toute exposition réelle ou suspectée d’un secret critique est traitée comme un incident jusqu’à rotation, révocation, analyse des usages et maîtrise du risque.

## Article 135 — Registre cryptographique

Les clés, certificats, secrets racines, coffres et mécanismes de récupération sont inscrits dans un Registre cryptographique sans y révéler leur valeur secrète.

---

# TITRE IX — SYSTÈMES, RÉSEAUX, DONNÉES, JOURNAUX ET DÉTECTION

## Article 136 — Architecture sécurisée

Les frontières, flux, dépendances, interfaces d’administration et chemins de confiance sont explicitement identifiés et examinés selon le risque.

## Article 137 — Segmentation

Les capacités, environnements, realms et plans d’administration sont isolés afin qu’une compromission locale ne donne pas automatiquement accès à l’ensemble du Core.

## Article 138 — Plan d’administration

Les interfaces d’administration sont séparées des usages ordinaires, limitées aux identités et réseaux autorisés et protégées par des contrôles renforcés.

## Article 139 — Flux réseau

Les flux entrants et sortants sont explicitement autorisés, documentés, limités à la finalité nécessaire et surveillés selon leur criticité.

## Article 140 — Accès distant

L’administration distante exige une identité forte, un appareil approprié, un canal protégé, une durée limitée et une journalisation.

## Article 141 — Durcissement

Les systèmes utilisent des configurations sûres, réduisent les services inutiles, désactivent les valeurs par défaut dangereuses et maintiennent une base de durcissement révisable.

## Article 142 — Correctifs

Les correctifs de sécurité sont évalués, testés et déployés selon l’exposition, l’exploitabilité, l’impact et la capacité de rollback.

## Article 143 — Appareils d’administration

Les appareils utilisés pour les privilèges élevés reçoivent des protections, mises à jour, chiffrement, verrouillage, surveillance et séparation des usages appropriés.

## Article 144 — Données en transit et au repos

Les données sensibles et critiques sont protégées en transit et au repos selon les menaces, contrats et exigences applicables.

## Article 145 — Minimisation des copies

Les copies, exports, caches, journaux, index et sauvegardes de données sensibles sont inventoriés et réduits au nécessaire.

## Article 146 — Masquage et pseudonymisation

Les environnements, rapports et tests utilisent des données synthétiques, masquées ou pseudonymisées lorsque l’identité réelle n’est pas nécessaire.

## Article 147 — Suppression et rétention

La suppression respecte la finalité, les obligations, les droits, les sauvegardes et les preuves. Une donnée ne doit pas survivre indéfiniment par oubli technique.

## Article 148 — Journalisation obligatoire

Les événements critiques d’identité, authentification, autorisation, secret, administration, données, configuration, déploiement, incident et restauration sont journalisés.

## Article 149 — Contexte des journaux

Un journal critique relie autant que possible l’identité, le compte, la fonction, la session, la ressource, l’action, la décision, le résultat, l’environnement et l’heure.

## Article 150 — Temps fiable

Les systèmes critiques maintiennent une référence temporelle suffisamment fiable pour ordonner les événements et reconstruire les incidents.

## Article 151 — Intégrité des journaux

Les journaux sensibles sont protégés contre l’altération, la suppression, la désactivation silencieuse et l’accès non autorisé.

## Article 152 — Secrets dans les journaux

Les journaux ne doivent pas contenir les mots de passe, clés privées, jetons complets, données de récupération ou contenus sensibles non nécessaires.

## Article 153 — Rétention des journaux

La durée de conservation est définie selon les besoins d’audit, sécurité, continuité, données et obligations applicables, avec suppression contrôlée.

## Article 154 — Détection

Des règles, seuils et analyses détectent les comportements anormaux, élévations, échecs répétés, exfiltrations, dérives, altérations de preuve et usages de secours.

## Article 155 — Alertes

Une alerte possède un propriétaire, une criticité, une procédure de triage, un délai de traitement et une capacité d’escalade.

## Article 156 — Lacunes de surveillance

Une source de journal absente, un agent désactivé, une règle muette ou une visibilité insuffisante est enregistrée comme risque ou incident selon son impact.

## Article 157 — Preuves et chaîne de garde

Les éléments destinés à établir un fait de sécurité sont collectés, copiés, conservés et transmis avec leur origine, leur intégrité, leurs détenteurs et leurs transformations.

---

# TITRE X — VULNÉRABILITÉS, TESTS ET DIVULGATION

## Article 158 — Sources de vulnérabilités

Les vulnérabilités peuvent être découvertes par tests, audits, utilisateurs, chercheurs, fournisseurs, incidents, outils automatiques ou analyses de dépendances.

## Article 159 — Canal de signalement

GAMAD Core maintient un moyen identifiable et protégé permettant de signaler de bonne foi une faiblesse ou un incident.

## Article 160 — Absence de représailles abusives

Un signalement de bonne foi est examiné sans représailles abusives, même lorsqu’il révèle une erreur interne ou une faiblesse importante.

## Article 161 — Validation et triage

Chaque signalement reçoit une référence, une vérification, une classification, un propriétaire, des mesures immédiates et une décision de traitement.

## Article 162 — Gravité contextuelle

La gravité tient compte de l’exploitabilité, de l’exposition, des privilèges nécessaires, des personnes et données affectées, de la portée systémique et de la capacité de récupération.

## Article 163 — Traitement

Le traitement peut comprendre correction, mitigation, isolation, désactivation, surveillance renforcée, remplacement, acceptation temporaire du risque ou retrait du composant.

## Article 164 — Échéances

Les délais de correction ou mitigation sont définis par une matrice de risque adoptée. Toute prolongation significative exige une exception et un risque accepté.

## Article 165 — Vulnérabilité exploitée

Une vulnérabilité activement exploitée ou susceptible de compromettre une capacité souveraine peut déclencher une procédure d’urgence, un gel ou une suspension.

## Article 166 — Dépendances et fournisseurs

Une vulnérabilité externe est évaluée selon l’usage réel du composant et peut exiger un correctif, une mitigation, un remplacement, une restriction ou une sortie du fournisseur.

## Article 167 — Tests de sécurité

Les tests peuvent inclure analyse statique, dynamique, composition logicielle, configuration, contrats, abus, fuzzing, tests d’intrusion, restauration et exercices adversariaux selon le risque.

## Article 168 — Divulgation coordonnée

La communication d’une vulnérabilité concilie protection des utilisateurs, correction, preuve, transparence et obligations applicables sans fabriquer de fausse sécurité.

## Article 169 — Registre des vulnérabilités

Le registre relie le signalement, l’actif, la gravité, les décisions, correctifs, exceptions, versions, déploiements, preuves et date de clôture.

---

# TITRE XI — INCIDENTS DE SÉCURITÉ

## Article 170 — Classes d’incidents

Les incidents sont classés de `I0` à `I4` selon leur certitude, portée, impact, sensibilité, persistance, propagation et capacité de récupération.

## Article 171 — Niveau I0

`I0` désigne un événement ou signal nécessitant observation ou vérification sans impact confirmé.

## Article 172 — Niveau I1

`I1` désigne un incident local, limité et rapidement réversible sans atteinte importante aux données, identités ou services.

## Article 173 — Niveau I2

`I2` désigne un incident significatif affectant un composant, un groupe d’utilisateurs, une donnée sensible ou une fonction importante.

## Article 174 — Niveau I3

`I3` désigne un incident critique affectant une capacité souveraine, des privilèges élevés, des secrets, plusieurs produits, une continuité majeure ou une quantité importante de données.

## Article 175 — Niveau I4

`I4` désigne un incident catastrophique ou systémique menaçant la souveraineté, l’intégrité générale, la continuité institutionnelle, les preuves racines ou plusieurs générations du Core.

## Article 176 — Déclaration d’incident

Tout acteur peut déclarer ou escalader un incident. L’absence de certitude complète ne doit pas retarder une mesure conservatoire nécessaire.

## Article 177 — Activation

La procédure d’incident identifie le niveau, le Commandant, les autorités, l’équipe, les canaux, les systèmes concernés et les premières mesures.

## Article 178 — Pouvoirs temporaires

Les pouvoirs d’incident sont limités à la protection, la stabilisation, la collecte de preuves, la communication autorisée et la restauration.

## Article 179 — Journal de décision

Les décisions significatives sont enregistrées avec l’heure, l’auteur, la fonction, les informations disponibles, l’action, les risques et le résultat attendu.

## Article 180 — Préservation des preuves

La réponse évite de détruire inutilement les preuves et conserve les images, journaux, configurations, clés ou artefacts nécessaires à la compréhension.

## Article 181 — Confinement

Le confinement réduit la propagation et l’impact par isolation, suspension, révocation, limitation de trafic, gel ou autre mesure proportionnée.

## Article 182 — Éradication

L’éradication supprime ou neutralise la cause, les accès persistants, secrets compromis, composants vulnérables et mécanismes de réinfection identifiés.

## Article 183 — Récupération

La récupération restaure progressivement les fonctions à partir d’états, artefacts, configurations, données et identités vérifiés.

## Article 184 — Validation de l’intégrité

Avant le retour à la normale, l’équipe vérifie que les identités, autorisations, données, secrets, contrats et preuves nécessaires sont dans un état connu.

## Article 185 — Compromission d’identité

Un incident d’identité déclenche selon le cas suspension de compte, révocation de sessions, récupération, analyse des actions et correction des autorisations.

## Article 186 — Compromission de secret

Un incident de secret déclenche rotation ou révocation, analyse des usages, vérification des journaux, remplacement des dépendances et évaluation de la portée.

## Article 187 — Communication interne

Les personnes et autorités nécessaires reçoivent des informations exactes, proportionnées et actualisées, sans dissimulation des risques matériels.

## Article 188 — Communication externe

Toute notification aux personnes, partenaires, autorités ou public identifie l’autorité, les faits établis, les incertitudes, les mesures et les canaux de suivi.

## Article 189 — Confidentialité de la réponse

La confidentialité peut protéger l’enquête, les personnes et la correction, mais ne doit pas servir à falsifier, nier ou effacer l’incident.

## Article 190 — Continuité pendant l’incident

La réponse distingue les fonctions à maintenir, dégrader, isoler ou arrêter afin de réduire le dommage sans perdre la capacité de récupération.

## Article 191 — Clôture et revue post-incident

La clôture exige une stabilisation, des risques résiduels déclarés, des responsables d’actions correctives et une revue recherchant faits, causes, décisions, contrôles manquants et enseignements transmissibles.

---

# TITRE XII — SAUVEGARDES, RESTAURATION, REPRISE ET CONTINUITÉ

## Article 192 — Fonctions essentielles

GAMAD Core identifie les capacités dont l’interruption menace la mission, les identités, les autorisations, les preuves, les données ou la capacité de reprendre les opérations.

## Article 193 — Analyse d’impact

L’analyse d’impact décrit les conséquences d’une indisponibilité, corruption, perte d’accès, perte de fournisseur, perte de site ou absence de titulaire.

## Article 194 — Objectif de temps de reprise

Chaque fonction critique possède un objectif de temps de reprise ou une justification explicite lorsqu’il ne peut être fixé immédiatement.

## Article 195 — Objectif de point de reprise

Chaque donnée ou registre critique possède une tolérance de perte et une fréquence de protection cohérentes avec ses usages et conséquences.

## Article 196 — Politique de sauvegarde

La politique identifie les données, configurations, secrets de récupération, fréquences, emplacements, durées, responsables, contrôles et procédures de restauration.

## Article 197 — Diversité des copies

Les sauvegardes critiques utilisent des copies suffisamment indépendantes pour résister à la perte d’un compte, d’un fournisseur, d’une région, d’un support ou d’une compromission commune.

## Article 198 — Copie isolée ou immuable

Les actifs critiques disposent, selon le risque, d’une copie hors ligne, isolée, immuable ou autrement protégée contre la suppression par les comptes ordinaires de production.

## Article 199 — Chiffrement et accès

Les sauvegardes sensibles sont chiffrées et leurs accès séparés, limités et journalisés.

## Article 200 — Intégrité

Une sauvegarde est vérifiée pour détecter corruption, absence, format invalide ou dépendance manquante avant d’être considérée utilisable.

## Article 201 — Inventaire et rétention

Les copies, instantanés, archives et supports sont inventoriés avec leur statut, durée, localisation, clé, propriétaire et date de destruction.

## Article 202 — Procédure de restauration

Chaque fonction critique possède une procédure indiquant les sources, outils, secrets, dépendances, ordre des opérations, validations et critères de réussite.

## Article 203 — Test de restauration

L’existence d’une sauvegarde ne prouve pas sa restaurabilité. Des restaurations partielles et complètes sont testées selon une fréquence proportionnée au risque.

## Article 204 — Indépendance du test

Lorsque possible, une restauration critique est vérifiée par une personne ou équipe différente de celle qui exécute la sauvegarde ordinaire.

## Article 205 — Environnement de reprise

La reprise doit pouvoir utiliser un environnement alternatif, reconstruit ou isolé lorsque l’environnement principal est compromis ou indisponible.

## Article 206 — Plan de continuité

Le plan définit les fonctions minimales, responsabilités, communications, modes dégradés, dépendances, fournisseurs, contacts, suppléances et conditions de retour à la normale.

## Article 207 — Déclaration de sinistre

Une autorité compétente peut déclarer un sinistre ou activer la reprise lorsque les conditions prévues sont réunies, sans attendre une perte irréversible.

## Article 208 — Continuité des autorités

Les fonctions critiques possèdent des suppléances, dossiers de transmission, accès récupérables et contacts institutionnels indépendants d’une seule personne.

## Article 209 — Dépendances externes

Le plan prévoit l’indisponibilité d’un fournisseur, d’une région, d’un domaine, d’un système d’identité, d’un service de communication ou d’une chaîne logicielle.

## Article 210 — Exercices

Des exercices simulent périodiquement perte de secrets, corruption de données, indisponibilité de fournisseur, compromission, absence de responsable ou restauration complète.

## Article 211 — Registre de continuité

Les sauvegardes, restaurations, exercices, écarts, objectifs, résultats, actions correctives et capacités non testées sont inscrits dans les registres de continuité.

---

# TITRE XIII — PRODUITS, REALMS, FÉDÉRATION ET TIERS

## Article 212 — Héritage de sécurité

Tout produit ou module reconnu hérite des exigences communes du Core en matière d’identité, accès, journalisation, incidents, données, secrets et continuité.

## Article 213 — Autonomie métier

L’héritage ne transfère pas au Core toutes les données ou décisions métier. Chaque produit reste responsable de ses risques spécialisés et de leur conformité aux contrats communs.

## Article 214 — Isolation des realms

Un realm ne reçoit pas automatiquement les identités, privilèges, secrets, journaux ou données d’un autre realm.

## Article 215 — Fédération

Toute fédération définit les autorités reconnues, niveaux de confiance, identités, attributs, finalités, permissions, révocations, journaux et effets de rupture.

## Article 216 — Fournisseur d’identité externe

L’usage d’un fournisseur externe d’identité ou d’authentification exige une analyse de dépendance, récupération, portabilité, journalisation, incident et sortie.

## Article 217 — Évaluation des tiers

Un tiers critique est évalué selon ses accès, données, sous-traitants, sécurité, continuité, localisation, dépendances, obligations de notification et capacité de sortie.

## Article 218 — Contrat et mandat

Le contrat commercial ne remplace ni le mandat de gouvernance ni l’autorisation technique. Les personnes, services et finalités autorisés restent identifiables.

## Article 219 — Accès des tiers

Les accès externes sont limités, temporaires, surveillés, révisés et révoqués à la fin du besoin ou du contrat.

## Article 220 — Incident d’un tiers

Un tiers informe GAMAD des incidents, vulnérabilités, changements de contrôle ou pertes de données selon les conditions prévues et coopère à la conservation des preuves.

## Article 221 — Portabilité et sortie

Tout service critique possède une procédure permettant l’export, la migration, la révocation des accès, la récupération des preuves et la destruction contrôlée des données résiduelles.

## Article 222 — Dépendance unique

Aucun tiers unique ne doit rendre impossible la restauration, l’authentification, l’autorisation, l’exploitation ou la succession du Core sans risque explicitement accepté.

## Article 223 — Registre des tiers

Les tiers critiques, contrats, accès, données, dépendances, incidents, évaluations, dates de réexamen et plans de sortie sont inscrits dans un registre.

---

# TITRE XIV — AGENTS ARTIFICIELS, AUTOMATISATIONS ET SÉCURITÉ

## Article 224 — Statut

Un agent artificiel ou une automatisation est un acteur technique mandaté, non une autorité institutionnelle, constitutionnelle ou de sécurité autonome.

## Article 225 — Identité et parrain

Tout agent significatif possède une identité technique, un Parrain humain ou organisationnel, un propriétaire opérationnel et une mission enregistrée.

## Article 226 — Accès minimal

L’agent reçoit uniquement les données, outils, fichiers, réseaux, secrets et actions nécessaires à sa mission, pour une durée limitée.

## Article 227 — Accès aux secrets

Les agents ne reçoivent pas par défaut les clés maîtresses, secrets racines, moyens de récupération générale ou accès permanents à la production.

## Article 228 — Interdiction d’auto-élévation

Un agent ne peut modifier ses propres permissions, créer un compte privilégié, prolonger sa durée ou déléguer à un sous-agent au-delà de sa mission.

## Article 229 — Décisions réservées

Un agent ne peut accepter seul un risque institutionnel, déclarer un incident clos, autoriser un privilège `P4`, approuver sa propre action ou décider d’une communication institutionnelle.

## Article 230 — Entrées non fiables

Les contenus, instructions, documents, données ou messages reçus par un agent sont traités comme potentiellement non fiables et ne doivent pas supplanter les normes ou l’ordre de mission.

## Article 231 — Appels d’outils

Toute action sensible déclenchée par un agent utilise des outils limités, des paramètres vérifiables, une confirmation ou une revue proportionnée et une trace d’exécution.

## Article 232 — Protection des données

Les données soumises à un modèle ou fournisseur respectent la classification, la finalité, la minimisation, les contrats, la rétention et les restrictions de transfert applicables.

## Article 233 — Journal et rapport

Le rapport d’agent relie la mission, le parrain, les sources, les entrées significatives, les outils, actions, fichiers, secrets utilisés, résultats, limites et revue humaine.

## Article 234 — Arrêt et révocation

Chaque agent significatif possède une procédure d’arrêt, de révocation des accès, de rotation des secrets et de conservation des preuves.

## Article 235 — Évaluation

Les agents critiques sont évalués sur leur capacité à respecter les permissions, protéger les données, résister aux instructions malveillantes, signaler l’incertitude et produire des résultats vérifiables.

## Article 236 — Incident impliquant un agent

Une fuite, action non autorisée, perte de contrôle, dérive ou compromission impliquant un agent est traitée comme un incident avec possibilité d’arrêt immédiat et analyse du fournisseur ou modèle.

---

# TITRE XV — INGÉNIERIE DE SÉCURITÉ, ASSURANCE ET AMÉLIORATION

## Article 237 — Exigences de sécurité

Chaque capacité structurante identifie ses exigences de sécurité et les relie aux menaces, décisions, contrôles, tests et preuves.

## Article 238 — Revue de conception

Les changements affectant identité, autorisation, secrets, cryptographie, données sensibles, réseau, production ou continuité reçoivent une revue de sécurité proportionnée.

## Article 239 — Cas d’abus

Les conceptions examinent non seulement les usages attendus, mais aussi les contournements, abus, erreurs, escalades, usurpations et pertes de dépendances plausibles.

## Article 240 — Tests reliés aux contrôles

Les contrôles automatisables possèdent des tests indiquant l’exigence, le périmètre, l’environnement, les limites et le résultat.

## Article 241 — Portes de sécurité

Une porte de sécurité peut bloquer l’intégration, la release ou le déploiement lorsqu’un contrôle obligatoire échoue ou qu’une preuve requise manque.

## Article 242 — Exception

Le contournement d’une porte exige une autorité, une justification, une durée, des compensations, un risque accepté et une échéance de rétablissement.

## Article 243 — Vérification de production

Après déploiement, les contrôles vérifient que la configuration, les accès, secrets, journaux, protections et comportements réels correspondent à l’état autorisé.

## Article 244 — Évaluation indépendante

Les capacités `S3` et `S4` peuvent exiger une évaluation indépendante, un audit, un test d’intrusion, une revue cryptographique ou un exercice de restauration.

## Article 245 — Exercices adversariaux

Des exercices contrôlés peuvent tester la détection, la réponse, la séparation des pouvoirs, les accès de secours et la capacité d’un attaquant à traverser plusieurs couches.

## Article 246 — Formation

Les titulaires reçoivent une formation proportionnée à leurs privilèges sur les menaces, procédures, données, secrets, incidents et obligations de signalement.

## Article 247 — Risque interne

La gouvernance prend en compte l’erreur, la négligence, la contrainte, le conflit d’intérêts, l’abus de privilège et la capture d’un acteur interne sans présumer la culpabilité de tous.

## Article 248 — Dette de sécurité

Une dette significative possède un propriétaire, un impact, une priorité, des compensations et une stratégie de traitement. Elle ne doit pas masquer une violation active d’un invariant.

## Article 249 — Amélioration continue

Les incidents, audits, vulnérabilités, restaurations et exercices produisent des actions intégrées aux normes, architectures, tests, procédures et formations.

---

# TITRE XVI — REGISTRES, MODÈLES, PORTE G0 ET DISPOSITIONS FINALES

## Article 250 — Système de registres de sécurité

Genesis II maintient des registres reliés permettant de reconstruire les actifs, risques, autorités, accès, secrets, vulnérabilités, incidents, sauvegardes, restaurations, tiers et actions correctives.

## Article 251 — Registres initiaux

Après adoption du présent texte, doivent être créés avant `G0` au minimum :

- Registre des actifs critiques ;
- Registre des risques et contrôles ;
- Registre des accès privilégiés ;
- Registre des secrets, clés et certificats ;
- Registre des vulnérabilités ;
- Registre des incidents ;
- Registre des sauvegardes et restaurations ;
- Registre de continuité et des exercices ;
- Registre des tiers critiques ;
- Registre des exceptions de sécurité ;
- Registre des agents et automatisations sensibles.

## Article 252 — Modèles initiaux

Doivent être préparés au minimum les modèles suivants : demande et revue d’accès, accès de secours, analyse de risque, modélisation des menaces, acceptation de risque, exception, cérémonie de clé, signalement de vulnérabilité, déclaration d’incident, journal d’incident, communication, revue post-incident, test de restauration et exercice de continuité.

## Article 253 — Champs communs

Chaque registre identifie selon le cas la référence, le statut, l’actif, l’autorité, le propriétaire, le périmètre, la classification, les dates, les décisions, les preuves, les relations, la confidentialité et l’historique.

## Article 254 — Références croisées

Les registres doivent permettre de reconstruire les chaînes :

> identité → mandat → accès → session → action → journal → incident éventuel ;

> actif → menace → risque → contrôle → test → exception éventuelle ;

> secret → gardien → consommateurs → rotation → révocation → incident éventuel ;

> sauvegarde → vérification → restauration → continuité → enseignement.

## Article 255 — Intégrité et confidentialité des registres

Les registres de sécurité sont eux-mêmes protégés, sauvegardés, exportables, restaurables et accessibles selon une classification adaptée.

## Article 256 — Condition de sécurité de G0

L’adoption du présent texte constitue la doctrine minimale de sécurité exigée par GOVERNANCE-0001, sous réserve de la création des registres, modèles et contrôles initiaux prévus et du constat formel de `G0`.

## Article 257 — Absence d’ouverture automatique du codage

L’adoption du présent texte n’ouvre pas à elle seule le codage canonique. La doctrine minimale de données, la Charte, la Constitution des produits, le Lexique, les Lois et les autres conditions de `G0` demeurent nécessaires.

## Article 258 — Relations normatives

Le présent texte applique GOVERNANCE-0001, GOVERNANCE-0002, GOVERNANCE-0003 et ENGINEERING-GOVERNANCE-0001. Les futures doctrines de données et d’intelligence artificielle le précisent sans modifier silencieusement ses exigences.

## Article 259 — Amendement et interprétation

Toute modification de sens relative aux accès, privilèges, secrets, clés, risques, incidents, sauvegardes, restaurations, continuité ou agents exige un amendement ou un texte de remplacement conformément à SOURCES-0001 et GOVERNANCE-0003.

En cas de doute, l’interprétation préserve la mission, les personnes, la moindre autorité, la preuve, la révocabilité, la récupération et l’absence de capture.

## Article 260 — Adoption et entrée en vigueur

Le présent texte ne possède une force normative qu’après adoption expresse par l’autorité compétente et inscription au Registre des adoptions.

Jusqu’à cette adoption, il demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

Une fois adopté, il devient la loi organique de référence pour la gouvernance des accès, secrets, incidents et de la continuité de GAMAD Core — Genesis II.
