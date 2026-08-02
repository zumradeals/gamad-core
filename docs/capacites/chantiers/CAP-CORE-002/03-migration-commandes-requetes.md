- organisation ;
- type ;
- assurance ;
- source ;
- preuve ;
- producteur ;
- date ;
- classification ;
- mandat référencé.

Ne pas recopier `mandat_verifie` comme vérité autonome.

Après migration :

- revérifier le mandat via `CAP-CORE-003` ;
- conserver l’ancienne valeur uniquement comme donnée de migration ou diagnostic ;
- signaler toute divergence ;
- ne jamais rendre une relation opposable sur la seule base de l’ancien booléen.

### 13.3 Événements de relation

Migrer les événements de catégorie `ORGANISATION` vers le cycle des affiliations.

Conserver les dates et preuves.

### 13.4 Idempotence

Le bootstrap doit être :

- versionné ;
- vérifié par empreinte ;
- transactionnel ;
- idempotent ;
- sans doublon ;
- sans suppression ;
- sans invention ;
- indépendant de Genesis II ;
- indépendant d’un Markdown runtime.

Une reconstruction de la baseline ou de l’index ne doit jamais modifier le registre d’organisations.

---

## 14. Correction de frontière avec CAP-CORE-001

À la fin du chantier, `CAP-CORE-001` ne doit plus être la source d’exploitation des relations organisationnelles.

Modifier `Ctr01::resoudreLiensOrganisations()` afin qu’il :

1. délègue au contrat canonique de `CAP-CORE-002` ;
2. conserve temporairement la signature utile aux appelants existants ;
3. fournisse une projection compatible ;
4. ne lise plus directement `relation_organisation` après migration complète ;
5. échoue explicitement si `CAP-CORE-002` est indisponible ;
6. n’invente aucun résultat depuis l’ancien magasin.

Après preuve zéro consommateur direct :

- déprécier les écritures organisationnelles dans `CAP-CORE-001` ;
- supprimer ou archiver les anciennes tables selon une migration sûre ;
- conserver les migrations nécessaires à la restauration historique ;
- mettre à jour les tests et diagnostics.

Ne pas casser les contrats publics déjà enregistrés dans `CAP-CORE-009`.

---

## 15. Raccordement à CAP-CORE-003

`CAP-CORE-003` reste la source de vérité des mandats.

Créer une requête applicative permettant de répondre :

```text
Cette affiliation donne-t-elle une représentation opposable à cette date ?
```

La réponse doit combiner :

1. affiliation active dans `CAP-CORE-002` ;
2. identité active dans `CAP-CORE-001` ;
3. organisation active dans `CAP-CORE-002` ;
4. mandat valide dans `CAP-CORE-003` ;
5. politique applicable dans `CAP-CORE-007` ;
6. décision de `CAP-CORE-004` lorsqu’une opération est demandée.

Réponse indicative :

```json
{
  "opposable": false,
  "affiliation": "AFL-GAMAD-000001",
  "organisation": "ORG-GAMAD-000001",
  "identite": "IDN-PER-000001",
  "mandat": null,
  "motifs": ["MANDAT_ABSENT"]
}
```

Une affiliation `DIRIGEANT` sans mandat actif doit rester non opposable.

Une absence de réponse de `CAP-CORE-003` vaut non opposable.

---

## 16. Politique d’administration

Créer :

```text
POL-ORGANISATIONS-V1
```

Actions minimales :

- `organisation.lire` ;
- `organisation.inscrire` ;
- `organisation.modifier` ;
- `organisation.activer` ;
- `organisation.suspendre` ;
- `organisation.dissoudre` ;
- `organisation.retirer` ;
- `organisation.identifiant.declarer` ;
- `organisation.identifiant.fermer` ;
- `organisation.unite.creer` ;
- `organisation.unite.modifier` ;
- `organisation.unite.fermer` ;
- `organisation.relation.declarer` ;
- `organisation.relation.fermer` ;
- `organisation.affiliation.proposer` ;
- `organisation.affiliation.activer` ;
- `organisation.affiliation.suspendre` ;
- `organisation.affiliation.fermer` ;
- `organisation.fonction.creer` ;
- `organisation.representation.verifier`.

Bornes minimales :

- lecture publique limitée aux champs classifiés comme publics ;
- lecture interne selon politique ;
- inscription réservée à l’autorité ou à un producteur explicitement habilité ;
- activation réservée à l’autorité ;
- suspension, dissolution et retrait réservés à l’autorité ;
- une organisation ne s’auto-active pas ;
- une organisation ne s’auto-atteste pas ;
- un acteur ne crée pas sa propre représentation opposable ;
- une affiliation ne contourne jamais `CAP-CORE-003` ;
- absence de décision = refus ;
- refus par défaut.

---

## 17. Commandes métier

### 17.1 `inscrireOrganisation`

Entrées minimales :

- référence organisationnelle ;
- identité canonique ;
- type ;
- personnalité juridique ;
- propriétaire ;
- source ;
- dénomination ;
- classification ;
- acteur ;
- politique ;
- preuve ;
- `correlation_id`.

Règles :

- identité existante de type `organisation` ;
- identité non déjà liée ;
- référence unique ;
- source active ;
- type canonique ;
- création en `PREPARATION` ;
- aucune activation automatique ;
- audit obligatoire.

### 17.2 `modifierOrganisation`

Règles :

- nouvelle révision ;
- référence et identité immuables ;
- historique conservé ;
- preuve obligatoire ;
- audit obligatoire.

### 17.3 `activerOrganisation`

Règles :

- identité active ou utilisable ;
- révision valide ;
- source active ;
- autorisation ;
- preuve ;
- activation atomique ;
- audit obligatoire.

### 17.4 `suspendreOrganisation`

Règles :

- effet immédiatement opposable aux nouvelles opérations ;
- affiliations historiques conservées ;
- nouvelles affiliations activées refusées ;
- unités conservées ;
- audit obligatoire.

### 17.5 `dissoudreOrganisation`

Règles :

- état terminal ;
- date d’effet ;
- aucune suppression ;
- aucune nouvelle affiliation ;
- aucune nouvelle représentation ;
- historique conservé ;
- audit obligatoire.

### 17.6 `retirerOrganisation`

Règles :

- retrait administratif du registre ;
- référence non réutilisable ;
- aucune suppression physique ;
- ne doit pas servir à masquer une dissolution ;
- motif obligatoire ;
- audit obligatoire.

### 17.7 `declarerIdentifiantExterne`

Règles :

- système et type canoniques ;
- valeur normalisée ;
- source compétente ;
- preuve ;
- unicité ;
- aucune auto-validation ;
- audit obligatoire.

### 17.8 `creerUnite`

Règles :

- organisation active ;
- parent valide ;
- absence de cycle ;
- type canonique ;
- source et preuve ;
- audit obligatoire.

### 17.9 `deplacerUnite`

Règles :

- ne jamais réécrire silencieusement le passé ;
- clôturer l’ancien rattachement ou créer une révision structurelle ;
- absence de cycle ;
- même organisation ;
- audit obligatoire.

### 17.10 `fermerUnite`

Règles :

- effet daté ;
- descendants traités explicitement ;
- aucune fermeture silencieuse en cascade ;
- affiliations actives rattachées signalées ;
- audit obligatoire.

### 17.11 `declarerRelationOrganisationnelle`

Règles :

- deux organisations existantes ;
- type canonique ;
- absence de cycle hiérarchique ;
- preuve ;
- aucun droit automatique ;
- audit obligatoire.

### 17.12 `proposerAffiliation`

Règles :

- organisation existante ;
- identité existante ;
- type canonique ;
- état initial `PROPOSEE` ;
- aucune représentation ;
- audit obligatoire.

### 17.13 `activerAffiliation`

Règles :

- affiliation proposée ;
- organisation active ;
- identité active ;
- source active ;
- preuve suffisante ;
- aucun mandat automatique ;
- audit obligatoire.

### 17.14 `suspendreAffiliation`

Règles :

- effet immédiat ;
- historique conservé ;
- représentation refusée ;
- audit obligatoire.

### 17.15 `fermerAffiliation`

Règles :

- clôture datée ;
- aucune suppression ;
- motif ;
- audit obligatoire.

### 17.16 `creerFonctionInterne`

Règles :

- organisation active ;
- unité valide ;
- type canonique ;
- aucun droit automatique ;
- aucun mandat automatique ;
- audit obligatoire.

---

## 18. Requêtes

Implémenter au minimum :

- `resoudreOrganisation(reference, date?)` ;
- `resoudreOrganisationParIdentite(identite, date?)` ;
- `listerOrganisations(filtres)` ;
- `resoudreEtat(reference, date?)` ;
- `resoudreRevision(reference, date?)` ;
- `resoudreIdentifiants(reference, date?)` ;
- `resoudreStructure(reference, date?)` ;
- `resoudreUnite(reference, date?)` ;
- `resoudreRelations(reference, date?)` ;
- `resoudreAffiliationsOrganisation(reference, filtres, date?)` ;
- `resoudreAffiliationsIdentite(identite, filtres, date?)` ;
- `resoudreFonctions(reference, date?)` ;
- `verifierAppartenance(identite, organisation, type?, date?)` ;
- `verifierRepresentation(identite, organisation, action?, date?)` ;
- `diagnostiquerStructure(reference)`.

Les réponses doivent distinguer :

```text
AFFILIATION_ACTIVE
MANDAT_VERIFIE
REPRESENTATION_OPPOSABLE
AUTORISATION_OPERATIONNELLE
```

Ces notions ne doivent jamais être fusionnées en un seul booléen ambigu.

---

## 19. Contrats CAP-CORE-009

Créer ou versionner les contrats suivants :

- résolution d’organisation ;
- inventaire ;
- structure ;
- affiliation ;
- appartenance ;
- représentation ;
- identifiants externes ;
- cycle de vie ;
- commandes d’administration.

Chaque contrat précise :

- producteur ;
- consommateurs ;
- finalité ;
- entrée ;
- sortie ;
- erreurs ;