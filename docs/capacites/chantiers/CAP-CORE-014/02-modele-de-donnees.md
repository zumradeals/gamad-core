# CAP-CORE-014 — MODÈLE DE DONNÉES ET INVARIANTS

Cette partie complète `01-fiche-de-codage.md`.

---

## 1. Magasin central

Créer un magasin persistant distinct :

```text
EVENT_JOURNAL_URL
EVENT_JOURNAL_PATH
GAMAD_EVENT_DRIVER
```

Règles :

- PostgreSQL obligatoire en production ;
- SQLite autorisé en local et en CI ;
- aucun repli silencieux vers SQLite en production ;
- aucune lecture du magasin d’audit comme substitut ;
- aucune utilisation de l’index reconstructible ;
- configuration Laravel mise en cache supportée ;
- chemin de test toujours explicite et isolé ;
- migrations additives et versionnées ;
- aucune migration pendant une requête de consommation.

Ajouter le magasin à :

- `apps/console-laravel/config/database.php` ;
- `.env.example` ;
- `core:fondation:migrer` ;
- `core:fondation:importer-sqlite` ;
- readiness ;
- sauvegarde ;
- restauration ;
- copie hors machine ;
- exercice PostgreSQL réel.

Ne pas ajouter un nouveau compteur de magasins codé en dur. Corriger les tests et scripts afin qu’ils découvrent ou énumèrent explicitement les cibles sans constante fragile.

---

## 2. Enveloppe canonique

Tout événement accepté dans le journal commun possède une enveloppe stable.

Champs minimaux :

```json
{
  "reference": "EVT-GAMAD-...",
  "sequence": 12345,
  "type": "PRODUIT_SUSPENDU",
  "contrat_reference": "EVT-GAMAD-PRODUIT-SUSPENDU",
  "contrat_version": "1.0.0",
  "producteur_capacite": "CAP-CORE-011",
  "producteur_produit": null,
  "source_reference": "SRC-...",
  "realm_reference": "RLM-...",
  "finalite_reference": "FINALITE-...",
  "sujet_type": "PRODUIT",
  "sujet_reference": "PRD-GAMAD-003",
  "correlation_id": "COR-...",
  "causation_reference": "EVT-... ou CMD-...",
  "survenu_le": "2026-08-02T12:00:00Z",
  "enregistre_le": "2026-08-02T12:00:01Z",
  "classification": "INTERNE",
  "schema_empreinte": "sha256...",
  "charge_empreinte": "sha256...",
  "reconstruction": false
}
```

Règles :

- `reference` unique et immuable ;
- `sequence` monotone dans le journal central ;
- `type` canonique livré par `CAP-CORE-010` ;
- contrat actif de type `EVENEMENT` dans `CAP-CORE-009` ;
- producteur déclaré dans la version du contrat ;
- source active ;
- realm actif ;
- finalité explicite ;
- sujet minimal ;
- corrélation obligatoire ;
- date du fait distincte de la date d’enregistrement ;
- empreinte du schéma active ;
- empreinte de la charge utile ;
- aucune information secrète dans l’enveloppe ;
- aucun nom ou adresse personnelle dans les champs libres.

Le champ `reconstruction` vaut `true` uniquement pour un événement de reprise explicitement créé à partir d’un état connu. Il ne doit jamais faire croire qu’un événement historique a été observé en temps réel.

---

## 3. Table centrale `evenement_commun`

Champs minimaux :

- `sequence_id` ;
- `reference` ;
- `type_evenement` ;
- `contrat_reference` ;
- `contrat_version` ;
- `producteur_capacite_reference` nullable ;
- `producteur_produit_reference` nullable ;
- `source_reference` ;
- `realm_reference` ;
- `finalite_reference` ;
- `sujet_type` nullable ;
- `sujet_reference` nullable ;
- `correlation_id` ;
- `causation_reference` nullable ;
- `idempotence_reference` ;
- `survenu_le` ;
- `enregistre_le` ;
- `classification` ;
- `schema_empreinte` ;
- `charge_empreinte` ;
- `empreinte_precedente` nullable ;
- `empreinte_evenement` ;
- `reconstruction` ;
- `charge_expire_le` nullable.

Contraintes :

- `reference` unique ;
- `idempotence_reference` unique dans le périmètre du producteur ;
- exactement un producteur principal : capacité ou produit ;
- `survenu_le <= enregistre_le` sauf tolérance d’horloge documentée ;
- `classification` issue du vocabulaire canonique ;
- ajout seul ;
- UPDATE et DELETE refusés par trigger ;
- empreinte chaînée comme preuve interne de cohérence, sans prétendre à une signature ;
- aucune charge utile complète dans cette table.

La chaîne du journal de diffusion est distincte de la chaîne du journal d’audit.

---

## 4. Table `evenement_charge`

La charge utile est séparée de l’enveloppe afin de permettre une conservation différente.

Champs minimaux :

- `evenement_reference` ;
- `media_type` ;
- `schema_format` ;
- `charge_json` ;
- `empreinte` ;
- `taille_octets` ;
- `cree_le` ;
- `expire_le` nullable.

Règles :

- une seule charge par événement dans la version initiale ;
- JSON canonique ;
- validation contre le schéma actif du contrat ;
- taille maximale configurable et bornée ;
- rejet de secrets, jetons, mots de passe, cookies, clés privées et codes de secours ;
- rejet de champs non prévus par le schéma ;
- rejet des données personnelles non nécessaires à la finalité ;
- empreinte égale à `evenement_commun.charge_empreinte` ;
- aucune modification après insertion.

### Conservation et purge

L’enveloppe et son empreinte restent en ajout seul.

La charge peut être supprimée après expiration uniquement par une commande de purge gouvernée, lorsque le contrat le permet.

La purge :

- supprime seulement la ligne `evenement_charge` ;
- conserve l’enveloppe, l’empreinte, le contrat, les dates et les références ;
- écrit une trace `CHARGE_EVENEMENT_PURGEE` dans `CAP-CORE-013` ;
- ne rend jamais une charge expirée de nouveau accessible ;
- n’est pas utilisée pour corriger un événement erroné.

Une correction se fait par un nouvel événement contractuel.

---

## 5. Outbox transactionnelle dans chaque magasin producteur

Chaque capacité raccordée doit posséder une table d’outbox dans le même magasin que son état métier.

Nom recommandé :

```text
evenement_sortant
```

Champs minimaux :

- `id` ;
- `idempotence_reference` ;
- `type_evenement` ;
- `contrat_reference` ;
- `contrat_version` ;
- `producteur_capacite_reference` nullable ;
- `producteur_produit_reference` nullable ;
- `source_reference` ;
- `realm_reference` ;
- `finalite_reference` ;
- `sujet_type` nullable ;
- `sujet_reference` nullable ;
- `correlation_id` ;
- `causation_reference` nullable ;
- `survenu_le` ;
- `classification` ;
- `charge_json` ;
- `schema_empreinte` ;
- `charge_empreinte` ;
- `etat` ;
- `tentatives` ;
- `prochaine_tentative_le` nullable ;
- `derniere_erreur_code` nullable ;
- `evenement_reference` nullable ;
- `cree_le` ;
- `publie_le` nullable.

États :

- `EN_ATTENTE` ;
- `EN_COURS` ;
- `PUBLIE` ;
- `ECHEC_TEMPORAIRE` ;
- `ECHEC_DEFINITIF`.

Invariants :

- la ligne d’outbox est créée dans la même transaction que la modification métier ;
- une transaction métier annulée ne laisse aucune outbox ;
- la publication n’est jamais effectuée avant commit ;
- le relais peut rejouer une ligne sans créer deux événements communs ;
- `idempotence_reference` est unique dans le magasin producteur ;
- `PUBLIE` exige `evenement_reference` ;
- la charge n’est plus modifiable après commit ;
- aucune suppression physique avant la politique de rétention ;
- une erreur de transport n’annule pas rétrospectivement le fait métier déjà commis ;
- un retard de publication est visible et alertable.

### Pourquoi l’outbox est obligatoire

Une écriture métier et une insertion directe dans un autre magasin PostgreSQL ne forment pas une transaction atomique.

Le modèle correct est :

```text
transaction métier
  → état métier
  → ligne d’outbox dans le même magasin
commit
  → relais asynchrone
  → journal commun
```

Ne pas simuler une atomicité distribuée inexistante.

---

## 6. Table `recu_publication`

Le magasin central doit conserver le lien entre l’intention productrice et l’événement accepté.

Champs :

- `producteur_reference` ;
- `idempotence_reference` ;
- `evenement_reference` ;
- `sequence_id` ;
- `accepte_le`.

Contrainte unique :

```text
(producteur_reference, idempotence_reference)
```

Le rejeu d’une même outbox retourne le même événement sans créer une nouvelle séquence.

---

## 7. Table `abonnement_evenement`

Champs minimaux :

- `reference` ;
- `nom` ;
- `consommateur_capacite_reference` nullable ;
- `consommateur_produit_reference` nullable ;
- `organisation_reference` nullable ;
- `realm_reference` ;
- `finalite_reference` ;
- `mode_livraison` ;
- `taille_lot_max` ;
- `duree_bail_secondes` ;
- `tentatives_max` ;
- `cree_par_reference` ;
- `source_reference` ;
- `cree_le`.

Modes initiaux :

- `PULL_API` obligatoire ;
- `PUSH_HTTPS` facultatif et non requis pour `GO` ;
- aucun mode arbitraire provenant d’un texte libre.

Contraintes :

- exactement un consommateur principal : capacité ou produit ;
- consommateur déclaré dans le contrat ;
- organisation exploitante cohérente lorsqu’elle est fournie ;
- realm actif ;
- finalité autorisée ;
- tailles et durées bornées ;
- aucune URL avec identifiants dans cette table ;
- aucun secret.

---

## 8. Table `abonnement_cycle`

Champs :

- `id` ;
- `abonnement_reference` ;
- `etat` ;
- `date_effet` ;
- `motif` ;
- `acteur_reference` ;
- `politique_reference` ;
- `preuve_reference` ;
- `correlation_id` ;
- `cree_le`.

États :

- `PREPARATION` ;
- `ACTIF` ;
- `SUSPENDU` ;
- `RETIRE`.

Règles :

- ajout seul ;
- activation explicite ;
- suspension immédiatement opposable ;
- retrait irréversible ;
- un abonnement suspendu ne reçoit pas de nouveaux baux ;
- ses livraisons non accusées restent traçables.

---

## 9. Tables de filtres fermés

Créer des tables relationnelles, pas une expression libre :

### `abonnement_type_evenement`

- abonnement ;
- contrat ;
- version minimale ou version exacte selon contrat ;
- type d’événement.

### `abonnement_producteur`

- abonnement ;
- capacité ou produit producteur.

### `abonnement_realm`

- abonnement ;
- realm autorisé ;
- portée `EXACT` ou `DESCENDANTS_EXPLICITES`.

Règles :

- aucun SQL libre ;
- aucune regex fournie par l’utilisateur ;
- aucun wildcard universel implicite ;
- `*` interdit dans une référence ;
- un abonnement sans type d’événement ne reçoit rien ;
- un realm parent ne reçoit pas automatiquement ses enfants ;
- les descendants doivent être résolus et enregistrés explicitement.

---

## 10. Table `livraison_evenement`

Une ligne représente la disponibilité d’un événement pour un abonnement.

Champs minimaux :

- `reference` ;
- `abonnement_reference` ;
- `evenement_reference` ;
- `sequence_evenement` ;
- `etat` ;
- `disponible_le` ;
- `bail_reference` nullable ;
- `bail_expire_le` nullable ;
- `tentatives` ;
- `prochaine_tentative_le` nullable ;
- `accuse_le` nullable ;
- `dernier_code_erreur` nullable ;
- `cree_le`.

États :

- `DISPONIBLE` ;
- `SOUS_BAIL` ;
- `ACCUSE` ;
- `A_REESSAYER` ;
- `LETTRE_MORTE` ;
- `ANNULE`.

Contraintes :

- couple `(abonnement_reference, evenement_reference)` unique ;
- un événement peut produire plusieurs livraisons, une par abonnement autorisé ;
- un bail expiré redevient disponible ;
- un accusé est terminal ;
- une lettre morte n’est pas supprimée ;
- une livraison annulée exige un motif et une trace.

---

## 11. Table `tentative_livraison`

Champs :

- `id` ;
- `livraison_reference` ;
- `numero` ;
- `type_tentative` ;
- `resultat` ;
- `code_erreur` nullable ;
- `detail_sanitaire` nullable ;
- `commence_le` ;
- `termine_le` nullable.

Résultats :

- `MISE_A_DISPOSITION` ;
- `BAIL_ACCORDE` ;
- `ACCUSE` ;
- `REFUS_TEMPORAIRE` ;
- `REFUS_DEFINITIF` ;
- `BAIL_EXPIRE` ;
- `ERREUR_TRANSPORT`.

`detail_sanitaire` ne doit contenir ni payload, ni secret, ni réponse brute d’un satellite.

---

## 12. Table `curseur_abonnement`

Champs :

- `abonnement_reference` ;
- `derniere_sequence_contigue_accusee` ;
- `mis_a_jour_le`.

Règles :

- le curseur avance uniquement sur une suite contiguë de livraisons terminales acceptées selon la politique ;
- un accusé hors ordre ne saute pas silencieusement une livraison précédente ;
- les livraisons individuelles restent la source de vérité ;
- le curseur sert à l’efficacité et à la reprise ;
- aucune modification manuelle sans commande gouvernée.

---

## 13. Table `demande_rejeu`

Champs :

- `reference` ;
- `abonnement_reference` ;
- `sequence_debut` nullable ;
- `sequence_fin` nullable ;
- `date_debut` nullable ;
- `date_fin` nullable ;
- `types_json` ;
- `motif` ;
- `etat` ;
- `demandeur_reference` ;
- `politique_reference` ;
- `preuve_reference` ;
- `correlation_id` ;
- `cree_le` ;
- `termine_le` nullable.

États :

- `DEMANDEE` ;
- `VALIDEE` ;
- `EN_COURS` ;
- `TERMINEE` ;
- `REFUSEE` ;
- `ANNULEE`.

Règles :

- bornes obligatoires ;
- aucune demande « depuis toujours » implicite ;
- taille maximale ;
- autorisation explicite ;
- aucune duplication si une livraison existe déjà, sauf mode de rejeu explicite ;
- toute livraison issue d’un rejeu est marquée comme telle dans ses métadonnées ;
- un rejeu ne modifie pas l’événement d’origine.

---

## 14. Lettre morte

La lettre morte peut être matérialisée par l’état terminal de la livraison et une table d’historique dédiée :

### `lettre_morte_evenement`

- `reference` ;
- `livraison_reference` ;
- `raison_code` ;
- `tentatives_total` ;
- `premiere_erreur_le` ;
- `derniere_erreur_le` ;
- `cree_le` ;
- `relancee_le` nullable ;
- `relancee_par_reference` nullable.

Règles :

- aucun payload dupliqué ;
- référence vers l’événement original ;
- relance gouvernée ;
- relance créant une nouvelle livraison ou réouvrant l’ancienne selon invariant choisi, jamais une mutation silencieuse ;
- historique complet.

---

## 15. Index et partitions

Ajouter au minimum des index sur :

- séquence ;
- référence ;
- type ;
- contrat/version ;
- producteur ;
- realm ;
- sujet ;
- corrélation ;
- date du fait ;
- abonnement/état ;
- événement/abonnement ;
- bail expirant ;
- prochaine tentative ;
- lettres mortes ;
- demandes de rejeu.

Préparer une stratégie de partitionnement PostgreSQL par période seulement si les tests réels le justifient.

Ne pas introduire un partitionnement complexe non éprouvé dans la première version.

---

## 16. Immutabilité et intégrité

Tables strictement en ajout seul :

- `evenement_commun` ;
- `tentative_livraison` ;
- `abonnement_cycle` ;
- historique de rejeu ;
- lettre morte historique.

Tables à mutation contrôlée :

- outbox ;
- livraison ;
- curseur ;
- charge utile lors de la purge ;
- demande de rejeu.

Toute mutation contrôlée doit :

- passer par un service métier ;
- utiliser une transaction ;
- vérifier l’état attendu ;
- produire une trace d’audit ;
- être protégée contre la concurrence.

L’empreinte SHA-256 prouve la cohérence interne, pas l’identité cryptographique du producteur.

Ne pas annoncer `signee: true` avant livraison réelle de `CAP-CORE-015` et `CAP-CORE-016`.
