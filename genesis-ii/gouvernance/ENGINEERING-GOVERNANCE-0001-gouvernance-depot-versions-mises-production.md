# ENGINEERING-GOVERNANCE-0001 — GOUVERNANCE DU DÉPÔT, DES VERSIONS ET DES MISES EN PRODUCTION

## Genesis II — Version 0.1

**Statut : PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

- **Périmètre :** dépôts, branches, contributions, commits, revues, intégration continue, versions, artefacts, migrations, environnements, déploiements, retours arrière, incidents et preuves d’ingénierie de GAMAD Core
- **Autorité de proposition :** Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD
- **Rédaction :** chantier GAMAD Core — Genesis II, avec assistance d’intelligence artificielle
- **Dépendances normatives :** ACTE-0001 ; SOURCES-0001 ; GOVERNANCE-0001 ; GOVERNANCE-0002 ; GOVERNANCE-0003
- **Principe directeur :** le dépôt n’est pas l’autorité de la mission ; il est l’instrument traçable par lequel des décisions valides sont transformées en implémentations contrôlées, versionnées, déployables, réversibles et transmissibles

---

## Préambule

GAMAD Core doit être construit comme une fondation durable, et non comme une accumulation de fichiers dépendant de la mémoire de leurs premiers auteurs.

Un dépôt propre ne suffit pas. Une suite de tests verte ne suffit pas. Une revue approuvée ne suffit pas. Un déploiement réussi ne suffit pas. Chacun de ces éléments constitue une preuve partielle dans une chaîne d’autorité, de conception, d’implémentation et d’exploitation.

La gouvernance d’ingénierie doit permettre de répondre à tout moment :

- quelle décision autorise le changement ;
- quelle norme et quel invariant il exécute ;
- quel auteur ou agent l’a produit ;
- quelles revues et validations ont été réalisées ;
- quels tests prouvent son comportement ;
- quel artefact exact a été publié ;
- où et quand il a été déployé ;
- comment revenir en arrière ;
- quelles données ou identités sont affectées ;
- et comment une génération future pourra reconstruire ces faits.

> **Les applications créent les usages. Le Core maintient la cohérence. L’ingénierie doit rendre cette cohérence vérifiable dans chaque changement.**

Le présent texte traduit les lois de gouvernance de Genesis II en discipline d’ingénierie. Il ne choisit pas encore un langage, un framework, un fournisseur ou une infrastructure définitive. Ces choix restent révisables et doivent être pris par les décisions architecturales et techniques compétentes.

---

# TITRE I — OBJET, CHAMP ET DÉFINITIONS

## Article 1 — Objet

Le présent texte définit les règles de gouvernance applicables au cycle d’ingénierie de GAMAD Core, depuis l’ouverture d’un travail jusqu’à sa mise en production, son observation, son retrait et son archivage.

## Article 2 — Champ d’application

Il s’applique :

- aux dépôts officiels de GAMAD Core ;
- aux dépôts documentaires de Genesis II ;
- aux bibliothèques et composants communs ;
- aux contrats, schémas, migrations et configurations ;
- aux pipelines de construction, test, livraison et déploiement ;
- aux artefacts exécutables ;
- aux environnements du Core ;
- aux contributions humaines, automatisées ou assistées par intelligence artificielle.

## Article 3 — Dépôt canonique

Un **dépôt canonique** est un dépôt officiellement reconnu comme source de référence d’un périmètre déterminé.

La possession ou l’administration technique d’un dépôt ne crée pas une autorité institutionnelle sur le Core.

## Article 4 — Arbre canonique

L’**arbre canonique** est l’état de fichiers correspondant à une référence Git ou à une version officiellement publiée.

Il doit pouvoir être identifié par une empreinte vérifiable.

## Article 5 — Branche canonique

La **branche canonique** est la branche contenant l’état officiellement adopté ou publié selon les règles applicables.

Pour le chantier documentaire Genesis II, cette branche est `main`, sauf décision ultérieure compétente.

## Article 6 — Branche de chantier

Une **branche de chantier** est un espace de travail isolé destiné à préparer une proposition, une correction, une expérimentation ou une implémentation avant son intégration canonique.

Sa présence distante ne vaut ni validation, ni adoption, ni mise en production.

## Article 7 — Changement

Un **changement** est toute modification intentionnelle d’un document, code, contrat, schéma, migration, configuration, pipeline, dépendance, artefact ou environnement.

## Article 8 — Contribution

Une **contribution** est l’ensemble cohérent de changements proposé par un auteur, une équipe ou un agent mandaté pour satisfaire une mission déterminée.

## Article 9 — Intégration

L’**intégration** est l’incorporation contrôlée d’une contribution dans une branche ou version de référence.

Elle ne vaut pas adoption d’une norme lorsque l’adoption institutionnelle est requise.

## Article 10 — Construction

La **construction** est le processus reproductible transformant les sources en artefacts vérifiables.

## Article 11 — Livraison

La **livraison** est la mise à disposition contrôlée d’un artefact versionné et accompagné de ses preuves, sans impliquer nécessairement son activation en production.

## Article 12 — Déploiement

Le **déploiement** est l’installation ou l’activation d’un artefact ou d’une configuration dans un environnement déterminé.

## Article 13 — Mise en production

La **mise en production** est l’autorisation et l’activation d’une version pour servir les usages réels, données réelles ou capacités souveraines de GAMAD Core.

## Article 14 — Retour arrière

Le **retour arrière** est l’action contrôlée visant à restaurer un état antérieur sûr ou à neutraliser les effets d’un changement.

Il peut nécessiter une restauration de code, configuration, schéma, données ou trafic.

## Article 15 — Preuve d’ingénierie

Une **preuve d’ingénierie** est un élément vérifiable démontrant qu’une exigence, un contrôle ou une action a été exécuté dans un périmètre déclaré.

---

# TITRE II — PRINCIPES DIRECTEURS DE L’INGÉNIERIE

## Article 16 — Primauté des normes

Le code, les tests, les pipelines et les configurations exécutent les normes et décisions applicables ; ils ne les remplacent pas.

## Article 17 — Décision source

Tout changement structurant doit être rattaché à une décision, une exigence, un incident, une correction ou une mission identifiable.

Un changement sans source déclarée doit être classé comme exploration, maintenance mineure ou anomalie à régulariser selon sa nature.

## Article 18 — Constitution avant code canonique

Avant le passage formel de la Porte `G0`, aucun code Genesis II ne peut être présenté comme implémentation canonique de GAMAD Core.

Les prototypes restent autorisés sous statut explicitement non canonique.

## Article 19 — Remplaçabilité

Toute technologie, bibliothèque, infrastructure, base de données, fournisseur ou outil d’intelligence artificielle est remplaçable.

Les décisions doivent éviter qu’une dépendance technique devienne la source de la mission ou de l’identité du Core.

## Article 20 — Cohérence avant vitesse

La vitesse de livraison ne justifie pas la violation des frontières, contrats, invariants, contrôles ou preuves exigées.

## Article 21 — Proportionnalité

La profondeur de revue, de test, de validation et d’autorisation est proportionnée au niveau de risque défini par GOVERNANCE-0003.

## Article 22 — Moindre changement nécessaire

Une contribution doit viser le changement minimal cohérent permettant de satisfaire l’objectif déclaré, sans introduire silencieusement des réformes non demandées.

## Article 23 — Réversibilité par conception

Tout changement structurant doit prévoir sa migration, son arrêt, son remplacement ou son retour arrière avant la mise en production.

## Article 24 — Refus par défaut

Un accès, une exception, une fusion, une publication ou un déploiement non explicitement autorisé est réputé interdit.

## Article 25 — Séparation des fonctions

La rédaction, la revue, l’approbation, l’intégration, la publication, le déploiement et l’audit sont des fonctions distinctes.

Le cumul transitoire est déclaré et compensé selon GOVERNANCE-0001 et GOVERNANCE-0002.

## Article 26 — Traçabilité continue

Chaque étape critique relie l’acteur, sa fonction, l’autorité d’origine, la décision source, l’objet, la date, le résultat et les preuves.

## Article 27 — Reproductibilité

Les constructions, tests et déploiements doivent être reproductibles autant que raisonnablement possible à partir de sources, versions, dépendances et configurations identifiables.

## Article 28 — Automatisation vérifiable

L’automatisation est privilégiée lorsqu’elle réduit les erreurs et améliore la preuve, sans masquer l’autorité ni rendre la procédure incompréhensible.

## Article 29 — Échec visible

Un contrôle échoué, ignoré ou contourné doit rester visible et être traité par une décision ou exception identifiable.

## Article 30 — Aucune preuve fabriquée

Il est interdit de falsifier un test, une revue, un journal, un artefact, une signature, une couverture, un déploiement ou une restauration.

## Article 31 — Documentation proportionnée

Le niveau de documentation dépend du risque, mais tout changement doit rester compréhensible par un mainteneur qui n’a pas participé à sa création.

## Article 32 — Données réelles protégées

Les données réelles, identités, secrets et preuves sensibles ne doivent pas être copiés dans les environnements, journaux ou tests sans finalité, autorisation et protection appropriées.

---

# TITRE III — DÉPÔTS, PÉRIMÈTRES ET STRUCTURE

## Article 33 — Registre des dépôts

Genesis II doit maintenir un Registre des dépôts officiels identifiant :

- le nom ;
- la finalité ;
- le propriétaire institutionnel ;
- l’autorité de gouvernance ;
- les mainteneurs ;
- la branche canonique ;
- la classification ;
- les dépendances ;
- le statut ;
- les procédures de sauvegarde et succession.

## Article 34 — Propriété institutionnelle

Les dépôts du Core sont des instruments institutionnels de GAMAD ou de l’entité valablement mandatée pour les conserver.

Ils ne constituent pas la propriété personnelle de leurs administrateurs ou contributeurs.

## Article 35 — Comptes organisationnels

Les dépôts critiques doivent être hébergés sous des comptes ou organisations dont la récupération et la succession sont institutionnellement organisées.

## Article 36 — Dépôt documentaire et dépôt d’implémentation

Les textes normatifs, décisions et registres peuvent être séparés des implémentations techniques lorsque cette séparation protège la clarté et les droits d’accès.

Les références croisées restent explicites.

## Article 37 — Mono-dépôt et dépôts multiples

Le choix entre mono-dépôt et dépôts multiples relève d’une décision architecturale analysant au minimum :

- les frontières de domaines ;
- les contrats ;
- les cycles de publication ;
- les droits d’accès ;
- la traçabilité ;
- la capacité de restauration ;
- la complexité opérationnelle.

## Article 38 — Structure canonique

Chaque dépôt possède une structure documentée indiquant où résident les sources, contrats, migrations, tests, outils, documents, exemples et artefacts de gouvernance.

## Article 39 — Fichiers d’autorité

Un fichier de configuration, de manifeste ou de métadonnées ne peut se déclarer supérieur aux normes dont il dérive.

Il doit référencer ses sources applicables lorsqu’il encode des règles de gouvernance.

## Article 40 — README de gouvernance

Tout dépôt officiel contient un point d’entrée indiquant au minimum :

- sa mission ;
- son statut ;
- ses autorités ;
- les textes à lire ;
- les règles de contribution ;
- les commandes de validation ;
- les procédures de publication ;
- les limites connues.

## Article 41 — Code owners

Les mécanismes de propriétaires de chemins ou de revue obligatoire traduisent des mandats enregistrés.

Ils ne créent pas par eux-mêmes une autorité durable.

## Article 42 — Archivage d’un dépôt

L’archivage d’un dépôt critique exige :

- une décision ;
- un état final identifié ;
- une exportation ;
- une empreinte ;
- la conservation des références ;
- la désignation du successeur ou le constat de fin.

## Article 43 — Miroirs et sauvegardes

Les dépôts critiques disposent de sauvegardes ou miroirs contrôlés permettant la restauration sans dépendance à un fournisseur unique.

## Article 44 — Forks et copies

Un fork, miroir ou export doit indiquer son statut et ne doit pas être présenté comme canonique sans décision de reconnaissance.

## Article 45 — Dépôts de produits

Les produits et modules peuvent posséder leurs propres dépôts et cycles, mais doivent respecter les contrats, versions et normes communes applicables du Core.

---

# TITRE IV — BRANCHES, ESPACES DE TRAVAIL ET ISOLATION

## Article 46 — Protection de `main`

La branche `main` doit être protégée contre les modifications non autorisées, les réécritures forcées ordinaires, les suppressions silencieuses et les intégrations sans preuves requises.

## Article 47 — Contenu de `main`

`main` contient l’état officiellement adopté, intégré ou publié dans le périmètre du dépôt.

Un projet ou prototype non adopté doit rester clairement identifiable hors de cet état canonique.

## Article 48 — Nomenclature des branches

Les branches de chantier utilisent une nomenclature explicite, par exemple :

- `agent/` pour un chantier exécuté ou assisté par agent ;
- `feature/` pour une capacité ;
- `fix/` pour une correction ;
- `security/` pour un traitement sensible ;
- `migration/` pour une évolution de données ;
- `release/` pour une préparation de version ;
- `hotfix/` pour une correction urgente.

## Article 49 — Une mission par branche

Une branche doit correspondre à une mission ou un dossier cohérent.

Les changements sans lien doivent être séparés afin de permettre une revue et un retour arrière intelligibles.

## Article 50 — Base identifiée

Toute branche de chantier doit avoir une base connue et vérifiable.

Lorsque la base influence le sens du changement, elle est inscrite dans le dossier ou le rapport de contribution.

## Article 51 — Synchronisation de la base

Avant intégration, la contribution doit être évaluée par rapport à l’état canonique actuel afin de détecter les conflits, divergences normatives et validations devenues obsolètes.

## Article 52 — Worktrees et espaces isolés

Les espaces de travail parallèles sont autorisés lorsqu’ils empêchent la contamination des états et permettent de conserver le checkout canonique intact.

## Article 53 — Environnement de développement

Le développement s’effectue dans un environnement distinct de la production, avec des secrets, données et permissions adaptés.

## Article 54 — Branches longues

Une branche durable doit être justifiée, régulièrement resynchronisée et surveillée contre la divergence.

Elle ne doit pas devenir un système canonique parallèle non gouverné.

## Article 55 — Branche abandonnée

Une branche abandonnée peut être supprimée après vérification que ses décisions, preuves ou enseignements nécessaires sont conservés.

## Article 56 — Branche de sécurité

Les détails d’une vulnérabilité non corrigée peuvent être confinés dans un canal ou une branche restreinte, sans supprimer les obligations de preuve et de revue.

## Article 57 — Fusion

La fusion incorpore un changement approuvé dans la branche cible.

Elle ne remplace ni l’adoption normative ni l’autorisation de mise en production.

## Article 58 — Méthode d’intégration

Le choix entre merge, squash, rebase ou autre méthode doit préserver l’attribution, la compréhension du changement et les références nécessaires.

## Article 59 — Réécriture d’historique

La réécriture forcée d’un historique partagé ou canonique est interdite sauf procédure exceptionnelle documentée visant à protéger un secret, une personne ou l’intégrité du dépôt.

## Article 60 — Suppression de branche

La suppression d’une branche après intégration ne supprime pas l’obligation de conserver les commits, décisions, revues et preuves utiles.

---

# TITRE V — COMMITS, AUTEURS ET HISTORIQUE

## Article 61 — Commit intentionnel

Un commit représente une unité cohérente de changement, suffisamment petite pour être comprise et suffisamment complète pour ne pas laisser volontairement le dépôt dans un état trompeur.

## Article 62 — Message de commit

Le message indique la nature et l’intention du changement.

Lorsqu’il exécute une décision ou corrige un incident, il référence l’identifiant applicable.

## Article 63 — Attribution humaine

L’auteur et le committer doivent être attribuables à une identité connue ou à un compte de service institutionnellement enregistré.

## Article 64 — Contribution assistée par IA

Une contribution significative assistée par IA indique cette assistance dans le rapport ou dossier approprié, sans attribuer à l’IA l’autorité humaine d’origine.

## Article 65 — Commits de service

Les commits automatiques sont produits par des identités de service distinctes, avec une mission, des permissions et un parrain identifiables.

## Article 66 — Signature

Les commits, tags ou artefacts critiques peuvent exiger une signature vérifiable selon le niveau de risque et la doctrine de sécurité.

La signature prouve une attribution ou intégrité technique, non la vérité de toutes les affirmations du contenu.

## Article 67 — Commits secrets interdits

Aucun secret actif, clé privée, mot de passe, jeton ou donnée sensible injustifiée ne doit être commité.

## Article 68 — Nettoyage d’un secret exposé

La suppression d’un secret de l’état courant ne suffit pas.

La procédure doit prévoir sa révocation, sa rotation, l’analyse de l’exposition et, si nécessaire, le traitement de l’historique.

## Article 69 — Commits générés

Les fichiers générés ne sont versionnés que lorsqu’une décision établit leur utilité pour la reproductibilité, la distribution ou la revue.

Leur source de génération reste identifiable.

## Article 70 — Traçabilité des changements normatifs

Toute modification d’un texte adopté, contrat canonique ou invariant doit passer par la procédure de correction, amendement ou remplacement applicable.

## Article 71 — Non-effacement

Un commit incorrect ou remplacé reste une trace historique sauf nécessité de sécurité ou de protection justifiant une procédure exceptionnelle.

## Article 72 — Revert

Un revert neutralise un changement par un nouveau commit et conserve la trace de l’état antérieur.

Il doit indiquer la cause et les effets attendus.

## Article 73 — Cherry-pick

Le transfert sélectif d’un commit entre branches doit préserver ses références et faire l’objet d’une nouvelle évaluation dans le contexte cible.

## Article 74 — Historique compréhensible

L’historique doit permettre de reconstruire les changements majeurs sans dépendre exclusivement d’un outil propriétaire ou d’une mémoire personnelle.

---

# TITRE VI — PROPOSITIONS, REVUES ET INTÉGRATION

## Article 75 — Dossier de contribution

Toute contribution structurante possède un dossier ou une demande d’intégration indiquant :

- l’objectif ;
- la décision source ;
- le périmètre ;
- les fichiers et domaines affectés ;
- le niveau de risque ;
- les migrations ;
- les tests ;
- le rollback ;
- les limites ;
- les revues requises.

## Article 76 — Description fidèle

La description ne doit pas minimiser un changement de sécurité, de données, d’autorité, de contrat ou d’architecture.

## Article 77 — Portée contrôlée

Les contributions trop larges peuvent être refusées ou découpées lorsqu’elles empêchent une revue fiable.

## Article 78 — Auto-revue

L’auteur réalise une première revue de sa propre contribution avant de solliciter les autres contrôles.

## Article 79 — Revue par les pairs

Une contribution significative reçoit une revue par une personne ou fonction compétente différente de son auteur principal lorsque cela est possible.

## Article 80 — Revue constitutionnelle

Un changement qui encode une autorité, un droit, une norme ou une procédure de gouvernance exige une vérification de conformité aux textes supérieurs.

## Article 81 — Revue architecturale

Un changement de frontière, contrat, dépendance structurante, modèle ou capacité souveraine exige une revue architecturale appropriée.

## Article 82 — Revue de sécurité

Un changement affectant authentification, autorisation, cryptographie, secrets, exposition réseau, données sensibles ou audit exige une revue de sécurité proportionnée.

## Article 83 — Revue des données

Un changement affectant la collecte, finalité, responsabilité, rétention, portabilité, suppression ou partage de données exige une revue de données.

## Article 84 — Revue métier

La logique spécialisée d’un produit est validée par son autorité métier sans lui permettre de modifier les invariants du Core.

## Article 85 — Revue opérationnelle

Un changement de déploiement, observabilité, sauvegarde, capacité ou restauration reçoit une revue opérationnelle.

## Article 86 — Revue renforcée

Les changements `R3` et `R4` exigent une revue renforcée, des preuves plus complètes et, lorsque prévu, une double approbation.

## Article 87 — Conflit d’intérêts

Un réviseur en conflit d’intérêts significatif le déclare et ne doit pas être l’unique approbateur.

## Article 88 — Commentaires de revue

Les commentaires distinguent :

- question ;
- suggestion ;
- réserve ;
- blocage fondé sur une exigence ;
- observation non bloquante.

## Article 89 — Blocage

Un blocage doit citer la norme, le contrôle ou le risque qui le justifie et indiquer, lorsque possible, les conditions de levée.

## Article 90 — Résolution

La résolution d’un commentaire conserve une trace suffisante de la réponse, du changement ou de la décision de ne pas modifier.

## Article 91 — Approbation

Une approbation atteste que le réviseur a contrôlé le périmètre relevant de sa compétence.

Elle ne garantit pas les domaines qu’il n’a pas examinés.

## Article 92 — Approbation devenue obsolète

Une modification substantielle après approbation peut rendre cette approbation obsolète et exiger une nouvelle revue.

## Article 93 — Conditions d’intégration

L’intégration exige au minimum :

- un objet identifiable ;
- les revues requises ;
- les contrôles obligatoires réussis ou une exception valide ;
- l’absence de conflit non résolu ;
- les références de décision ;
- les preuves de migration et rollback lorsque nécessaires.

## Article 94 — Intégration par mainteneur

Le mainteneur vérifie les conditions d’intégration dans le cadre de son mandat.

Il ne peut convertir sa permission technique en pouvoir de déroger aux normes.

---

# TITRE VII — INTÉGRATION CONTINUE, TESTS ET PORTES DE QUALITÉ

## Article 95 — Pipeline officiel

Chaque dépôt d’implémentation critique possède un pipeline officiel versionné et contrôlé.

## Article 96 — Pipeline comme code

Les définitions de pipeline sont versionnées, revues et testées comme les autres composants critiques.

## Article 97 — Environnements éphémères

Des environnements éphémères peuvent être créés pour tester une contribution, avec isolation, durée et destruction contrôlées.

## Article 98 — Classes de tests

Les tests peuvent comprendre :

- tests unitaires ;
- tests de contrats ;
- tests d’intégration ;
- tests de propriétés et invariants ;
- tests de migrations ;
- tests de sécurité ;
- tests de performance ;
- tests de restauration ;
- tests de compatibilité ;
- tests de bout en bout.

## Article 99 — Tests reliés aux exigences

Les exigences et invariants critiques doivent être reliés aux tests ou preuves qui les contrôlent.

## Article 100 — Couverture utile

Un indicateur de couverture ne remplace pas l’analyse des comportements, frontières et risques réellement testés.

## Article 101 — Tests déterministes

Les tests doivent être déterministes autant que possible.

Les tests instables sont identifiés, corrigés ou isolés avec une échéance.

## Article 102 — Échec de test

Un test obligatoire échoué bloque l’intégration ou le déploiement, sauf exception valide, limitée et enregistrée.

## Article 103 — Tests désactivés

La désactivation ou l’affaiblissement d’un test critique exige une justification, une autorité et un plan de rétablissement.

## Article 104 — Analyse statique

Le pipeline peut imposer des contrôles de typage, style, dépendances, secrets, vulnérabilités, licences, frontières et qualité documentaire.

## Article 105 — Tests d’architecture

Les frontières, dépendances interdites et invariants structurels doivent être automatisés lorsque cela est raisonnablement possible.

## Article 106 — Compilateur constitutionnel partiel

Les contrôles automatiques de structure, références, statuts, contrats et permissions forment un compilateur constitutionnel partiel.

Ils vérifient des règles adoptées ; ils ne les adoptent pas.

## Article 107 — Matrice des contrôles

Une matrice relie les niveaux de risque aux contrôles obligatoires, facultatifs et renforcés.

## Article 108 — Conservation des résultats

Les résultats critiques de pipeline sont conservés avec l’identifiant du commit, de l’environnement, des outils et de la configuration utilisés.

## Article 109 — Confiance dans le pipeline

Les identités, permissions, dépendances et environnements du pipeline font eux-mêmes l’objet de contrôles de sécurité et de continuité.

## Article 110 — Contournement

Tout contournement d’un contrôle requis est visible, attribuable, limité dans le temps et inscrit comme exception ou décision d’urgence.

---

# TITRE VIII — VERSIONS, RELEASES ET ARTEFACTS

## Article 111 — Politique de version

GAMAD Core adopte une politique de version explicite pour ses documents, contrats, composants, schémas et distributions.

## Article 112 — Version sémantique ou équivalente

Lorsqu’elle est appropriée, la version distingue les changements incompatibles, compatibles fonctionnels et correctifs.

Une autre convention peut être adoptée si elle offre une clarté équivalente.

## Article 113 — Version de contrat

Les API, événements, schémas et contrats possèdent une version et une politique de compatibilité distinctes de la seule version de l’application.

## Article 114 — Release candidate

Une version candidate est identifiable et ne doit pas être confondue avec une version stable ou autorisée en production.

## Article 115 — Tag de version

Un tag de version critique est immuable, attribuable et, lorsque requis, signé.

## Article 116 — Notes de version

Chaque version significative documente :

- les changements ;
- les décisions sources ;
- les incompatibilités ;
- les migrations ;
- les risques ;
- les procédures de rollback ;
- les problèmes connus.

## Article 117 — Artefact immuable

Un artefact publié sous une version donnée ne doit pas être remplacé silencieusement.

Toute reconstruction produit une nouvelle empreinte et, si nécessaire, une nouvelle version.

## Article 118 — Provenance d’artefact

L’artefact doit pouvoir être relié :

- au commit source ;
- au pipeline ;
- aux dépendances ;
- aux résultats de test ;
- à l’identité de construction ;
- à son empreinte.

## Article 119 — Registre des versions

Le Registre des versions identifie les versions publiées, leur statut, leurs artefacts, compatibilités, environnements et décisions d’autorisation.

## Article 120 — SBOM

Les artefacts critiques disposent, lorsque possible, d’une nomenclature de composants ou preuve équivalente permettant d’identifier leurs dépendances.

## Article 121 — Attestations

Les attestations de provenance, test, signature ou conformité sont conservées comme preuves et ne doivent pas être présentées au-delà de leur périmètre réel.

## Article 122 — Canaux de distribution

Les canaux de distribution officiels sont identifiés, protégés et séparés des sources non vérifiées.

## Article 123 — Dépréciation

La dépréciation d’une version ou d’un contrat indique son calendrier, son successeur, ses impacts et les obligations de migration.

## Article 124 — Fin de support

La fin de support d’une version est décidée, publiée et accompagnée des mesures de sécurité, d’archivage et de continuité nécessaires.

---

# TITRE IX — SCHÉMAS, MIGRATIONS ET ÉVOLUTION DES DONNÉES

## Article 125 — Responsabilité du schéma

Chaque schéma de données possède un domaine responsable et une autorité de changement identifiable.

## Article 126 — Migration versionnée

Toute migration est versionnée, attribuable, ordonnée et reliée à la décision ou exigence qu’elle exécute.

## Article 127 — Migration immuable exécutée

Une migration déjà exécutée dans un environnement partagé ne doit pas être modifiée silencieusement.

Une correction ultérieure utilise une nouvelle migration ou une procédure explicitement gouvernée.

## Article 128 — Compatibilité progressive

Les évolutions critiques privilégient des étapes compatibles permettant le déploiement, la migration, l’observation et le retrait sans rupture brutale.

## Article 129 — Expand and contract

Lorsqu’elle est adaptée, la stratégie d’expansion puis contraction est utilisée pour séparer l’introduction, la transition et la suppression d’un schéma ou contrat.

## Article 130 — Données irréversibles

Une transformation irréversible de données exige une autorité renforcée, une sauvegarde vérifiée, une simulation et une procédure de réparation ou compensation.

## Article 131 — Test de migration

Les migrations sont testées sur des structures et volumes représentatifs sans exposer indûment les données réelles.

## Article 132 — Durée et verrouillage

L’impact d’une migration sur la disponibilité, les verrous, la réplication et les performances doit être analysé avant production.

## Article 133 — Migration de retour

Lorsque le rollback de schéma est impossible ou dangereux, cette limite est déclarée et une stratégie alternative est préparée.

## Article 134 — Sauvegarde préalable

Une migration critique exige une sauvegarde ou un mécanisme de récupération vérifié avant exécution.

## Article 135 — Validation après migration

Après migration, des contrôles vérifient l’intégrité, les volumes, les invariants, les erreurs et la capacité de servir les contrats attendus.

## Article 136 — Journal de migration

L’exécution conserve l’environnement, la version, l’acteur, la date, la durée, le résultat, les erreurs et les validations.

## Article 137 — Données de test

Les jeux de test sont synthétiques, anonymisés ou autorisés selon la sensibilité et la finalité.

## Article 138 — Données personnelles

Les migrations affectant des données personnelles ou sensibles reçoivent les revues de données, sécurité et conformité applicables.

## Article 139 — Propriété des données

Le déplacement technique d’une donnée ne transfère pas silencieusement sa responsabilité métier ou institutionnelle.

## Article 140 — Restauration testée

La capacité de restaurer les schémas et données critiques est testée périodiquement, et non supposée à partir de la seule existence d’une sauvegarde.

---

# TITRE X — CONFIGURATIONS, ENVIRONNEMENTS ET SECRETS

## Article 141 — Environnements reconnus

Les environnements sont enregistrés avec leur finalité, classification, responsable, données autorisées, accès, versions et politique de durée.

## Article 142 — Séparation des environnements

Le développement, les tests, la préproduction et la production sont séparés proportionnellement au risque.

## Article 143 — Parité contrôlée

La préproduction reproduit les caractéristiques nécessaires à la validation sans exposer inutilement les secrets et données de production.

## Article 144 — Configuration versionnée

Les configurations non secrètes critiques sont versionnées, revues et reliées aux versions qu’elles gouvernent.

## Article 145 — Configuration secrète

Les secrets sont conservés dans des systèmes adaptés et injectés selon des permissions limitées.

Ils ne doivent pas être inscrits dans les dépôts, images ou journaux ordinaires.

## Article 146 — Inventaire des secrets

Les secrets critiques possèdent un propriétaire institutionnel, une finalité, une portée, une rotation, une procédure de récupération et une date de réexamen.

## Article 147 — Secrets temporaires

Les identifiants temporaires et à portée limitée sont privilégiés pour les pipelines, agents et interventions ponctuelles.

## Article 148 — Rotation

La rotation d’un secret ou certificat est testée et ne doit pas dépendre d’une seule personne.

## Article 149 — Variables et paramètres

Les paramètres modifiant un comportement de sécurité, de données ou de gouvernance sont documentés, contrôlés et audités.

## Article 150 — Feature flags

Les mécanismes d’activation progressive possèdent un responsable, une finalité, une durée, une valeur par défaut, une procédure de retrait et une traçabilité.

## Article 151 — Configuration dérivante

Les écarts entre configuration déclarée et configuration réelle sont détectés, signalés et corrigés.

## Article 152 — Accès d’administration

Les accès d’administration sont nominatifs ou attribuables, à durée et portée limitées, avec journalisation appropriée.

## Article 153 — Accès de secours

Les accès de secours sont protégés, testés, surveillés et utilisés uniquement selon une procédure identifiable.

## Article 154 — Données de production hors production

La copie de données de production vers un autre environnement exige une finalité, une minimisation, une autorisation, une protection et une procédure de suppression.

## Article 155 — Destruction d’environnement

La destruction d’un environnement temporaire inclut la révocation des accès, l’effacement contrôlé des secrets et données, et la conservation des preuves nécessaires.

---

# TITRE XI — LIVRAISON, DÉPLOIEMENT ET MISE EN PRODUCTION

## Article 156 — Séparation livraison-déploiement

La production d’un artefact livrable est distincte de la décision de l’activer dans un environnement.

## Article 157 — Ordre de déploiement

Tout déploiement significatif possède un ordre ou dossier identifiant :

- la version ;
- l’environnement ;
- l’autorité ;
- l’exécutant ;
- les préconditions ;
- les migrations ;
- les contrôles ;
- le rollback ;
- la fenêtre ;
- les communications.

## Article 158 — Autorité de mise en production

La mise en production est autorisée par la fonction compétente selon GOVERNANCE-0002 et GOVERNANCE-0003.

L’opérateur qui exécute ne devient pas l’autorité qui décide.

## Article 159 — Artefact déjà validé

Le déploiement utilise l’artefact exact ayant satisfait les contrôles, sans reconstruction silencieuse entre validation et production.

## Article 160 — Déploiement automatisé

Un pipeline peut exécuter un déploiement lorsqu’il possède une mission, des permissions, des contrôles et une autorisation applicables.

## Article 161 — Stratégie de déploiement

La stratégie — progressive, canary, blue-green, rolling, remplacement complet ou autre — est choisie selon le risque, la capacité de rollback et les caractéristiques du système.

## Article 162 — Vérifications préalables

Avant production, sont vérifiés selon le cas :

- les validations ;
- les sauvegardes ;
- la capacité ;
- les dépendances ;
- les migrations ;
- les secrets ;
- l’observabilité ;
- le rollback ;
- les contacts d’incident.

## Article 163 — Fenêtre de changement

Les changements critiques peuvent être limités à des fenêtres permettant la présence des responsables et la réduction du risque.

## Article 164 — Gel de changement

Un gel peut suspendre temporairement les déploiements non essentiels pendant une période critique, un incident ou une transition majeure.

## Article 165 — Vérifications post-déploiement

Après activation, des contrôles vérifient la santé, les erreurs, les contrats, les données, les performances, la sécurité et les objectifs du changement.

## Article 166 — Observation

Une période d’observation proportionnée précède la clôture d’un changement structurant.

## Article 167 — Journal de déploiement

Le journal relie la version, l’artefact, l’environnement, l’autorisation, l’exécutant, les dates, les résultats, les incidents et les mesures de rollback.

## Article 168 — Déploiement partiel

Un déploiement partiel ou progressif doit indiquer les populations, realms, régions, capacités ou pourcentages concernés.

## Article 169 — Échec de déploiement

Un déploiement échoué déclenche l’arrêt, le rollback, la correction ou l’escalade selon les seuils définis.

## Article 170 — Déploiement manuel

Une intervention manuelle en production est exceptionnelle, attribuable, journalisée et régularisée dans la configuration ou le code de référence.

## Article 171 — Écart de production

Tout écart entre l’état déclaré et l’état réel de production est traité comme dérive, incident ou changement non régularisé.

## Article 172 — Mise en production de contrats

L’activation d’un contrat incompatible exige la coordination des consommateurs, la politique de version et le plan de migration approuvés.

## Article 173 — Clôture de release

Une release est clôturée après vérification des preuves, incidents, migrations, documentation, versions et observations ouvertes.

---

# TITRE XII — RETOURS ARRIÈRE, HOTFIX ET INCIDENTS

## Article 174 — Plan de rollback

Tout changement `R2`, `R3` ou `R4` comporte un plan de rollback ou une justification explicite de son impossibilité avec des mesures alternatives.

## Article 175 — Déclencheurs de rollback

Les seuils de rollback peuvent inclure erreurs, corruption, dégradation, faille, rupture de contrat ou incapacité à vérifier l’état.

## Article 176 — Autorité de rollback

Les personnes ou automatisations pouvant déclencher un rollback sont identifiées avant le changement.

## Article 177 — Rollback de code et de données

Le retour du code n’implique pas nécessairement le retour des données.

Les deux dimensions doivent être analysées séparément.

## Article 178 — Hotfix

Un hotfix est une correction urgente et limitée destinée à réduire un risque immédiat.

Il ne doit pas servir à introduire une réforme non instruite.

## Article 179 — Branche de hotfix

Le hotfix part de l’état réellement déployé ou d’une base vérifiée et est réintégré dans les branches pertinentes après stabilisation.

## Article 180 — Contrôles minimaux d’urgence

L’urgence peut réduire certains délais, mais ne supprime pas :

- l’identité de l’auteur ;
- l’autorité ;
- la description du risque ;
- les tests essentiels ;
- le rollback ;
- la revue postérieure.

## Article 181 — Correctif direct en production

Une modification directe en production est réservée au risque grave et imminent lorsqu’aucun chemin plus sûr n’est disponible.

Elle doit être capturée, revue et réappliquée dans les sources canoniques.

## Article 182 — Incident lié au changement

Tout incident provoqué ou révélé par un changement est relié à la release, au déploiement, aux décisions et aux preuves correspondantes.

## Article 183 — Gel après incident

Un incident significatif peut déclencher un gel temporaire des changements jusqu’à stabilisation et évaluation.

## Article 184 — Revue post-incident

La revue post-incident recherche les faits, causes, contrôles manquants, décisions, impacts et corrections sans falsifier l’histoire ni réduire l’analyse à la faute individuelle.

## Article 185 — Actions correctives

Les actions correctives possèdent un responsable, une priorité, une échéance, une preuve de clôture et une décision lorsqu’elles modifient une norme.

## Article 186 — Retour d’expérience

Les enseignements généralisables sont intégrés aux règles, tests, modèles, formations ou architectures appropriés.

---

# TITRE XIII — DÉPENDANCES, CHAÎNE LOGICIELLE ET REPRODUCTIBILITÉ

## Article 187 — Registre des dépendances

Les dépendances critiques sont inventoriées avec leur version, source, licence, statut de maintenance, vulnérabilités, alternatives et responsable.

## Article 188 — Source vérifiée

Les dépendances et outils de construction sont obtenus depuis des sources identifiées et protégées contre la substitution non contrôlée.

## Article 189 — Verrouillage des versions

Les versions sont verrouillées ou contraintes de manière à permettre une construction reproductible et une mise à jour contrôlée.

## Article 190 — Mise à jour de dépendance

Une mise à jour est traitée comme un changement avec analyse de compatibilité, sécurité, licence, performance et rollback.

## Article 191 — Dépendance abandonnée

Une dépendance critique abandonnée déclenche une décision de remplacement, isolation, reprise ou acceptation temporaire du risque.

## Article 192 — Vulnérabilité de dépendance

Une vulnérabilité est évaluée selon son exploitabilité, son exposition, son impact et les compensations, sans se limiter à son score public.

## Article 193 — Paquets internes

Les bibliothèques internes possèdent des versions, contrats, responsables, tests et politiques de dépréciation.

## Article 194 — Build hermétique

Les constructions critiques limitent les accès réseau, dépendances implicites et variations non contrôlées lorsque cela est possible.

## Article 195 — Images et conteneurs

Les images exécutables sont minimales, versionnées, analysées, signées lorsque requis et reliées à leurs sources.

## Article 196 — Infrastructure comme code

Les ressources d’infrastructure sont déclarées, versionnées, revues et contrôlées autant que possible.

## Article 197 — Dérive d’infrastructure

Les différences entre infrastructure déclarée et réelle sont détectées et traitées.

## Article 198 — Licences

Les licences des dépendances et outils sont évaluées afin de protéger la capacité de distribuer, maintenir et transmettre le Core.

## Article 199 — Services externes

Un service externe critique possède une analyse de dépendance, portabilité, export, continuité, sécurité et sortie.

## Article 200 — Dépendance unique

Aucun fournisseur unique ne doit rendre impossible la restauration, l’exploitation ou la migration des fonctions essentielles sans risque explicitement accepté.

## Article 201 — Vérification de provenance

La provenance des dépendances, artefacts et outils critiques est vérifiée selon le risque et les capacités disponibles.

## Article 202 — Reproduction indépendante

Les versions critiques doivent pouvoir être reconstruites ou vérifiées depuis un environnement distinct selon une procédure documentée.

## Article 203 — Archivage de construction

Les sources, manifestes, dépendances, outils et preuves nécessaires à la reconstruction des versions importantes sont conservés.

---

# TITRE XIV — DOCUMENTATION, CONTRATS ET PREUVES ARCHITECTURALES

## Article 204 — Documentation comme partie du changement

La documentation nécessaire à l’usage, l’exploitation, la sécurité, la migration ou la transmission est mise à jour dans la même contribution ou par une action explicitement liée.

## Article 205 — Lexique canonique

Le code, les contrats et la documentation utilisent le lexique canonique applicable et signalent les termes provisoires.

## Article 206 — Contrats explicites

Les interactions entre domaines passent par des contrats explicites, versionnés et testables.

## Article 207 — Schémas et exemples

Les schémas, exemples et fixtures illustrant un contrat doivent correspondre à sa version réelle et être validés automatiquement lorsque possible.

## Article 208 — ADR, AMD et TD

Les choix structurants sont documentés dans le type de décision approprié avec contexte, options, décision, conséquences et statut.

## Article 209 — Décision remplacée

Une décision architecturale remplacée reste accessible et référence son successeur.

## Article 210 — Cartographie des dépendances

Les dépendances entre domaines, services, événements, données et produits sont documentées à un niveau permettant l’analyse d’impact.

## Article 211 — Documentation exécutable

Les exemples, contrats et procédures sont exécutables ou testés lorsque cela réduit le risque de divergence.

## Article 212 — Runbooks

Les opérations critiques disposent de procédures indiquant les préconditions, actions, vérifications, rollback, escalade et preuves.

## Article 213 — Dossiers de transmission

Les composants critiques conservent les informations nécessaires à leur reprise par un nouveau mainteneur ou opérateur.

## Article 214 — Documentation des limites

Les hypothèses, limites, dettes, risques et comportements non garantis sont déclarés.

## Article 215 — Preuve de conformité

Une release structurante possède une déclaration reliant les exigences, décisions, implémentations, tests, exceptions et risques résiduels.

## Article 216 — Auditabilité

Les documents et preuves doivent permettre à un auditeur compétent de vérifier le cycle sans exiger l’accès aux raisonnements privés d’un auteur ou d’une IA.

---

# TITRE XV — AGENTS ARTIFICIELS ET AUTOMATISATIONS D’INGÉNIERIE

## Article 217 — Statut de l’agent

Un agent artificiel est un acteur technique mandaté, non une autorité institutionnelle, constitutionnelle ou architecturale autonome.

## Article 218 — Ordre de lecture

Avant une contribution structurante, l’agent consulte les textes de Genesis II, décisions, contrats et directives applicables dans l’ordre prévu par GOVERNANCE-0001.

## Article 219 — Ordre de mission

La mission de l’agent précise l’objectif, les sources, le périmètre, les actions permises, les interdictions, les tests, les validations et les conditions d’arrêt.

## Article 220 — Branche dédiée

Un agent travaille dans une branche ou un espace isolé, sauf délégation explicite pour une opération différente.

## Article 221 — Accès minimal

L’agent reçoit uniquement les fichiers, données, secrets et permissions nécessaires à sa mission, pour une durée limitée.

## Article 222 — Pas de supposition normative

L’agent ne doit pas inventer une autorité, un contrat, un identifiant, une règle métier, un schéma, une permission ou une décision historique.

Il signale les lacunes et hypothèses.

## Article 223 — Rapport de contribution

Le rapport identifie :

- l’agent ou l’outil ;
- le parrain ;
- la mission ;
- les sources consultées ;
- les fichiers affectés ;
- les tests exécutés ;
- les limites ;
- les résultats ;
- la revue humaine.

## Article 224 — Revue renforcée

Les contributions IA affectant gouvernance, identité, autorisation, cryptographie, migrations, données sensibles, sécurité ou production exigent une revue humaine renforcée.

## Article 225 — Interdiction d’auto-approbation

Un agent ne peut être l’unique auteur, validateur, approbateur et déployeur d’un changement critique.

## Article 226 — Écriture dans `main`

L’écriture automatisée dans `main` est limitée à une procédure autorisée, par exemple la publication fidèle d’un texte adopté ou l’intégration d’un changement approuvé.

Elle ne vaut jamais adoption.

## Article 227 — Déploiement par agent

Un agent peut exécuter un déploiement seulement avec un artefact, un environnement, une autorisation, des contrôles et une procédure de rollback identifiables.

## Article 228 — Sous-agents et outils

L’usage de sous-agents ou services reste dans le périmètre de la mission et doit être traçable.

## Article 229 — Secrets et production

Les agents ne reçoivent pas par défaut les secrets maîtres, accès permanents ou permissions générales de production.

## Article 230 — Remplaçabilité

Les procédures, prompts, formats et preuves doivent permettre de remplacer un outil ou fournisseur d’IA sans perdre la capacité d’ingénierie.

---

# TITRE XVI — REGISTRES, AUDIT, RÉGIME TRANSITOIRE ET DISPOSITIONS FINALES

## Article 231 — Registre des contributions

Le Registre des contributions relie les dossiers, branches, auteurs, décisions, revues, commits, contrôles et intégrations.

## Article 232 — Registre des versions et releases

Il relie chaque version aux artefacts, dépendances, attestations, notes, décisions, environnements et statuts de support.

## Article 233 — Registre des déploiements

Il conserve les autorisations, versions, environnements, exécutants, résultats, incidents, rollbacks et observations.

## Article 234 — Registre des migrations

Il conserve les migrations, schémas, environnements, sauvegardes, validations, durées, résultats et procédures de récupération.

## Article 235 — Registre des exceptions d’ingénierie

Toute dérogation significative à une porte, revue, test ou procédure est reliée au Registre des exceptions de GOVERNANCE-0003.

## Article 236 — Registre des dépendances

Il permet d’identifier les composants critiques, leurs versions, origines, licences, risques, responsables et plans de remplacement.

## Article 237 — Références croisées

Les registres utilisent des identifiants communs afin de reconstruire le chemin :

> décision → contribution → commit → artefact → release → migration → déploiement → observation → clôture.

## Article 238 — Audit périodique

L’audit d’ingénierie examine notamment :

- protections des branches ;
- accès et mandats ;
- changements orphelins ;
- contrôles contournés ;
- versions non traçables ;
- dépendances critiques ;
- secrets exposés ;
- dérives d’environnement ;
- restaurations non testées ;
- agents et services actifs.

## Article 239 — Métriques de gouvernance

Les métriques servent à détecter les risques et améliorer le système.

Elles ne doivent pas encourager la falsification, la livraison précipitée ou la réduction de la qualité à un indicateur unique.

## Article 240 — Dette technique

La dette significative est enregistrée avec ses impacts, risques, propriétaire, priorité et stratégie de traitement.

Elle ne doit pas masquer une violation active d’un invariant ou d’une obligation de sécurité.

## Article 241 — Registre initial d’ingénierie

Après adoption du présent texte, Genesis II doit créer avant `G0` les registres initiaux nécessaires, notamment les dépôts, contributions, versions, déploiements, migrations, dépendances et exceptions d’ingénierie.

## Article 242 — Modèles initiaux

Doivent être préparés au minimum les modèles suivants :

- dossier de contribution ;
- matrice de risque et de revues ;
- rapport d’agent ;
- checklist d’intégration ;
- dossier de release ;
- ordre de déploiement ;
- plan de rollback ;
- rapport de migration ;
- revue post-déploiement ;
- hotfix ;
- revue post-incident.

## Article 243 — Protection de la phase pré-G0

L’adoption du présent texte n’ouvre pas à elle seule le codage canonique.

Les autres conditions de `G0` demeurent obligatoires et doivent faire l’objet d’un constat formel.

## Article 244 — Relation avec SECURITY-GOVERNANCE-0001

La future gouvernance de sécurité précisera les contrôles d’accès, secrets, cryptographie, vulnérabilités, incidents, continuité et privilèges élevés sans modifier silencieusement le présent texte.

## Article 245 — Relation avec AI-GOVERNANCE-0001

La future doctrine des agents artificiels précisera leur identité, classification, permissions, supervision, évaluation et révocation.

## Article 246 — Relation avec les Lois et la Charte du Core

Les règles d’ingénierie devront être réévaluées après adoption de la Charte, des Lois, du Lexique, du modèle conceptuel, des doctrines de données et de sécurité.

## Article 247 — Interprétation conservatrice

En cas de doute, le présent texte est interprété de manière à préserver :

- l’autorité des décisions ;
- la cohérence du Core ;
- la séparation des fonctions ;
- la sécurité des personnes et données ;
- la traçabilité ;
- la reproductibilité ;
- la réversibilité ;
- la continuité générationnelle.

## Article 248 — Amendement

Toute modification de sens relative aux dépôts, branches, revues, portes, versions, migrations, déploiements, rollbacks, preuves ou agents exige un amendement ou un texte de remplacement conformément à SOURCES-0001 et GOVERNANCE-0003.

## Article 249 — Principe directeur du contributeur

Avant de proposer ou intégrer un changement, tout contributeur humain ou artificiel doit pouvoir répondre :

- Quelle décision ou exigence ce changement exécute-t-il ?
- Quel est son niveau de risque ?
- Quelles frontières et quels invariants affecte-t-il ?
- Quelles revues sont requises ?
- Quels tests constituent la preuve ?
- Quel artefact exact sera publié ?
- Comment migrer et revenir en arrière ?
- Qui autorise la production ?
- Quelle trace restera pour la génération suivante ?

## Article 250 — Adoption et entrée en vigueur

Le présent texte ne possède une force normative qu’après adoption expresse par l’autorité compétente et inscription au Registre des adoptions.

Jusqu’à cette adoption, il demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

Une fois adopté, il devient la loi organique de référence pour la gouvernance d’ingénierie des dépôts, versions et mises en production de GAMAD Core — Genesis II.