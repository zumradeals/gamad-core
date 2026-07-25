# DATA-GOVERNANCE-0001 — GOUVERNANCE DES DONNÉES, FINALITÉS, RESPONSABILITÉS, CLASSIFICATION, CONSERVATION, PARTAGE ET DROITS

## Genesis II — Version 0.1

**Statut : PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

- **Périmètre :** données communes et spécialisées, finalités, responsabilités, domaines, classification, collecte, qualité, provenance, identité, droits, accès, partage, fédération, conservation, archivage, suppression, analytique, intelligence artificielle, incidents et preuves de GAMAD Core
- **Autorité de proposition :** Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD
- **Rédaction :** chantier GAMAD Core — Genesis II, avec assistance d’intelligence artificielle
- **Dépendances normatives :** ACTE-0001 ; SOURCES-0001 ; GOVERNANCE-0001 ; GOVERNANCE-0002 ; GOVERNANCE-0003 ; ENGINEERING-GOVERNANCE-0001 ; SECURITY-GOVERNANCE-0001
- **Principe directeur :** aucune donnée ne doit exister dans GAMAD Core sans finalité, responsabilité, classification, provenance, durée, droits, règles d’usage et capacité de sortie identifiables

---

## Préambule

GAMAD Core maintiendra des identités persistantes, des autorités, des organisations, des contrats, des permissions, des preuves et d’autres fondations communes dont dépendront les produits de l’écosystème.

Cette position ne donne pas au Core le droit d’absorber toutes les informations de GAMAD. Les applications et métiers demeurent responsables de leurs dossiers spécialisés. Le Core conserve seulement les données communes nécessaires à la cohérence, à la sécurité, à l’interopérabilité, à la continuité et à la preuve.

Une donnée n’est pas neutre. Elle peut représenter une personne, attribuer un statut, ouvrir ou fermer un droit, révéler une appartenance, influencer une décision, produire une réputation ou survivre longtemps après la finalité qui justifiait sa collecte.

La gouvernance des données doit donc permettre de répondre à tout moment :

- pourquoi cette donnée existe ;
- qui en répond institutionnellement et métier ;
- quelle source l’établit ;
- qui peut la voir, la modifier, la partager ou la supprimer ;
- quelle décision elle peut soutenir ;
- combien de temps elle demeure ;
- quels droits peuvent être exercés ;
- comment elle est corrigée, exportée, archivée ou détruite ;
- comment les copies, sauvegardes, dérivations et destinataires sont maîtrisés ;
- et comment une génération future peut reconstruire son histoire sans exposer inutilement les personnes.

> **Les applications créent les usages. Le Core maintient la cohérence. La donnée demeure sous une responsabilité identifiable et ne devient jamais la propriété silencieuse d’un outil, d’un compte, d’un fournisseur ou d’une intelligence artificielle.**

Le présent texte institue la doctrine minimale de données de Genesis II. Il n’impose pas encore un moteur de base de données, un format, un fournisseur ou une juridiction unique. Les exigences légales applicables complètent la présente gouvernance sans être inventées ni réduites par le code.

---

# TITRE I — OBJET, CHAMP ET DÉFINITIONS

## Article 1 — Objet

Le présent texte définit les principes, autorités, responsabilités, cycles, droits, contrôles et preuves applicables aux données de GAMAD Core et aux échanges entre le Core, les produits, les organisations, les realms et les tiers.

## Article 2 — Champ d’application

Il s’applique aux données structurées ou non structurées, métadonnées, documents, événements, journaux, fichiers, schémas, index, caches, sauvegardes, exports, modèles analytiques, résultats dérivés et données utilisées par des agents artificiels.

## Article 3 — Donnée

Une **donnée** est une représentation de fait, déclaration, observation, événement, relation, décision, mesure, contenu ou état pouvant être enregistrée, transmise, interprétée ou transformée.

## Article 4 — Information

Une **information** est le sens attribué à une ou plusieurs données dans un contexte déterminé.

La présence d’une donnée ne garantit pas que son interprétation est exacte, complète ou autorisée.

## Article 5 — Métadonnée

Une **métadonnée** décrit notamment l’origine, le type, la structure, la version, le responsable, la classification, la date, le statut, la qualité ou les usages d’une donnée.

Les métadonnées peuvent elles-mêmes être sensibles.

## Article 6 — Donnée personnelle

Une **donnée personnelle** est une donnée se rapportant directement ou indirectement à une personne physique identifiée ou identifiable selon le contexte et les moyens raisonnablement disponibles.

## Article 7 — Donnée sensible

Une **donnée sensible** est une donnée dont l’exposition, l’altération, la perte ou l’usage abusif peut causer un préjudice important à une personne, une communauté, une organisation, une capacité souveraine ou à la mission de GAMAD.

## Article 8 — Donnée critique

Une **donnée critique** est une donnée indispensable à l’identité, à l’autorité, aux droits, à la sécurité, à la continuité, à la preuve ou au fonctionnement essentiel du Core.

Une donnée peut être publique et néanmoins critique par son intégrité.

## Article 9 — Jeu de données

Un **jeu de données** est un ensemble cohérent de données géré pour une ou plusieurs finalités déclarées, avec une responsabilité, un schéma, une classification et un cycle de vie identifiables.

## Article 10 — Domaine de données

Un **domaine de données** est un périmètre conceptuel et organisationnel dans lequel une autorité métier répond du sens, des règles, de la qualité et du cycle de vie des données.

## Article 11 — Finalité

La **finalité** est l’objectif légitime, déterminé et déclaré pour lequel une donnée est créée, collectée, utilisée, partagée, conservée ou supprimée.

## Article 12 — Traitement

Un **traitement** est toute opération réalisée sur une donnée, notamment création, collecte, consultation, validation, combinaison, modification, transmission, analyse, archivage, anonymisation ou suppression.

## Article 13 — Provenance

La **provenance** désigne l’origine déclarée d’une donnée, la source, l’acteur, le contexte, la méthode, la date et les transformations ayant contribué à son état actuel.

## Article 14 — Lignée

La **lignée de données** relie une donnée ou un jeu de données à ses sources, transformations, dérivations, copies, versions et destinations.

## Article 15 — Personne concernée

La **personne concernée** est la personne physique à laquelle se rapporte une donnée personnelle.

Elle ne devient pas propriétaire technique de tous les systèmes qui traitent ses données, mais possède les droits et protections reconnus par les normes et lois applicables.

---

# TITRE II — PRINCIPES DIRECTEURS

## Article 16 — Primauté de la mission

Les données servent la mission de GAMAD, la mission du Core et les finalités légitimes des produits.

La collecte de données ne doit pas devenir une mission autonome de surveillance ou d’accumulation.

## Article 17 — Finalité avant donnée

Une donnée ne doit pas être collectée ou créée avant que sa finalité, son responsable et son cycle de vie soient raisonnablement définis.

## Article 18 — Minimisation

Seules les données nécessaires et proportionnées à la finalité déclarée sont traitées.

La commodité future indéterminée ne suffit pas à justifier une collecte excessive.

## Article 19 — Responsabilité explicite

Chaque donnée ou jeu de données significatif possède une responsabilité institutionnelle et métier identifiable.

Un serveur, un compte `admin`, un fournisseur ou une application ne constitue pas une responsabilité institutionnelle.

## Article 20 — Séparation des responsabilités

La définition du sens, l’administration technique, l’autorisation d’accès, l’usage, le contrôle de qualité, la sécurité et l’audit sont des fonctions distinctes.

## Article 21 — Refus par défaut

Toute collecte, consultation, modification, combinaison, exportation, transmission ou conservation non explicitement autorisée est réputée interdite.

## Article 22 — Moindre donnée et moindre accès

Un acteur reçoit uniquement les champs, lignes, périodes, usages et opérations nécessaires à sa mission.

## Article 23 — Exactitude contextualisée

Une donnée doit être suffisamment exacte, complète, actuelle et contextualisée pour la décision qu’elle soutient.

Une donnée ancienne ou incertaine ne doit pas être présentée comme une vérité actuelle.

## Article 24 — Provenance obligatoire

Une donnée structurante doit pouvoir être reliée à sa source, à son mode d’acquisition et à son niveau d’assurance.

## Article 25 — Canonicalité limitée

Une source canonique l’est pour un concept, un périmètre et une période déterminés.

La canonicalité d’une identité ne transforme pas le registre d’identité en source universelle de tous les faits concernant la personne.

## Article 26 — Frontières des domaines

Chaque domaine conserve ses règles et données spécialisées.

Le Core ne copie ni n’absorbe les données métier détaillées sans nécessité commune et décision compétente.

## Article 27 — Interopérabilité par contrat

Les échanges entre domaines utilisent des contrats explicites, versionnés, minimisés et auditables plutôt que des lectures directes non gouvernées des bases internes.

## Article 28 — Transparence proportionnée

Les personnes, autorités et produits concernés reçoivent une information intelligible sur les finalités, responsabilités, usages, destinataires, durées et droits, sous réserve des exigences légitimes de sécurité et confidentialité.

## Article 29 — Contestabilité

Une donnée, une interprétation ou une décision dérivée doit pouvoir être signalée, corrigée, contextualisée ou contestée selon une procédure adaptée à son rang et à son impact.

## Article 30 — Non-discrimination et dignité

Les données ne doivent pas être utilisées pour humilier, exclure, surveiller ou discriminer abusivement une personne ou une communauté.

Les règles légitimes d’autorité, d’appartenance, de sécurité ou de mission restent explicites, proportionnées et contestables selon leur nature.

## Article 31 — Protection des vulnérables

Les données concernant les mineurs, personnes vulnérables, victimes, personnes sous protection ou situations de dépendance reçoivent des garanties renforcées.

## Article 32 — Réversibilité et portabilité

Les données, schémas, contrats et historiques critiques doivent pouvoir être exportés, restaurés, migrés ou transmis sans dépendance irréversible à un fournisseur unique.

---

# TITRE III — AUTORITÉS ET RESPONSABILITÉS

## Article 33 — Autorité des données du Core

L’Autorité des données du Core définit les principes transversaux, classifications, contrats, règles de qualité, conservation, partage, droits et preuves applicables aux données communes.

## Article 34 — Limites de l’Autorité des données

Elle ne devient pas propriétaire de toutes les données de GAMAD et ne modifie pas seule les règles métier des produits.

## Article 35 — Responsable institutionnel de données

Le Responsable institutionnel répond de la légitimité, de la finalité, de la conformité et des effets d’un jeu de données dans le périmètre qui lui est confié.

## Article 36 — Responsable métier de données

Le Responsable métier définit le sens, les règles, les événements, les états, les critères de qualité et les usages légitimes du domaine.

## Article 37 — Data steward ou intendant de données

L’Intendant de données maintient le catalogue, les définitions, la qualité, les métadonnées, la lignée, les problèmes et les procédures de correction.

Il n’acquiert pas une autorité institutionnelle par cette fonction.

## Article 38 — Gardien technique

Le Gardien technique administre les systèmes de stockage, sauvegarde, accès, transformation ou échange conformément aux décisions et contrats applicables.

## Article 39 — Producteur de données

Le Producteur est l’acteur ou système qui crée, collecte ou transmet une donnée.

Il doit respecter le schéma, la provenance, la qualité et la finalité convenus.

## Article 40 — Consommateur de données

Le Consommateur utilise une donnée pour une finalité autorisée sans acquérir automatiquement le droit de la republier, la combiner ou la conserver indéfiniment.

## Article 41 — Destinataire

Le Destinataire est la personne, organisation, produit, realm ou tiers auquel une donnée est transmise ou rendue accessible.

## Article 42 — Sous-traitant ou opérateur externe

Un tiers qui traite des données pour le compte de GAMAD agit selon un contrat, des instructions, une finalité, une sécurité, une durée et une procédure de sortie identifiables.

## Article 43 — Autorité de sécurité

L’Autorité de sécurité définit et vérifie les contrôles de confidentialité, intégrité, disponibilité, traçabilité, résilience et réponse aux incidents.

## Article 44 — Autorité architecturale

L’Autorité architecturale protège les frontières de données, responsabilités de schéma, contrats, modèles et dépendances.

## Article 45 — Autorités métier des produits

Chaque produit gouverne ses dossiers spécialisés et répond de leurs finalités, qualité, droits, conservation et risques.

## Article 46 — Auditeur de données

L’Auditeur vérifie les responsabilités, finalités, accès, flux, qualités, conservations, suppressions, exceptions et preuves sans devenir opérateur permanent du domaine audité.

## Article 47 — Conflit d’intérêts

Un acteur ne doit pas être seul à définir une finalité, autoriser l’accès, exploiter les données et certifier la conformité du même traitement critique.

## Article 48 — Registre des responsabilités

Les autorités, responsables, intendants, gardiens, producteurs, consommateurs et tiers significatifs sont inscrits dans un registre avec leur mandat, périmètre, durée, suppléance et contacts.

---

# TITRE IV — DOMAINES, CATALOGUE, MODÈLES ET SOURCES CANONIQUES

## Article 49 — Cartographie des domaines

Genesis II maintient une cartographie des domaines de données, de leurs responsabilités, contrats, dépendances et frontières.

## Article 50 — Domaine propriétaire du sens

Le domaine qui définit un concept en possède la responsabilité sémantique, sans posséder personnellement les personnes ou organisations représentées.

## Article 51 — Responsabilité du schéma

Chaque schéma possède une autorité de modification, une version, une compatibilité, une documentation et une procédure de migration.

## Article 52 — Catalogue de données

Les jeux de données significatifs sont inscrits dans un catalogue indiquant au minimum : nom, domaine, finalité, responsable, classification, source, schéma, qualité, accès, conservation et statut.

## Article 53 — Identifiant du jeu de données

Chaque jeu de données gouverné possède une référence stable distincte du nom physique de la table, du bucket ou du service.

## Article 54 — Source canonique

Une source canonique est désignée par une décision ou un contrat précisant le concept, le périmètre, les limites, les consommateurs et la procédure de correction.

## Article 55 — Copies et répliques

Une copie ne devient pas canonique par ancienneté, disponibilité ou usage.

Elle indique sa source, sa date, sa méthode de synchronisation et son niveau de fraîcheur.

## Article 56 — Vues et projections

Une vue ou projection dérivée indique les transformations, filtres, omissions et finalités qui la distinguent de la source.

## Article 57 — Données de référence

Les vocabulaires, codes, statuts, pays, rôles, catégories et autres données de référence possèdent une autorité, une version et une politique d’évolution.

## Article 58 — Données maîtres

Les données maîtres communes sont maintenues dans un périmètre limité afin d’éviter les duplications concurrentes sans centraliser les détails métier inutiles.

## Article 59 — Modèle conceptuel avant modèle physique

Les concepts, relations et invariants sont définis indépendamment des tables, documents, index ou fournisseurs particuliers.

## Article 60 — Lexique canonique

Les noms de données utilisent le Lexique canonique de Genesis II et distinguent les synonymes, traductions, termes historiques et termes provisoires.

## Article 61 — Lignée enregistrée

Les transformations significatives indiquent les sources, versions, règles, acteurs, outils, dates, résultats et destinations.

## Article 62 — Relations inter-domaines

Une relation entre domaines possède une sémantique, une cardinalité, une autorité de création, une autorité de rupture et des effets de suppression identifiables.

## Article 63 — Données orphelines

Une donnée sans domaine, responsable, finalité ou source est signalée comme anomalie et ne doit pas devenir silencieusement canonique.

## Article 64 — Ombres de données

Les feuilles personnelles, bases parallèles, exports permanents, copies locales ou outils non enregistrés contenant des données institutionnelles sont identifiés, régularisés ou supprimés.

## Article 65 — Dictionnaire de données

Chaque domaine critique maintient des définitions compréhensibles, types, contraintes, unités, valeurs permises, exemples et règles de nullité.

## Article 66 — Évolution du modèle

Toute modification de sens, responsabilité, relation ou finalité exige une décision adaptée et ne doit pas être cachée dans une simple migration technique.

---

# TITRE V — FINALITÉS, FONDEMENTS ET COLLECTE

## Article 67 — Finalité déterminée

Toute collecte ou création structurante possède une finalité précise, intelligible et documentée avant son activation.

## Article 68 — Compatibilité des usages

Un nouvel usage d’une donnée existante est évalué selon sa compatibilité avec la finalité initiale, les attentes légitimes, la sensibilité, les droits et les risques.

## Article 69 — Changement de finalité

Un changement substantiel de finalité exige une nouvelle décision, une information appropriée et, lorsque nécessaire, un nouveau fondement ou consentement.

## Article 70 — Fondement légitime

Chaque traitement significatif identifie le fondement institutionnel, contractuel, opérationnel, sécuritaire, historique, probatoire ou légal qui le justifie.

## Article 71 — Lois applicables

Les exigences légales applicables sont identifiées par juridiction et périmètre.

Le présent texte ne fabrique pas une obligation légale inexistante et ne permet pas d’ignorer une obligation réellement applicable.

## Article 72 — Consentement

Lorsque le consentement constitue le fondement retenu, il doit être libre autant que le contexte le permet, spécifique, éclairé, attribuable, révocable et distinct des finalités non consenties.

## Article 73 — Consentement non forcé

Un accès essentiel ne doit pas être conditionné à une collecte non nécessaire, sauf justification légitime et explicitée.

## Article 74 — Retrait du consentement

Le retrait produit les effets prévus pour les traitements futurs sans falsifier les actes déjà valablement accomplis ou les preuves dont la conservation demeure légitime.

## Article 75 — Données obligatoires et facultatives

Les champs obligatoires, facultatifs, recommandés ou dérivés sont clairement distingués.

## Article 76 — Collecte directe

Lorsqu’une donnée est collectée auprès de la personne ou organisation concernée, l’interface explique sa finalité, son caractère nécessaire, ses destinataires, sa durée et les droits applicables.

## Article 77 — Collecte indirecte

Une donnée obtenue auprès d’un tiers indique sa source, son niveau d’assurance, les restrictions et la possibilité d’information ou de contestation.

## Article 78 — Observation automatique

Les journaux, mesures, traces d’usage, localisation, appareil, réseau et comportement ne sont collectés qu’avec une finalité, une minimisation et une durée adaptées.

## Article 79 — Données inférées

Une donnée déduite, prédite ou classifiée par un système est clairement distinguée d’un fait déclaré ou vérifié.

## Article 80 — Données déclaratives

Une déclaration d’une personne ou organisation conserve son auteur, sa date, son contexte et son statut de vérification.

## Article 81 — Données historiques

Une donnée historique est conservée avec son contexte temporel et ne doit pas être utilisée comme état actuel sans vérification.

## Article 82 — Données nécessaires au Core

Le Core ne collecte que les données communes nécessaires à l’identité persistante, l’autorité, l’organisation, l’accès, l’audit, les contrats, la sécurité, la continuité et les autres capacités souveraines adoptées.

## Article 83 — Données spécialisées des produits

Les profils détaillés, transactions, contenus, activités, préférences et dossiers métier restent dans les domaines produits sauf besoin commun explicitement adopté.

## Article 84 — Interdiction de collecte spéculative

Il est interdit de collecter massivement des données au seul motif qu’elles pourraient être utiles un jour à une analyse, une intelligence artificielle ou un partenariat non défini.

---

# TITRE VI — CLASSIFICATION ET PROTECTION

## Article 85 — Classification commune

Les classes de sécurité adoptées par SECURITY-GOVERNANCE-0001 s’appliquent aux données : `PUBLIQUE`, `INTERNE`, `CONFIDENTIELLE`, `SENSIBLE` et `CRITIQUE`.

## Article 86 — Dimensions de classification

La classification considère séparément la confidentialité, l’intégrité, la disponibilité, l’identifiabilité, la criticité, la durée et les effets sur les personnes.

## Article 87 — Donnée publique critique

Une Constitution publiée, une clé publique, un statut officiel ou un contrat public peut être `PUBLIQUE` pour la confidentialité et `CRITIQUE` pour l’intégrité.

## Article 88 — Données sensibles par nature

Reçoivent une protection renforcée, selon le contexte : données spirituelles ou religieuses, santé, biométrie, mineurs, sécurité, finances, localisation précise, communications privées, vulnérabilités, secrets, sanctions, conflits et relations familiales.

## Article 89 — Sensibilité par combinaison

Des données ordinaires peuvent devenir sensibles lorsqu’elles sont combinées, corrélées ou réidentifiées.

## Article 90 — Classification par défaut

Une donnée non classifiée n’est pas publique par défaut.

Elle reçoit une classe provisoire restrictive jusqu’à évaluation.

## Article 91 — Autorité de classification

Le Responsable institutionnel et le Responsable métier classifient les données avec l’Autorité de sécurité et l’Autorité des données selon le risque.

## Article 92 — Marquage

Les jeux de données, exports, documents, événements et interfaces indiquent leur classification lorsque cela est utile à l’application des contrôles.

## Article 93 — Héritage de classification

Une copie ou dérivation hérite au minimum de la classification de ses éléments les plus sensibles, sauf analyse démontrant une réduction réelle du risque.

## Article 94 — Déclassification

La réduction de classification exige une décision, une justification et une vérification que les risques, liens et copies ne rendent pas la mesure trompeuse.

## Article 95 — Chiffrement

Les données sensibles et critiques sont protégées en transit et au repos selon les menaces, contrats et capacités, conformément à SECURITY-GOVERNANCE-0001.

## Article 96 — Masquage

Les interfaces, journaux, exports et environnements affichent seulement les parties nécessaires des données sensibles.

## Article 97 — Pseudonymisation

La pseudonymisation remplace certains identifiants sans rendre nécessairement la réidentification impossible.

La clé ou table de correspondance reçoit une protection séparée.

## Article 98 — Anonymisation

Une donnée n’est déclarée anonymisée que si la réidentification est rendue raisonnablement impraticable dans le contexte prévu et selon les moyens disponibles.

## Article 99 — Données synthétiques

Les données synthétiques sont clairement identifiées et évaluées afin d’éviter qu’elles reproduisent des personnes réelles, secrets, biais ou informations sensibles.

## Article 100 — Environnements

Les données de production ne sont pas copiées vers le développement, le test ou la démonstration sans finalité, autorité, minimisation, protection et suppression planifiée.

## Article 101 — Exports

Tout export significatif possède un auteur, un destinataire, une finalité, une classification, une durée, un canal, une protection et une procédure de destruction.

## Article 102 — Impression et supports physiques

Les copies papier, périphériques, appareils et supports amovibles sont soumis aux mêmes responsabilités, classifications, durées et contrôles que les données numériques.

---

# TITRE VII — QUALITÉ, PROVENANCE ET CYCLE DE VIE

## Article 103 — Dimensions de qualité

La qualité peut inclure exactitude, complétude, actualité, cohérence, unicité, validité, disponibilité, traçabilité et adéquation à la finalité.

## Article 104 — Qualité proportionnée

Le niveau de qualité exigé dépend de l’usage.

Une donnée soutenant une autorisation, une identité ou une décision critique reçoit des contrôles supérieurs à une donnée informative sans effet direct.

## Article 105 — Règles de validation

Les formats, contraintes, références, plages, unités, statuts et relations sont validés à l’entrée et lors des transformations selon le risque.

## Article 106 — Source d’autorité

Lorsqu’un fait peut provenir de plusieurs sources, les règles de priorité, arbitrage et contradiction sont documentées conformément à SOURCES-0001.

## Article 107 — Donnée non vérifiée

Une donnée non vérifiée reste utilisable uniquement dans les finalités qui acceptent explicitement ce niveau d’assurance.

## Article 108 — Incertitude

L’incertitude, l’estimation, la marge d’erreur, la date et les hypothèses d’une donnée dérivée sont conservées lorsqu’elles influencent son interprétation.

## Article 109 — Correction

Une correction modifie l’état applicable sans effacer la trace nécessaire pour comprendre l’erreur, son auteur, sa date, ses impacts et sa résolution.

## Article 110 — Rectification distribuée

Lorsqu’une donnée corrigée a été partagée, les destinataires pertinents reçoivent une notification ou une nouvelle version selon les contrats et risques.

## Article 111 — Fusion de doublons

La fusion de deux enregistrements exige des critères, preuves, contrôles et une capacité de contestation proportionnés à l’impact.

## Article 112 — Séparation après fusion

Les fusions critiques doivent prévoir, lorsque raisonnable, une procédure de séparation ou de réparation en cas d’erreur.

## Article 113 — Détection de doublons

Un nom, un numéro de téléphone, un visage ou une similarité statistique ne suffit pas seul à fusionner des identités persistantes.

## Article 114 — Valeurs absentes

Les notions `inconnu`, `non fourni`, `non applicable`, `refusé`, `supprimé` et `non encore vérifié` sont distinguées lorsqu’elles ont des effets différents.

## Article 115 — Temporalité

Les données susceptibles d’évoluer indiquent leur période de validité, date d’effet, date de fin ou date de dernière vérification.

## Article 116 — Événements immuables

Un événement historique correctement enregistré n’est pas modifié pour représenter un nouvel état ; une correction ou un événement compensatoire conserve l’histoire.

## Article 117 — États actuels

Un état actuel peut être recalculé à partir d’événements ou maintenu séparément, mais sa source et sa méthode restent vérifiables.

## Article 118 — Lignée des transformations

Les calculs, agrégations, rapprochements, enrichissements et modèles indiquent les entrées, versions, paramètres, outils et résultats.

## Article 119 — Contrôles de qualité

Les domaines critiques maintiennent des indicateurs, seuils, alertes et dossiers de correction sans réduire la qualité à un score unique.

## Article 120 — Problèmes de qualité

Un problème significatif possède une référence, un impact, un responsable, une priorité, une décision de traitement et une preuve de clôture.

## Article 121 — Qualité à la source

La correction est effectuée au plus près de la source responsable plutôt que par des ajustements silencieux dans chaque consommateur.

## Article 122 — Certification limitée

Une validation de qualité précise le jeu de données, la version, les dimensions contrôlées, la date, les limites et l’environnement.

---

# TITRE VIII — IDENTITÉ, PERSONNES, COMPTES ET ORGANISATIONS

## Article 123 — Identité distincte de la donnée personnelle détaillée

L’identité persistante permet de reconnaître une réalité dans le temps. Elle ne constitue pas un dossier complet sur la personne ou l’organisation.

## Article 124 — Identity Registry

L’Identity Registry est une capacité souveraine du Core qui attribue et conserve des identifiants internes persistants, types d’entités, statuts, alias, liens, fusions, remplacements, révocations et preuves minimales de provenance.

## Article 125 — Minimisation du registre d’identité

L’Identity Registry ne doit pas contenir par défaut les mots de passe, conversations, transactions, préférences, activités spirituelles détaillées, dossiers médicaux, historiques métier ou profils complets.

## Article 126 — Identifiant interne

L’identifiant interne canonique n’est pas choisi pour être mémorisé, affiché publiquement ou porteur de sens métier.

Il demeure stable autant que possible et ne doit pas être recyclé.

## Article 127 — Alias et identifiants externes

Les numéros publics, noms d’usage, adresses, identifiants GAMAD ID et identifiants de partenaires sont des alias ou références liées, non la clé primaire universelle de l’identité.

## Article 128 — GAMAD ID

GAMAD ID est un produit ou service permettant de présenter, vérifier ou utiliser certaines identités et informations autorisées.

Il consomme les contrats du Core sans posséder l’Identity Registry.

## Article 129 — Compte utilisateur

Un compte est un moyen d’accès à un système. Sa fermeture, suspension ou remplacement ne supprime pas automatiquement l’identité persistante ni les obligations historiques légitimes.

## Article 130 — Personne et adhésion

L’identité d’une personne, son adhésion à GAMAD, son rôle, son autorité, son compte et ses permissions sont des concepts distincts.

## Article 131 — Organisations

Une organisation possède une identité, des statuts, représentants, relations, mandats et cycles de vie distincts des comptes des personnes qui l’administrent.

## Article 132 — Représentation

Le lien entre une personne et une organisation indique la fonction, le mandat, la durée, la source et les pouvoirs réels de représentation.

## Article 133 — Décès ou disparition

Le décès ou la disparition d’une personne ne provoque pas l’effacement automatique de son existence historique.

Les accès sont révoqués, les données sont réévaluées et la dignité, les droits des tiers, la mémoire légitime et les obligations applicables sont conciliés.

## Article 134 — Mineurs

Les identités et données de mineurs utilisent des contrôles renforcés, une représentation appropriée, une minimisation et une réévaluation lors du passage à l’âge ou statut applicable.

## Article 135 — Données spirituelles et religieuses

Les appartenances, pratiques, fonctions spirituelles, enseignements reçus ou appréciations doctrinales sont sensibles et ne doivent pas être inférés, exposés ou utilisés hors finalité et autorité légitimes.

## Article 136 — Autorité et réputation

Une donnée technique ne doit pas attribuer à elle seule une autorité spirituelle, doctrinale, institutionnelle ou morale.

## Article 137 — Biometrie

Toute biométrie exige une nécessité démontrée, une analyse de risques, une alternative raisonnable lorsque possible, une durée et des contrôles renforcés.

## Article 138 — Profilage identitaire

Il est interdit de construire silencieusement un profil universel agrégeant toutes les activités d’une personne dans l’écosystème.

## Article 139 — Identités contestées

Une contestation d’identité, de doublon, de fusion ou de représentation est enregistrée, sécurisée et instruite par une autorité différente de l’unique auteur de l’état contesté lorsque possible.

## Article 140 — Révocation et remplacement

Une identité ne se supprime pas comme un compte ordinaire. Les cas d’erreur, fraude, fusion ou remplacement utilisent des statuts et liens explicites préservant la preuve nécessaire.

## Article 141 — Frontière produit

Les produits peuvent maintenir leurs profils, préférences et dossiers spécialisés, liés à l’identité canonique par contrat et sans répliquer les données communes au-delà du nécessaire.

---

# TITRE IX — TRANSPARENCE, DROITS ET DEMANDES

## Article 142 — Socle de droits

Toute personne concernée bénéficie, selon les normes et lois applicables, d’un socle de transparence, accès, correction, contestation, limitation, suppression ou portabilité adapté au traitement et à ses exceptions légitimes.

## Article 143 — Information intelligible

L’information relative aux données utilise un langage accessible et distingue le responsable, la finalité, les catégories, les destinataires, la durée, les fondements, les risques et les moyens d’exercice.

## Article 144 — Accès

Une personne peut demander confirmation des données la concernant et une copie intelligible, sous réserve de l’identité du demandeur, des droits des tiers, de la sécurité et des exceptions applicables.

## Article 145 — Rectification

Une personne peut demander la correction d’une donnée inexacte ou incomplète et fournir des preuves ou observations utiles.

## Article 146 — Annotation de désaccord

Lorsqu’une donnée ne peut être immédiatement corrigée ou lorsqu’un fait demeure contesté, le désaccord peut être enregistré avec le contexte et les autorités compétentes.

## Article 147 — Limitation

L’usage d’une donnée peut être temporairement limité pendant une vérification, une contestation, un recours ou une obligation de conservation.

## Article 148 — Suppression

Une personne peut demander la suppression lorsque la finalité a disparu, le traitement n’est plus légitime ou un droit applicable le permet.

La décision tient compte des preuves, obligations, droits des tiers, archives légitimes et contraintes de sauvegarde.

## Article 149 — Opposition

Une personne peut contester certains usages ou finalités et recevoir une décision motivée selon les règles applicables.

## Article 150 — Portabilité

Lorsque prévue et techniquement raisonnable, la portabilité fournit les données dans un format structuré, documenté et réutilisable sans révéler les secrets ni droits d’autres personnes.

## Article 151 — Décision automatisée

Une décision produisant des effets importants ne doit pas reposer exclusivement sur une automatisation opaque lorsque les normes ou lois applicables exigent une intervention humaine, une explication ou un recours.

## Article 152 — Explication proportionnée

Une personne affectée reçoit une explication utile sur les données, règles, facteurs, limites et autorité ayant contribué à une décision, sans exiger la divulgation de secrets de sécurité non nécessaires.

## Article 153 — Vérification de l’identité du demandeur

Les demandes de droits utilisent une vérification proportionnée afin d’éviter qu’un tiers obtienne ou modifie les données d’une autre personne.

## Article 154 — Mandataire

Une demande peut être exercée par un représentant autorisé dont le mandat, l’identité et le périmètre sont vérifiés.

## Article 155 — Délais

Les délais de traitement sont définis selon le droit applicable, le risque et la complexité. Tout retard significatif est expliqué et suivi.

## Article 156 — Refus motivé

Un refus total ou partiel identifie la demande, les motifs, les sources, les limites et les voies de contestation disponibles.

## Article 157 — Gratuité raisonnable

L’exercice normal d’un droit ne doit pas devenir inaccessible par un coût ou une procédure disproportionnés.

Les demandes manifestement abusives ou répétitives peuvent être encadrées selon une décision motivée.

## Article 158 — Droits des tiers

La réponse protège les données, secrets, communications et droits d’autres personnes ou organisations.

## Article 159 — Données dérivées

Les scores, classifications, rapprochements et inférences concernant une personne sont inclus dans le périmètre des droits lorsqu’ils produisent des effets et que les normes applicables le prévoient.

## Article 160 — Journaux et preuves

L’accès aux journaux est concilié avec la sécurité, les secrets, les tiers et l’intégrité des preuves. Une synthèse ou extraction adaptée peut être fournie.

## Article 161 — Registre des demandes

Chaque demande possède une référence, un demandeur vérifié, un périmètre, des dates, décisions, actions, communications, exceptions et preuve de clôture.

## Article 162 — Recours

Une personne peut contester le traitement de sa demande selon GOVERNANCE-0003 et les voies externes applicables.

---

# TITRE X — ACCÈS, PARTAGE, FÉDÉRATION ET TIERS

## Article 163 — Accès fondé sur la finalité

L’autorisation de consulter ou modifier une donnée précise la finalité, le domaine, les champs, les opérations, la durée et les conditions.

## Article 164 — Accès par rôle et attribut

Les rôles, attributs, mandats, relations et contextes peuvent contribuer à l’autorisation sans remplacer la décision de gouvernance qui les définit.

## Article 165 — Séparation lecture-écriture

Le droit de lire, créer, corriger, supprimer, exporter, déléguer ou administrer est attribué séparément selon le risque.

## Article 166 — Accès privilégié

Les accès massifs, transversaux ou capables de contourner les règles ordinaires sont enregistrés, limités, surveillés et revus conformément à SECURITY-GOVERNANCE-0001.

## Article 167 — Accès d’urgence

Un accès de secours est temporaire, justifié, fortement journalisé et soumis à une revue postérieure.

## Article 168 — Partage interne

Le partage entre équipes ou produits n’est pas automatiquement autorisé par leur appartenance à GAMAD.

Il exige une finalité, un contrat, une minimisation et des responsabilités.

## Article 169 — Contrat de partage

Tout partage structurant indique : source, destinataire, finalité, champs, fréquence, fondement, classification, sécurité, qualité, conservation, droits, incidents, audit et fin de relation.

## Article 170 — API et événements

Les API et événements exposent seulement les données nécessaires, avec des versions, schémas, permissions, finalités et politiques de compatibilité.

## Article 171 — Accès direct aux bases

La lecture ou écriture directe dans la base interne d’un autre domaine est interdite hors procédure exceptionnelle, temporaire et auditée.

## Article 172 — Données agrégées

Une agrégation n’est pas automatiquement anonyme. Le risque de petits groupes, recoupements et réidentification est évalué.

## Article 173 — Publication ouverte

Une donnée publiée ouvertement possède une autorité, une licence ou règle d’usage, une version, une intégrité, une fréquence et une procédure de correction.

## Article 174 — Fédération

Les échanges entre realms ou organisations reposent sur une confiance limitée et révocable, des identités reconnues, des contrats, des responsabilités, des journaux et une procédure de rupture.

## Article 175 — Souveraineté des realms

Un realm ne reçoit pas silencieusement l’ensemble des données ou pouvoirs d’un autre realm du seul fait de la fédération.

## Article 176 — Transfert international ou inter-juridictionnel

Un transfert entre pays, juridictions ou régimes identifie les exigences applicables, les protections, les risques, les sous-traitants et les conditions de sortie.

## Article 177 — Tiers critique

Un tiers recevant ou traitant des données sensibles ou critiques fait l’objet d’une évaluation, d’un contrat, de contrôles, d’un suivi, d’une notification d’incident et d’un plan de sortie.

## Article 178 — Sous-traitance ultérieure

Un tiers ne sous-traite pas silencieusement le traitement à un nouvel acteur lorsque le contrat exige information, autorisation ou protections équivalentes.

## Article 179 — Réutilisation par le tiers

Le tiers ne réutilise pas les données pour sa publicité, son entraînement de modèle, son profilage ou ses propres finalités sans autorité explicite.

## Article 180 — Fin de relation

À la fin du contrat ou de la finalité, les accès sont révoqués, les données restituées ou supprimées, les copies traitées et les preuves conservées.

## Article 181 — Propagation des corrections

Les contrats définissent comment les corrections, suppressions, restrictions et changements de classification sont propagés aux destinataires.

## Article 182 — Registre des flux

Les flux significatifs relient source, destination, champs, finalité, contrat, fréquence, classification, chiffrement, responsable et statut.

## Article 183 — Interdiction de vente silencieuse

Aucune donnée de GAMAD ne peut être vendue, cédée ou monétisée sans décision institutionnelle explicite, analyse des droits, finalités, risques, lois applicables et information appropriée.

---

# TITRE XI — CONSERVATION, ARCHIVAGE, SUPPRESSION ET DESTRUCTION

## Article 184 — Durée déterminée

Chaque catégorie de données possède une durée ou un critère de conservation fondé sur la finalité, les obligations, la preuve, les droits et les risques.

## Article 185 — Calendrier de conservation

Un calendrier relie les catégories de données, finalités, responsables, durées, événements déclencheurs, archives, suppressions et exceptions.

## Article 186 — Interdiction de conservation indéfinie par défaut

L’absence de règle ne justifie pas la conservation permanente.

Une durée provisoire restrictive est appliquée jusqu’à décision.

## Article 187 — Conservation active et archive

Les données nécessaires aux opérations courantes sont distinguées des archives historiques, probatoires ou institutionnelles soumises à des accès et usages différents.

## Article 188 — Archives fondatrices

Les sources fondatrices, actes adoptés, preuves d’autorité et archives institutionnelles peuvent recevoir une conservation durable ou permanente selon SOURCES-0001, avec minimisation des données personnelles non nécessaires.

## Article 189 — Gel probatoire

Une donnée soumise à incident, litige, audit, enquête ou obligation de preuve peut être placée sous conservation temporaire empêchant sa suppression ordinaire.

## Article 190 — Réexamen du gel

Le gel probatoire possède une autorité, un motif, un périmètre, une date et un réexamen afin d’éviter une conservation indéfinie injustifiée.

## Article 191 — Suppression logique

La suppression logique rend une donnée indisponible aux usages ordinaires mais ne constitue pas toujours une destruction physique ou cryptographique.

## Article 192 — Suppression physique

La destruction d’une donnée tient compte des copies, index, caches, réplications, exports, appareils et supports.

## Article 193 — Effacement cryptographique

Lorsque pertinent, la destruction vérifiable des clés peut rendre les données irrécupérables, sous réserve des copies et mécanismes de récupération existants.

## Article 194 — Sauvegardes

Les données supprimées peuvent demeurer temporairement dans des sauvegardes protégées jusqu’à leur rotation normale.

Elles ne doivent pas être restaurées dans les usages ordinaires sans réappliquer les suppressions et restrictions.

## Article 195 — Tombstone et preuve minimale

Une preuve minimale de suppression, fusion, révocation ou remplacement peut être conservée afin d’éviter la recréation incohérente et préserver l’intégrité historique.

## Article 196 — Suppression des journaux

Les journaux suivent une rétention proportionnée à l’audit, la sécurité, les droits et les obligations, sans contenir davantage de données personnelles que nécessaire.

## Article 197 — Anonymisation pour conservation

Une conservation statistique ou historique peut utiliser une anonymisation réellement évaluée lorsque l’identité n’est plus nécessaire.

## Article 198 — Données agrégées après suppression

La suppression d’une donnée source n’exige pas nécessairement la destruction d’une statistique réellement anonyme, mais les risques de réidentification sont réévalués.

## Article 199 — Destruction des exports

Les exports temporaires possèdent une date de fin et une preuve de destruction ou de restitution.

## Article 200 — Fin de produit

La fermeture d’un produit comprend une décision sur l’export, la migration, les droits, la conservation, la suppression, les contrats et la continuité des identités communes.

## Article 201 — Fin d’organisation ou realm

La dissolution ou sortie d’une organisation prévoit la responsabilité des données, les transferts légitimes, les archives, les révocations et la preuve.

## Article 202 — Rapport de suppression

Toute suppression structurante indique l’autorité, le périmètre, les méthodes, les systèmes, les copies, les exceptions, la date et les vérifications.

## Article 203 — Test du cycle de suppression

Les domaines critiques testent périodiquement que les règles de rétention et suppression fonctionnent réellement dans les bases, fichiers, index, caches et sauvegardes.

---

# TITRE XII — ANALYTIQUE, PROFILAGE, INTELLIGENCE ARTIFICIELLE ET DONNÉES DÉRIVÉES

## Article 204 — Finalité analytique

Toute analyse, tableau de bord, segmentation, prédiction ou modèle possède une finalité, un responsable, un périmètre, des sources et une durée.

## Article 205 — Séparation opération-analytique

Les copies analytiques ne deviennent pas une seconde source canonique et respectent les corrections, classifications, droits et durées.

## Article 206 — Profilage

Le profilage combinant des comportements, identités ou attributs est évalué selon son impact, sa transparence, sa nécessité, ses biais et ses droits.

## Article 207 — Inférences sensibles

Il est interdit d’inférer silencieusement des croyances, appartenances, santé, vulnérabilités, opinions, relations privées ou autres attributs sensibles sans finalité et autorité renforcées.

## Article 208 — Décisions à fort impact

Un score ou modèle influençant un droit, une autorité, un accès, une sanction, une réputation ou une opportunité reçoit une validation, une explicabilité, une surveillance et un recours proportionnés.

## Article 209 — Données d’entraînement

L’utilisation de données pour entraîner, ajuster ou évaluer un modèle exige une finalité, un fondement, une classification, une minimisation, une provenance, une durée et une analyse des droits.

## Article 210 — Données sensibles et fournisseurs d’IA

Les données sensibles ou critiques ne sont pas transmises à un fournisseur d’IA sans contrat, nécessité, sécurité, politique de rétention, restrictions d’entraînement, localisation et sortie évaluées.

## Article 211 — Prompts et conversations

Les prompts, réponses, fichiers joints et traces d’agent sont des données gouvernées selon leur contenu, leur classification, leur finalité et leur durée.

## Article 212 — Sorties de modèle

Une sortie de modèle est une donnée dérivée dont la source, le modèle, la version, le contexte, les limites et la revue doivent être connus lorsqu’elle soutient une action structurante.

## Article 213 — Absence de vérité autonome

Une sortie probabiliste ne devient pas un fait canonique, une identité, une autorité, une preuve historique ou une règle métier sans validation compétente.

## Article 214 — Hallucinations et erreurs

Les systèmes utilisant l’IA prévoient la détection, le signalement, la correction et la non-propagation des contenus inventés ou non vérifiés.

## Article 215 — Biais et représentativité

Les données et modèles sont évalués pour les biais, exclusions, erreurs différentielles et effets sur les groupes concernés selon le contexte réel.

## Article 216 — Minimisation pour agents

Un agent reçoit uniquement les fragments, outils et données nécessaires à sa mission plutôt qu’un accès général à l’ensemble du Core.

## Article 217 — Mémoire d’agent

Toute mémoire persistante d’un agent possède une finalité, un responsable, une classification, une durée, des droits et une procédure d’effacement.

## Article 218 — Sous-agents

La transmission de données à un sous-agent ou outil reste dans le périmètre du mandat et est enregistrée lorsqu’elle est significative.

## Article 219 — Évaluation humaine

Les analyses sensibles produites par IA reçoivent une revue humaine compétente avant décision, publication ou inscription canonique.

## Article 220 — Registre des usages IA

Les usages significatifs relient le modèle, le fournisseur, la finalité, les données, les classifications, les responsables, les évaluations, les incidents et la date de réexamen.

## Article 221 — Sortie et remplacement

Les données, prompts, procédures, évaluations et preuves nécessaires doivent permettre de remplacer le fournisseur ou modèle sans perdre la gouvernance ni la continuité.

---

# TITRE XIII — INGÉNIERIE, CONTRATS, MIGRATIONS ET PREUVES

## Article 222 — Data by design

Chaque capacité structurante identifie ses données, finalités, responsables, classifications, droits, durées, flux, menaces et preuves dès la conception.

## Article 223 — Revue de données

Un changement affectant collecte, finalité, schéma, responsabilité, partage, droits, rétention ou suppression reçoit une revue de données proportionnée.

## Article 224 — Analyse d’impact

Les traitements `R3` ou `R4`, les données sensibles à grande échelle, les identités, la biométrie, le profilage ou les décisions à fort impact peuvent exiger une analyse d’impact dédiée.

## Article 225 — Contrats de données

Les producteurs et consommateurs conviennent des schémas, sens, versions, qualité, fraîcheur, erreurs, classifications, permissions et procédures de changement.

## Article 226 — Compatibilité

Une évolution incompatible exige une version, une migration, une période de coexistence ou une coordination explicite des consommateurs.

## Article 227 — Migrations

Toute migration de données est versionnée, testée, sauvegardée, journalisée et reliée à une décision conformément à ENGINEERING-GOVERNANCE-0001.

## Article 228 — Transformation irréversible

Une transformation irréversible exige une autorité renforcée, une simulation, des sauvegardes vérifiées, une méthode de contrôle et une réparation ou compensation.

## Article 229 — Reconciliation

Après migration ou synchronisation, des contrôles comparent volumes, clés, relations, totaux, erreurs, doublons et invariants.

## Article 230 — Tests

Les tests utilisent des données synthétiques, anonymisées ou autorisées et vérifient les finalités, permissions, schémas, qualité, rétention et suppression applicables.

## Article 231 — Fixtures

Les exemples et fixtures ne contiennent pas de personnes réelles ou secrets non nécessaires et restent alignés sur les contrats actuels.

## Article 232 — Observabilité

Les métriques et journaux de données détectent les échecs, retards, dérives, ruptures de contrat, accès anormaux et problèmes de qualité sans reproduire excessivement les contenus sensibles.

## Article 233 — Documentation

Les schémas, contrats, catalogues, lignées, règles de qualité, calendriers de conservation et procédures de droits sont versionnés avec les changements concernés.

## Article 234 — Preuve de conformité

Une release structurante relie les exigences de données aux décisions, schémas, migrations, contrôles, tests, exceptions, risques et validations.

## Article 235 — Secrets de raisonnement

L’audit exige les sources, règles, paramètres, décisions et preuves utiles, non les raisonnements privés internes d’une personne ou d’une intelligence artificielle.

## Article 236 — Données dans le code et la configuration

Les données de référence intégrées au code ou à la configuration possèdent une source, une version et une autorité de modification.

## Article 237 — Suppression testable

Les architectures critiques rendent la suppression, la restriction, l’export et la rectification testables plutôt que dépendantes d’interventions manuelles imprévisibles.

---

# TITRE XIV — INCIDENTS DE DONNÉES, CONTINUITÉ ET RESTAURATION

## Article 238 — Incident de données

Une perte, exposition, corruption, altération, indisponibilité, partage non autorisé, suppression abusive, erreur de fusion ou usage incompatible constitue un incident ou problème de données selon l’impact.

## Article 239 — Déclaration

Tout acteur peut signaler un incident de données sans attendre une certitude complète lorsque des mesures conservatoires sont nécessaires.

## Article 240 — Coordination

Les incidents de données sont traités avec SECURITY-GOVERNANCE-0001 et relient les autorités de sécurité, données, domaine, exploitation, communication et droits.

## Article 241 — Confinement

Les mesures peuvent inclure suspension d’accès, arrêt de flux, révocation de jetons, gel de suppression, isolation d’un jeu, correction temporaire ou notification aux consommateurs.

## Article 242 — Intégrité

Une corruption ou perte d’intégrité exige l’identification de la dernière source fiable, des transformations, des consommateurs et des décisions affectées.

## Article 243 — Notification

Les personnes, autorités, produits, partenaires ou organismes applicables sont informés selon les risques, contrats et lois, sans retarder les mesures de protection.

## Article 244 — Restauration

La restauration utilise des sauvegardes, journaux, événements, exports ou sources vérifiés et réapplique les corrections, suppressions, restrictions et changements intervenus depuis le point restauré.

## Article 245 — Validation après restauration

Les contrôles vérifient les identités, relations, droits, classifications, volumes, invariants, suppressions et contrats avant retour normal.

## Article 246 — Continuité des responsables

Les domaines critiques disposent de suppléants, dossiers de transmission, accès récupérables et procédures permettant de poursuivre la gouvernance malgré l’absence d’un titulaire.

## Article 247 — Indépendance des sauvegardes

Les sauvegardes critiques ne dépendent pas exclusivement des mêmes comptes, clés, régions, fournisseurs ou erreurs que les données actives.

## Article 248 — Exercices

Des exercices testent la perte d’une base, d’un fournisseur, d’une région, d’une clé, d’un responsable, d’un flux ou d’un registre canonique.

## Article 249 — Revue post-incident

La revue établit les faits, finalités, accès, flux, erreurs, décisions, impacts, droits, restaurations et corrections sans falsifier l’histoire.

## Article 250 — Actions correctives

Les actions de données possèdent un responsable, une priorité, une échéance, des preuves et une décision lorsqu’elles modifient une norme, un contrat ou une finalité.

## Article 251 — Registre des incidents de données

Le registre relie l’actif, la personne ou organisation affectée, les faits, classifications, accès, destinataires, décisions, communications, restaurations et clôture.

---

# TITRE XV — AUDIT, RISQUES, EXCEPTIONS, REGISTRES ET PORTE G0

## Article 252 — Audit périodique

L’audit de données vérifie notamment les domaines, responsables, finalités, catalogues, accès, flux, qualités, durées, suppressions, droits, tiers, usages IA et objets orphelins.

## Article 253 — Risques de données

Les risques significatifs sont inscrits avec l’actif, la menace, l’impact, les personnes affectées, les contrôles, le responsable, la durée et l’autorité d’acceptation.

## Article 254 — Exception

Toute dérogation à une règle de finalité, accès, partage, qualité, conservation, suppression ou droit possède une autorité, une justification, une durée, des compensations et un réexamen.

## Article 255 — Dette de données

Une dette de qualité, modèle, lignée, documentation, rétention ou suppression possède un responsable et ne doit pas masquer une violation active des droits ou de la sécurité.

## Article 256 — Métriques

Les métriques servent à détecter les risques et améliorer la gouvernance sans encourager la collecte excessive, la surveillance ou la falsification d’indicateurs.

## Article 257 — Registre initial des données

Après adoption du présent texte, doivent être créés avant `G0` au minimum :

- Registre des domaines et responsabilités de données ;
- Catalogue des jeux de données ;
- Registre des finalités et traitements ;
- Registre des classifications ;
- Registre des sources, provenances et lignées ;
- Registre des flux, partages et tiers ;
- Calendrier de conservation, archivage et suppression ;
- Registre des demandes et droits ;
- Registre des problèmes de qualité et corrections ;
- Registre des analyses d’impact ;
- Registre des usages analytiques et IA ;
- Registre des exceptions et risques de données ;
- Registre des incidents de données.

## Article 258 — Modèles initiaux

Doivent être préparés au minimum : fiche de jeu de données, déclaration de finalité, matrice de responsabilité, classification, contrat de données, accord de partage, analyse d’impact, demande de droit, correction ou fusion, calendrier de conservation, rapport de suppression, revue de qualité, incident et usage IA.

## Article 259 — Références croisées

Les registres doivent permettre de reconstruire les chaînes :

> finalité → jeu de données → responsable → source → traitement → accès → destinataire → durée → suppression ;

> identité → donnée personnelle → usage → décision → droit → correction ou recours ;

> source → transformation → donnée dérivée → modèle → décision → preuve ;

> incident → donnée affectée → destinataires → restauration → action corrective.

## Article 260 — Intégrité et confidentialité

Les catalogues et registres de données sont eux-mêmes classifiés, sauvegardés, exportables, restaurables et protégés selon leur contenu.

## Article 261 — Condition de données de G0

L’adoption du présent texte constitue la doctrine minimale de données exigée par GOVERNANCE-0001, sous réserve de la création des registres, modèles et contrôles initiaux prévus et du constat formel de `G0`.

## Article 262 — Absence d’ouverture automatique

L’adoption du présent texte n’ouvre pas à elle seule le codage canonique. La Charte du Core, la Constitution des produits, le Lexique, les premières Lois et les autres conditions de `G0` demeurent nécessaires.

## Article 263 — Relation avec la sécurité

SECURITY-GOVERNANCE-0001 gouverne les contrôles de protection, accès, incidents, sauvegardes et continuité ; le présent texte gouverne le sens, la finalité, la responsabilité, le cycle et les droits des données.

Les deux textes s’appliquent conjointement.

## Article 264 — Relation avec l’Identity Registry

Le futur texte d’identité précisera les invariants, statuts, contrats et cycles de l’Identity Registry sans modifier silencieusement les principes de minimisation, responsabilité, droits et frontière produit du présent texte.

## Article 265 — Relation avec les produits

La Constitution des produits précisera les obligations d’héritage, autonomie métier, conformité, échanges et sortie des produits.

## Article 266 — Relation avec l’intelligence artificielle

AI-GOVERNANCE-0001 précisera les classes d’agents, missions, permissions, évaluations et responsabilités sans réduire les exigences de finalité, minimisation, classification, droits et sortie établies ici.

## Article 267 — Interprétation conservatrice

En cas de doute, le présent texte est interprété de manière à préserver la mission, la dignité, la minimisation, la responsabilité, la provenance, la qualité, la sécurité, les droits, la réversibilité et l’absence de capture.

## Article 268 — Amendement

Toute modification de sens relative aux finalités, responsabilités, classifications, droits, partages, conservations, suppressions, données d’identité ou usages IA exige un amendement ou un texte de remplacement conformément à SOURCES-0001 et GOVERNANCE-0003.

## Article 269 — Principe directeur du bâtisseur

Avant de créer ou utiliser une donnée, tout bâtisseur humain ou artificiel doit pouvoir répondre :

- Pourquoi cette donnée existe-t-elle ?
- Qui en répond ?
- Quelle est sa source et son assurance ?
- Qui peut l’utiliser et pour quelle finalité ?
- Quelle décision peut-elle influencer ?
- Quelle est sa classification ?
- Combien de temps demeure-t-elle ?
- Quels droits et recours s’appliquent ?
- Comment la corriger, l’exporter, la supprimer ou la restaurer ?
- Quelle trace restera sans exposer inutilement la personne ?

## Article 270 — Adoption et entrée en vigueur

Le présent texte ne possède une force normative qu’après adoption expresse par l’autorité compétente et inscription au Registre des adoptions.

Jusqu’à cette adoption, il demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**

Une fois adopté, il devient la loi organique de référence pour la gouvernance des données, finalités, responsabilités, classifications, conservations, partages et droits de GAMAD Core — Genesis II.
