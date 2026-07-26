# REGISTRES-ET-MODELES-SECURITE-0001 — REGISTRES ET MODÈLES INITIAUX DE SÉCURITÉ DE GAMAD CORE

## Genesis II — Version 0.1

**Statut : PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

- **Périmètre :** actifs critiques, risques, contrôles, identités et accès privilégiés, secrets, clés, certificats, vulnérabilités, incidents, sauvegardes, restaurations, continuité, tiers critiques, exceptions, agents et automatisations sensibles de GAMAD Core — Genesis II
- **Autorité de proposition :** Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD
- **Rédaction initiale :** ChatGPT, sous instruction et supervision de l’autorité de proposition
- **Enregistrement Git :** chantier GAMAD Core — Genesis II au moyen du connecteur GitHub autorisé
- **Dépendances normatives adoptées :** ACTE-0001 ; SOURCES-0001 ; GOVERNANCE-0001 ; GOVERNANCE-0002 ; GOVERNANCE-0003 ; ENGINEERING-GOVERNANCE-0001 ; SECURITY-GOVERNANCE-0001 ; DATA-GOVERNANCE-0001 ; AI-GOVERNANCE-0001 ; CORE-CHARTER-0001 ; PRODUCT-CONSTITUTION-0001 ; LEXICON-0001 ; CORE-LAWS-0001 ; CORE-ATLAS-0001 ; REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001 ; REGISTRE-INITIAL-PRODUITS-0001 ; REGISTRE-INITIAL-AUTORITES-MANDATS-0001 ; REGISTRE-INITIAL-DECISIONS-0001 ; MODELES-INITIAUX-CYCLE-DECISION-0001
- **Référence documentaire de chantier non normative :** REGISTRES-ET-MODELES-INGENIERIE-0001, uniquement pour cohérence inter-documentaire tant qu’il n’est pas adopté
- **Principe directeur :** aucun accès, secret, compte, clé, appareil, fournisseur, agent, sauvegarde ou automatisation ne doit devenir un pouvoir incontrôlé ; toute capacité sensible doit être attribuable, limitée, surveillée, révocable, restaurable et transmissible

---

## Préambule

La sécurité de Genesis II ne se réduit ni à un mot de passe, ni à un pare-feu, ni à l’existence d’une sauvegarde. Elle constitue une chaîne gouvernée :

> **actif → menace → risque → contrôle → test → preuve → exception éventuelle → décision → réexamen.**

Elle constitue également une chaîne d’autorité :

> **identité → fonction → mandat → permission → authentification → session → action → journal → incident éventuel → révocation.**

Le présent projet fournit les premiers registres et modèles permettant de rendre ces chaînes reconstructibles avant la Porte constitutionnelle `G0`.

Il ne révèle aucun secret actif, ne publie aucune clé privée, ne crée aucun compte, ne modifie aucune permission, ne déclare aucun système invulnérable, ne certifie aucune sauvegarde et ne prétend pas qu’une restauration a été testée lorsqu’aucune preuve n’est disponible.

Les informations inconnues sont inscrites comme `NON ÉTABLI`, `NON INVENTORIÉ`, `À QUALIFIER`, `À TESTER`, `À RAPPROCHER` ou `NON APPLICABLE — MOTIF`. Ces états constituent des écarts visibles et non des autorisations implicites.

---

# TITRE I — NATURE, OBJET, RANG ET LIMITES

## Article 1 — Objet

Le présent document établit le système initial des registres et modèles de sécurité nécessaire à la gouvernance, au contrôle, à la récupération et à l’audit de GAMAD Core — Genesis II.

## Article 2 — Rang

Le document applique les textes adoptés de rang supérieur. Il ne modifie ni leurs autorités, ni leurs Lois, ni leurs frontières, ni leurs conditions de `G0`.

## Article 3 — Portée documentaire

L’inscription d’un objet dans un registre établit uniquement qu’il est suivi selon les informations disponibles. Elle ne prouve pas automatiquement sa sécurité, son autorisation, sa conformité, sa disponibilité ou sa restauration.

## Article 4 — Portée pré-G0

Avant le constat formel de `G0`, le présent document gouverne la préparation documentaire, les contrôles du chantier et les prototypes explicitement non canoniques. Il n’autorise aucun codage canonique.

## Article 5 — Aucune autorité créée

Aucun rôle, champ d’approbation, identifiant de registre, compte ou permission mentionné ne nomme un titulaire ni ne crée un mandat.

## Article 6 — Aucune permission créée

Le document ne crée, n’élève, ne prolonge et ne régularise aucun accès GitHub, serveur, cloud, base, coffre, secret, clé, certificat, appareil ou environnement.

## Article 7 — Aucune sécurité absolue déclarée

Une mesure, un contrôle ou une preuve ne permet jamais de déclarer un système absolument sûr. La sécurité demeure contextualisée, limitée dans le temps et réévaluée après changement.

## Article 8 — Aucun secret dans le registre

Les valeurs de secrets, clés privées, mots de passe, jetons, codes de récupération ou détails exploitables de vulnérabilités non corrigées sont interdits dans le présent document et dans ses futurs registres publics.

## Article 9 — Interprétation conservatrice

En cas de doute, l’interprétation préserve la mission, les personnes, le moindre pouvoir, la preuve, la révocabilité, la récupération, la transmission et l’absence de capture.

## Article 10 — Finalité générationnelle

Les registres et modèles doivent permettre à une future autorité compétente de comprendre, reprendre, révoquer, restaurer et remplacer les moyens de sécurité sans dépendre de la mémoire ou de la présence d’une personne unique.

---

# TITRE II — RÉFÉRENCES, CLASSIFICATIONS, ÉTATS ET PREUVES

## Article 11 — Références persistantes

Les séries initiales proposées sont :

- `ACTIF-SEC-XXXX` — actif critique ;
- `RISQUE-SEC-XXXX` — risque de sécurité ;
- `CONTROLE-SEC-XXXX` — contrôle de sécurité ;
- `ACCES-PRIV-XXXX` — accès privilégié ;
- `SECRET-XXXX` — secret, clé ou certificat, sans valeur sensible ;
- `VULN-XXXX` — vulnérabilité ;
- `INCIDENT-SEC-XXXX` — incident de sécurité ;
- `SAUVEGARDE-XXXX` — dispositif ou jeu de sauvegarde ;
- `RESTAURATION-XXXX` — test ou opération de restauration ;
- `CONTINUITE-XXXX` — plan ou exercice de continuité ;
- `TIERS-CRIT-XXXX` — tiers critique ;
- `EXCEPTION-SEC-XXXX` — exception de sécurité ;
- `AGENT-SENS-XXXX` — agent ou automatisation sensible ;
- `CEREMONIE-CLE-XXXX` — cérémonie de clé ;
- `REVUE-ACCES-XXXX` — campagne de revue d’accès.

## Article 12 — Non-réutilisation

Une référence attribuée n’est jamais réutilisée pour un autre objet, même après clôture, révocation, remplacement ou archivage.

## Article 13 — Classification de sensibilité

Les informations peuvent être classées :

- `PUBLIQUE` ;
- `INTERNE` ;
- `CONFIDENTIELLE` ;
- `RESTREINTE` ;
- `SECRÈTE`.

La classification indique la protection requise, non la valeur morale ou institutionnelle de l’information.

## Article 14 — Criticité d’un actif

Un actif peut être `C0` à `C4`, selon son importance pour la mission, l’identité, l’autorité, les droits, la preuve, la continuité et le nombre de dépendances.

## Article 15 — Risque

Les risques utilisent les niveaux `R0` à `R4` de `GOVERNANCE-0003`. Le niveau le plus exigeant entre impact, exposition, sensibilité, irréversibilité et récupération détermine les contrôles minimaux.

## Article 16 — Niveaux de preuve

Les niveaux de preuve sont :

- `P0 — DÉCLARÉ` ;
- `P1 — DOCUMENTÉ` ;
- `P2 — CONTRÔLÉ` ;
- `P3 — TESTÉ` ;
- `P4 — OPÉRATIONNEL PROUVÉ`.

## Article 17 — États d’un actif

Un actif peut être `PROPOSÉ`, `À QUALIFIER`, `INVENTORIÉ`, `ACTIF`, `LIMITÉ`, `SUSPENDU`, `EN TRANSFERT`, `ARCHIVÉ`, `REMPLACÉ`, `RETIRÉ` ou `PERDU`.

## Article 18 — États d’un risque

Un risque peut être `IDENTIFIÉ`, `À ANALYSER`, `EN TRAITEMENT`, `RÉDUIT`, `TRANSFÉRÉ`, `ÉVITÉ`, `ACCEPTATION REQUISE`, `ACCEPTÉ SOUS CONDITIONS`, `SOUS SURVEILLANCE`, `RÉALISÉ` ou `CLOS`.

## Article 19 — États d’un accès

Un accès peut être `DEMANDÉ`, `EN REVUE`, `REFUSÉ`, `APPROUVÉ`, `À ACTIVER`, `ACTIF`, `SUSPENDU`, `EXPIRÉ`, `À RÉVOQUER`, `RÉVOQUÉ`, `ORPHELIN` ou `NON RAPPROCHÉ`.

## Article 20 — États d’un secret ou certificat

Un secret peut être `PLANIFIÉ`, `GÉNÉRÉ`, `ACTIF`, `EN ROTATION`, `À RÉVOQUER`, `RÉVOQUÉ`, `EXPIRÉ`, `COMPROMIS`, `PERDU`, `ARCHIVÉ` ou `DÉTRUIT`.

## Article 21 — États d’une vulnérabilité

Une vulnérabilité peut être `SIGNALÉE`, `À QUALIFIER`, `CONFIRMÉE`, `EN CORRECTION`, `COMPENSÉE`, `CORRIGÉE`, `EN VÉRIFICATION`, `ACCEPTATION REQUISE`, `NON REPRODUCTIBLE`, `DUPLIQUÉE` ou `CLOSE`.

## Article 22 — États d’un incident

Un incident peut être `SIGNALÉ`, `À QUALIFIER`, `OUVERT`, `CONTENU`, `ÉRADIQUÉ`, `RESTAURATION EN COURS`, `SOUS SURVEILLANCE`, `CLOS SOUS RÉSERVES`, `CLOS` ou `RÉOUVERT`.

## Article 23 — États d’une sauvegarde

Une sauvegarde peut être `PLANIFIÉE`, `ACTIVE`, `ÉCHOUÉE`, `INCOMPLÈTE`, `NON VÉRIFIÉE`, `VÉRIFIÉE`, `EXPIRÉE`, `CORROMPUE`, `ISOLÉE`, `ARCHIVÉE` ou `DÉTRUITE`.

## Article 24 — États d’une restauration

Une restauration peut être `PLANIFIÉE`, `EN PRÉPARATION`, `EN COURS`, `RÉUSSIE SOUS CONTRÔLE`, `RÉUSSIE`, `PARTIELLE`, `ÉCHOUÉE`, `ANNULÉE` ou `CLOSE`.

## Article 25 — États d’une exception

Une exception peut être `DEMANDÉE`, `EN REVUE`, `REFUSÉE`, `ACCEPTÉE SOUS CONDITIONS`, `ACTIVE`, `SUSPENDUE`, `EXPIRÉE`, `RÉVOQUÉE`, `RÉSORBÉE` ou `CLOSE`.

## Article 26 — Preuve limitée

Une preuve établit uniquement l’exigence, le périmètre, la version, l’environnement et la période qu’elle couvre.

## Article 27 — Transition attribuable

Toute transition d’état indique l’acteur, sa fonction, son mandat ou fondement, la date, le motif, les contrôles et les preuves.

## Article 28 — Historique non effacé

Une correction, rotation, révocation, restauration ou clôture conserve l’historique nécessaire à la compréhension et à l’audit.

---

# TITRE III — CHAMPS COMMUNS ET PROTECTION DES REGISTRES

## Article 29 — Identification

Chaque inscription contient selon le cas : référence, titre, type, statut, actif, environnement, propriétaire, autorité, responsable, classification, criticité, risque, dates, décisions et preuves.

## Article 30 — Autorité et responsabilité

Chaque inscription distingue l’autorité qui décide, le responsable qui maintient, l’exécutant, le contrôleur, l’auditeur et les personnes consultées.

## Article 31 — Source de pouvoir

Tout accès, secret, contrôle, exception ou action critique référence une norme, décision, mandat, délégation ou mission identifiable.

## Article 32 — Relations

Les registres relient les actifs, identités, comptes, accès, secrets, dépendances, risques, contrôles, tests, vulnérabilités, incidents, sauvegardes et restaurations.

## Article 33 — Données minimales

Les registres ne contiennent que les métadonnées nécessaires à la gouvernance. Les contenus sensibles sont conservés dans des systèmes adaptés et référencés sans exposition.

## Article 34 — Confidentialité

Chaque champ ou pièce possède une classification, un besoin de connaître, une durée de conservation et une procédure de divulgation ou d’export.

## Article 35 — Intégrité

Les registres critiques sont versionnés, protégés contre l’altération silencieuse, sauvegardés et vérifiables par empreinte ou mécanisme équivalent.

## Article 36 — Disponibilité

Les registres nécessaires à la réponse à incident et à la restauration disposent d’un mode d’accès dégradé, contrôlé et documenté.

## Article 37 — Export et portabilité

Les registres doivent pouvoir être exportés dans un format documenté, lisible et vérifiable sans dépendance exclusive à un fournisseur.

## Article 38 — Restauration des registres

La capacité de restaurer les registres de sécurité doit elle-même être testée et prouvée.

## Article 39 — Accès aux registres

La consultation, modification, export, suppression et administration sont séparées proportionnellement à la sensibilité.

## Article 40 — Journalisation

Les actions critiques sur les registres identifient l’acteur, le compte, la session, l’objet, l’action, le résultat, le temps et l’environnement.

## Article 41 — Points ouverts

Chaque point ouvert possède un propriétaire, un risque, une échéance ou condition, un effet en cas de maintien et un statut.

## Article 42 — Assistance par IA

Toute assistance significative par IA indique l’agent ou outil, le Parrain, la mission, les données autorisées, les actions, les limites et la revue humaine disponible.

---

# TITRE IV — REGISTRE DES ACTIFS CRITIQUES

## Article 43 — Finalité

Le Registre des actifs critiques identifie ce qui doit être protégé, pourquoi, par qui, contre quels dommages et avec quelles exigences de continuité.

## Article 44 — Champs obligatoires

Chaque actif indique : mission, propriétaire institutionnel, responsable opérationnel, domaine, capacité, produit ou realm, localisation logique, dépendances, classification, criticité, exigences de confidentialité, intégrité, disponibilité, authenticité et continuité.

## Article 45 — Catégories

Les actifs comprennent notamment : identités, autorités, normes, décisions, dépôts, données, contrats, secrets, clés, certificats, journaux, sauvegardes, environnements, comptes, appareils, fournisseurs, modèles et procédures.

## Article 46 — Propriétaire institutionnel

Le propriétaire répond de la valeur et des exigences de protection. L’administrateur technique ne devient pas propriétaire institutionnel par sa seule capacité d’accès.

## Article 47 — Responsable opérationnel

Le responsable maintient l’inventaire, les contrôles, incidents, changements, dépendances et preuves, dans les limites de son mandat.

## Article 48 — Actif orphelin

Un actif sans propriétaire ou responsable établi est classé `ORPHELIN — RISQUE OUVERT` et fait l’objet d’une décision de régularisation, limitation ou retrait.

## Article 49 — État initial

Sont identifiables au niveau documentaire, au minimum : le dépôt `zumradeals/gamad-core`, la branche `main`, les textes Genesis II publiés, les registres d’adoption et l’archive Genesis I. Leur classification, propriétaire institutionnel, sauvegarde et restauration réelles demeurent `À QUALIFIER`.

---

# TITRE V — REGISTRE DES RISQUES ET CONTRÔLES

## Article 50 — Finalité

Le Registre des risques et contrôles relie chaque scénario de dommage à ses actifs, menaces, vulnérabilités, impacts, responsables, traitements, contrôles et preuves.

## Article 51 — Scénario de risque

Un risque est formulé comme un scénario concret comprenant cause, événement, cible, conséquence et contexte.

## Article 52 — Analyse

L’analyse distingue vraisemblance, exposition, impact, détectabilité, irréversibilité, capacité de confinement et capacité de restauration.

## Article 53 — Traitements

Un risque peut être évité, réduit, transféré ou accepté par l’autorité compétente. L’inaction non documentée ne constitue pas une décision de traitement.

## Article 54 — Contrôle

Chaque contrôle indique son objectif, type préventif, détectif, correctif ou de récupération, responsable, fréquence, périmètre, test, preuve et limites.

## Article 55 — Risque résiduel

Le risque résiduel est évalué après prise en compte des contrôles réellement établis, non des contrôles seulement prévus.

## Article 56 — Acceptation

L’acceptation appartient à l’autorité compétente, reste limitée dans le temps et ne peut être réalisée seule par un développeur, un fournisseur ou une IA.

## Article 57 — Réexamen

Un changement d’actif, menace, fournisseur, incident, contrôle, preuve ou environnement déclenche un réexamen proportionné.

---

# TITRE VI — REGISTRE DES ACCÈS PRIVILÉGIÉS

## Article 58 — Finalité

Le Registre des accès privilégiés rapproche identité, compte, fonction, mandat, permission, environnement, durée, authentification, journalisation et révocation.

## Article 59 — Champs obligatoires

Chaque accès indique : identité humaine ou technique, compte, organisation, mandat, propriétaire, approbateur, ressource, privilèges, finalité, environnement, début, expiration, authentification, secrets associés par référence, journaux et dernière revue.

## Article 60 — Refus par défaut

Tout privilège non explicitement autorisé est interdit.

## Article 61 — Accès nominatif

Les accès humains critiques sont nominatifs. Les comptes partagés sont interdits sauf impossibilité documentée, contrôle compensatoire et procédure de sortie.

## Article 62 — Accès de service

Un compte de service possède un propriétaire organisationnel, une mission, des permissions limitées, une rotation, une surveillance et une procédure d’arrêt.

## Article 63 — Expiration

Les accès temporaires expirent automatiquement lorsque possible. Une prolongation exige une nouvelle justification.

## Article 64 — Rapprochement des mandats

Un accès sans mandat actif ou sans source identifiable est `NON RAPPROCHÉ` et doit être suspendu, limité ou régularisé selon le risque.

## Article 65 — Revue périodique

La revue confirme le besoin, la portée, le titulaire, le mandat, l’usage récent, les incidents, l’expiration et les moyens de révocation.

## Article 66 — Accès de secours

Les accès de secours sont séparés, protégés, testés, surveillés et utilisés uniquement selon une procédure enregistrée.

## Article 67 — État initial

Aucun inventaire consolidé des accès GitHub, serveurs, clouds, bases, coffres ou environnements n’est établi dans le corpus canonique. L’état initial est `NON INVENTORIÉ — À RAPPROCHER`.

---

# TITRE VII — REGISTRE DES SECRETS, CLÉS ET CERTIFICATS

## Article 68 — Finalité

Le Registre maintient les métadonnées de gouvernance sans contenir les valeurs secrètes.

## Article 69 — Champs obligatoires

Chaque objet indique : type, finalité, propriétaire institutionnel, gardien, consommateurs, portée, environnement, système de conservation, algorithme ou format si publiable, création, activation, expiration, rotation, révocation, récupération, sauvegarde et dernière vérification.

## Article 70 — Valeur interdite

La valeur du secret, la clé privée, le mot de passe, le jeton ou le code de récupération ne figurent jamais dans le registre.

## Article 71 — Génération

Les secrets critiques sont générés selon une procédure adaptée au risque, avec attribution, qualité de source et conservation contrôlée.

## Article 72 — Distribution

La distribution est limitée aux consommateurs autorisés et utilise des canaux adaptés.

## Article 73 — Rotation

La rotation possède une fréquence, des déclencheurs, une procédure, des tests et un plan de coordination des consommateurs.

## Article 74 — Révocation

La révocation traite les copies, caches, sessions, jetons dérivés, certificats et accès dépendants.

## Article 75 — Compromission

Une suspicion raisonnable déclenche confinement, rotation ou révocation, analyse d’exposition, recherche d’usage et traitement d’incident.

## Article 76 — Récupération

La récupération d’un secret racine ou d’une clé critique ne dépend pas d’une seule personne, d’un seul appareil ou d’un seul fournisseur.

## Article 77 — État initial

Aucun inventaire canonique des secrets, clés et certificats réels n’est disponible. Aucun secret ne doit être déduit des dépôts ou de la mémoire des opérateurs.

---

# TITRE VIII — REGISTRE DES VULNÉRABILITÉS

## Article 78 — Finalité

Le Registre relie chaque vulnérabilité à l’actif, la version, la source, l’exposition, l’exploitabilité, l’impact, les compensations, la correction et la vérification.

## Article 79 — Signalement

Tout acteur peut signaler une vulnérabilité sans attendre une preuve d’exploitation complète.

## Article 80 — Confidentialité coordonnée

Les détails exploitables sont restreints jusqu’à réduction raisonnable du risque, sans supprimer la traçabilité institutionnelle.

## Article 81 — Qualification

La qualification distingue faiblesse, configuration, dépendance, conception, procédure, exposition de secret et absence de contrôle.

## Article 82 — Priorité

La priorité combine criticité de l’actif, exposition, exploitabilité, impact, disponibilité d’un correctif et compensations.

## Article 83 — Correction

La correction référence contribution, commit, artefact, version, tests, déploiement et vérification lorsqu’ils existent.

## Article 84 — Exception

Un report de correction significatif exige une exception ou acceptation de risque valide, limitée et réexaminée.

## Article 85 — Clôture

La clôture exige une preuve de correction ou une décision de traitement ; la disparition d’une alerte ne suffit pas.

---

# TITRE IX — REGISTRE DES INCIDENTS

## Article 86 — Finalité

Le Registre des incidents permet de reconstruire détection, qualification, décisions, confinement, éradication, restauration, communication et enseignements.

## Article 87 — Déclaration précoce

Un signal peut être déclaré avant certitude complète lorsqu’un retard augmenterait le dommage.

## Article 88 — Classification

La classification prend en compte personnes, identités, autorités, données, secrets, disponibilité, preuve, dépendances, produits et obligations externes.

## Article 89 — Commandement

Chaque incident significatif possède une autorité de décision et un commandant d’incident mandaté, distincts lorsque possible.

## Article 90 — Chronologie

Le journal distingue temps du fait, temps de détection, temps de décision, temps d’action et temps d’enregistrement.

## Article 91 — Préservation des preuves

Les journaux, images, configurations, versions, communications et artefacts nécessaires sont conservés sans retarder les mesures urgentes de protection.

## Article 92 — Confinement

Les mesures peuvent inclure suspension, révocation, isolement, gel, arrêt de flux, restriction d’accès et activation du mode dégradé.

## Article 93 — Éradication

L’éradication traite la cause, les accès, secrets, persistance, dépendances et copies affectées.

## Article 94 — Restauration

Le retour au service utilise un état vérifié, réapplique les révocations et corrections, puis observe le comportement.

## Article 95 — Communication

La communication est exacte, proportionnée, autorisée, respectueuse des personnes et coordonnée avec les obligations applicables.

## Article 96 — Clôture

Un incident critique ne peut être déclaré clos par une IA seule. La clôture identifie les réserves, risques, actions et preuves.

---

# TITRE X — REGISTRE DES SAUVEGARDES ET RESTAURATIONS

## Article 97 — Finalité

Le Registre distingue clairement l’existence d’une sauvegarde de la preuve qu’une restauration utilisable est possible.

## Article 98 — Champs d’une sauvegarde

Chaque sauvegarde indique : actif, périmètre, méthode, fréquence, rétention, chiffrement, localisation logique, comptes et clés nécessaires, indépendance, surveillance, dernière exécution, résultat et dernière vérification.

## Article 99 — Indépendance

Les sauvegardes critiques ne dépendent pas exclusivement des mêmes comptes, clés, régions, fournisseurs ou erreurs que l’actif principal.

## Article 100 — Vérification

La vérification contrôle l’existence, l’intégrité, la lisibilité, la complétude et la disponibilité des moyens de déchiffrement ou de récupération.

## Article 101 — Test de restauration

Chaque test indique objectif, scénario, point de restauration, environnement, données, exécutants, durée, résultat, écarts et décision.

## Article 102 — RTO et RPO

Les objectifs de temps et de point de reprise sont définis par l’autorité compétente et vérifiés par des exercices, non supposés.

## Article 103 — Réapplication

Une restauration réapplique les révocations, corrections, suppressions, restrictions et décisions intervenues après le point restauré.

## Article 104 — Échec

Un échec de sauvegarde ou restauration crée un risque et une action corrective avec responsable et échéance.

## Article 105 — État initial

Aucune preuve canonique de sauvegarde indépendante ou de restauration testée du dépôt, des registres ou des futurs systèmes Genesis II n’est établie. L’état est `NON ÉTABLI — À TESTER`.

---

# TITRE XI — REGISTRE DE CONTINUITÉ ET DES EXERCICES

## Article 106 — Finalité

Le Registre de continuité relie fonctions essentielles, scénarios de perte, priorités, modes dégradés, suppléances, objectifs de reprise et exercices.

## Article 107 — Analyse d’impact

Chaque fonction critique identifie les conséquences d’une indisponibilité, les dépendances, délais tolérables et priorités de reprise.

## Article 108 — Scénarios

Les scénarios comprennent perte de personne clé, compte, clé, appareil, dépôt, fournisseur, région, base, réseau, modèle, registre ou autorité.

## Article 109 — Mode dégradé

Le mode dégradé indique les services maintenus, suspendus, manuels ou limités, ainsi que les décisions autorisées.

## Article 110 — Suppléance

La continuité des autorités et responsables exige des suppléants, dossiers de transmission et moyens d’accès récupérables.

## Article 111 — Exercice

Chaque exercice possède scénario, objectifs, participants, limites de sécurité, chronologie, résultats, écarts et actions correctives.

## Article 112 — Absence de simulation fictive

Un exercice non exécuté ne peut être inscrit comme réussi. Un scénario discuté sans test demeure `P1 — DOCUMENTÉ`.

## Article 113 — État initial

Aucun exercice consolidé de continuité Genesis II n’est établi dans le corpus canonique.

---

# TITRE XII — REGISTRE DES TIERS CRITIQUES

## Article 114 — Finalité

Le Registre des tiers critiques gouverne les fournisseurs, hébergeurs, plateformes, dépôts, modèles, services, opérateurs et sous-traitants dont dépend une fonction sensible.

## Article 115 — Champs obligatoires

Chaque tiers indique : service, propriétaire interne, données, accès, sous-traitants, juridiction à établir, sécurité, disponibilité, incidents, audit, portabilité, sauvegarde, sortie, réversibilité et dépendances.

## Article 116 — Criticité

La criticité dépend de l’impact d’une compromission, indisponibilité, censure, changement de conditions, perte de données ou impossibilité de sortie.

## Article 117 — Contrat

Le contrat ou cadre applicable traite au minimum données, accès, confidentialité, notification d’incident, changement, continuité, audit, rétention, suppression et sortie.

## Article 118 — Changement du tiers

Un changement de propriétaire, sous-traitant, juridiction, politique, modèle ou sécurité déclenche un réexamen.

## Article 119 — Plan de sortie

Le plan précise export, remplacement, révocation, migration, conservation des preuves, délais et responsabilités.

## Article 120 — État initial

GitHub et les services OpenAI/connecteurs utilisés pour le chantier sont des dépendances techniques observées à qualifier. Leur mention ne constitue aucune approbation générale de sécurité, de conformité ou de permanence.

---

# TITRE XIII — REGISTRE DES EXCEPTIONS DE SÉCURITÉ

## Article 121 — Finalité

Le Registre empêche qu’une dérogation temporaire devienne une règle silencieuse.

## Article 122 — Champs obligatoires

Chaque exception indique : exigence concernée, motif, actif, risque, portée, durée, autorité, responsable, compensations, surveillance, sortie et preuve de clôture.

## Article 123 — Interdictions de nature

La falsification, l’usurpation d’autorité, l’effacement intentionnel de preuve et le contournement malveillant des permissions ne sont pas des exceptions ordinaires.

## Article 124 — Durée

Une exception possède une expiration. Son renouvellement exige une nouvelle évaluation et décision.

## Article 125 — Compensations

Les contrôles compensatoires sont testés et ne doivent pas être présentés comme équivalents au contrôle absent sans preuve.

## Article 126 — Sortie

La sortie indique la correction, le remplacement, la révocation, la restauration du contrôle et les risques résiduels.

## Article 127 — État initial

Aucune exception de sécurité formellement acceptée n’est établie dans le corpus canonique.

---

# TITRE XIV — REGISTRE DES AGENTS ET AUTOMATISATIONS SENSIBLES

## Article 128 — Finalité

Le Registre identifie les agents et automatisations pouvant lire, modifier, déployer, supprimer, communiquer ou agir sur des actifs sensibles.

## Article 129 — Champs obligatoires

Chaque agent indique : identité technique, modèle ou moteur, fournisseur, version, Parrain, responsable opérationnel, mission, classification `A0` à `A4`, permissions, outils, données, secrets, environnements, durée, supervision, évaluations, incidents, arrêt et révocation.

## Article 130 — Autorité limitée

Un agent ne devient jamais autorité institutionnelle par sa performance, son accès ou son ancienneté d’usage.

## Article 131 — Entrées non fiables

Les fichiers, contenus, messages, pages web et sorties d’autres agents sont traités comme potentiellement hostiles.

## Article 132 — Appels d’outils

Les actions sensibles utilisent des outils limités, des paramètres vérifiables, une confirmation ou revue proportionnée et une trace d’exécution.

## Article 133 — Secrets

Les agents ne reçoivent pas par défaut les secrets racines, moyens de récupération générale ou accès permanents à la production.

## Article 134 — Auto-élévation interdite

Un agent ne modifie pas ses permissions, sa durée ou ses sous-agents au-delà de sa mission.

## Article 135 — Arrêt

Chaque agent significatif possède une procédure testable d’arrêt, de révocation des accès et de conservation des preuves.

## Article 136 — État initial

ChatGPT et le connecteur GitHub ont contribué au chantier sous instructions humaines. Leur dossier IA détaillé demeure à constituer dans le futur ensemble IA ; le présent registre ne leur attribue aucun accès général ni autorité.

---

# TITRE XV — MODÈLE 1 : DEMANDE ET REVUE D’ACCÈS

## Article 137 — Identification

Le modèle comporte : référence, demandeur, bénéficiaire, identité, fonction, mandat, ressource, environnement, privilèges, finalité, début, expiration et urgence éventuelle.

## Article 138 — Justification

La demande explique pourquoi un accès moins puissant ne suffit pas.

## Article 139 — Revues

Sont renseignées les revues du propriétaire de ressource, de l’autorité métier, de la sécurité et des données lorsque nécessaires.

## Article 140 — Décision

La décision réelle demeure vide jusqu’à confirmation de l’autorité compétente. Une demande complétée ne vaut pas approbation.

## Article 141 — Activation et preuve

L’activation indique l’exécutant, le compte, la méthode d’authentification, les journaux, la date et le test de fonctionnement.

## Article 142 — Révocation

Le modèle prévoit expiration, révocation, confirmation de retrait et traitement des sessions ou secrets dérivés.

---

# TITRE XVI — MODÈLE 2 : ACCÈS DE SECOURS

## Article 143 — Objet

Le modèle décrit le scénario justifiant un accès de secours, les seuils d’ouverture, les gardiens, les contrôles et les actions permises.

## Article 144 — Protection

Il précise stockage, séparation, authentification, journalisation, alerte et vérification périodique.

## Article 145 — Utilisation

Toute utilisation indique motif, autorité, utilisateurs, durée, actions, preuves et retour à l’état normal.

## Article 146 — Test

Le test confirme l’accessibilité contrôlée sans exposer durablement le secret ou privilège.

---

# TITRE XVII — MODÈLE 3 : ANALYSE DE RISQUE

## Article 147 — Contexte

Le modèle identifie décision, actif, périmètre, hypothèses, parties affectées et sources.

## Article 148 — Scénarios

Chaque scénario indique menace, vulnérabilité, événement, impact et contrôles existants.

## Article 149 — Évaluation

Sont distingués risque brut, efficacité prouvée des contrôles et risque résiduel.

## Article 150 — Traitement

Le modèle documente options, responsable, échéance, coûts, dépendances et décision requise.

## Article 151 — Acceptation

Le champ d’acceptation demeure vide tant que l’autorité compétente n’a pas décidé au moyen du modèle décisionnel applicable.

---

# TITRE XVIII — MODÈLE 4 : MODÉLISATION DES MENACES

## Article 152 — Système examiné

Le modèle décrit mission, frontières, acteurs, actifs, flux, dépendances et hypothèses de confiance.

## Article 153 — Cas d’abus

Il examine usurpation, escalade, exfiltration, altération, indisponibilité, contournement, répudiation, capture et perte de récupération.

## Article 154 — Données et secrets

Les classifications, flux, stockages, journaux et points de sortie sont identifiés sans révéler les valeurs sensibles.

## Article 155 — Contrôles et tests

Chaque menace est reliée aux contrôles, tests, preuves, limites et risques résiduels.

## Article 156 — Réexamen

Le modèle expire après changement significatif d’architecture, données, fournisseurs, permissions ou exposition.

---

# TITRE XIX — MODÈLE 5 : ACCEPTATION DE RISQUE DE SÉCURITÉ

## Article 157 — Référence

Le modèle référence l’analyse de risque, l’actif, les contrôles, l’exception éventuelle et les décisions liées.

## Article 158 — Risque résiduel

Le risque résiduel, les personnes et capacités affectées, la durée et les scénarios de réalisation sont exposés sans minimisation.

## Article 159 — Conditions

Les compensations, surveillance, seuils de suspension, échéance et plan de sortie sont obligatoires.

## Article 160 — Autorité

L’autorité réelle, son mandat, la date et la décision ne sont jamais préremplis ni déduits d’un accès technique.

---

# TITRE XX — MODÈLE 6 : EXCEPTION DE SÉCURITÉ

## Article 161 — Exigence concernée

Le modèle cite la règle exacte, le périmètre et la raison pour laquelle elle ne peut être satisfaite immédiatement.

## Article 162 — Limites

Il indique durée, actifs, environnements, opérations autorisées et opérations interdites.

## Article 163 — Compensations

Les contrôles compensatoires, leurs tests, responsables et preuves sont renseignés.

## Article 164 — Sortie

Le plan prévoit correction, date cible, révocation et preuve de résorption.

---

# TITRE XXI — MODÈLE 7 : CÉRÉMONIE DE CLÉ

## Article 165 — Objet

Le modèle couvre génération, activation, rotation, récupération, révocation, destruction ou transfert d’une clé critique.

## Article 166 — Participants

Il distingue autorité, gardiens, opérateurs, témoins, auditeur et personnes récusées.

## Article 167 — Préconditions

Sont vérifiés environnement, outils, sources d’aléa, appareils, sauvegardes, journaux, identités et procédures d’urgence.

## Article 168 — Exécution

La chronologie consigne actions et résultats sans inscrire les valeurs secrètes.

## Article 169 — Vérification

Le modèle inclut empreintes publiques, attestations, tests, distribution contrôlée et confirmation des destructions ou révocations requises.

---

# TITRE XXII — MODÈLE 8 : SIGNALEMENT DE VULNÉRABILITÉ

## Article 170 — Signalement

Le modèle contient canal, déclarant, date, actif, version, description minimale, impact supposé et niveau de confidentialité.

## Article 171 — Preuve sûre

Les éléments de reproduction sont transmis par un canal adapté et minimisent les données ou secrets exposés.

## Article 172 — Accusé de réception

La réception, le responsable, la priorité initiale et la prochaine échéance sont enregistrés.

## Article 173 — Coordination

Le modèle prévoit échanges, correction, vérification, communication et clôture.

---

# TITRE XXIII — MODÈLES 9 ET 10 : DÉCLARATION ET JOURNAL D’INCIDENT

## Article 174 — Déclaration d’incident

La déclaration contient signal, faits connus, actifs, personnes ou organisations affectées, classification initiale, urgence, mesures déjà prises et contacts.

## Article 175 — Incertitude

Les faits, hypothèses et inconnues sont distingués. L’absence de certitude ne bloque pas les mesures conservatoires.

## Article 176 — Journal d’incident

Chaque entrée indique temps du fait, temps d’enregistrement, acteur, décision ou action, objet, résultat, preuve et prochain responsable.

## Article 177 — Autorisations

Les décisions urgentes, accès exceptionnels, communications et restaurations référencent leur autorité.

## Article 178 — Clôture

La clôture conserve réserves, risques, actions correctives et décision humaine compétente.

---

# TITRE XXIV — MODÈLE 11 : COMMUNICATION D’INCIDENT

## Article 179 — Public

Le modèle identifie destinataires internes, personnes affectées, partenaires, autorités ou public, selon les obligations réelles.

## Article 180 — Contenu

Il distingue faits établis, impacts, protections, actions attendues, limites, contact et prochaine mise à jour.

## Article 181 — Validation

L’autorité de communication et les revues sécurité, juridique, données ou métier sont identifiées selon le cas.

## Article 182 — Protection

La communication ne révèle pas de détails exploitables, secrets ou données personnelles non nécessaires.

---

# TITRE XXV — MODÈLE 12 : REVUE POST-INCIDENT

## Article 183 — Finalité

La revue recherche les faits, conditions, décisions, contrôles manquants et améliorations sans réduire l’analyse à la faute individuelle.

## Article 184 — Chronologie

Elle reconstruit détection, qualification, autorité, confinement, éradication, restauration, communication et clôture.

## Article 185 — Causes

Elle distingue causes directes, contributives, organisationnelles, techniques, humaines et externes.

## Article 186 — Contrôles

Les contrôles attendus, présents, absents, contournés ou inefficaces sont reliés à des preuves.

## Article 187 — Actions

Chaque action possède responsable, priorité, échéance, décision source et preuve de clôture.

---

# TITRE XXVI — MODÈLE 13 : TEST DE RESTAURATION

## Article 188 — Objectif

Le modèle identifie actif, scénario, point de restauration, RTO, RPO, environnement et critères de succès.

## Article 189 — Préconditions

Il vérifie sauvegarde, clés, comptes, outils, dépendances, isolation et autorité.

## Article 190 — Exécution

Les étapes, durées, erreurs, décisions et résultats sont enregistrés.

## Article 191 — Validation

La validation contrôle intégrité, droits, relations, versions, suppressions, révocations et capacité à servir la mission.

## Article 192 — Résultat

Le résultat peut être `RÉUSSI`, `PARTIEL`, `ÉCHOUÉ` ou `ANNULÉ`, avec écarts et actions.

---

# TITRE XXVII — MODÈLE 14 : EXERCICE DE CONTINUITÉ

## Article 193 — Scénario

Le modèle décrit événement, fonctions perdues, hypothèses, limites, participants et règles de sécurité.

## Article 194 — Objectifs

Les objectifs couvrent décision, communication, mode dégradé, suppléance, restauration, fournisseur alternatif et preuve.

## Article 195 — Observations

Les temps, décisions, blocages, dépendances et contournements sont enregistrés.

## Article 196 — Retour d’expérience

Les écarts deviennent actions, risques, décisions ou mises à jour de procédures.

---

# TITRE XXVIII — MATRICES ET CHAÎNES DE PREUVE

## Article 197 — Matrice actif-risque

Chaque actif critique est relié à ses menaces, risques, contrôles, tests, responsables et preuves.

## Article 198 — Matrice accès-mandat

Chaque accès privilégié est relié à une identité, fonction, mandat, permission, ressource, environnement, expiration et révocation.

## Article 199 — Matrice secret-consommateur

Chaque secret est relié à ses gardiens, consommateurs, environnements, rotations, révocations et incidents.

## Article 200 — Matrice sauvegarde-restauration

Chaque sauvegarde critique est reliée à une fréquence, vérification, test de restauration, RTO, RPO, dépendances et actions correctives.

## Article 201 — Matrice Loi-contrôle-preuve

Le futur audit G0 doit relier les Lois de sécurité aux domaines, responsables, contrôles, tests, preuves, fréquences et écarts.

## Article 202 — Références croisées

Les registres permettent de reconstruire :

> identité → mandat → accès → session → action → journal → incident éventuel ;

> actif → menace → risque → contrôle → test → exception éventuelle ;

> secret → gardien → consommateurs → rotation → révocation → incident éventuel ;

> sauvegarde → vérification → restauration → continuité → enseignement.

---

# TITRE XXIX — ÉTAT INITIAL ET ÉCARTS AVANT G0

## Article 203 — État de preuve

Le niveau maximal généralement disponible pour les objets de sécurité Genesis II est `P1 — DOCUMENTÉ`. Les contrôles techniques, tests, restaurations et exercices restent à établir.

## Article 204 — Autorités

Les fonctions permanentes de sécurité, garde des clés, exploitation, incident, continuité, données et audit demeurent vacantes ou non attribuées selon le Registre des autorités et mandats.

## Article 205 — Actifs

L’inventaire exhaustif des actifs critiques n’est pas établi.

## Article 206 — Accès

L’inventaire et le rapprochement des accès privilégiés réels ne sont pas établis.

## Article 207 — Secrets

L’inventaire des secrets, clés et certificats n’est pas établi.

## Article 208 — Vulnérabilités

Aucun registre consolidé de vulnérabilités n’est établi.

## Article 209 — Incidents

Aucun registre initial consolidé des incidents Genesis II n’est établi ; l’absence d’inscription ne prouve pas l’absence d’incident historique.

## Article 210 — Sauvegardes

Les sauvegardes, miroirs, clés de récupération et tests de restauration du dépôt et des registres restent à inventorier et tester.

## Article 211 — Continuité

Les RTO, RPO, modes dégradés, suppléances et exercices restent à décider et prouver.

## Article 212 — Tiers

Les tiers techniques observés restent à qualifier sur les plans contractuel, sécurité, données, continuité et sortie.

## Article 213 — Exceptions

Aucune exception active formellement acceptée n’est établie.

## Article 214 — Agents

Les missions et contributions assistées par IA sont documentées partiellement ; leur consolidation relève du futur ensemble IA.

## Article 215 — Contrôles techniques

Les protections de branche, authentifications, journaux, scans, sauvegardes et alertes ne sont pas déclarés opérationnels sans preuve.

---

# TITRE XXX — DÉCISIONS HUMAINES À VALIDER

## Article 216 — Références et états

Les séries de références, classifications, criticités, états et transitions proposés doivent être confirmés ou modifiés.

## Article 217 — Autorités

Doivent être nommées ou confirmées les autorités de sécurité, accès, secrets, incident, continuité, exploitation, données et audit.

## Article 218 — Propriétaires

Les propriétaires institutionnels et responsables opérationnels des actifs critiques doivent être désignés.

## Article 219 — Actifs et périmètres

L’inventaire réel, les frontières, classifications, dépendances et exigences de protection doivent être validés.

## Article 220 — Accès

Les méthodes d’authentification, durées, privilèges, approbations, revues et procédures de révocation doivent être décidées.

## Article 221 — Secrets

Les systèmes de conservation, cérémonies, gardiens, rotations, récupérations, algorithmes et destructions doivent être validés.

## Article 222 — Risques

Les méthodes d’évaluation, seuils, autorités d’acceptation, durées et contrôles compensatoires doivent être confirmés.

## Article 223 — Vulnérabilités

Les canaux, délais, niveaux de confidentialité, priorités et règles de divulgation doivent être établis.

## Article 224 — Incidents

Les niveaux d’incident, pouvoirs d’urgence, commandement, communications, obligations externes et conditions de clôture doivent être décidés.

## Article 225 — Sauvegarde et continuité

Les RTO, RPO, fréquences, rétentions, indépendances, exercices et critères de succès doivent être validés.

## Article 226 — Tiers

La qualification de GitHub, OpenAI et de tout autre fournisseur doit être réalisée sans présumer leur permanence.

## Article 227 — Registres

La classification, conservation, accès, export, sauvegarde, restauration et destruction des registres de sécurité doivent être définis.

## Article 228 — Modèles

Les champs obligatoires, signatures, formats d’horodatage, règles de non-applicabilité et conditions futures d’automatisation doivent être validés.

---

# TITRE XXXI — EFFET DOCUMENTAIRE, G0 ET SUITE

## Article 229 — Condition visée

L’adoption éventuelle du présent document satisfera la condition documentaire relative aux registres et modèles initiaux de sécurité exigés par `SECURITY-GOVERNANCE-0001`.

## Article 230 — Effet limité

Cette adoption éventuelle ne :

- nommera aucun titulaire ;
- validera aucun accès ;
- créera ou révélera aucun secret ;
- corrigera aucune vulnérabilité ;
- clôturera aucun incident ;
- prouvera aucune sauvegarde ni restauration ;
- approuvera aucun tiers ;
- acceptera aucune exception ni aucun risque ;
- rendra aucune capacité opérationnelle ;
- prononcera pas `G0` ;
- autorisera aucun codage canonique.

## Article 231 — Conditions encore ouvertes

Même après une adoption éventuelle demeureront ouverts les registres et modèles de données, IA et produits, le Registre lexical initial, la matrice complète `Loi → domaine → responsable → contrôle → preuve`, les inventaires réels, nominations, contrôles, tests, restaurations et l’audit final de `G0`.

## Article 232 — Prochain ensemble documentaire

Le prochain ensemble prévu dans la file Genesis II est :

`REGISTRES-ET-MODELES-DONNEES-0001`.

Il devra couvrir les domaines et responsabilités, jeux de données, finalités, traitements, classifications, sources, provenances, lignées, flux, partages, tiers, conservation, droits, qualité, analyses d’impact, usages IA, risques, exceptions et incidents de données, ainsi que les modèles exigés par `DATA-GOVERNANCE-0001`.

## Article 233 — Évolution

Toute modification substantielle des registres, modèles, états, autorités, classifications ou effets exige une révision, un amendement ou un texte de remplacement gouverné.

## Article 234 — Statut du projet

Jusqu’à adoption expresse par Koné Djakaridja, dit Zakaria le Soufi, ou par l’autorité compétente ultérieurement reconnue, et inscription au Registre des adoptions, le présent document demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

---

## Index de contrôle

- **Nombre de titres :** 31
- **Nombre d’articles :** 234
- **Registres initiaux couverts :** 11
- **Registres :** actifs critiques ; risques et contrôles ; accès privilégiés ; secrets, clés et certificats ; vulnérabilités ; incidents ; sauvegardes et restaurations ; continuité et exercices ; tiers critiques ; exceptions de sécurité ; agents et automatisations sensibles
- **Modèles initiaux couverts :** 14
- **Modèles :** demande et revue d’accès ; accès de secours ; analyse de risque ; modélisation des menaces ; acceptation de risque ; exception ; cérémonie de clé ; signalement de vulnérabilité ; déclaration d’incident ; journal d’incident ; communication ; revue post-incident ; test de restauration ; exercice de continuité
- **Accès privilégié réel validé :** aucun
- **Secret ou clé publié :** aucun
- **Vulnérabilité déclarée corrigée :** aucune
- **Incident déclaré clos :** aucun
- **Sauvegarde déclarée restaurable :** aucune
- **Test de restauration déclaré réussi :** aucun
- **Exception de sécurité active établie :** aucune
- **Tiers critique définitivement approuvé :** aucun
- **Fonction permanente nommée par le document :** aucune
- **Codage canonique commencé :** non
- **Condition `G0` visée :** registres et modèles initiaux de sécurité
- **Prochain ensemble prévu :** `REGISTRES-ET-MODELES-DONNEES-0001`
- **Règle d’intégrité :** toute adoption future devra identifier le commit de rédaction et l’empreinte Git exacte du contenu soumis

## Formule finale

Avant de déclarer la sécurité d’un actif, accès, secret, fournisseur, sauvegarde ou système comme établie, Genesis II doit pouvoir répondre sans supposition :

- quel actif et quelle mission sont protégés ;
- qui en répond institutionnellement et opérationnellement ;
- quelles menaces et quels risques ont été analysés ;
- quels contrôles sont réellement en place ;
- quels tests et preuves couvrent quelles exigences ;
- quels accès, secrets et dépendances permettent l’action ;
- comment suspendre, révoquer, isoler, restaurer et remplacer ;
- quelles exceptions et risques résiduels subsistent ;
- qui peut décider, exécuter, communiquer, auditer et clôturer ;
- quelles traces permettront à une génération future de reconstruire les faits.

Jusqu’à son adoption expresse et son inscription au Registre des adoptions, le présent document demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
