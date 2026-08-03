# CAP-CORE-020 — Fiche de codage

## 1. Objet du chantier

Construire une capacité de production qui donne une vue fiable et exploitable de l’écosystème GAMAD sans réintroduire l’ancien annuaire documentaire supprimé avec Genesis II.

Le chantier doit créer :

```text
core/annuaire-atlas/
```

Branche de travail recommandée :

```text
claude/cap-core-020-directory-atlas-go
```

La capacité passe de `NO GO` à `GO` seulement lorsque :

- le magasin PostgreSQL est exploitable ;
- les projections proviennent de sources techniques autorisées ;
- la recherche et les requêtes de graphe sont bornées ;
- les relations sont exactes, versionnées et réconciliées ;
- les états déclarés et observés sont séparés ;
- la fraîcheur et les divergences sont visibles ;
- les contrôles d’autorisation et de realm sont effectifs ;
- les vues internes et publiques sont distinctes ;
- les API, console, contrats, audit, événements, métriques et sauvegardes sont livrés ;
- la garde de capacité, les intégrations et l’exercice PostgreSQL réel sont verts.

Une note ou un écran sans projection réelle ne suffit pas.

---

## 2. Problème actuel

Le Core possède plusieurs registres opérationnels spécialisés : identités, produits, sources, politiques, contrats, fédération, accès et journal. Les chantiers précédents ajoutent organisations, realms, événements, preuves, secrets, décisions, risques et incidents.

Mais il n’existe pas encore de vue commune permettant de répondre simplement à des questions comme :

```text
Quelles capacités existent réellement ?
Quel produit consomme quel contrat ?
Quelle organisation est responsable de ce produit ?
Dans quel realm ce service est-il exploité ?
Quel endpoint est autorisé pour cet environnement ?
Quelle source nourrit cette capacité ?
Quels événements publie-t-elle ?
Quelles dépendances seraient touchées par une suspension ?
Quel état est déclaré, quel état est observé et depuis quand ?
Quels incidents ou risques affectent actuellement ce chemin ?
```

Les informations existent de façon dispersée. Un opérateur doit aujourd’hui ouvrir plusieurs écrans et connaître les relations à l’avance.

L’ancien module d’annuaire ne doit pas être restauré : il dépendait du corpus documentaire supprimé et ne rendait aucun service au runtime.

---

## 3. Mission

`CAP-CORE-020` possède :

- l’identité opérationnelle des entrées de l’annuaire ;
- la projection minimale et reconstructible de leurs métadonnées autorisées ;
- la représentation des relations exactes entre les entrées ;
- la date, la source et la version de chaque observation ;
- les états de fraîcheur, complétude et divergence ;
- les vues de recherche et de navigation ;
- les analyses de dépendance et d’impact bornées ;
- les profils de visibilité interne, partenaire et public ;
- les instantanés signés de l’Atlas ;
- les diagnostics de collecte et de réconciliation.

`CAP-CORE-020` ne possède pas les données métier originales.

---

## 4. Vocabulaire métier

### 4.1 Entrée d’annuaire

Une entrée est une référence opérationnelle consultable, par exemple :

```text
CAP-CORE-011
PRD-GAMAD-DRIVE
ORG-GAMAD-CI
RLM-CI-ABIDJAN
CTR-FEDERATION-01
SRC-GAMAD-DEPOT
POL-FEDERATION-SATELLITES-V1
INC-2026-0001
```

L’entrée ne copie pas le dossier complet de l’objet.

### 4.2 Nœud d’Atlas

Un nœud représente une entrée dans le graphe des dépendances.

### 4.3 Relation d’Atlas

Une relation exacte lie deux nœuds selon un type canonique, par exemple :

```text
PRODUIT_EXPLOITE_PAR_ORGANISATION
PRODUIT_RATTACHE_A_REALM
CAPACITE_EXPOSE_CONTRAT
PRODUIT_CONSOMME_CONTRAT
CONTRAT_UTILISE_SOURCE
CAPACITE_PUBLIE_EVENEMENT
CAPACITE_DEPEND_DE_CAPACITE
INCIDENT_AFFECTE_PRODUIT
RISQUE_CONCERNE_CONTRAT
```

### 4.4 Observation

Une observation est un fait lu à une date donnée depuis une source souveraine ou une sonde autorisée.

### 4.5 Projection

Une projection est la représentation minimale de ce fait dans l’Atlas.

### 4.6 Fraîcheur

La fraîcheur exprime le délai entre l’observation attendue et la dernière observation réussie.

### 4.7 Divergence

Une divergence existe lorsque deux sources autorisées ne racontent pas la même chose ou lorsque l’état observé contredit l’état déclaré.

---

## 5. Différence entre Directory et Atlas

### Directory

Le Directory sert à retrouver rapidement :

- une capacité ;
- un produit ;
- une organisation ;
- un realm ;
- un contrat ;
- une politique ;
- une source ;
- un endpoint autorisé ;
- un responsable ou une fonction de contact ;
- un état déclaré et son origine ;
- une dernière observation technique.

### Atlas

L’Atlas sert à comprendre :

- qui dépend de quoi ;
- qui produit et qui consomme ;
- quels realms sont traversés ;
- quelles organisations sont responsables ;
- quels contrats encadrent les échanges ;
- quelles sources et finalités sont impliquées ;
- quels produits seraient touchés par une panne ou une suspension ;
- quels chemins sont bloqués, périmés ou divergents.

Le Directory répond « qu’est-ce que c’est ? ».

L’Atlas répond « comment cela est relié au reste ? ».

---

## 6. Frontières avec les autres capacités

### CAP-CORE-001 — Identity Registry

Possède les identités canoniques. L’Atlas ne copie que les références nécessaires et les projections autorisées.

### CAP-CORE-002 — Organizations Registry

Possède les organisations, unités et affiliations. L’Atlas ne devient pas un organigramme RH.

### CAP-CORE-003 — Authorities & Mandates

Possède les mandats et délégations. L’Atlas peut afficher une fonction responsable résolue, jamais fabriquer un mandat.

### CAP-CORE-004 — Authorization

Décide l’accès à chaque vue et requête. L’Atlas n’accorde aucune permission.

### CAP-CORE-005 — Authentication & Access

Authentifie les lecteurs. Aucun secret de session n’entre dans l’Atlas.

### CAP-CORE-006 — Sources Registry

Possède les sources, leur lignée et leurs finalités. L’Atlas projette seulement les références et relations utiles.

### CAP-CORE-007 — Rules / Policies Registry

Possède les politiques actives. L’Atlas indique quelles politiques encadrent un élément ou un chemin.

### CAP-CORE-008 — Decisions Registry

Possède les décisions formelles. L’Atlas peut montrer qu’une décision gouverne une transition ou un effet.

### CAP-CORE-009 — Contracts Registry

Possède les contrats, versions, parties, opérations et schémas. L’Atlas construit les relations producteurs-consommateurs à partir de ce registre.

### CAP-CORE-010 — Canonical Vocabulary

Possède les codes et relations sémantiques. Tous les types de nœuds et relations de l’Atlas doivent être canoniques.

### CAP-CORE-011 — Products Registry

Possède les fiches produit et environnements. L’Atlas ne modifie aucun produit ni endpoint.

### CAP-CORE-012 — Realms Registry

Possède les realms, rattachements et franchissements. L’Atlas respecte ces frontières dans toutes les projections.

### CAP-CORE-013 — Common Audit

Conserve les traces des consultations et changements sensibles. L’Atlas ne duplique pas le journal.

### CAP-CORE-014 — Event Journal

Transporte les changements permettant de rafraîchir les projections. Une réconciliation périodique reste obligatoire.

### CAP-CORE-015 — Integrity Proofs

Signe les instantanés et paquets exportés. L’Atlas ne signe rien directement.

### CAP-CORE-016 — Secrets & Keys

Gère les références de clés nécessaires aux signatures. Aucune clé privée n’entre dans l’Atlas.

### CAP-CORE-017 — Risks & Exceptions

Possède les risques et exceptions. L’Atlas affiche uniquement les projections autorisées affectant un chemin.

### CAP-CORE-018 — Incidents

Possède les incidents. L’Atlas peut calculer les dépendances affectées, mais ne gère pas la réponse à incident.

### CAP-CORE-019 — Backup & Restore

Sauvegarde et restaure le magasin Atlas. L’Atlas peut exposer le dernier état de continuité autorisé.

### CAP-CORE-021 — Matching Engine

Consommera l’Atlas pour découvrir les produits, contrats, realms et sources possibles. L’Atlas ne produit aucun score de matching.

### CAP-CORE-022 — Satellite Federation

Possède les ouvertures et jetons fédérés. L’Atlas projette les produits fédérables et environnements autorisés, jamais les jetons.

---

## 7. Ce que la capacité ne doit pas devenir

Elle ne doit pas devenir :

- une base maître universelle ;
- un moteur d’autorisation ;
- un outil de surveillance réseau généraliste ;
- un scanner automatique d’infrastructure ;
- une CMDB contenant des secrets ;
- un moteur de recherche dans les données métier ;
- un graphe social des utilisateurs ;
- un répertoire RH ;
- un agrégateur de profils complets ;
- un moteur de matching ;
- un remplaçant des registres souverains ;
- un lecteur de Markdown déclarant arbitrairement les états ;
- une interface capable d’appeler n’importe quelle URL découverte.

---

## 8. Types d’entrées minimaux

```text
CAPACITE
PRODUIT
ENVIRONNEMENT_PRODUIT
ORGANISATION
UNITE_ORGANISATIONNELLE
REALM
SOURCE
POLITIQUE
CONTRAT
OPERATION_CONTRAT
TYPE_EVENEMENT
ABONNEMENT_EVENEMENT
PREUVE
SECRET_REFERENCE
DECISION
RISQUE
EXCEPTION
INCIDENT
SERVICE_CONTINUITE
ENDPOINT
```

Les types sont des termes de `CAP-CORE-010`.

Une entrée inconnue est refusée, jamais stockée sous un type libre.

---

## 9. Relations minimales

```text
APPARTIENT_A
EXPLOITE_PAR
RESPONSABLE_DE
RATTACHE_A_REALM
DEPLOYE_DANS
EXPOSE
CONSOMME
PRODUIT
DEPEND_DE
UTILISE_SOURCE
ENCADRE_PAR_POLITIQUE
ENCADRE_PAR_CONTRAT
PUBLIE_EVENEMENT
CONSOMME_EVENEMENT
FRANCHIT_REALM
PROTEGE_PAR_PREUVE
UTILISE_SECRET_REFERENCE
GOUVERNE_PAR_DECISION
AFFECTE_PAR_RISQUE
COUVERT_PAR_EXCEPTION
AFFECTE_PAR_INCIDENT
SAUVEGARDE_PAR
REMPLACE
DEPRECIE
```

Une relation doit préciser son origine exacte et sa période de validité.

---

## 10. États multidimensionnels

L’Atlas ne doit jamais résumer un élément par un unique feu vert ambigu.

Pour chaque entrée, séparer au minimum :

```text
etat_declare
etat_observe
fraicheur
completude
conformite
impact_incident
impact_risque
preuve_disponible
```

Exemple :

```json
{
  "etat_declare": "ACTIF",
  "etat_observe": "INDISPONIBLE",
  "fraicheur": "FRAICHE",
  "completude": "COMPLETE",
  "conformite": "CONFORME",
  "impact_incident": "SEV-2"
}
```

L’écran doit mettre en évidence la contradiction au lieu de choisir silencieusement un état.

---

## 11. Cas d’usage prioritaires

### Retrouver un produit

Afficher sa référence, son organisation responsable, ses realms, ses environnements, ses contrats, ses capacités dépendantes et son état observé.

### Analyser l’impact d’un contrat suspendu

Lister les producteurs, consommateurs, produits, événements et chemins dépendants, avec profondeur bornée.

### Préparer une réponse à incident

Depuis un produit ou une capacité touchée, produire les dépendances amont et aval autorisées.

### Vérifier un franchissement de realm

Afficher le chemin exact, les contrats, politiques, décisions ou exceptions qui le justifient.

### Préparer le Matching

Lister les sources, produits, contrats et realms compatibles sans accéder aux données métier ni calculer de correspondance.

### Publier un annuaire public

Exposer uniquement les entrées explicitement marquées publiables et une projection minimale approuvée.

---

## 12. Architecture cible

```text
Registres souverains
        │
        ├── événements CAP-CORE-014
        ├── requêtes internes contractuelles
        └── attestations / sondes autorisées
                    │
                    ▼
        Collecteurs CAP-CORE-020
                    │
                    ▼
        Validation + canonicalisation
                    │
                    ▼
        Magasin de projections PostgreSQL
                    │
        ┌───────────┴───────────┐
        ▼                       ▼
     Directory                Atlas
  recherche/listes       graphe/dépendances
        │                       │
        └───────────┬───────────┘
                    ▼
          API / Console / Exports
```

---

## 13. Magasin

Variables dédiées :

```text
DIRECTORY_ATLAS_URL
DIRECTORY_ATLAS_PATH
GAMAD_DIRECTORY_ATLAS_DRIVER
DIRECTORY_ATLAS_RECONCILIATION_INTERVAL
DIRECTORY_ATLAS_MAX_GRAPH_DEPTH
DIRECTORY_ATLAS_MAX_GRAPH_NODES
DIRECTORY_ATLAS_PUBLIC_ENABLED
```

Règles :

- PostgreSQL obligatoire en production ;
- SQLite seulement en local et en CI ;
- aucun fallback silencieux ;
- transaction pour chaque lot de projection ;
- contraintes d’unicité et d’intégrité ;
- migrations idempotentes ;
- aucun accès SQL direct aux magasins des autres capacités.

---

## 14. Descripteur de capacité

Chaque capacité exploitable doit fournir un descripteur technique versionné, validé par schéma, par exemple :

```text
core/<module>/resources/capacite.json
```

Le descripteur contient uniquement :

```text
reference
nom
type
module
proprietaire_capacite
contrats_exposes
contrats_consommes
evenements_publies
evenements_consommes
sonde_readiness
classification
version_descripteur
```

Il ne contient pas :

- statut `GO` inventé ;
- secret ;
- URL avec identifiants ;
- données métier ;
- permission implicite.

Le statut de livraison doit venir d’une attestation technique distincte.

---

## 15. Bootstrap initial

Le bootstrap reprend seulement les éléments réellement présents et interrogeables :

- capacités possédant un descripteur valide ;
- produits du registre produits ;
- sources du registre sources ;
- politiques du registre politiques ;
- contrats du registre contrats ;
- identités techniques nécessaires ;
- informations de fédération autorisées ;
- readiness connue.

Il ne crée aucune organisation, aucun realm, aucun endpoint ni relation manquante par supposition.

Les entrées absentes sont signalées comme incomplètes.

---

## 16. Définition de terminé

Le chantier n’est terminé que lorsque l’opérateur peut :

1. rechercher une entrée réelle ;
2. voir sa provenance et sa fraîcheur ;
3. naviguer dans ses relations exactes ;
4. distinguer état déclaré et état observé ;
5. lancer une analyse d’impact bornée ;
6. constater une divergence volontairement injectée ;
7. voir une relation disparaître après réconciliation ;
8. restaurer le magasin ;
9. vérifier un instantané signé ;
10. démontrer qu’un lecteur non autorisé ne voit ni les détails d’un autre realm ni les endpoints internes.

Claude Code livre ensuite la fiche finale :

```text
docs/capacites/CAP-CORE-020-directory-atlas.md
```

et s’arrête.