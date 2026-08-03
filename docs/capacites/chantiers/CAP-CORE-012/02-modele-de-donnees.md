# CAP-CORE-012 — MODÈLE DE DONNÉES ET INVARIANTS

Cette partie complète `01-fiche-de-codage.md`.

Toutes les tables d’historique sont en ajout seul.

Les noms de tables peuvent être adaptés aux conventions réellement présentes dans `main`, mais les responsabilités et invariants sont obligatoires.

---

## 11. Principes de modélisation

Le modèle doit respecter les principes suivants :

- une identité canonique `realm` est créée par `CAP-CORE-001` ;
- la fiche opérationnelle du realm appartient à `CAP-CORE-012` ;
- une référence de realm n’est jamais réattribuée ;
- une correction descriptive crée une révision ;
- un changement d’état crée un événement de cycle ;
- une relation entre realms est datée et prouvée ;
- un rattachement à un realm est daté et prouvé ;
- aucun rattachement ne produit automatiquement une autorisation ;
- aucune hiérarchie ne peut contenir de cycle ;
- une frontière de realm ne doit jamais être déduite d’un simple nom ;
- un code pays ou territoire doit être explicitement déclaré ;
- les termes canoniques viennent de `CAP-CORE-010` ;
- les contrats viennent de `CAP-CORE-009` ;
- les politiques viennent de `CAP-CORE-007` ;
- les données métier restent chez leurs propriétaires.

---

## 12. Table `realm`

Champs minimaux :

- `reference` ;
- `identite_reference` ;
- `code_canonique` ;
- `type_realm_reference` ;
- `source_reference` ;
- `politique_inscription_reference` ;
- `producteur_reference` ;
- `preuve_reference` ;
- `cree_le` ;
- `modifie_le`.

Contraintes :

- `reference` unique et immuable ;
- `identite_reference` unique ;
- l’identité doit exister dans `CAP-CORE-001` ;
- l’identité doit être de type `realm` ;
- `code_canonique` unique dans son espace de nom ;
- le type doit être actif dans `CAP-CORE-010` ;
- la source doit être active dans `CAP-CORE-006` ;
- la politique d’inscription doit être active dans `CAP-CORE-007` ;
- la preuve est obligatoire ;
- aucun secret ;
- aucune donnée métier profonde.

Format recommandé des références :

```text
RLM-GAMAD-0001
RLM-CI-0001
RLM-ML-0001
```

Ne pas imposer ce format si `CAP-CORE-010` établit une convention différente.

La référence d’identité reste distincte :

```text
IDN-RLM-0001
```

Le lien entre les deux est unique et explicite.

---

## 13. Types de realms

Les types sont définis dans `CAP-CORE-010`.

Le premier vocabulaire doit couvrir au minimum les concepts réellement nécessaires :

- `TERRITORIAL` ;
- `INSTITUTIONNEL` ;
- `PROGRAMME` ;
- `MARCHE` ;
- `PRODUIT` ;
- `TECHNIQUE` ;
- `COOPERATION`.

Ces valeurs sont des propositions de bootstrap.

Claude doit utiliser les références réellement livrées par `CAP-CORE-010`.

Ne pas créer un type générique `AUTRE` qui masquerait l’absence de définition.

Un nouveau type de realm ne doit jamais créer automatiquement un comportement de sécurité.

---

## 14. Table `realm_revision`

Champs minimaux :

- `id` ;
- `realm_reference` ;
- `numero_revision` ;
- `nom_affichage` ;
- `description` nullable ;
- `organisation_responsable_reference` nullable ;
- `classification_reference` ;
- `date_debut_validite` ;
- `date_fin_validite` nullable ;
- `acteur_reference` ;
- `source_reference` ;
- `preuve_reference` ;
- `correlation_id` nullable ;
- `cree_le`.

Contraintes :

- couple `(realm_reference, numero_revision)` unique ;
- ajout seul ;
- la référence du realm ne change jamais ;
- le code canonique ne change pas silencieusement ;
- l’organisation responsable doit exister dans `CAP-CORE-002` ;
- la classification doit exister dans `CAP-CORE-010` ;
- date de fin supérieure ou égale à la date de début ;
- la lecture courante prend la dernière révision applicable à la date demandée.

Une organisation responsable n’est pas automatiquement propriétaire juridique du territoire ou de l’institution.

Le rôle exact doit rester explicite dans les rattachements.

---

## 15. Table `realm_cycle`

Champs minimaux :

- `id` ;
- `realm_reference` ;
- `etat_reference` ;
- `date_effet` ;
- `motif_reference` ;
- `motif_detail` nullable ;
- `acteur_reference` ;
- `politique_reference` ;
- `preuve_reference` ;
- `correlation_id` nullable ;
- `cree_le`.

États initiaux proposés :

- `PREPARATION` ;
- `ACTIF` ;
- `SUSPENDU` ;
- `FERME` ;
- `RETIRE`.

Règles :

- ajout seul ;
- création initiale en `PREPARATION` ;
- aucune activation automatique ;
- `SUSPENDU` ferme les nouveaux usages ;
- `FERME` indique que le périmètre n’accueille plus de nouvelles opérations mais conserve son histoire ;
- `RETIRE` est irréversible ;
- une référence retirée n’est jamais réutilisée ;
- l’état courant est la dernière ligne applicable ;
- aucun état passé n’est réécrit.

La différence entre `FERME` et `RETIRE` doit être testée :

- `FERME` peut rester consultable et servir à expliquer l’historique ;
- `RETIRE` interdit toute réactivation sous la même référence.

---

## 16. Table `realm_relation`

Cette table décrit les relations entre realms.

Champs minimaux :

- `id` ;
- `realm_source_reference` ;
- `realm_cible_reference` ;
- `type_relation_reference` ;
- `date_debut` ;
- `date_fin` nullable ;
- `acteur_reference` ;
- `source_reference` ;
- `preuve_reference` ;
- `correlation_id` nullable ;
- `cree_le`.

Types initiaux proposés :

- `PARENT_DE` ;
- `INCLUS_DANS` ;
- `CHEVAUCHE` ;
- `EQUIVALENT_OPERATIONNEL` ;
- `SUCCEDE_A` ;
- `COOPERE_AVEC`.

Règles :

- les deux realms doivent exister ;
- un realm ne peut pas se relier à lui-même ;
- `PARENT_DE` et `INCLUS_DANS` construisent une hiérarchie acyclique ;
- `CHEVAUCHE` ne crée pas une inclusion ;
- `EQUIVALENT_OPERATIONNEL` n’implique pas une identité juridique ou territoriale ;
- `SUCCEDE_A` ne retire pas automatiquement l’ancien realm ;
- aucune relation ne donne une autorisation ;
- chaque relation est datée et prouvée.

Pour éviter les incohérences, choisir une représentation canonique pour la hiérarchie :

- soit uniquement `PARENT_DE` ;
- soit uniquement `INCLUS_DANS`.

L’autre sens peut être dérivé en lecture.

Ne pas enregistrer deux lignes inverses pour la même relation hiérarchique.

---

## 17. Table `realm_perimetre`

Cette table décrit les dimensions bornant le realm.

Champs minimaux :

- `id` ;
- `realm_reference` ;
- `dimension_reference` ;
- `valeur_reference` ;
- `valeur_externe` nullable ;
- `systeme_externe_reference` nullable ;
- `date_debut` ;
- `date_fin` nullable ;
- `acteur_reference` ;
- `source_reference` ;
- `preuve_reference` ;
- `cree_le`.

Dimensions possibles :

- pays ;
- région ;
- ville ;
- juridiction ;
- marché ;
- domaine d’activité ;
- programme ;
- environnement ;
- institution ;
- classification de données.

Les dimensions et valeurs canoniques viennent de `CAP-CORE-010`.

Règles :

- aucune dimension libre utilisée par le moteur de portée ;
- les textes libres peuvent servir de description, jamais de borne de sécurité ;
- une valeur externe doit préciser son système de référence ;
- une même dimension ne doit pas avoir deux valeurs contradictoires actives sans relation explicite ;
- aucune donnée de localisation personnelle ;
- aucune géométrie lourde en base sans décision de conception séparée.

Pour une géométrie territoriale complexe, stocker uniquement :

- la référence de l’artefact externe ;
- son empreinte éventuelle ;
- sa source ;
- sa date de validité.

---

## 18. Table `realm_identifiant_externe`

Champs minimaux :

- `id` ;
- `realm_reference` ;
- `systeme_reference` ;
- `valeur` ;
- `date_debut` ;
- `date_fin` nullable ;
- `source_reference` ;
- `preuve_reference` ;
- `cree_le`.

Exemples possibles :

- code ISO pays ;
- code administratif régional ;
- code institutionnel ;
- identifiant de programme ;
- identifiant externe de tenant.

Contraintes :

- couple `(systeme_reference, valeur)` unique pendant une période active lorsque le système l’exige ;
- aucun rapprochement approximatif ;
- aucun identifiant secret ;
- aucun token ;
- aucun numéro personnel.

---

## 19. Table `realm_organisation`

Champs minimaux :

- `reference` ;
- `realm_reference` ;
- `organisation_reference` ;
- `role_reference` ;
- `date_debut` ;
- `date_fin` nullable ;
- `classification_reference` ;
- `acteur_reference` ;
- `politique_reference` ;
- `source_reference` ;
- `preuve_reference` ;
- `correlation_id` nullable ;
- `cree_le`.

Rôles initiaux proposés :

- `RESPONSABLE` ;
- `OPERATEUR` ;
- `PARTICIPANT` ;
- `REGULATEUR` ;
- `BENEFICIAIRE` ;
- `OBSERVATEUR`.

Règles :

- l’organisation doit exister et être active dans `CAP-CORE-002` ;
- le realm doit être actif ou en préparation selon l’action ;
- le rôle vient de `CAP-CORE-010` ;
- aucune organisation n’est implicitement responsable ;
- `RESPONSABLE` ne donne pas à une personne le droit de représenter l’organisation ;
- la personne agissant pour l’organisation doit disposer d’un mandat valide via `CAP-CORE-003` ;
- la clôture est historisée ;
- aucun effacement physique.

---

## 20. Table `realm_produit`

Champs minimaux :

- `reference` ;
- `realm_reference` ;
- `produit_reference` ;
- `role_reference` ;
- `environnement_reference` nullable ;
- `date_debut` ;
- `date_fin` nullable ;
- `acteur_reference` ;
- `politique_reference` ;
- `source_reference` ;
- `preuve_reference` ;
- `correlation_id` nullable ;
- `cree_le`.

Rôles initiaux proposés :

- `OPERE_DANS` ;
- `FOURNIT_SERVICE` ;
- `CONSOMME_SERVICE` ;
- `ADMINISTRE` ;
- `OBSERVE`.

Règles :

- produit connu et actif dans `CAP-CORE-011` ;
- environnement, lorsqu’il est fourni, doit exister dans `CAP-CORE-011` ;
- ne pas recopier les URLs, audiences ou secrets d’environnement ;
- rattachement sans autorisation automatique ;
- produit suspendu ou retiré rend le rattachement inutilisable ;
- fermeture historisée ;
- aucun effacement physique.

---

## 21. Table `realm_capacite`

Cette table est facultative mais recommandée pour les capacités transversales.

Champs minimaux :

- `id` ;
- `realm_reference` ;
- `capacite_reference` ;
- `mode_reference` ;
- `date_debut` ;
- `date_fin` nullable ;
- `preuve_reference` ;
- `cree_le`.

Modes possibles :

- `DISPONIBLE` ;
- `RESTREINTE` ;
- `INTERDITE` ;
- `PILOTE`.

Règles :

- capacité connue dans le catalogue ou `CAP-CORE-020` lorsqu’il sera `GO` ;
- cette table ne remplace pas `CAP-CORE-004` ;
- `INTERDITE` constitue une borne ferme supplémentaire ;
- `DISPONIBLE` ne crée aucune permission.

Ne créer cette table que si au moins un consommateur réel l’utilise pendant le chantier.

---

## 22. Table `realm_contrat`

Cette table associe un contrat actif à un ou plusieurs realms.

Champs minimaux :

- `id` ;
- `realm_reference` ;
- `contrat_reference` ;
- `version_reference` nullable ;
- `role_reference` ;
- `date_debut` ;
- `date_fin` nullable ;
- `preuve_reference` ;
- `cree_le`.

Règles :

- contrat connu dans `CAP-CORE-009` ;
- version active ou explicitement dépréciée mais encore supportée ;
- aucun contrat suspendu ou retiré utilisable ;
- un contrat global n’est pas automatiquement valable dans tous les realms ;
- un contrat borné à un realm ne peut pas être utilisé dans un autre sans rattachement ou règle de franchissement.

Ne dupliquer aucun schéma de contrat dans cette table.

---

## 23. Table `realm_franchissement`

Cette table décrit les passages explicitement permis ou interdits entre realms.

Champs minimaux :

- `id` ;
- `realm_source_reference` ;
- `realm_cible_reference` ;
- `objet_reference` ;
- `type_objet_reference` ;
- `effet_reference` ;
- `finalite_reference` ;
- `contrat_reference` nullable ;
- `date_debut` ;
- `date_fin` nullable ;
- `politique_reference` ;
- `source_reference` ;
- `preuve_reference` ;
- `cree_le`.

Effets :

- `PERMET` ;
- `REFUSE`.

Règles :

- refus par défaut ;
- un `REFUSE` applicable l’emporte ;
- finalité obligatoire ;
- contrat obligatoire pour un échange interproduit lorsque `CAP-CORE-009` le prévoit ;
- aucune règle libre exécutable ;
- aucun wildcard universel implicite ;
- un realm ne franchit pas automatiquement sa frontière vers son parent ;
- un parent ne voit pas automatiquement toutes les données de ses enfants ;
- la politique `CAP-CORE-007` reste nécessaire ;
- la décision finale reste rendue par `CAP-CORE-004`.

Cette table décrit la borne de realm. Elle ne remplace pas une politique d’autorisation.

---

## 24. Table `realm_verification`

Champs minimaux :

- `id` ;
- `realm_reference` ;
- `type_verification_reference` ;
- `resultat_reference` ;
- `verifie_par_reference` ;
- `preuve_reference` ;
- `verifie_le` ;
- `expire_le` nullable ;
- `motif` nullable ;
- `cree_le`.

Règles :

- ajout seul ;
- vérificateur connu ;
- preuve obligatoire ;
- expiration opposable ;
- aucune auto-attestation pour les vérifications fortes ;
- une vérification n’est jamais un score universel de confiance ;
- une vérification expirée ne valide pas une nouvelle opération.

---

## 25. Contraintes transversales

Le schéma doit empêcher ou détecter :

- deux realms portant la même identité ;
- une identité non `realm` ;
- une hiérarchie cyclique ;
- un realm parent de lui-même ;
- une référence réutilisée ;
- une relation vers une organisation inconnue ;
- une relation vers un produit inconnu ;
- une relation vers un contrat inconnu ;
- un rattachement actif hors période ;
- une frontière fondée sur du texte libre ;
- un franchissement sans finalité ;
- une permission universelle implicite ;
- un secret dans le schéma ;
- une suppression physique de l’historique ;
- une transition après retrait ;
- une activation partielle.

La portabilité PostgreSQL / SQLite est obligatoire.
