# CAP-CORE-020 — Modèle Annuaire & Atlas

## 1. Principes du modèle

Le magasin de `CAP-CORE-020` contient des projections reconstructibles et des preuves de leur provenance.

Il ne remplace aucune table souveraine.

Chaque information projetée doit répondre à cinq questions :

```text
Quel objet est représenté ?
Quelle source souveraine l’a déclaré ?
À quelle version ou révision ?
Quand a-t-il été observé ?
Qui a le droit de le voir ?
```

Le modèle distingue :

- l’identité stable d’une entrée ;
- ses révisions de projection ;
- ses états observés ;
- ses relations ;
- les lots de collecte ;
- les divergences ;
- les profils de visibilité ;
- les instantanés exportables.

---

## 2. Schéma logique

Tables minimales :

```text
atlas_entree
atlas_entree_revision
atlas_entree_cycle
atlas_relation
atlas_relation_cycle
atlas_observation
atlas_endpoint_projection
atlas_contact_fonctionnel
atlas_collecteur
atlas_collecte
atlas_collecte_element
atlas_divergence
atlas_profil_visibilite
atlas_projection_publique
atlas_requete_impact
atlas_instantane
atlas_instantane_element
atlas_reconciliation
atlas_reconciliation_ecart
atlas_checkpoint
```

Le préfixe peut être adapté aux conventions du dépôt, mais le découpage fonctionnel doit rester explicite.

---

## 3. Table `atlas_entree`

Colonnes minimales :

```text
id                       BIGSERIAL / INTEGER
reference                VARCHAR(128) UNIQUE NOT NULL
type_reference           VARCHAR(96) NOT NULL
reference_souveraine     VARCHAR(256) NOT NULL
capacite_souveraine      VARCHAR(32) NOT NULL
organisation_reference   VARCHAR(128) NULL
realm_reference          VARCHAR(128) NULL
classification_reference VARCHAR(96) NOT NULL
cree_le                   TIMESTAMPTZ NOT NULL
retiree_le                TIMESTAMPTZ NULL
```

Contraintes :

- `reference` immuable ;
- `type_reference` appartient au vocabulaire canonique ;
- `capacite_souveraine` correspond à une capacité connue ;
- une même combinaison `(capacite_souveraine, reference_souveraine)` ne produit qu’une entrée active ;
- aucune réattribution d’une référence retirée ;
- aucune suppression physique ordinaire.

Exemples :

```text
DIR-CAP-CAP-CORE-011
DIR-PRD-PRD-GAMAD-DRIVE
DIR-CTR-CTR-FEDERATION-01
```

La référence de l’Atlas n’a pas vocation à remplacer la référence souveraine. Elle sert à identifier uniformément le nœud de graphe.

---

## 4. Table `atlas_entree_revision`

Colonnes minimales :

```text
id
atlas_entree_id
revision_source
version_source
nom_canonique
nom_affichage
description_courte
etat_declare_reference
proprietaire_reference
responsable_fonction_reference
source_reference
preuve_reference
contrat_collecte_reference
schema_version
contenu_projection_json
empreinte_projection
observee_le
valide_depuis
valide_jusqua
cree_le
```

Règles :

- ajout seul ;
- aucune modification après insertion ;
- `contenu_projection_json` strictement validé selon le type ;
- taille maximale ;
- clés autorisées en liste blanche ;
- aucune donnée secrète ;
- aucune URL avec credentials ;
- empreinte calculée sur une forme canonique ;
- révision source obligatoire si la source en expose une ;
- en absence de version source, l’empreinte et la date d’observation sont obligatoires.

Une nouvelle observation identique peut mettre à jour la fraîcheur sans créer une révision identique inutile, mais cette optimisation doit conserver une trace de collecte.

---

## 5. Table `atlas_entree_cycle`

États minimaux de projection :

```text
DECOUVERTE
ACTIVE
INCOMPLETE
PERIMEE
SUSPENDUE
RETIREE
DIVERGENTE
INDISPONIBLE
```

Ces états décrivent la projection, pas l’état métier souverain.

Exemple :

```text
Produit déclaré ACTIF par CAP-CORE-011
mais projection PERIMEE car dernière collecte trop ancienne
```

Colonnes :

```text
id
atlas_entree_id
etat_projection
motif_reference
motif_detail_minimal
source_reference
preuve_reference
date_effet
cree_le
```

Ajout seul. L’état courant est la dernière ligne par date d’effet puis identifiant.

---

## 6. Table `atlas_relation`

Colonnes minimales :

```text
id
reference UNIQUE
source_entree_id
cible_entree_id
type_relation_reference
capacite_souveraine
reference_souveraine
contrat_reference
operation_reference
realm_source_reference
realm_cible_reference
franchissement_reference
classification_reference
direction
criticite_reference
preuve_reference
observee_le
valide_depuis
valide_jusqua
cree_le
```

Règles :

- source différente de cible sauf types explicitement réflexifs ;
- type exact de `CAP-CORE-010` ;
- pas de relation créée par ressemblance de noms ;
- source souveraine obligatoire ;
- relation orientée par défaut ;
- contrat et opération exigés pour les flux techniques ;
- realm source et cible exigés pour un franchissement ;
- relation active impossible si une entrée est retirée, sauf relation historique ;
- unicité logique par source, cible, type, version et période.

---

## 7. Table `atlas_relation_cycle`

États :

```text
OBSERVEE
ACTIVE
SUSPENDUE
PERIMEE
RETIRÉE
DIVERGENTE
```

Colonnes :

```text
id
atlas_relation_id
etat
motif_reference
source_reference
preuve_reference
date_effet
cree_le
```

Une relation non revue après le seuil de fraîcheur devient `PERIMEE`, jamais supprimée silencieusement.

Lors d’une réconciliation confirmant sa disparition de la source souveraine, elle devient `RETIRÉE` avec motif et lot de réconciliation.

---

## 8. Table `atlas_observation`

Une observation décrit l’état technique observé sans écraser l’état déclaré.

Colonnes :

```text
id
atlas_entree_id
type_observation_reference
etat_observe_reference
valeur_normalisee
unite_reference
severite_reference
sonde_reference
collecteur_reference
contrat_reference
source_reference
preuve_reference
correlation_id
observee_le
expire_le
cree_le
```

Types minimaux :

```text
READINESS
LIVENESS
DISPONIBILITE_MAGASIN
VERSION_DEPLOYEE
DERNIERE_MIGRATION
DERNIER_EVENEMENT
FRAICHEUR_SOURCE
CONFORMITE_CONTRAT
ETAT_CONTINUITE
IMPACT_INCIDENT
IMPACT_RISQUE
```

Une observation ne déclenche pas elle-même une transition métier.

---

## 9. Fraîcheur

Définir des classes canoniques :

```text
FRAICHE
A_SURVEILLER
PERIMEE
INCONNUE
```

Chaque type de source possède :

```text
frequence_attendue
seuil_surveillance
seuil_peremption
```

Exemples indicatifs, à configurer et non coder en dur :

```text
readiness produit : minutes
révision organisation : heures ou événements
contrat actif : événements + réconciliation quotidienne
preuve de restauration : jours ou selon politique
```

La fraîcheur est calculée à la lecture depuis :

```text
derniere_observation_reussie
heure_courante
politique_de_fraicheur
```

Elle n’est jamais enregistrée comme vérité définitive.

---

## 10. Complétude

La complétude dépend du type d’entrée.

Exemple produit :

```text
reference
nom
type
proprietaire
etat declare
au moins un realm ou justification d’absence
environnement si nécessaire
contrats exposés/consommés
source
```

Exemple capacité :

```text
reference
nom
module propriétaire
descripteur valide
contrats
readiness
classification
propriétaire fonctionnel
```

États :

```text
COMPLETE
PARTIELLE
INSUFFISANTE
NON_EVALUEE
```

Une projection partielle reste visible comme telle ; le système ne remplit jamais les champs manquants par défaut.

---

## 11. Confiance et provenance

Ne pas produire un score opaque unique.

Afficher des dimensions explicites :

```text
source_verifiee
contrat_actif
preuve_verifiable
observation_fraiche
revision_identifiee
collecte_complete
absence_divergence
```

La confiance est une synthèse lisible de ces dimensions, pas un nombre arbitraire utilisé pour autoriser une action.

---

## 12. Table `atlas_endpoint_projection`

Colonnes :

```text
id
atlas_entree_id
produit_reference
environnement_reference
type_endpoint_reference
url_normalisee
audience_reference
protocole_reference
classification_reference
exposition_reference
healthcheck_autorise
contrat_reference
source_reference
preuve_reference
observee_le
valide_depuis
valide_jusqua
```

Règles :

- schémas autorisés en liste blanche ;
- aucun userinfo dans l’URL ;
- aucun query string contenant un secret ;
- aucun fragment ;
- hostname et port validés ;
- endpoints internes masqués aux vues publiques ;
- aucune requête sortante automatique vers un endpoint simplement enregistré ;
- les sondes utilisent une liste d’autorisation distincte.

---

## 13. Table `atlas_contact_fonctionnel`

L’Atlas ne stocke pas un annuaire personnel complet.

Colonnes :

```text
id
atlas_entree_id
fonction_reference
organisation_reference
identite_reference NULL
canal_reference NULL
classification_reference
source_reference
mandat_reference NULL
observee_le
valide_depuis
valide_jusqua
```

Préférer les fonctions :

```text
RESPONSABLE_PRODUIT
RESPONSABLE_CAPACITE
RESPONSABLE_SECURITE
RESPONSABLE_CONTINUITE
RESPONSABLE_INCIDENT
```

Une identité nominative n’est projetée que si elle est nécessaire, autorisée et issue de CAP-CORE-003/CAP-CORE-002.

---

## 14. Table `atlas_collecteur`

Colonnes :

```text
id
reference UNIQUE
capacite_source
contrat_reference
operation_reference
mode_reference
frequence_attendue
realm_reference NULL
classification_maximale
actif
cree_le
retire_le
```

Modes :

```text
EVENEMENT
REQUETE_INTERNE
SONDE_AUTORISEE
ATTESTATION
BOOTSTRAP
```

Un collecteur ne peut pas définir une requête SQL libre ni une URL arbitraire.

---

## 15. Table `atlas_collecte`

Colonnes :

```text
id
reference UNIQUE
collecteur_id
mode_reference
statut
commencee_le
terminee_le
cursor_reference
lot_source_reference
nombre_lu
nombre_valide
nombre_rejete
nombre_cree
nombre_modifie
nombre_retire
preuve_reference
correlation_id
erreur_reference NULL
```

Statuts :

```text
PREPAREE
EN_COURS
REUSSIE
PARTIELLE
ECHOUÉE
ANNULEE
```

Une collecte partielle ne peut pas retirer les entrées absentes du lot. Seule une réconciliation complète et attestée peut conclure qu’un élément a disparu.

---

## 16. Table `atlas_collecte_element`

Colonnes :

```text
id
atlas_collecte_id
reference_source
type_element
resultat
atlas_entree_id NULL
atlas_relation_id NULL
empreinte_source
schema_version
erreur_reference NULL
cree_le
```

Résultats :

```text
IDENTIQUE
CREE
REVISE
RETIRE
REJETE
DIVERGENT
IGNORE_EXPLICITEMENT
```

Permet un diagnostic détaillé sans enregistrer la charge complète si elle contient des données inutiles.

---

## 17. Table `atlas_divergence`

Colonnes :

```text
id
reference UNIQUE
type_divergence_reference
atlas_entree_id NULL
atlas_relation_id NULL
source_a_reference
source_b_reference
empreinte_a
empreinte_b
resume_minimal
severite_reference
statut
ouverte_le
resolue_le NULL
decision_reference NULL
preuve_reference NULL
```

Types minimaux :

```text
ETAT_DECLARE_CONTREDIT_OBSERVATION
RELATION_ABSENTE_DANS_SOURCE
CONTRAT_PARTIES_DIVERGENTES
REALM_DIVERGENT
ENDPOINT_DIVERGENT
VERSION_DEPLOYEE_INCONNUE
DESCRIPTEUR_INCOMPATIBLE
PREUVE_NON_VERIFIABLE
```

États :

```text
OUVERTE
EN_ANALYSE
ACCEPTEE_TEMPORAIREMENT
RESOLUE
FAUX_POSITIF_PROUVE
```

Une divergence n’est jamais résolue en écrasant simplement une source.

---

## 18. Visibilité

Table `atlas_profil_visibilite` :

```text
id
reference UNIQUE
nom
audience_reference
classification_maximale
realm_reference NULL
organisation_reference NULL
produit_reference NULL
champs_autorises_json
relations_autorisees_json
endpoints_autorises_json
politique_reference
version_politique
actif
```

Profils minimaux :

```text
INTERNE_OPERATIONNEL
INTERNE_SECURITE
RESPONSABLE_PRODUIT
PARTENAIRE_CONTRACTUEL
SATELLITE_REALM
PUBLIC_MINIMAL
```

Une projection publique est une projection distincte ; elle ne repose pas sur le masquage tardif d’une réponse interne complète.

---

## 19. Table `atlas_projection_publique`

Colonnes :

```text
id
atlas_entree_id
version_projection
contenu_public_json
empreinte
preuve_reference
approuvee_par_decision_reference
valide_depuis
valide_jusqua
retiree_le
```

Champs possibles :

```text
reference publique
nom public
type
organisation publique
realm public
résumé
URL publique approuvée
état public volontaire
preuve publique
```

Aucune publication automatique d’un incident, risque, endpoint interne ou relation privée.

---

## 20. Analyse d’impact

Table `atlas_requete_impact` :

```text
id
reference UNIQUE
acteur_reference
point_depart_entree_id
sens_reference
profondeur_maximale
nombre_noeuds_maximal
types_relations_json
realm_reference
inclure_historique
statut
resultat_empreinte
preuve_reference NULL
cree_le
expire_le
```

Sens :

```text
AMONT
AVAL
BIDIRECTIONNEL
```

Règles :

- profondeur maximale configurable ;
- nombre de nœuds maximal ;
- délai d’exécution ;
- types de relations en liste blanche ;
- contrôle d’autorisation avant et pendant le parcours ;
- pas de fuite par nombre ou erreur ;
- résultat expirant ;
- pas d’usage comme décision automatique.

---

## 21. Instantanés

Table `atlas_instantane` :

```text
id
reference UNIQUE
portee_reference
realm_reference NULL
profil_visibilite_reference
cree_le
point_de_coupure_evenement
nombre_entrees
nombre_relations
empreinte_manifeste
preuve_reference
statut
expire_le NULL
```

Table `atlas_instantane_element` :

```text
id
atlas_instantane_id
type_element
reference_element
empreinte_element
ordre
```

Un instantané ne contient que les projections autorisées au moment de sa création.

Il est signé via CAP-CORE-015 et vérifiable sans accès aux clés privées.

---

## 22. Réconciliation

Table `atlas_reconciliation` :

```text
id
reference UNIQUE
portee_reference
capacite_source
collecteur_reference
statut
point_depart
point_fin
nombre_attendu
nombre_observe
nombre_ecarts
preuve_reference
commencee_le
terminee_le
```

Table `atlas_reconciliation_ecart` :

```text
id
atlas_reconciliation_id
type_ecart
reference_source
reference_atlas NULL
resultat
resolution_reference NULL
```

Règles :

- réconciliation complète par source ;
- aucune suppression pendant une collecte partielle ;
- retrait seulement après confirmation ;
- divergences persistantes ouvertes ;
- résultat idempotent ;
- reprise après crash ;
- journalisation et preuve.

---

## 23. Checkpoints d’événements

Table `atlas_checkpoint` :

```text
id
abonnement_reference
consommateur_reference
partition_reference
sequence_derniere_appliquee
evenement_reference
applique_le
empreinte
```

La consommation CAP-CORE-014 est au moins une fois.

L’application d’un événement doit être idempotente et dédupliquée par identifiant stable.

---

## 24. Historisation

L’Atlas doit pouvoir répondre :

```text
Quelle relation était connue à une date donnée ?
Quel endpoint était déclaré ?
Quand une divergence a-t-elle commencé ?
Quelle version de contrat justifiait ce flux ?
```

Les révisions et cycles sont ajout-seul.

Les corrections passent par une nouvelle révision ou un retrait daté.

---

## 25. Classification des données

Niveaux minimaux, alignés sur le vocabulaire canonique :

```text
PUBLIC
INTERNE
RESTREINT
SENSIBLE
```

La classification d’une projection ne peut pas être inférieure à la classification minimale imposée par sa source ou son contrat.

Une relation hérite du niveau le plus restrictif entre :

- source ;
- cible ;
- contrat ;
- realms ;
- endpoint ;
- observation.

---

## 26. Index PostgreSQL

Prévoir au minimum :

```text
atlas_entree(reference)
atlas_entree(type_reference)
atlas_entree(capacite_souveraine, reference_souveraine)
atlas_entree(organisation_reference)
atlas_entree(realm_reference)
atlas_relation(source_entree_id, type_relation_reference)
atlas_relation(cible_entree_id, type_relation_reference)
atlas_relation(contrat_reference)
atlas_observation(atlas_entree_id, type_observation_reference, observee_le DESC)
atlas_divergence(statut, severite_reference)
atlas_collecte(collecteur_id, commencee_le DESC)
atlas_endpoint_projection(produit_reference, environnement_reference)
```

Pour la recherche textuelle :

- utiliser une stratégie PostgreSQL documentée ;
- pas de dépendance obligatoire à une extension non installée sans garde ;
- normalisation canonique ;
- aucune recherche floue pour résoudre une référence de sécurité.

---

## 27. Contraintes anti-corruption

Le modèle doit refuser :

- référence vide ;
- type libre ;
- source inconnue ;
- relation sans provenance ;
- cycle rétroactif non justifié ;
- endpoint avec credentials ;
- JSON trop volumineux ;
- champ secret ;
- preuve privée ;
- relation croisant des realms sans référence de franchissement ;
- observation future ;
- instantané non signé ;
- suppression physique d’une entrée active ;
- réutilisation d’une référence retirée ;
- collecte présentée comme complète sans preuve de complétude.

---

## 28. Données explicitement interdites

```text
mots de passe
clés privées
phrases secrètes
jetons actifs
cookies
codes de secours
challenges WebAuthn
contenus de fichiers utilisateur
dumps de base
dossiers RH
salaires
données médicales
messages privés
transactions métier complètes
charges utiles complètes d’événements
positions détaillées de décisions restreintes
preuves brutes sensibles
```

---

## 29. Rétention

Définir une politique par catégorie :

```text
révisions d’entrée : durée longue, selon gouvernance
relations historiques : durée longue
observations techniques : fenêtre bornée
collectes détaillées : fenêtre opérationnelle
résultats d’impact : courte durée
instantanés signés : selon finalité
projections publiques : historique des versions publiées
```

La purge doit conserver :

- les preuves ;
- les cycles nécessaires ;
- les références de divergence ;
- les éléments exigés par une décision ou un incident ;
- les dépendances nécessaires à une vérification historique.

---

## 30. Migration et bootstrap

La migration crée uniquement le schéma vide.

Le bootstrap :

1. valide les prérequis ;
2. enregistre les collecteurs connus ;
3. charge les descripteurs techniques valides ;
4. interroge les registres via contrats internes ;
5. crée les entrées et relations exactes ;
6. produit un rapport de complétude ;
7. ouvre les divergences ;
8. enregistre un checkpoint ;
9. produit une preuve de lot ;
10. reste idempotent.

Il ne doit jamais inventer un endpoint, un realm ou une responsabilité pour atteindre artificiellement 100 % de complétude.