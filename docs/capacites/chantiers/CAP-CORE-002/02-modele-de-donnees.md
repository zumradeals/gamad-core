- `personnalite_juridique` ;
- `proprietaire_reference` ;
- `source_reference` ;
- `politique_inscription_reference` ;
- `preuve_reference` ;
- `cree_par_reference` ;
- `cree_le` ;
- `modifie_le`.

Contraintes :

- `reference` unique et immuable ;
- `identite_reference` unique ;
- l’identité doit exister dans `CAP-CORE-001` ;
- l’identité doit être de type `organisation` ;
- aucune référence retirée n’est réattribuée ;
- type issu de `CAP-CORE-010` ;
- source active ;
- aucun secret ;
- aucune donnée RH détaillée.

Format recommandé :

```text
ORG-GAMAD-000001
```

Ne pas modifier la référence d’identité `IDN-ORG-*`.

La référence `ORG-*` identifie la fiche organisationnelle ; la référence `IDN-ORG-*` identifie l’identité canonique.

### 11.2 Table `organisation_revision`

Champs minimaux :

- `id` ;
- `organisation_reference` ;
- `numero_revision` ;
- `denomination_officielle` ;
- `nom_court` nullable ;
- `nom_commercial` nullable ;
- `description` nullable ;
- `forme_reference` nullable ;
- `classification_reference` ;
- `date_effet` ;
- `acteur_reference` ;
- `source_reference` ;
- `preuve_reference` ;
- `correlation_id` ;
- `cree_le`.

Règles :

- ajout seul ;
- numéro unique par organisation ;
- aucune réécriture d’une ancienne dénomination ;
- la lecture courante utilise la dernière révision applicable ;
- les libellés n’accordent aucun droit.

### 11.3 Table `organisation_cycle`

Champs minimaux :

- `id` ;
- `organisation_reference` ;
- `etat_reference` ;
- `date_effet` ;
- `motif` nullable ;
- `acteur_reference` ;
- `politique_reference` ;
- `preuve_reference` ;
- `correlation_id` ;
- `cree_le`.

États initiaux à prendre dans `CAP-CORE-010` :

- `PREPARATION` ;
- `ACTIVE` ;
- `SUSPENDUE` ;
- `DISSOUTE` ;
- `RETIREE`.

Règles :

- ajout seul ;
- `DISSOUTE` et `RETIREE` sont terminaux ;
- une organisation suspendue ne peut recevoir de nouvelle affiliation opposable ;
- une organisation dissoute reste consultable historiquement ;
- l’état de l’identité dans `CAP-CORE-001` reste une condition supplémentaire ;
- aucun automatisme silencieux entre les deux magasins.

### 11.4 Table `organisation_identifiant_externe`

Champs minimaux :

- `id` ;
- `organisation_reference` ;
- `systeme_reference` ;
- `type_identifiant_reference` ;
- `valeur_normalisee` ;
- `valeur_affichage` nullable ;
- `pays_ou_realm_reference` nullable ;
- `date_debut` ;
- `date_fin` nullable ;
- `verifie` ;
- `source_reference` ;
- `preuve_reference` ;
- `cree_le`.

Règles :

- unicité selon système, type et valeur normalisée ;
- aucune pièce justificative complète ;
- aucune donnée secrète ;
- un identifiant fermé reste dans l’historique ;
- une valeur non vérifiée doit être explicitement marquée ;
- le registre ne prétend pas valider juridiquement un identifiant sans source compétente.

### 11.5 Table `organisation_unite`

Champs minimaux :

- `reference` ;
- `organisation_reference` ;
- `unite_parente_reference` nullable ;
- `type_unite_reference` ;
- `nom` ;
- `code_interne` nullable ;
- `realm_reference` nullable ;
- `classification_reference` ;
- `date_debut` ;
- `source_reference` ;
- `preuve_reference` ;
- `cree_le`.

Exemples de types :

- siège ;
- direction ;
- département ;
- service ;
- agence ;
- établissement ;
- succursale ;
- projet ;
- comité.

Les références exactes viennent de `CAP-CORE-010`.

Règles :

- hiérarchie acyclique ;
- une unité appartient à une seule organisation ;
- une unité parente appartient à la même organisation ;
- aucune unité ne constitue automatiquement une identité canonique ;
- la fermeture ne supprime pas l’unité.

### 11.6 Table `organisation_unite_cycle`

Champs :

- `id` ;
- `unite_reference` ;
- `etat_reference` ;
- `date_effet` ;
- `motif` nullable ;
- `acteur_reference` ;
- `preuve_reference` ;
- `correlation_id` ;
- `cree_le`.

États initiaux :

- `ACTIVE` ;
- `SUSPENDUE` ;
- `FERMEE`.

### 11.7 Table `organisation_relation`

Cette table représente les relations organisation-à-organisation.

Champs minimaux :

- `reference` ;
- `organisation_source_reference` ;
- `organisation_cible_reference` ;
- `type_relation_reference` ;
- `date_debut` ;
- `date_fin` nullable ;
- `pourcentage` nullable ;
- `classification_reference` ;
- `source_reference` ;
- `preuve_reference` ;
- `acteur_reference` ;
- `cree_le`.

Exemples de types :

- `PARENTE_DE` ;
- `FILIALE_DE` ;
- `AFFILIEE_A` ;
- `MEMBRE_DE` ;
- `PARTENAIRE_DE` ;
- `OPERE_POUR`.

Les codes exacts doivent venir du vocabulaire canonique.

Règles :

- source et cible distinctes ;
- organisations existantes ;
- cycle interdit pour les relations hiérarchiques ;
- pourcentage facultatif et borné de 0 à 100 ;
- aucune relation ne crée automatiquement un droit d’accès ;
- aucune relation ne crée automatiquement une représentation.

### 11.8 Table `organisation_affiliation`

Cette table représente le rattachement d’une identité à une organisation.

Champs minimaux :

- `reference` ;
- `organisation_reference` ;
- `identite_reference` ;
- `unite_reference` nullable ;
- `type_affiliation_reference` ;
- `date_debut` ;
- `date_fin_prevue` nullable ;
- `niveau_assurance_reference` ;
- `classification_reference` ;
- `source_reference` ;
- `preuve_reference` ;
- `producteur_reference` ;
- `acteur_reference` ;
- `cree_le`.

Affiliations initiales à migrer depuis les codes existants :

- membre ;
- employé ;
- dirigeant ;
- représentant ;
- bénéficiaire ;
- client ;
- fournisseur ;
- partenaire ;
- contact autorisé.

Les références exactes viennent de `CAP-CORE-010`.

Règles :

- identité connue ;
- organisation active ;
- unité appartenant à l’organisation ;
- aucune affiliation universelle implicite ;
- une affiliation ne vaut pas mandat ;
- `DIRIGEANT` et `REPRESENTANT` restent descriptifs tant que `CAP-CORE-003` ne confirme pas un mandat ;
- une affiliation peut concerner une personne ou une organisation selon le vocabulaire ;
- aucune affiliation ne crée une session ou un rôle applicatif.

### 11.9 Table `organisation_affiliation_cycle`

Champs :

- `id` ;
- `affiliation_reference` ;
- `etat_reference` ;
- `date_effet` ;
- `motif` nullable ;
- `acteur_reference` ;
- `politique_reference` ;
- `preuve_reference` ;
- `correlation_id` ;
- `cree_le`.

États initiaux :

- `PROPOSEE` ;
- `ACTIVE` ;
- `SUSPENDUE` ;
- `CLOSE` ;
- `REJETEE`.

Règles :

- ajout seul ;
- aucune suppression physique ;
- une affiliation clôturée n’est plus opposable ;
- une proposition n’est pas une affiliation active ;
- fermeture datée.

### 11.10 Table `organisation_fonction_interne`

Champs minimaux :

- `reference` ;
- `organisation_reference` ;
- `unite_reference` nullable ;
- `type_fonction_reference` ;
- `libelle` ;
- `date_debut` ;
- `date_fin` nullable ;
- `source_reference` ;
- `preuve_reference` ;
- `cree_le`.

Règles :

- fonction descriptive uniquement ;
- aucun droit automatique ;
- aucun mandat automatique ;
- pour devenir opposable, un mandat de `CAP-CORE-003` doit référencer la fonction ou une projection contractuelle compatible.

### 11.11 Table `organisation_mandat_projection`

Cette table est une projection locale facultative, jamais la source de vérité.

Champs possibles :

- `mandat_reference` ;
- `organisation_reference` ;
- `identite_reference` ;
- `fonction_interne_reference` nullable ;
- `etat` ;
- `date_debut` ;
- `date_fin` nullable ;
- `synchronise_le`.

Règles :

- la source de vérité reste `CAP-CORE-003` ;
- aucune modification directe ;
- reconstruction possible ;
- absence de projection ne transforme pas une affiliation en mandat ;
- divergence signalée.

Créer cette table seulement si les performances ou l’indisponibilité le justifient.

---

## 12. Vocabulaire canonique obligatoire

Aucune liste métier nouvelle ne doit être cachée dans des constantes locales sans enregistrement dans `CAP-CORE-010`.

Le chantier doit consommer au minimum les vocabulaires suivants :

- types d’organisation ;
- formes d’organisation ;
- états d’organisation ;
- types d’unités ;
- états d’unités ;
- types de relations organisationnelles ;
- types d’affiliation ;
- états d’affiliation ;
- types de fonctions internes ;
- classifications ;
- niveaux d’assurance ;
- types d’identifiants externes ;
- actions d’autorisation ;
- codes d’erreur ;
- types d’événements.

Ajouter un terme dans le vocabulaire ne doit jamais modifier automatiquement les transitions ou autorisations du registre.

Le code conserve des listes de capacités supportées, générées ou vérifiées depuis le vocabulaire.

---

## 13. Bootstrap et migration des données existantes

Créer :

```text
core/registre-organisations/resources/bootstrap-organisations-v1.json
```

Le bootstrap doit reprendre uniquement les données réellement présentes.

### 13.1 Organisations existantes

Pour chaque identité de type `organisation` :

- créer une fiche organisationnelle liée ;
- conserver la référence d’identité ;
- conserver le libellé comme dénomination initiale si aucune donnée plus précise n’existe ;
- conserver la source ;
- ne pas inventer de forme juridique ;
- utiliser un type canonique `INDETERMINE` lorsque nécessaire et prévu par le vocabulaire ;
- conserver explicitement le caractère non vérifié.

### 13.2 Relations existantes

Migrer les lignes de `relation_organisation` de `CAP-CORE-001` vers `organisation_affiliation`.

Conserver :

- référence ;
- identité ;