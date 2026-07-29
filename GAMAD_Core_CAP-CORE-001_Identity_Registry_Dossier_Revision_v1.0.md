# GAMAD CORE — CAP-CORE-001  
# DOSSIER D’INSTRUCTION POUR LA RÉVISION DE L’IDENTITY REGISTRY

**Version du document :** 1.0  
**Nature :** dossier de travail destiné à la rédaction d’une loi révisée  
**Statut :** document d’instruction — non normatif — non adopté  
**Dépôt de référence :** `zumradeals/gamad-core`  
**État du dépôt examiné :** `main` au commit `111a78361372c9d0a026197d66388b9d5d49b8f5`  
**Capacité concernée :** `CAP-CORE-001 — Identity Registry`  
**Contrat actuel :** `CTR-01`  
**Nom canonique du produit :** `GamaDrive`  
**Principe historique :** `GAMAD ID` est dissous ; l’identité a été rendue au Core.

---

## 0. INSTRUCTION PRINCIPALE À CLAUDE

Le présent dossier doit servir à préparer une **révision doctrinale, normative et technique de `CAP-CORE-001 — Identity Registry`**.

La révision doit corriger l’interprétation actuelle selon laquelle l’Identity Registry ne devrait reconnaître que quelques entités institutionnelles déjà présentes dans le corpus et ne devrait pas couvrir les utilisateurs des produits ou les acteurs des organisations.

### Correction exigée

L’Identity Registry doit reconnaître comme entités du Core :

- les personnes utilisant un ou plusieurs produits GAMAD ;
- les organisations reconnues dans l’écosystème ;
- les personnes liées à ces organisations ;
- les agents artificiels ;
- les produits ;
- les services ;
- les realms ;
- les autres entités canoniquement admises par la loi.

Un utilisateur de Wasplex, de GamaDrive, de G-Search, de G-Business, de G-Mail, de G-Docs, de Zumra ou d’un futur satellite **ne doit pas rester une identité souveraine locale enfermée dans ce produit**.

Il doit pouvoir être relié à une **référence canonique stable du Core**, même si :

- son profil détaillé demeure dans le produit ;
- ses contenus demeurent dans le produit ;
- son activité métier demeure dans le produit ;
- son niveau d’assurance est encore faible ;
- son identité est provisoire, pseudonyme ou partiellement vérifiée selon le contexte autorisé.

### Finalité supérieure

La révision doit permettre à GAMAD de disposer d’une identité commune et durable pour :

- le portail GAMAD ;
- les satellites ;
- la sécurité ;
- les mandats ;
- les relations organisationnelles ;
- la continuité ;
- l’autorisation ;
- l’audit ;
- la qualification ;
- et, ultérieurement, le **Moteur de Matching GAMAD**.

---

# 1. RÉSUMÉ EXÉCUTIF

`CAP-CORE-001` a été conçue comme le successeur institutionnel de `GAMAD ID`, dissous au profit du Core. Son premier incrément sait actuellement :

- résoudre une identité par référence ;
- reconstruire son état à une date ;
- inventorier les entités connues ;
- signaler les divergences de dénomination ;
- conserver l’histoire d’une identité dissoute ;
- exposer ces opérations par `CTR-01` en lecture seule ;
- vérifier ce comportement par une garde `P3`.

Cette première conception était cohérente avec un corpus initial contenant très peu d’entités. Elle devient toutefois trop étroite pour le futur portail GAMAD et pour les produits de l’écosystème.

La doctrine actuelle risque d’être interprétée ainsi :

> « L’Identity Registry ne doit pas connaître les utilisateurs des produits, parce que leurs données appartiennent aux produits. »

Cette interprétation doit être corrigée.

La règle correcte est :

> **Les produits possèdent leurs données métier. Le Core possède la référence canonique et la continuité de l’identité.**

Par conséquent :

- Wasplex conserve les offres, campagnes, audiences métier et interactions annonceurs ;
- GamaDrive conserve les fichiers, dossiers, partages, quotas et préférences ;
- G-Business conserve les opérations et données métier des entreprises ;
- G-Mail conserve les boîtes, messages et règles métier ;
- G-Docs conserve les documents et collaborations ;
- Zumra conserve ses relations et contenus spécialisés ;
- mais toutes ces plateformes doivent pouvoir référencer une même personne ou organisation par une identité commune du Core.

L’Identity Registry ne doit pas devenir un profil universel.  
Mais il ne doit pas non plus devenir un registre vide, limité aux seules autorités, produits et agents déjà cités par les textes.

---

# 2. ÉTAT CANONIQUE ACTUEL

## 2.1 Filiation

La conception actuelle constate que :

- `PRD-GAMAD-001 — GAMAD ID` est dissous ;
- la fonction d’identité a été rendue au Core ;
- `CAP-CORE-001` recueille cette fonction.

Cette filiation doit être conservée.

## 2.2 Invariants actuels à préserver

La révision doit conserver le noyau des invariants existants :

### Identifiant stable

Une référence canonique :

- est indépendante du nom ;
- est indépendante de l’état ;
- est indépendante du produit utilisé ;
- n’est jamais réattribuée après clôture ou dissolution.

### Type déclaré

Chaque entité possède un type déclaré.  
Aucun type n’est présumé sans source ou procédure autorisée.

### Existence distincte de l’assurance

Une identité peut exister avec un faible niveau d’assurance.  
L’absence de vérification forte ne doit pas provoquer l’effacement de l’identité.

### Cycle de vie en ajout seul

Les corrections, fusions, scissions, clôtures et dissolutions s’ajoutent à l’histoire.  
Elles ne réécrivent pas silencieusement le passé.

### Minimalité

Le registre ne devient pas un dossier universel contenant l’ensemble des données produites par tous les satellites.

## 2.3 Code actuel

Le module actuel est situé dans :

```text
core/registre-identites/
```

Le contrat `CTR-01` fournit notamment :

```text
resoudreIdentite(reference, date?)
resoudreInventaire(type?)
resoudreDenominations(reference?)
```

La livraison HTTP existe dans :

```text
apps/console-laravel/app/Http/Controllers/Ctr01Controller.php
apps/console-laravel/routes/web.php
```

Les routes actuelles sont en lecture seule :

```text
GET /identites/{reference}
GET /identites
GET /denominations
```

La garde actuelle est :

```text
core/registre-identites/tests/identite_p3.php
```

## 2.4 État institutionnel actuel

`ADOPTION-0063` a prononcé, à titre exceptionnel, l’admission des vingt capacités et leur passage à l’état `ACTIVE`.

Pour `CAP-CORE-001` :

```text
Conception      : CONÇUE
Implémentation  : ADMISE
Exploitation    : ACTIVE
Preuve          : P3 — TESTÉ
Famille         : CTR-01
Commit admis    : 65093691e0efbb35cf8ff92aee9c59dcfb3b7704
```

Cette admission est toutefois exceptionnelle.

Elle ne signifie pas que :

- le service est réellement déployé en production ;
- un opérateur permanent est nommé ;
- une surveillance opérationnelle est établie ;
- la restauration a été testée ;
- l’audit est indépendant ;
- `G0` est constaté.

Toute modification du module admis peut rendre l’admission actuelle **caduque** au sens du mécanisme d’admission. La future révision devra donc préparer un nouveau dossier et une nouvelle décision d’admission pour la version révisée.

---

# 3. PROBLÈME DOCTRINAL À CORRIGER

## 3.1 Confusion actuelle

La conception initiale part du constat que seuls quelques acteurs, agents et produits sont déclarés dans le corpus.

Ce constat historique a été transformé, ou risque d’être transformé, en doctrine restrictive :

> « Le registre des identités ne doit contenir que les entités déclarées par des actes institutionnels. »

Cette règle est insuffisante pour un écosystème réel comptant potentiellement :

- des milliers d’utilisateurs ;
- des entreprises ;
- des membres d’organisations ;
- des représentants ;
- des partenaires ;
- des bénéficiaires institutionnels ;
- des annonceurs ;
- des clients ;
- des agents techniques ;
- des services ;
- des identités pseudonymes ou provisoires.

Un acte d’adoption individuel pour chaque utilisateur ne peut pas constituer le mécanisme normal d’inscription.

## 3.2 Erreur à éviter

La révision ne doit pas opposer :

```text
Identité souveraine du Core
contre
Utilisateur d’un produit
```

Un utilisateur est une personne ou une autre entité qui entretient une relation d’usage avec un produit.

Le mot `utilisateur` désigne donc principalement :

- une relation ;
- un rôle ;
- un contexte d’usage ;
- un état dans un produit.

Il ne remplace pas l’identité canonique.

## 3.3 Conséquence d’une identité uniquement locale

Si chaque produit crée sa propre identité souveraine :

- une même personne peut devenir plusieurs personnes pour GAMAD ;
- les doublons se multiplient ;
- les mandats ne sont pas portables ;
- les relations organisationnelles divergent ;
- les sanctions ou révocations de sécurité ne se propagent pas correctement ;
- les droits d’accès deviennent incohérents ;
- le Matching travaille sur des profils fragmentés ;
- la continuité entre les satellites disparaît ;
- la sortie d’un produit peut emporter l’identité de ses utilisateurs ;
- un produit populaire peut capturer une fondation souveraine du Core.

Cette fragmentation contredit la mission même de `CAP-CORE-001`.

---

# 4. DOCTRINE RÉVISÉE PROPOSÉE

## 4.1 Principe fondamental

> **Toute personne, organisation, agent, produit, service ou realm reconnu comme acteur durable de l’écosystème GAMAD doit pouvoir recevoir une référence canonique stable de l’Identity Registry.**

## 4.2 Principe des utilisateurs

> **Tout utilisateur durable d’un produit GAMAD doit être relié à une identité canonique du Core ou, lorsque la finalité l’autorise, à une identité provisoire ou pseudonyme gouvernée et transformable.**

## 4.3 Principe des organisations

> **Toute organisation reconnue dans l’écosystème possède une identité canonique dans `CAP-CORE-001`, tandis que ses données institutionnelles détaillées relèvent de `CAP-CORE-002 — Registre des organisations`.**

Ainsi :

- `CAP-CORE-001` répond : « quelle entité est-ce ? » ;
- `CAP-CORE-002` répond : « quelle organisation est-ce, comment est-elle structurée et selon quelles sources ? » ;
- `CAP-CORE-003` répond : « qui la représente et sous quel mandat ? ».

## 4.4 Principe de séparation du profil

> **Une identité canonique n’est pas un profil universel.**

L’Identity Registry peut connaître :

- qu’une personne existe ;
- sa référence ;
- son type ;
- son état ;
- sa source d’inscription ;
- ses identifiants ou alias gouvernés ;
- son niveau d’assurance ;
- ses liens minimaux avec des produits ou organisations ;
- son cycle de vie ;
- les références de décisions, mandats ou restrictions applicables.

Il ne doit pas absorber automatiquement :

- les fichiers GamaDrive ;
- les messages G-Mail ;
- les documents G-Docs ;
- les offres et campagnes Wasplex ;
- les recherches G-Search ;
- les activités commerciales détaillées de G-Business ;
- les contenus sociaux ou communautaires de Zumra ;
- les préférences détaillées de chaque produit ;
- les dossiers métier complets ;
- les jugements moraux ou spirituels ;
- les données intimes sans finalité légitime.

## 4.5 Principe de relation d’usage

Le fait qu’une identité utilise un produit est une relation gouvernée :

```text
IDENTITÉ  ── utilise / administre / représente / possède un compte dans ──> PRODUIT
```

Cette relation peut être :

- active ;
- suspendue ;
- close ;
- provisoire ;
- pseudonyme ;
- vérifiée ;
- non vérifiée ;
- limitée à un realm ;
- limitée à une organisation ;
- limitée à une finalité.

## 4.6 Principe d’inscription à l’échelle

La doctrine « toute création d’identité exige un acte signé individuel » doit être révisée.

La règle proposée est :

> **Toute création d’identité doit dériver d’une autorité d’inscription et d’un événement de preuve vérifiable ; elle ne nécessite pas nécessairement un acte normatif individuel.**

Une loi ou politique adoptée peut autoriser des mécanismes limités d’inscription :

- auto-inscription par une personne ;
- inscription par un produit reconnu ;
- inscription par une organisation reconnue ;
- inscription par une institution ;
- import gouverné ;
- création d’un agent ou d’un service ;
- transformation d’une identité provisoire en identité vérifiée.

Chaque inscription doit laisser :

- une source ;
- une date ;
- un canal ;
- un responsable ou producteur ;
- un niveau d’assurance ;
- une preuve ou attestation ;
- une politique d’inscription ;
- un historique.

---

# 5. CATÉGORIES D’IDENTITÉS PROPOSÉES

Les noms et références ci-dessous sont conceptuels. Claude doit vérifier le lexique et éviter toute collision canonique avant de numéroter les nouveaux termes.

## 5.1 Personne

Une personne humaine pouvant être :

- utilisateur d’un produit ;
- membre d’une organisation ;
- représentant ;
- autorité ;
- opérateur ;
- client ;
- annonceur ;
- bénéficiaire ;
- partenaire ;
- autre acteur reconnu.

## 5.2 Organisation

Une entité collective reconnue :

- entreprise ;
- association ;
- institution ;
- administration ;
- confrérie ;
- structure partenaire ;
- entité interne GAMAD.

L’identité canonique est portée par `CAP-CORE-001`.  
Le dossier organisationnel détaillé est porté par `CAP-CORE-002`.

## 5.3 Agent artificiel

Un agent d’intelligence artificielle ou automatisé :

- possède sa propre référence ;
- ne se confond pas avec son fournisseur ;
- possède une mission ;
- possède un parrain ou une autorité ;
- possède des permissions limitées.

## 5.4 Produit

Un produit reconnu de l’écosystème :

- GamaDrive ;
- Wasplex ;
- les futurs G-Search, G-Business, G-Mail, G-Docs, Zumra ;
- tout autre produit adopté.

## 5.5 Service

Une capacité technique ou institutionnelle identifiable consommée selon un contrat.

## 5.6 Realm

Un espace gouverné pouvant accueillir plusieurs produits, organisations ou populations.

## 5.7 Identité provisoire

Une identité créée avec une assurance faible, dans l’attente d’une vérification ou d’un rapprochement.

Elle :

- possède une référence ;
- n’est pas automatiquement digne d’une action sensible ;
- peut être fusionnée selon une procédure gouvernée ;
- ne doit pas être confondue avec une identité fortement vérifiée.

## 5.8 Identité pseudonyme

Une identité permettant un usage autorisé sans divulgation générale de l’identité civile.

Elle doit préciser :

- le contexte ;
- le producteur ;
- la durée ;
- le niveau d’assurance ;
- les règles de levée ou de non-levée ;
- les finalités compatibles.

---

# 6. DISTINCTION ENTRE IDENTITÉ, COMPTE, AUTHENTIFICATION ET PROFIL

## 6.1 Identité

Réponse à la question :

> Qui ou quelle entité existe pour le Core ?

Gardien principal :

```text
CAP-CORE-001
```

## 6.2 Compte de produit

Réponse à la question :

> Quelle présence opérationnelle cette identité possède-t-elle dans ce produit ?

Gardien principal :

```text
le produit concerné
```

Un compte peut contenir :

- identifiant local ;
- préférences ;
- statut d’abonnement ;
- paramètres ;
- configuration ;
- historique métier ;
- métadonnées du produit.

## 6.3 Authentification

Réponse à la question :

> Comment cette entité prouve-t-elle qu’elle contrôle cette identité ?

Gardien principal :

```text
CAP-CORE-005
```

## 6.4 Autorisation

Réponse à la question :

> Que peut faire cette identité dans ce contexte ?

Gardien principal :

```text
CAP-CORE-004
```

## 6.5 Mandat

Réponse à la question :

> Au nom de qui et dans quelles limites cette identité peut-elle agir ?

Gardien principal :

```text
CAP-CORE-003
```

## 6.6 Profil métier

Réponse à la question :

> Quelles informations spécialisées ce produit conserve-t-il pour accomplir sa mission ?

Gardien principal :

```text
le produit ou la capacité métier compétente
```

---

# 7. MODÈLE DE DONNÉES RÉVISÉ PROPOSÉ

Le modèle exact doit être conçu après vérification des invariants, contrats et registres actuels.

## 7.1 Entité canonique

```sql
CREATE TABLE identite (
    reference_canonique      text PRIMARY KEY,
    type_identite            text NOT NULL,
    etat                     text NOT NULL,
    niveau_assurance         text NOT NULL,
    source_inscription       text NOT NULL,
    politique_inscription    text NOT NULL,
    date_creation            datetime NOT NULL,
    date_effet               datetime NOT NULL,
    classification           text NOT NULL,
    version                  integer NOT NULL
);
```

## 7.2 Dénominations et alias

```sql
CREATE TABLE denomination_identite (
    id                       ...,
    identite_reference       text NOT NULL,
    libelle                  text NOT NULL,
    type_denomination        text NOT NULL,
    source                   text NOT NULL,
    date_debut               datetime NOT NULL,
    date_fin                 datetime NULL
);
```

## 7.3 Relation entre identité et produit

```sql
CREATE TABLE relation_identite_produit (
    id                       ...,
    identite_reference       text NOT NULL,
    produit_reference        text NOT NULL,
    relation_type            text NOT NULL,
    etat                     text NOT NULL,
    sujet_local_opaque       text NULL,
    niveau_assurance         text NOT NULL,
    source                   text NOT NULL,
    date_debut               datetime NOT NULL,
    date_fin                 datetime NULL,
    classification           text NOT NULL
);
```

Exemples de `relation_type` :

- `UTILISATEUR` ;
- `CLIENT` ;
- `ANNONCEUR` ;
- `ADMINISTRATEUR` ;
- `OPERATEUR` ;
- `PROPRIETAIRE_INSTITUTIONNEL` ;
- `RESPONSABLE_PRODUIT` ;
- `PERSONNE_AFFECTEE` ;
- `PARTENAIRE`.

## 7.4 Relation entre identité et organisation

```sql
CREATE TABLE relation_identite_organisation (
    id                       ...,
    identite_reference       text NOT NULL,
    organisation_reference   text NOT NULL,
    relation_type            text NOT NULL,
    etat                     text NOT NULL,
    mandat_reference         text NULL,
    source                   text NOT NULL,
    date_debut               datetime NOT NULL,
    date_fin                 datetime NULL,
    classification           text NOT NULL
);
```

Exemples de `relation_type` :

- `MEMBRE` ;
- `EMPLOYE` ;
- `REPRESENTANT` ;
- `DIRIGEANT` ;
- `BENEFICIAIRE` ;
- `CLIENT` ;
- `FOURNISSEUR` ;
- `PARTENAIRE` ;
- `CONTACT_AUTORISE`.

Les relations d’autorité ou de représentation juridiquement sensibles doivent être validées par `CAP-CORE-003`, et non déduites d’un simple lien.

## 7.5 Cycle de vie

```sql
CREATE TABLE evenement_cycle_identite (
    id                       ...,
    identite_reference       text NOT NULL,
    evenement_type           text NOT NULL,
    etat_avant               text NULL,
    etat_apres               text NOT NULL,
    source                   text NOT NULL,
    date_effet               datetime NOT NULL,
    acteur_reference         text NOT NULL
);
```

Événements possibles :

- création ;
- vérification ;
- suspension ;
- réactivation ;
- fusion ;
- scission ;
- clôture ;
- dissolution ;
- correction ;
- conversion d’une identité provisoire ;
- rattachement ou retrait d’un produit ;
- rattachement ou retrait d’une organisation.

---

# 8. INVARIANTS RÉVISÉS PROPOSÉS

Claude doit relever le dernier numéro canonique disponible avant d’attribuer de nouvelles références. Les numéros ci-dessous sont donc provisoires.

## INV-ID-A — Universalité de la référence

Toute entité durable de l’écosystème possède ou peut recevoir une référence canonique du Core.

## INV-ID-B — L’utilisateur n’est pas une identité locale souveraine

Un produit peut posséder un compte local.  
Il ne peut pas créer une identité souveraine concurrente au Core.

## INV-ID-C — Compte distinct de l’identité

La suppression d’un compte produit ne supprime pas automatiquement l’identité canonique.

## INV-ID-D — Organisation de premier rang

Toute organisation reconnue reçoit une identité canonique, même si son dossier détaillé relève de `CAP-CORE-002`.

## INV-ID-E — Relation explicite

Toute relation significative entre une identité, un produit ou une organisation possède :

- un type ;
- un état ;
- une source ;
- une durée ;
- une classification.

## INV-ID-F — Inscription gouvernée

Une identité est créée seulement par un canal autorisé et traçable, selon une politique adoptée.

## INV-ID-G — Assurance distincte

L’assurance ne se déduit ni du nombre de produits utilisés, ni de la popularité, ni du volume d’activité.

## INV-ID-H — Minimalité par domaine

Le Core conserve les données minimales communes.  
Le produit conserve les données détaillées nécessaires à son métier.

## INV-ID-I — Pas de profil universel implicite

Le rapprochement de données issues de plusieurs satellites nécessite une finalité autorisée, un contrat et une politique de données.

## INV-ID-J — Continuité inter-produits

Une même identité peut traverser plusieurs produits sans être recréée ni réattribuée.

## INV-ID-K — Pseudonymie gouvernée

L’usage pseudonyme ou provisoire peut être autorisé sans être confondu avec une identité fortement vérifiée.

## INV-ID-L — Matching fondé sur des identités valides

Le futur Moteur de Matching ne peut présenter comme entités qualifiées des comptes locaux non reliés ou des identités dont l’état ne permet pas l’usage demandé.

---

# 9. MENACES À AJOUTER OU RÉVISER

## M-ID-A — Fragmentation des utilisateurs

Chaque produit possède ses propres identités sans référence commune.

## M-ID-B — Capture par un produit

Un produit devient propriétaire de facto des identités et empêche leur portabilité.

## M-ID-C — Doublons transversaux

Une même personne possède plusieurs références non rapprochées.

## M-ID-D — Fusion abusive

Deux personnes distinctes sont fusionnées sur la base d’un signal insuffisant.

## M-ID-E — Profil universel

Le Core absorbe les données métier détaillées de tous les satellites.

## M-ID-F — Relation présumée

Une appartenance, une fonction ou une représentation est déduite sans source.

## M-ID-G — Assurance exagérée

Une personne est considérée comme fortement vérifiée parce qu’elle utilise plusieurs produits.

## M-ID-H — Identité orpheline

La suppression d’un produit ou d’un compte rend l’identité inaccessible.

## M-ID-I — Matching sur données fragmentées

Le Matching produit des correspondances incohérentes parce que les entités ne sont pas canoniquement reconnues.

## M-ID-J — Exposition transversale

Un satellite accède à toutes les relations et données d’une identité sans besoin autorisé.

---

# 10. CONTRAT `CTR-01` RÉVISÉ PROPOSÉ

Le contrat actuel doit être étendu, sans perdre les opérations existantes.

## 10.1 Opérations de lecture à conserver

```text
resoudre_identite(reference, date?)
resoudre_inventaire(type?)
resoudre_denominations(reference?)
```

## 10.2 Opérations nouvelles proposées

```text
resoudre_liens_produits(identity_reference, product_reference?, relation_type?)
```

Retour minimal :

```json
{
  "identity_reference": "IDN-PER-000001",
  "relations": [
    {
      "product_reference": "PRD-GAMAD-002",
      "relation_type": "UTILISATEUR",
      "state": "ACTIVE",
      "assurance": "A2"
    }
  ]
}
```

```text
resoudre_liens_organisations(identity_reference, organization_reference?, relation_type?)
```

```text
resoudre_identite_depuis_sujet_produit(product_reference, local_subject_opaque)
```

Cette opération doit être réservée au produit concerné ou à une autorité habilitée.

```text
proposer_inscription(dossier)
```

```text
proposer_rapprochement(reference_a, reference_b, preuves[])
```

```text
resoudre_assurance(identity_reference, context?)
```

```text
resoudre_etat_utilisable(identity_reference, purpose)
```

## 10.3 Écriture gouvernée

La révision peut introduire des commandes d’écriture, mais jamais un CRUD libre.

Chaque commande doit :

- vérifier l’autorité du producteur ;
- identifier la politique d’inscription ;
- produire un événement ;
- journaliser la source ;
- refuser les types ou relations non autorisés ;
- permettre le contrôle et le rollback institutionnel ;
- laisser une preuve.

## 10.4 Versionnement

La future version doit probablement devenir :

```text
CTR-01 v2
```

ou une nouvelle version canonique de la famille `CTR-01`, selon la doctrine du Registre des contrats.

Claude doit consulter `CAP-CORE-009` avant de choisir le mécanisme exact.

---

# 11. FLUX D’INSCRIPTION PROPOSÉS

## 11.1 Inscription d’un utilisateur de produit

```text
Utilisateur
   ↓
Produit reconnu
   ↓
CAP-CORE-005 vérifie le contrôle ou crée une assurance initiale
   ↓
Produit soumet un dossier selon une politique autorisée
   ↓
Identity Registry cherche un doublon
   ↓
Création, rattachement ou demande de vérification
   ↓
Référence canonique retournée au produit
```

Le produit conserve ensuite une référence opaque vers l’identité.

## 11.2 Inscription d’une organisation

```text
Organisation ou représentant
   ↓
CAP-CORE-002 reçoit le dossier organisationnel
   ↓
CAP-CORE-001 attribue ou retrouve la référence canonique
   ↓
CAP-CORE-003 vérifie les représentants et mandats
   ↓
Les produits consomment la référence commune
```

## 11.3 Utilisateur déjà connu

Lorsqu’une personne rejoint un nouveau produit :

```text
Le nouveau produit ne crée pas une seconde identité.
Il rattache un nouveau compte ou une nouvelle relation à la référence existante.
```

## 11.4 Identité incertaine

Si le rapprochement n’est pas suffisamment fiable :

```text
IDENTITÉ PROVISOIRE
ou
RAPPROCHEMENT À VALIDER
```

Aucune fusion automatique irréversible ne doit être exécutée sur une simple probabilité.

---

# 12. RELATION AVEC LE MATCHING

Le Matching est brièvement mentionné ici parce que sa capacité dépend directement de la qualité de `CAP-CORE-001`.

## 12.1 Principe

> **Le Moteur de Matching GAMAD transforme la connaissance autorisée de l’écosystème en correspondances utiles entre les personnes, les organisations, les besoins, les offres et les institutions.**

## 12.2 Dépendance à l’identité

Le Matching doit pouvoir recevoir :

- une personne canonique ;
- une organisation canonique ;
- un produit ;
- une institution ;
- une relation autorisée ;
- un état ;
- un niveau d’assurance ;
- des attributs métier provenant de leurs domaines gardiens.

Il ne doit pas construire ses correspondances sur :

- des comptes locaux impossibles à rapprocher ;
- des doublons non résolus ;
- des organisations non reconnues ;
- des relations présumées ;
- des identités suspendues ou incompatibles avec la finalité.

## 12.3 Frontière

L’Identity Registry fournit :

- qui existe ;
- sous quelle référence ;
- avec quel état ;
- avec quel niveau d’assurance ;
- avec quels liens minimaux autorisés.

Le Matching fournit :

- qui correspond à quoi ;
- dans quel contexte ;
- selon quelle politique ;
- avec quelle pertinence ;
- avec quelle confiance ;
- avec quelles restrictions.

Les données détaillées restent dans Wasplex, G-Business, les institutions ou les autres domaines compétents.

---

# 13. IMPACTS SUR LES PRODUITS

## 13.1 Wasplex

Wasplex doit pouvoir rattacher :

- annonceurs ;
- entreprises ;
- représentants ;
- bénéficiaires ;
- audiences qualifiées ;
- partenaires ;

à des références canoniques.

Wasplex conserve :

- offres ;
- campagnes ;
- budgets ;
- créations ;
- statistiques ;
- interactions ;
- règles commerciales.

## 13.2 GamaDrive

GamaDrive doit pouvoir rattacher :

- propriétaires de fichiers ;
- collaborateurs ;
- organisations ;
- administrateurs ;
- comptes techniques ;

à l’Identity Registry.

GamaDrive conserve :

- fichiers ;
- dossiers ;
- partages ;
- versions ;
- quotas ;
- préférences ;
- métadonnées de stockage.

## 13.3 G-Business

G-Business peut consommer les identités communes des personnes et organisations tout en conservant ses données commerciales détaillées.

## 13.4 G-Mail, G-Docs, G-Search et Zumra

Chaque produit consomme une référence commune et conserve son propre domaine métier.

---

# 14. CONFIDENTIALITÉ ET CLASSIFICATION

L’Identity Registry appartient au monde invisible du Core.

Cela ne signifie pas que toutes ses données sont accessibles à tous les opérateurs du Core.

Les données doivent être classifiées, par exemple :

- `PUBLIC_ECOSYSTEME` ;
- `INTERNE` ;
- `CONFIDENTIEL` ;
- `RESTREINT` ;
- `SECRET_CORE`.

Un produit ne reçoit que le résultat nécessaire :

```json
{
  "identity_reference": "IDN-PER-000001",
  "state": "ACTIVE",
  "assurance": "A2"
}
```

Il ne reçoit pas automatiquement :

- toutes les organisations liées ;
- tous les produits utilisés ;
- les risques ;
- les incidents ;
- les restrictions profondes ;
- les identifiants d’autres produits ;
- les dossiers de rapprochement.

---

# 15. PREUVES ET GARDES P3 À AJOUTER

La nouvelle garde propre à `CAP-CORE-001` doit notamment vérifier :

1. qu’un utilisateur de Wasplex peut recevoir une référence canonique ;
2. que la même personne rejoignant GamaDrive conserve la même identité ;
3. qu’un compte produit supprimé ne supprime pas l’identité ;
4. qu’une organisation possède une identité canonique et un dossier distinct dans `CAP-CORE-002` ;
5. qu’un représentant sans mandat n’est pas présenté comme représentant autorisé ;
6. qu’une identité provisoire ne reçoit pas une assurance forte ;
7. qu’un doublon probable ne provoque pas une fusion automatique ;
8. qu’un produit ne peut pas lire les relations d’un autre produit sans autorisation ;
9. qu’une relation expirée n’est pas restituée comme active ;
10. que les données métier détaillées ne sont pas absorbées dans le registre ;
11. que le Matching refuse une identité non qualifiée pour le contexte demandé ;
12. que les falsifications de source, de relation, d’assurance ou de date font échouer la garde.

---

# 16. MIGRATION PROPOSÉE

## 16.1 Inventaire des identités locales

Chaque produit pilote doit produire :

- ses identifiants locaux ;
- les sources disponibles ;
- les doublons connus ;
- les relations organisationnelles ;
- les niveaux de vérification ;
- les comptes techniques ;
- les comptes suspendus ou clos.

## 16.2 Création des références canoniques

Les identités sont :

- rapprochées ;
- créées ;
- placées en état provisoire ;
- ou soumises à vérification.

## 16.3 Table de correspondance

Chaque produit conserve une correspondance :

```text
local_subject_opaque → identity_reference
```

Le Core ne doit pas exiger l’export de toutes les données métier locales.

## 16.4 Migration progressive

Ordre pilote recommandé :

1. Wasplex ;
2. GamaDrive ;
3. portail GAMAD ;
4. G-Business ;
5. autres satellites.

## 16.5 Compatibilité

Pendant la migration :

- les identités locales restent lisibles ;
- les nouvelles opérations utilisent la référence canonique ;
- les doublons sont signalés ;
- les fusions restent gouvernées ;
- le rollback demeure possible.

---

# 17. CONSÉQUENCES SUR L’ADMISSION ACTUELLE

La version actuellement admise de `CAP-CORE-001` nomme le commit :

```text
65093691e0efbb35cf8ff92aee9c59dcfb3b7704
```

La révision du module doit être considérée comme une évolution significative.

Claude doit donc préparer :

1. une conception révisée ou un amendement de conception ;
2. une loi ou un acte de modification ;
3. la mise à jour du Registre des capacités en ajout seul ;
4. la nouvelle version du contrat `CTR-01` ;
5. le code correspondant ;
6. les gardes P3 et contre-épreuves ;
7. un dossier de migration ;
8. un dossier de réadmission ;
9. une nouvelle décision humaine d’admission ;
10. la constatation explicite de la caducité de l’admission précédente, si le mécanisme applicable l’exige.

Claude ne doit pas :

- réécrire silencieusement `ADOPTION-0038` ;
- supprimer l’histoire initiale ;
- modifier rétroactivement les faits de 2026 ;
- prétendre que la première version couvrait déjà les utilisateurs ;
- fusionner la révision sur `main` sans décision de l’autorité.

---

# 18. DOCUMENTS CANONIQUES À LIRE AVANT RÉDACTION

Claude doit lire intégralement, au minimum :

```text
genesis-ii/conception/CONCEPTION-CAP-CORE-001-REGISTRE-DES-IDENTITES-0001.md
genesis-ii/registre/ADOPTION-0038-CAP-CORE-001-0001.md
genesis-ii/registre/ADOPTION-0063-ADMISSION-EXCEPTIONNELLE-VINGT-0001.md
genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md
genesis-ii/constitution/PRODUCT-CONSTITUTION-0001-constitution-produits.md
genesis-ii/gouvernance/DATA-GOVERNANCE-0001-gouvernance-donnees-finalites-responsabilites-classification-conservation-partage-droits.md
genesis-ii/gouvernance/SECURITY-GOVERNANCE-0001-gouvernance-acces-secrets-incidents-continuite.md
genesis-ii/gouvernance/GOVERNANCE-0001-constitution-gouvernance-gamad-core.md
core/registre-identites/src/Ctr01.php
core/registre-identites/tests/identite_p3.php
apps/console-laravel/app/Http/Controllers/Ctr01Controller.php
apps/console-laravel/routes/web.php
```

Il doit aussi consulter les capacités suivantes afin d’éviter les collisions de domaine :

```text
CAP-CORE-002 — Registre des organisations
CAP-CORE-003 — Autorités et mandats
CAP-CORE-004 — Autorisation commune
CAP-CORE-005 — Authentification et assurance
CAP-CORE-009 — Registre des contrats
CAP-CORE-013 — Audit commun
CAP-CORE-014 — Journal d’événements
CAP-CORE-015 — Preuves d’intégrité
CAP-CORE-017 — Risques et exceptions
CAP-CORE-018 — Incidents
CAP-CORE-020 — Annuaire et Atlas
```

---

# 19. LIVRABLES ATTENDUS DE CLAUDE

Claude doit préparer une proposition complète comprenant :

## 19.1 Loi révisée

Un texte normatif ou projet normatif qui :

- corrige le périmètre de `CAP-CORE-001` ;
- reconnaît les utilisateurs des produits ;
- reconnaît les organisations comme identités de premier rang ;
- distingue identité, compte, profil, authentification, autorisation et mandat ;
- autorise une inscription gouvernée à l’échelle ;
- préserve la minimalité et l’interdit du profil universel ;
- établit la relation avec le Matching.

## 19.2 Amendement en ajout seul

La révision doit préserver les textes antérieurs et ajouter :

- un nouveau titre ;
- de nouveaux articles ;
- une décision de portée ;
- les nouveaux invariants ;
- les nouvelles menaces ;
- les nouvelles frontières ;
- les impacts d’état.

## 19.3 Conception technique

Elle doit contenir :

- modèle de données ;
- contrats ;
- événements ;
- politiques d’inscription ;
- niveaux d’assurance ;
- cycle de vie ;
- sécurité ;
- classification ;
- migration ;
- sauvegarde ;
- restauration ;
- audit.

## 19.4 Code

Claude peut ensuite proposer ou écrire :

- migrations ;
- nouveaux services ;
- commandes gouvernées ;
- contrats de lecture ;
- intégrations ;
- contrôleurs ;
- tests ;
- contre-épreuves ;
- documentation.

## 19.5 Preuve

La garde P3 doit prouver le nouveau périmètre et échouer sur les falsifications ciblées.

---

# 20. CRITÈRES D’ACCEPTATION DE LA RÉVISION

La révision est conceptuellement acceptable seulement si les affirmations suivantes deviennent vraies :

- [ ] Une personne utilisant un produit GAMAD peut recevoir une identité canonique du Core.
- [ ] La même personne peut utiliser plusieurs produits sous la même référence.
- [ ] Un produit ne possède pas l’identité souveraine de ses utilisateurs.
- [ ] Une organisation reconnue possède une identité canonique.
- [ ] Les membres et acteurs d’une organisation peuvent être reliés à celle-ci.
- [ ] Les mandats sensibles demeurent gouvernés par `CAP-CORE-003`.
- [ ] Les profils détaillés restent dans les produits.
- [ ] L’Identity Registry n’est pas un profil universel.
- [ ] L’inscription d’utilisateurs est possible sans acte normatif individuel.
- [ ] Toute inscription reste traçable et gouvernée.
- [ ] L’assurance reste distincte de l’existence.
- [ ] Les identités provisoires et pseudonymes sont encadrées.
- [ ] Les doublons sont détectés sans fusion automatique abusive.
- [ ] Les relations produit et organisation sont historisées.
- [ ] Le Matching peut consommer les références canoniques et les qualifications autorisées.
- [ ] Les produits ne reçoivent pas la connaissance profonde du Core sans autorisation.
- [ ] La nouvelle version possède ses propres gardes et contre-épreuves.
- [ ] L’admission actuelle est réexaminée après modification.
- [ ] Aucun texte historique n’est silencieusement réécrit.
- [ ] La décision finale reste humaine.

---

# 21. FORMULE FONDATRICE PROPOSÉE

> **L’Identity Registry est la capacité souveraine du GAMAD Core chargée de reconnaître, référencer et maintenir dans le temps les personnes, organisations, agents, produits, services, realms et autres entités de l’écosystème. Il fournit à chaque entité une référence canonique stable, permet aux produits et institutions de rattacher leurs utilisateurs et acteurs à cette référence, gouverne les identités provisoires ou vérifiées et préserve leur cycle de vie. Il ne remplace ni les profils métier, ni l’authentification, ni les autorisations, ni les mandats, mais leur fournit la continuité d’identité commune indispensable au portail GAMAD, aux satellites et au futur Moteur de Matching GAMAD.**

---

# 22. FORMULE COURTE

> **Le produit connaît l’usage. L’organisation connaît sa structure. Le Core connaît l’identité.**

---

# 23. STATUT DU PRÉSENT DOSSIER

Le présent dossier :

- ne modifie pas le dépôt ;
- ne constitue pas une adoption ;
- ne crée aucune identité ;
- ne change pas l’état de `CAP-CORE-001` ;
- ne rend pas la nouvelle doctrine opposable ;
- ne fusionne aucun code ;
- ne déploie aucun service.

Il sert à demander à Claude de préparer une **loi révisée complète**, cohérente avec le corpus actuel et avec la vision future de GAMAD.

**STATUT : DOCUMENT D’INSTRUCTION FINAL — À REMETTRE À CLAUDE POUR RÉDACTION DE LA LOI RÉVISÉE.**
