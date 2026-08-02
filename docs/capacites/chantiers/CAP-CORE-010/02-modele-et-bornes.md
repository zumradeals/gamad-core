Créer :

```text
core/registre-vocabulaire/
├── README.md
├── resources/
│   └── bootstrap-vocabulaire-v1.json
├── src/
│   ├── Magasin.php
│   ├── SchemaVocabulaire.php
│   ├── RegistreVocabulaire.php
│   ├── ValidateurTerme.php
│   ├── AnalyseurCompatibilite.php
│   ├── GenerateurProjection.php
│   ├── PolitiqueVocabulaire.php
│   └── ExceptionVocabulaire.php
└── tests/
    └── vocabulaire_p3.php
```

Variables proposées :

```text
VOCABULARY_REGISTRY_URL
VOCABULARY_REGISTRY_PATH
GAMAD_VOCABULARY_DRIVER
```

Règles :

- PostgreSQL obligatoire en production ;
- SQLite autorisé en local et CI ;
- aucun fallback silencieux en production ;
- magasin distinct ;
- configuration Laravel mise en cache supportée ;
- chemin de test explicite et isolé ;
- aucune migration pendant une lecture HTTP ;
- aucun bootstrap pendant une décision métier.

Raccorder le magasin à :

- `config/database.php` ;
- `.env.example` ;
- `core:fondation:migrer` ;
- readiness ;
- import SQLite ;
- sauvegarde ;
- restauration ;
- copie hors machine ;
- CI PostgreSQL.

---

## 11. Modèle de données minimal

Toutes les tables d’historique sont en ajout seul.

### 11.1 Table `vocabulaire`

Champs minimaux :

- `reference` ;
- `namespace` ;
- `nom` ;
- `domaine` ;
- `proprietaire_reference` ;
- `source_reference` ;
- `portee` ;
- `description` nullable ;
- `cree_le` ;
- `modifie_le`.

Portées :

- `CORE` ;
- `ECOSYSTEME` ;
- `CONTRAT` ;
- `CAPACITE` ;
- `PRODUIT_PARTAGE`.

Contraintes :

- référence unique et immuable ;
- namespace unique et stable ;
- propriétaire connu ;
- source active ;
- aucun secret ;
- aucun code exécutable.

### 11.2 Table `vocabulaire_version`

Champs minimaux :

- `id` ;
- `vocabulaire_reference` ;
- `version` ;
- `schema_version` ;
- `date_effet_prevue` nullable ;
- `empreinte_contenu` ;
- `cree_par_reference` ;
- `cree_le`.

Contraintes :

- couple vocabulaire/version unique ;
- contenu immuable après soumission ;
- empreinte canonique ;
- aucune suppression physique.

### 11.3 Table `vocabulaire_version_cycle`

Champs :

- `id` ;
- `vocabulaire_version_id` ;
- `etat` ;
- `date_effet` ;
- `motif` ;
- `acteur_reference` ;
- `politique_reference` ;
- `preuve_reference` ;
- `correlation_id` ;
- `cree_le`.

États :

- `BROUILLON` ;
- `EN_VALIDATION` ;
- `ACTIVE` ;
- `DEPRECIEE` ;
- `REMPLACEE` ;
- `RETIREE`.

Règles :

- ajout seul ;
- une version active par défaut ;
- coexistence explicite uniquement ;
- activation atomique ;
- version retirée jamais réactivée.

### 11.4 Table `terme`

Champs minimaux :

- `reference` ;
- `vocabulaire_version_id` ;
- `code` ;
- `definition` ;
- `type_semantique` ;
- `ordre_affichage` nullable ;
- `date_debut` ;
- `date_fin` nullable ;
- `remplace_par_reference` nullable ;
- `cree_le`.

Types sémantiques initiaux :

- `TYPE` ;
- `ETAT` ;
- `ACTION` ;
- `FINALITE` ;
- `RELATION` ;
- `NIVEAU` ;
- `RESULTAT` ;
- `ROLE` ;
- `CATEGORIE` ;
- `ERREUR` ;
- `ENVIRONNEMENT` ;
- `CLASSIFICATION`.

Contraintes :

- référence stable ;
- code unique dans une version ;
- définition obligatoire ;
- aucune expression exécutable ;
- aucune réutilisation d’un code retiré avec un autre sens ;
- un remplacement explicite ne réécrit pas l’historique.

### 11.5 Table `terme_libelle`

Champs :

- `id` ;
- `terme_reference` ;
- `locale` ;
- `libelle` ;
- `description_courte` nullable ;
- `principal` ;
- `cree_le`.

Contraintes :

- un libellé principal par locale ;
- locale normalisée ;
- libellé non utilisé comme identifiant machine ;
- changement de traduction sans changement de code.

### 11.6 Table `terme_alias`

Champs :

- `id` ;
- `terme_reference` ;
- `alias` ;
- `locale` nullable ;
- `type_alias` ;
- `date_debut` ;
- `date_fin` nullable ;
- `source_reference` ;
- `cree_le`.

Types :

- `ANCIEN_CODE` ;
- `LIBELLE` ;
- `ABREVIATION` ;
- `CODE_EXTERNE` ;
- `ORTHOGRAPHE_HISTORIQUE`.

Règles :

- alias explicite ;
- aucune résolution floue ;
- aucune permission basée sur un alias humain ;
- ambiguïté interdite dans un même contexte ;
- les anciens codes de sécurité doivent être mappés explicitement.

### 11.7 Table `terme_relation`

Champs :

- `id` ;
- `terme_source_reference` ;
- `terme_cible_reference` ;
- `type_relation` ;
- `date_effet` ;
- `preuve_reference` ;
- `cree_le`.

Relations :

- `PLUS_LARGE_QUE` ;
- `PLUS_ETROIT_QUE` ;
- `EQUIVALENT_EXPLICITE` ;
- `REMPLACE` ;
- `ASSOCIE_A` ;
- `INCOMPATIBLE_AVEC`.

Règles :

- aucune auto-relation ;
- cycles hiérarchiques interdits ;
- équivalence jamais inférée ;
- relation de remplacement directionnelle ;
- aucune relation ne crée une autorisation.

### 11.8 Table `terme_mapping_externe`

Champs :

- `id` ;
- `terme_reference` ;
- `systeme_reference` ;
- `vocabulaire_externe` ;
- `code_externe` ;
- `sens` ;
- `statut_mapping` ;
- `date_debut` ;
- `date_fin` nullable ;
- `preuve_reference` ;
- `cree_le`.

Sens :

- `ENTRANT` ;
- `SORTANT` ;
- `BIDIRECTIONNEL`.

Statuts :

- `EXACT` ;
- `APPROXIMATIF` ;
- `PERTE_INFORMATION` ;
- `INTERDIT`.

Règles :

- un mapping approximatif ne doit pas être utilisé pour une décision de sécurité ;
- une perte d’information doit être visible ;
- aucun mapping implicite par égalité de libellé ;
- système externe connu dans `CAP-CORE-011` ou par contrat actif.

### 11.9 Table `terme_usage`

Champs :

- `id` ;
- `terme_reference` ;
- `capacite_reference` nullable ;
- `contrat_reference` nullable ;
- `contrat_version` nullable ;
- `politique_reference` nullable ;
- `produit_reference` nullable ;
- `usage_type` ;
- `obligatoire` ;
- `date_debut` ;
- `date_fin` nullable ;
- `cree_le`.

Types d’usage :

- `ENTREE` ;
- `SORTIE` ;
- `REGLE` ;
- `ETAT_PERSISTE` ;
- `AFFICHAGE` ;
- `MAPPING` ;
- `EVENEMENT` ;
- `SIGNAL`.

Cette table permet de calculer l’impact d’une dépréciation ou d’un retrait.

### 11.10 Table `vocabulaire_conformite`

Champs :

- `reference` ;
- `vocabulaire_version_id` ;
- `consommateur_reference` ;
- `type_consommateur` ;
- `resultat` ;
- `commit_reference` nullable ;
- `rapport_resume_json` ;
- `execute_le` ;
- `expire_le` nullable.

Résultats :

- `CONFORME` ;
- `NON_CONFORME` ;
- `INCOMPLET`.

### 11.11 Table `vocabulaire_projection`

Champs :

- `id` ;
- `vocabulaire_version_id` ;
- `type_projection` ;
- `chemin_artefact` nullable ;
- `contenu_json` nullable ;
- `empreinte_artefact` ;
- `generee_le` ;
- `cree_le`.

Types :

- `JSON` ;
- `PHP_CONSTANTS` ;
- `OPENAPI_ENUM` ;
- `SQL_CHECK` ;
- `DOCUMENTATION`.

Une projection n’est jamais modifiée en place.

---

## 12. Principe essentiel : registre canonique sans comportement dynamique incontrôlé

Le registre ne doit pas permettre qu’un administrateur ajoute un mot et modifie immédiatement le comportement d’un module qui ne sait pas le traiter.

Distinguer :

```text
TERME ACTIF DANS LE VOCABULAIRE
```

et :

```text
TERME SUPPORTÉ PAR UN CONSOMMATEUR PRÉCIS
```

Un terme peut être actif sémantiquement sans être encore supporté par toutes les capacités.

Pour qu’un terme soit utilisable par un contrat ou une capacité :

1. le terme existe dans une version active ;
2. son usage est déclaré ;
3. le consommateur déclare la version supportée ;
4. une conformité est verte ;
5. le contrat actif référence le terme ou le vocabulaire ;
6. les bornes locales du code l’acceptent.

Aucun élargissement automatique des valeurs de sécurité n’est autorisé.

Exemples :
