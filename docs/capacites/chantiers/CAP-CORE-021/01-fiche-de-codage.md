# CAP-CORE-021 — Fiche de codage du Matching Engine

## 1. Statut de départ

```text
Capacité : CAP-CORE-021 — Matching Engine
État au lancement : NO GO
Code métier existant : aucun moteur opérationnel
Documents existants : conception transversale et note fondatrice
Objectif du chantier : GO de production pour un moteur déterministe, explicable et multi-consommateurs
```

Le catalogue actuel indique qu’aucun module de Matching n’est livré. La présence de documents conceptuels ne constitue pas une preuve d’implémentation.

## 2. Mission

Construire une capacité souveraine du GAMAD Core qui transforme, pour une finalité autorisée, des références, besoins, offres, critères et signaux minimaux en :

- qualifications ;
- correspondances ;
- classements ;
- segments protégés ;
- explications ;
- activations temporaires ;
- mesures d’utilité et de qualité.

Le moteur doit servir plusieurs consommateurs sans être capturé par Wasplex ni par un autre satellite.

## 3. Formule fonctionnelle

```text
connaissance autorisée
+ finalité exacte
+ politique versionnée
+ contexte et realm
+ sources contractuelles
= correspondance explicable, temporaire et activable
```

L’absence d’un élément obligatoire produit un refus ou un résultat `INDETERMINE`, jamais une invention.

## 4. Premier périmètre GO

Le premier périmètre admis comprend :

1. qualification déterministe d’une entité ou d’un objet ;
2. appariement déterministe entre une offre et un besoin, ou entre deux objets autorisés ;
3. classement de candidats avec score de pertinence distinct du niveau de confiance ;
4. création d’un segment protégé ;
5. vérification d’appartenance sans export de la liste complète ;
6. activation temporaire pour un consommateur et une finalité ;
7. explication des critères favorables, défavorables et non établis ;
8. mesure des résultats fournis par le consommateur ;
9. comparaison de deux versions de politique sur un jeu d’évaluation contrôlé ;
10. contestation et réexamen d’un résultat.

Ne pas inclure dans ce chantier :

- apprentissage automatique ;
- grand modèle de langage ;
- modèle prédictif externe ;
- collecte comportementale universelle ;
- recommandation sans finalité ;
- optimisation automatique d’une politique en production ;
- décision automatique d’embauche, d’aide, de crédit ou de sanction ;
- publicité ou facturation Wasplex ;
- gestion métier d’une offre, d’un stock ou d’une campagne.

## 5. Architecture cible

Créer :

```text
core/moteur-matching/
├── README.md
├── composer.json
├── migrations/
├── src/
│   ├── Magasin.php
│   ├── SchemaMatching.php
│   ├── Matching.php
│   ├── Qualificateur.php
│   ├── Apparieur.php
│   ├── Classement.php
│   ├── Segments.php
│   ├── Activation.php
│   ├── Explication.php
│   ├── Mesure.php
│   ├── Contestations.php
│   ├── ResolutionSources.php
│   ├── CompilateurPolitique.php
│   ├── EvaluateurDeterministe.php
│   └── Exceptions/
└── tests/
    └── matching_p3.php
```

Dans la console Laravel :

```text
apps/console-laravel/app/Application/Matching/
apps/console-laravel/app/Http/Controllers/Api/V1/MatchingController.php
apps/console-laravel/app/Http/Controllers/MatchingController.php
apps/console-laravel/resources/views/matching/
apps/console-laravel/app/Console/Commands/
```

## 6. Magasin isolé

Variables :

```text
MATCHING_REGISTRY_URL
MATCHING_REGISTRY_PATH
GAMAD_MATCHING_DRIVER
```

Règles :

- PostgreSQL obligatoire en production ;
- SQLite uniquement en local et CI ;
- aucun fallback silencieux en production ;
- aucune utilisation de `DATABASE_URL` comme solution de secours ;
- aucune lecture SQL directe des magasins des autres capacités ;
- toute donnée extérieure arrive par contrat ou événement autorisé ;
- la readiness vérifie migration, connectivité, politique active et accès aux dépendances obligatoires.

## 7. Frontière avec les capacités du Core

### CAP-CORE-001 — Identity Registry

Possède :

- identité canonique ;
- référence stable ;
- type d’entité ;
- continuité minimale.

Le Matching utilise la référence et ne modifie jamais l’identité.

### CAP-CORE-002 — Organizations Registry

Possède :

- profil organisationnel ;
- unités ;
- affiliations ;
- structure.

Le Matching consomme seulement les attributs explicitement autorisés pour la finalité.

### CAP-CORE-003 — Authorities & Mandates

Détermine qui peut engager ou représenter une organisation.

Un score de pertinence ne vaut jamais mandat.

### CAP-CORE-004 — Authorization

Rend la décision finale `PERMIS` ou `REFUSE` pour chaque opération.

Le Matching ne s’auto-autorise pas parce qu’une politique de scoring existe.

### CAP-CORE-005 — Authentication & Access

Authentifie les acteurs et fournit l’assurance de session.

Le Matching ne stocke ni mot de passe, ni passkey, ni session.

### CAP-CORE-006 — Sources Registry

Déclare les sources autorisées, leurs finalités, vérifications et cycles.

Tout signal utilisé doit référencer une source active et utilisable pour la finalité exacte.

### CAP-CORE-007 — Rules / Policies Registry

Possède les politiques versionnées.

Le Matching ne crée pas un second registre de politiques. Il compile une version active de politique en plan d’exécution déterministe et conserve l’empreinte du plan.

### CAP-CORE-008 — Decisions Registry

Enregistre les décisions formelles : adoption d’un contexte sensible, activation d’une politique, suspension d’un consommateur, acceptation d’un risque ou clôture d’une contestation majeure.

Le résultat du Matching reste une recommandation ou une qualification, pas une décision formelle.

### CAP-CORE-009 — Contracts Registry

Possède les contrats d’entrée, de requête, de résultat, de segment, d’activation et de mesure.

Aucun consommateur ou source n’est interrogé hors contrat actif.

### CAP-CORE-010 — Canonical Vocabulary

Possède les codes : types d’objet, opérateurs, états, facteurs, obligations et contextes.

Aucune comparaison de sécurité ou de critère par texte libre approximatif.

### CAP-CORE-011 — Products Registry

Référence les consommateurs produits.

Le Matching vérifie qu’un produit est actif et autorisé pour le contexte demandé.

### CAP-CORE-012 — Realms Registry

Borne la collecte, l’exécution, les résultats et les activations.

Un realm parent n’accède pas automatiquement aux membres d’un realm enfant.

### CAP-CORE-013 — Common Audit

Conserve les traces transversales autorisées.

Le Matching y écrit les commandes, refus, activations, exports et contestations sans contenu sensible complet.

### CAP-CORE-014 — Event Journal

Transporte les signaux normalisés, changements de source, résultats minimaux, expirations et mesures.

Le Matching ne lit pas directement les bases satellites.

### CAP-CORE-015 — Integrity Proofs

Produit les preuves des politiques compilées, exécutions, segments, instantanés et paquets de contestation.

### CAP-CORE-016 — Secrets & Keys

Réalise les opérations cryptographiques et fournit les références de clés.

Aucune clé privée dans le magasin du Matching.

### CAP-CORE-017 — Risks & Exceptions

Conserve les risques, restrictions, exceptions et mesures compensatoires.

Une exception ne permet jamais d’utiliser un critère interdit par défaut.

### CAP-CORE-018 — Incidents

Coordonne les incidents de fuite, biais grave, activation abusive, politique fantôme ou segment non expiré.

### CAP-CORE-019 — Backup & Restore

Sauvegarde et restaure le magasin du Matching et ses preuves.

### CAP-CORE-020 — Directory & Atlas

Fournit la carte des consommateurs, contrats, dépendances et realms.

L’Atlas ne fournit pas une population de candidats par simple traversée de graphe ; il aide à résoudre les composants autorisés.

### CAP-CORE-022 — Satellite Federation

Établit le produit consommateur et les échanges fédérés.

Un jeton fédéré ne devient jamais un droit général de Matching.

## 8. Frontière avec Wasplex

Wasplex possède :

- annonceurs ;
- campagnes ;
- créations publicitaires ;
- budgets ;
- quotas ;
- diffusion ;
- vues et interactions ;
- facturation ;
- règles commerciales locales.

Le Matching possède :

- critères normalisés ;
- qualification ;
- pertinence et confiance ;
- segment protégé ;
- explication ;
- obligations d’activation ;
- expiration ;
- mesure de qualité transversale.

Parcours :

```text
Wasplex déclare une demande normalisée
→ le Core vérifie le consommateur, la finalité et les critères
→ le Matching produit un segment protégé
→ Wasplex vérifie l’éligibilité ou active le segment
→ Wasplex applique budget, dates, fréquence et diffusion
→ Wasplex retourne des mesures minimales autorisées
```

Le Matching ne reçoit pas automatiquement toute la base Wasplex et Wasplex ne reçoit pas toute la base Core.

## 9. Deux consommateurs obligatoires

Le statut `GO` exige au moins deux consommateurs réels utilisant les mêmes contrats fondamentaux :

```text
Pilote 1 : Wasplex
Pilote 2 : Portail GAMAD, G-Business ou autre produit effectivement disponible
```

Ne pas inventer le deuxième consommateur. S’il n’existe pas au moment du chantier :

- livrer le moteur et le pilote Wasplex ;
- documenter les tests réussis ;
- maintenir `CAP-CORE-021` à `NO GO` ;
- ne pas présenter une intégration simulée comme preuve de non-exclusivité.

## 10. Contextes de Matching

Créer une liste fermée initiale dans `CAP-CORE-010`, activée par politique :

```text
WASPLEX_AUDIENCE
B2B_OPPORTUNITY
SERVICE_ORIENTATION
INSTITUTIONAL_ELIGIBILITY
RESOURCE_RECOMMENDATION
```

Un contexte non activé retourne `CONTEXTE_NON_AUTORISE`.

Un contexte ne donne pas accès à toutes les sources. La politique associe explicitement :

- consommateur ;
- finalité ;
- types d’objets ;
- critères autorisés ;
- sources admissibles ;
- realms ;
- obligations ;
- durées ;
- seuils ;
- mode de supervision humaine.

## 11. Critères autorisés et interdits

Chaque critère possède :

```text
reference canonique
contexte
operateur
type de valeur
obligatoire ou facultatif
poids
traitement du non-etabli
sources autorisees
fraicheur maximale
classification
explication publique autorisee ou non
```

Interdits par défaut :

- jugement moral ;
- valeur humaine globale ;
- rang spirituel ;
- pratique religieuse supposée ;
- opinion privée sans lien nécessaire avec la finalité ;
- donnée intime non nécessaire ;
- réputation générale non sourcée ;
- caractéristique obtenue illicitement ;
- critère utilisé uniquement pour exclure sans rapport légitime ;
- proxy volontaire d’un critère interdit ;
- score social transversal ;
- inférence de vulnérabilité à des fins commerciales.

Les critères interdits ne deviennent pas dérogeables par une simple exception.

## 12. Invariants de souveraineté

1. Le Matching reste remplaçable : aucun fournisseur externe ne possède les politiques ni les résultats canoniques.
2. Les politiques et plans d’exécution sont versionnés et vérifiables.
3. Une exécution est reproductible à données et versions identiques.
4. Une donnée sans source, date ou finalité est inutilisable.
5. Le score de pertinence est séparé du niveau de confiance.
6. Les facteurs inconnus restent visibles.
7. Un résultat expire.
8. Un segment expire.
9. Une activation expire.
10. Un consommateur ne reçoit que la projection prévue par son contrat.
11. Le Matching n’apprend pas de tout comportement par défaut.
12. Les mesures commerciales d’un consommateur ne deviennent pas automatiquement vérité commune.

## 13. Phases du chantier

### Phase A — audit

- vérifier tous les prérequis ;
- confirmer les consommateurs disponibles ;
- inventorier les sources et contrats ;
- identifier les données d’essai autorisées ;
- confirmer les contextes réellement nécessaires.

Arrêt obligatoire si un prérequis souverain manque.

### Phase B — fondation

- magasin ;
- schéma ;
- migrations ;
- politiques ;
- contrats ;
- vocabulaire ;
- commandes internes ;
- tests déterministes.

### Phase C — intégrations

- événements ;
- sources ;
- Wasplex ;
- deuxième consommateur ;
- console ;
- API ;
- audit et preuves.

### Phase D — admission

- tests de sécurité ;
- tests de qualité ;
- tests d’équité ;
- tests de charge ;
- sauvegarde et restauration ;
- exercices pilotes ;
- rapport final `GO` ou `NO GO`.

## 14. Livrables obligatoires

- code `core/moteur-matching/` ;
- migrations PostgreSQL et SQLite ;
- politiques actives ;
- contrats actifs ;
- vocabulaire canonique ;
- API v1 ;
- console ;
- événements et audit ;
- preuves ;
- métriques et alertes ;
- sauvegarde et restauration ;
- deux pilotes réels ;
- fiche finale `docs/capacites/CAP-CORE-021-matching-engine.md` ;
- rapport d’admission indiquant honnêtement `GO` ou `NO GO`.

## 15. Interdictions de chantier

Claude Code ne doit pas :

- supprimer une garde parce qu’elle gêne le chantier ;
- utiliser le texte fondateur comme fausse preuve d’exécution ;
- modifier directement les bases d’un satellite ;
- déclarer Wasplex propriétaire du moteur ;
- produire un modèle opaque pour accélérer le développement ;
- introduire une dépendance à une API d’IA sans décision ;
- créer de données personnelles fictives ressemblant à des personnes réelles ;
- déclarer un deuxième pilote qui n’existe pas ;
- fusionner tant que les critères `GO` ne sont pas démontrés.
