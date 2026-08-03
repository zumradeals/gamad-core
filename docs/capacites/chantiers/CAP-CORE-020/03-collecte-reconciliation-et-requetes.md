# CAP-CORE-020 — Collecte, réconciliation et requêtes

## 1. Stratégie générale

L’Atlas est alimenté par trois mécanismes complémentaires :

```text
1. événements CAP-CORE-014 pour la rapidité ;
2. requêtes internes contractuelles pour compléter ou vérifier ;
3. réconciliation périodique pour réparer les pertes et divergences.
```

Aucun de ces mécanismes ne suffit seul.

Les événements peuvent être retardés ou rejoués.

Les requêtes peuvent échouer.

La réconciliation doit donc être capable de reconstruire les projections sans dépendre d’un historique parfait.

---

## 2. Principe de non-intrusion

`CAP-CORE-020` ne lit jamais directement les tables d’un autre magasin.

Interdictions :

```text
SELECT direct dans PRODUCT_REGISTRY_URL
SELECT direct dans CONTRACT_REGISTRY_URL
scan des fichiers internes d’un autre module
scan réseau automatique
lecture de variables secrètes
inspection de processus
parcours de répertoire de production
```

Chaque collecte passe par :

- un service interne documenté ;
- un contrat actif CAP-CORE-009 ;
- une opération exacte ;
- une autorisation CAP-CORE-004 ;
- un schéma validé ;
- une source CAP-CORE-006 ;
- une finalité ;
- un realm ;
- une corrélation ;
- une trace CAP-CORE-013.

---

## 3. Descripteurs techniques de capacités

Créer un schéma JSON versionné :

```text
core/annuaire-atlas/resources/schemas/capacite-descripteur-v1.json
```

Exemple :

```json
{
  "reference": "CAP-CORE-011",
  "nom": "Products Registry",
  "type": "REGISTRE_CORE",
  "module": "core/registre-produits",
  "proprietaire_capacite": "ORG-GAMAD",
  "contrats_exposes": ["CTR-PRODUITS-01"],
  "contrats_consommes": ["CTR-01"],
  "evenements_publies": ["PRODUIT_ACTIVE", "PRODUIT_SUSPENDU"],
  "evenements_consommes": [],
  "sonde_readiness": "FOUNDATION_PRODUCT_REGISTRY",
  "classification": "INTERNE",
  "version_descripteur": "1.0.0"
}
```

Règles :

- référence exacte ;
- chemin relatif connu, non utilisé pour exécuter du code ;
- contrats existants ;
- événements existants ;
- aucune clé inconnue ;
- aucun secret ;
- aucune valeur d’état opérationnel écrite à la main ;
- descripteur testé par la capacité propriétaire ;
- modification incompatible détectée par CI.

Les capacités qui ne possèdent pas encore de module peuvent fournir leur descripteur lors de leur chantier, pas avant.

---

## 4. Attestation de livraison d’une capacité

Le statut de livraison ne vient pas du descripteur.

Créer un contrat d’attestation technique portant au minimum :

```text
capacite_reference
version_livree
commit_sha
pr_reference
pipeline_reference
suite_tests_reference
resultat
plateforme
cree_le
preuve_reference
```

Résultats autorisés :

```text
LIVREE
RETIRÉE
DEPRECIEE
```

`GO` reste une notion du catalogue de travail et du processus de livraison. L’Atlas affiche :

```text
livraison attestée
version déployée observée
readiness observée
```

Il ne conclut pas seul à un statut de gouvernance.

---

## 5. Collecteurs minimaux

Créer des collecteurs pour :

```text
CAPACITES
PRODUITS
ORGANISATIONS
REALMS
SOURCES
POLITIQUES
CONTRATS
EVENEMENTS_ET_ABONNEMENTS
PREUVES_PUBLIABLES
DECISIONS_D_IMPACT
RISQUES_ET_EXCEPTIONS_D_IMPACT
INCIDENTS_D_IMPACT
CONTINUITE
FEDERATION
READINESS
```

Chaque collecteur a son propre contrat et son propre seuil de fraîcheur.

Un échec de collecteur n’empêche pas les autres de progresser.

---

## 6. Collecte par événement

Abonnements minimaux :

```text
CAPACITE_LIVREE
CAPACITE_RETIRÉE
PRODUIT_INSCRIT
PRODUIT_ACTIVE
PRODUIT_SUSPENDU
PRODUIT_RETIRE
ENVIRONNEMENT_PRODUIT_DECLARE
ENVIRONNEMENT_PRODUIT_FERME
ORGANISATION_ACTIVEE
ORGANISATION_SUSPENDUE
REALM_ACTIVE
REALM_SUSPENDU
RATTACHEMENT_REALM_ACTIVE
SOURCE_ACTIVEE
SOURCE_SUSPENDUE
POLITIQUE_ACTIVEE
POLITIQUE_SUSPENDUE
CONTRAT_ACTIVE
CONTRAT_DEPRECIE
CONTRAT_SUSPENDU
ABONNEMENT_EVENEMENT_ACTIVE
SECRET_REFERENCE_COMPROMISE
DECISION_MISE_EN_VIGUEUR
EXCEPTION_ACTIVEE
EXCEPTION_EXPIREE
INCIDENT_CONFIRME
INCIDENT_RESOLU
INCIDENT_CLOTURE
RESTAURATION_TERMINEE
```

L’événement doit transporter des références minimales.

Après réception :

1. vérifier l’enveloppe ;
2. vérifier le contrat et le schéma ;
3. dédupliquer ;
4. vérifier le realm ;
5. charger si nécessaire la projection complète par requête interne ;
6. appliquer idempotemment ;
7. enregistrer le checkpoint ;
8. accuser réception ;
9. auditer sans recopier la charge utile.

---

## 7. Événement incomplet

Un événement minimal n’est pas une fiche complète.

Exemple :

```json
{
  "type": "PRODUIT_ACTIVE",
  "reference": "PRD-GAMAD-DRIVE",
  "revision": "17",
  "realm": "RLM-GAMAD"
}
```

Le collecteur appelle ensuite le contrat de projection du registre produit avec la référence et la révision.

Il ne complète pas le nom ou l’organisation depuis une ancienne cache sans signaler cette ancienneté.

---

## 8. Réconciliation périodique

Commandes minimales :

```text
php artisan core:atlas:reconcilier
php artisan core:atlas:reconcilier --source=CAP-CORE-011
php artisan core:atlas:reconcilier --realm=RLM-...
php artisan core:atlas:reconcilier --dry-run
php artisan core:atlas:diagnostiquer
```

La commande complète doit :

1. créer un lot ;
2. figer son périmètre ;
3. lire la liste paginée de la source ;
4. valider chaque élément ;
5. calculer les empreintes ;
6. appliquer les nouvelles révisions ;
7. marquer les éléments absents comme candidats au retrait ;
8. confirmer la complétude de la lecture ;
9. retirer seulement après confirmation ;
10. ouvrir les divergences ;
11. produire un rapport ;
12. créer une preuve ;
13. publier un événement de fin.

---

## 9. Pagination et point de coupure

Toute source volumineuse doit proposer :

```text
cursor stable
ordre déterministe
point de coupure
version ou révision
fin explicite
```

Une collecte sans point de coupure cohérent ne peut pas être qualifiée de complète si des écritures concurrentes peuvent déplacer les pages.

Solutions acceptables :

- snapshot transactionnel ;
- curseur signé ;
- séquence maximale figée ;
- version de registre ;
- date de coupure assortie d’un ordre stable.

---

## 10. Retrait en deux phases

Une entrée non observée dans une collecte ne doit pas être immédiatement retirée.

Processus :

```text
ABSENCE_OBSERVEE
→ vérification de complétude
→ seconde confirmation ou preuve de retrait source
→ RETIREE dans la projection
```

Une panne de pagination ne doit jamais effacer la moitié de l’Atlas.

---

## 11. Reprise après crash

Chaque collecte conserve :

```text
lot
curseur
point de coupure
éléments appliqués
empreintes
statut
```

Après crash :

- reprendre depuis le dernier curseur sûr ;
- ne pas dupliquer les révisions ;
- ne pas retirer des éléments sur un lot incomplet ;
- conserver les erreurs ;
- produire une nouvelle tentative liée.

---

## 12. Rejeu d’événements

Le rejeu CAP-CORE-014 doit être sûr :

- événement déjà appliqué : résultat idempotent ;
- événement ancien : ne pas écraser une révision plus récente ;
- événement hors realm : refus ;
- schéma retiré : utiliser la version contractuelle historique ou mettre en divergence ;
- preuve invalide : quarantaine ;
- source retirée : conserver l’historique sans réactiver l’entrée.

---

## 13. File de quarantaine

Les éléments rejetés vont dans une quarantaine logique avec :

```text
reference collecte
reference source
raison exacte
schema attendu
empreinte reçue
classification
premiere detection
derniere tentative
nombre tentatives
```

La charge sensible complète n’est pas conservée par défaut.

Une correction exige :

- source corrigée ;
- nouvelle collecte ;
- ou décision documentée pour un faux positif.

---

## 14. Recherche Directory

Fonction interne minimale :

```php
rechercher(RequeteAnnuaire $requete): ResultatAnnuaire
```

Filtres :

```text
reference exacte
texte normalisé
type
capacite souveraine
organisation
realm
produit
état déclaré
état observé
fraîcheur
classification
contrat
source
incident actif
risque actif
```

Règles :

- pagination obligatoire ;
- ordre stable ;
- maximum de résultats ;
- recherche exacte prioritaire ;
- pas de résolution sécuritaire par recherche floue ;
- filtres autorisés selon profil ;
- aucune fuite par facettes ;
- temps d’exécution borné.

---

## 15. Recherche textuelle

La recherche textuelle sert à trouver une fiche par son nom ou résumé autorisé.

Elle ne doit jamais :

- décider qu’une référence approximative est une référence exacte ;
- relier deux nœuds ;
- résoudre une organisation ou un mandat ;
- déclencher une action ;
- contourner la classification.

Les résultats indiquent toujours la référence canonique.

---

## 16. Fiche Directory

Une fiche interne peut contenir :

```text
reference Atlas
reference souveraine
type
nom
résumé
propriétaire fonctionnel
organisation
realms
état déclaré
état observé
fraîcheur
complétude
classification
contrats principaux
endpoints autorisés
dernière preuve
dernière collecte
divergences actives
incidents d’impact autorisés
```

La fiche montre les champs absents comme absents.

---

## 17. Requête de voisinage Atlas

Fonction :

```php
voisinage(
    string $reference,
    array $typesRelations,
    string $sens,
    int $profondeur,
    ContexteAcces $contexte,
): GrapheAtlas
```

Contraintes :

```text
profondeur <= DIRECTORY_ATLAS_MAX_GRAPH_DEPTH
nœuds <= DIRECTORY_ATLAS_MAX_GRAPH_NODES
temps <= seuil configuré
relations en liste blanche
realm contrôlé à chaque nœud
classification contrôlée à chaque relation
```

Un nœud masqué ne doit pas être déductible par un lien vide ou un compteur précis.

---

## 18. Analyse d’impact

Entrées :

```text
point de départ
sens amont/aval
profondeur
types de relations
realm
instant de référence
inclure ou non historique
```

Sorties :

```text
nœuds directement touchés
nœuds indirectement touchés
chemins exacts
contrats traversés
realms traversés
relations périmées
divergences
états observés
limites de calcul
preuve ou empreinte de résultat
```

L’analyse ne conclut jamais :

```text
suspendre automatiquement
incident confirmé
risque accepté
permission accordée
matching valide
```

Elle fournit une information d’aide.

---

## 19. Chemin contractuel

Requête :

```text
De PRD-A vers PRD-B, quel chemin contractuel autorisé est connu ?
```

Le résultat doit montrer :

```text
produit source
contrat
opération
capacité productrice
événement ou requête
realm source
franchissement
realm cible
produit consommateur
fraîcheur de chaque projection
```

Aucun chemin n’est créé si une relation manque.

---

## 20. Détection de cycles

Les cycles techniques peuvent être réels, mais doivent être visibles.

L’Atlas doit :

- détecter les cycles de dépendance ;
- distinguer cycle autorisé et cycle problématique ;
- borner le parcours ;
- éviter les boucles infinies ;
- produire un diagnostic.

Une relation `DEPEND_DE` critique formant un cycle non déclaré ouvre une divergence ou un risque selon les règles.

---

## 21. Points uniques de défaillance

L’Atlas peut calculer des candidats :

```text
nœud dont de nombreux chemins dépendent
contrat unique sans alternative
source unique pour une finalité
realm traversé par tous les flux
produit sans environnement de secours déclaré
```

Il ne qualifie pas seul ces candidats comme risques acceptés.

Les résultats peuvent alimenter CAP-CORE-017 après validation humaine ou règle explicite.

---

## 22. Analyse de blast radius pour incident

CAP-CORE-018 peut demander :

```text
quels nœuds pourraient être affectés par INC-... ?
```

L’Atlas utilise :

- actifs explicitement affectés ;
- relations actives et fraîches ;
- profondeur bornée ;
- types de dépendances autorisés ;
- realm de l’incident ;
- classification du demandeur.

Le résultat est attaché à l’incident comme référence, pas recopié intégralement si sensible.

---

## 23. Requêtes historiques

Support minimal :

```text
état du nœud à une date
relations actives à une date
version de contrat à une date
endpoint déclaré à une date
chemin connu à une date
```

Règles :

- date UTC ;
- données réellement historisées seulement ;
- aucune interpolation silencieuse ;
- résultat indique les lacunes ;
- profondeur et volume bornés.

---

## 24. Vues publiques

Une projection publique est préparée à partir de champs explicitement approuvés.

Processus :

```text
proposition de projection
→ validation politique
→ décision si nécessaire
→ preuve CAP-CORE-015
→ activation
→ publication
```

La vue publique ne contient pas :

- topologie interne ;
- dépendances sensibles ;
- endpoints privés ;
- incidents non publiés ;
- risques ;
- exceptions ;
- noms personnels non autorisés ;
- versions vulnérables ;
- données de sécurité.

---

## 25. Export d’Atlas

Formats autorisés :

```text
JSON canonique
CSV borné pour Directory
paquet signé pour instantané
```

Un export de graphe doit préciser :

```text
portée
profil de visibilité
instant de coupure
nombre de nœuds
nombre de relations
filtres
fraîcheur
empreinte
preuve
```

Pas d’export brut universel de tout l’Atlas.

---

## 26. Commandes Artisan minimales

```text
core:atlas:migrer
core:atlas:bootstrap
core:atlas:collecter {source?}
core:atlas:reconcilier {source?}
core:atlas:diagnostiquer
core:atlas:verifier
core:atlas:instantane
core:atlas:purger-observations
core:atlas:rejouer-evenements
```

Chaque commande :

- supporte `--no-interaction` ;
- retourne un code non nul en échec ;
- ne révèle aucun secret ;
- produit un résumé machine-readable facultatif ;
- refuse PostgreSQL absent en production ;
- audite les opérations sensibles ;
- est idempotente lorsque pertinent.

---

## 27. Commande `core:atlas:diagnostiquer`

Sortie minimale :

```text
magasin disponible
migration courante
collecteurs actifs
sources fraîches
sources périmées
collectes échouées
divergences ouvertes
entrées incomplètes
relations orphelines
endpoints invalides
checkpoints événements
instantané récent
preuve vérifiable
```

Un diagnostic rend compte de la panne ; il ne doit pas échouer sans rapport parce qu’une source est indisponible.

---

## 28. Commande `core:atlas:verifier`

Vérifie :

- références uniques ;
- révisions chaînées correctement ;
- relations sans orphelin ;
- types canoniques ;
- contrats existants ;
- realms valides ;
- classifications cohérentes ;
- absence de secrets ;
- empreintes ;
- instantanés ;
- checkpoints monotones ;
- cohérence des cycles ;
- rétentions.

La vérification ne répare rien automatiquement par défaut.

---

## 29. Intégration au bootstrap fondation

Mettre à jour :

```text
php artisan core:fondation:migrer
```

pour intégrer le magasin Directory & Atlas après validation de ses prérequis.

Ordre logique de connexion :

```text
registres souverains disponibles
→ migration Atlas
→ collecteurs enregistrés
→ bootstrap
→ réconciliation
→ readiness
```

Une erreur Atlas ne doit pas corrompre les autres magasins.

---

## 30. Readiness

La readiness CAP-CORE-020 est verte seulement si :

- magasin accessible ;
- migration courante ;
- schémas de descripteurs valides ;
- collecteurs critiques configurés ;
- dernière réconciliation critique dans le seuil ;
- aucun checkpoint incohérent ;
- aucune corruption détectée ;
- politique de visibilité disponible ;
- preuve de dernier instantané vérifiable si exigée.

Une source métier momentanément indisponible peut rendre l’Atlas `DEGRADE` ou non prêt selon sa criticité, jamais artificiellement sain.

---

## 31. Gestion des dépendances indisponibles

### CAP-CORE-014 indisponible

Continuer à servir les projections existantes avec fraîcheur visible, puis réconcilier. Ne pas inventer de nouveaux événements.

### Registre souverain indisponible

Conserver la dernière projection, la marquer selon fraîcheur et ouvrir un diagnostic. Ne pas retirer les entrées.

### CAP-CORE-015 indisponible

Servir les lectures autorisées mais refuser les nouveaux instantanés signés et exports exigeant une preuve.

### CAP-CORE-004 indisponible

Refuser les vues protégées. Les projections publiques déjà signées peuvent rester accessibles selon politique.

### CAP-CORE-010 indisponible

Refuser l’ingestion de nouveaux types non validables. Continuer les lectures existantes bornées si la projection est valide.

---

## 32. Préparation de CAP-CORE-021

L’Atlas expose au Matching uniquement :

```text
références de produits
capacités disponibles
contrats actifs
sources et finalités autorisées
realms
relations structurelles
fraîcheur
classification
```

Il n’expose pas :

- dossiers métier complets ;
- profils utilisateurs ;
- historique comportemental ;
- scores ;
- recommandations ;
- résultats de matching précédents ;
- secrets.

Le Matching doit accéder aux signaux métier par leurs propres contrats et autorisations.

---

## 33. Règle d’arrêt du chantier

Après livraison, tests, CI et rapport `GO` de CAP-CORE-020 :

- mettre à jour la fiche finale ;
- enregistrer les preuves ;
- indiquer les collecteurs réellement raccordés ;
- documenter les sources encore absentes ;
- s’arrêter.

Ne pas commencer le Matching dans la même PR.