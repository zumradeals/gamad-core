# CAP-CORE-018 — Modèle des incidents et cycle de vie

## 1. Principes du magasin

Le magasin `CAP-CORE-018` est persistant, relationnel et append-only pour les faits historiques.

Il doit permettre :

- une lecture de l’état courant ;
- une reconstruction complète de l’historique ;
- une résolution à une date donnée ;
- un rapprochement après restauration ;
- des écritures concurrentes sûres ;
- une suppression logique des projections non nécessaires sans effacer les faits opposables techniquement.

Aucune table ne doit porter une clé privée, un secret, un jeton, un mot de passe ou une pièce brute.

## 2. Tables minimales

Créer au minimum :

```text
incident
incident_cycle
incident_revision
incident_signal
incident_signal_liaison
incident_impact
incident_actif_affecte
incident_participant
incident_role_cycle
incident_chronologie
incident_action
incident_action_tentative
incident_action_accuse
incident_communication
incident_preuve
incident_decision
incident_risque
incident_exception
incident_relation
incident_retablissement
incident_revue
incident_lecon
incident_action_corrective
incident_idempotence
incident_projection_publique
```

Le nom final peut être ajusté à la convention du dépôt, mais la responsabilité de chaque concept doit rester distincte.

## 3. Table `incident`

Colonnes minimales :

```text
id
reference
organisation_reference
realm_reference
type_reference
titre_court
resume_minimal
classification
source_ouverture_reference
cree_par_reference
cree_le
premier_signal_le
instant_debut_estime
instant_confirmation
instant_resolution
instant_cloture
environnement
produit_principal_reference
capacite_principale_reference
correlation_id
version_concurrence
```

Contraintes :

- `reference` unique et immuable ;
- organisation et realm obligatoires ;
- type canonique obligatoire ;
- résumé court, borné et expurgé ;
- aucune donnée sensible dans le titre ;
- `version_concurrence` utilisée pour le verrouillage optimiste ;
- l’état courant n’est jamais modifié sans ligne dans `incident_cycle`.

Format de référence recommandé :

```text
INC-YYYYMMDD-XXXXXXXX
```

L’identifiant aléatoire doit être non prédictible et la collision testée.

## 4. Table `incident_cycle`

Colonnes :

```text
id
incident_id
etat
resultat
motif
acteur_reference
decision_reference
preuve_reference
date_effet
cree_le
correlation_id
```

États minimaux :

```text
SIGNALE
EN_TRIAGE
CONFIRME
EN_CONFINEMENT
CONTENU
EN_ERADICATION
ERADIQUE
EN_RETABLISSEMENT
RETABLI
SOUS_SURVEILLANCE
RESOLU
CLOS
REJETE
DOUBLON
ROUVERT
```

Règles :

- `REJETE` signifie qu’aucun incident n’a été confirmé ;
- `DOUBLON` conserve la référence et pointe vers l’incident principal ;
- `CLOS` exige une revue ;
- `ROUVERT` ne réécrit pas l’ancienne clôture ;
- une transition invalide retourne `422` ;
- les transitions concurrentes retournent `409` ;
- aucune transition n’est produite par une simple lecture.

## 5. Graphe de transitions

Transitions normales :

```text
SIGNALE
→ EN_TRIAGE
→ CONFIRME | REJETE | DOUBLON

CONFIRME
→ EN_CONFINEMENT
→ CONTENU
→ EN_ERADICATION
→ ERADIQUE
→ EN_RETABLISSEMENT
→ RETABLI
→ SOUS_SURVEILLANCE
→ RESOLU
→ CLOS
```

Transitions permises selon le cas :

```text
CONFIRME → EN_RETABLISSEMENT
CONTENU → EN_RETABLISSEMENT
RETABLI → EN_CONFINEMENT
SOUS_SURVEILLANCE → EN_CONFINEMENT
RESOLU → ROUVERT
CLOS → ROUVERT
ROUVERT → EN_TRIAGE | CONFIRME
```

Une transition en arrière exige un motif et ne supprime aucune étape précédente.

## 6. Table `incident_revision`

Les champs descriptifs modifiables avant clôture sont versionnés :

```text
id
incident_id
numero_revision
titre_court
resume_minimal
type_reference
classification
organisation_reference
realm_reference
produit_principal_reference
capacite_principale_reference
motif_revision
acteur_reference
cree_le
empreinte
```

Après clôture :

- les corrections factuelles passent par une nouvelle révision ;
- le texte précédent reste consultable selon autorisation ;
- la projection courante pointe vers la dernière révision ;
- aucune mise à jour SQL directe de l’ancienne révision.

## 7. Table `incident_signal`

Colonnes :

```text
id
reference
producteur_reference
source_reference
contrat_reference
contrat_version
operation_reference
type_signal_reference
sujet_type
sujet_reference
realm_reference
organisation_reference
environnement
detecte_le
recu_le
niveau_producteur
resume_minimal
empreinte_charge
preuve_reference
correlation_id
causalite_id
classification
expire_le
```

Contraintes :

- référence unique ;
- charge complète non stockée si elle contient des données sensibles ;
- empreinte stable de la charge minimale ;
- source et producteur connus ;
- type canonique ;
- date du producteur et date de réception distinctes ;
- aucun signal futur au-delà d’une tolérance gouvernée sans drapeau d’anomalie.

## 8. Table `incident_signal_liaison`

Permet de rattacher un signal à :

- un candidat ;
- un incident confirmé ;
- plusieurs incidents lorsqu’il constitue une cause commune, avec justification.

Colonnes :

```text
signal_id
incident_id
role_liaison
motif
acteur_reference
cree_le
```

Rôles :

```text
DECLENCHEUR
CONFIRMATION
AGGRAVATION
RETABLISSEMENT
RECURRENCE
BRUIT
```

Un signal marqué `BRUIT` reste conservé pour expliquer le triage.

## 9. Table `incident_impact`

Colonnes :

```text
id
reference
incident_id
dimension_reference
niveau_reference
constate
perimetre_type
perimetre_reference
nombre_estime
unite_reference
debut_estime
fin_estimee
confiance_reference
source_reference
preuve_reference
motif
acteur_reference
cree_le
remplace_impact_id
```

Règles :

- une estimation n’est pas présentée comme un fait certain ;
- la confiance est obligatoire ;
- une correction remplace par référence, jamais par suppression ;
- les nombres de personnes ou dossiers restent agrégés ;
- une dimension retirée du vocabulaire reste résoluble historiquement.

## 10. Table `incident_actif_affecte`

Un actif affecté peut être :

```text
CAPACITE
PRODUIT
CONTRAT
SOURCE
ORGANISATION
REALM
MAGASIN
SERVICE
CLE_REFERENCE
SAUVEGARDE
SATELLITE
DEPENDANCE_EXTERNE
```

Colonnes :

```text
id
incident_id
type_actif
actif_reference
role_affectation
etat_affectation
premier_impact_le
dernier_impact_le
preuve_reference
motif
```

Aucune URL contenant des secrets ni aucun chemin arbitraire local ne doit être accepté comme référence d’actif.

## 11. Table `incident_participant`

Colonnes :

```text
id
incident_id
identite_reference
organisation_reference
fonction_reference
mandat_reference
ajoute_par_reference
ajoute_le
retire_le
motif_retrait
classification_max
```

Un participant :

- doit être une identité active ;
- doit appartenir à une organisation autorisée ;
- ne gagne aucun droit par la seule présence dans le dossier ;
- reste soumis à `CAP-CORE-004` pour chaque lecture ou action ;
- peut être retiré sans effacer ses contributions.

## 12. Table `incident_role_cycle`

Colonnes :

```text
id
incident_id
participant_id
role_reference
etat
nomme_par_reference
decision_reference
preuve_reference
date_effet
date_fin
motif
```

Rôles actifs à un instant donné, sans chevauchement interdit pour :

```text
COMMANDANT_INCIDENT
SCRIBE
VALIDATEUR_CLOTURE
```

Plusieurs responsables techniques sont possibles selon les actifs.

## 13. Table `incident_chronologie`

Chronologie append-only :

```text
id
reference
incident_id
instant_fait
instant_enregistrement
type_entree_reference
auteur_reference
source_reference
resume_minimal
classification
preuve_reference
correlation_id
causalite_id
empreinte_precedente
empreinte
```

Règles :

- aucun `UPDATE` ni `DELETE` ;
- ordre déterministe par instant d’enregistrement puis identifiant ;
- l’instant du fait peut être antérieur mais jamais réécrire l’ordre ;
- une entrée tardive porte `SAISIE_TARDIVE` ;
- les contradictions sont conservées et résolues par une nouvelle entrée ;
- la chaîne d’empreintes ne remplace pas `CAP-CORE-015` ;
- un checkpoint signé peut être produit périodiquement.

Types minimaux :

```text
SIGNAL_RECU
TRIAGE_COMMENCE
INCIDENT_CONFIRME
SEVERITE_EVOLUEE
ROLE_ATTRIBUE
IMPACT_CONSTATE
ACTION_DEMANDEE
ACTION_EXECUTEE
ACTION_ECHOUEE
DECISION_PRISE
COMMUNICATION_EMISE
CONFINEMENT_ATTEINT
ERADICATION_ATTEINTE
RETABLISSEMENT_ATTEINT
SURVEILLANCE_COMMENCEE
RECURRENCE_DETECTEE
RESOLUTION_DECLAREE
CLOTURE_VALIDEE
INCIDENT_ROUVERT
```

## 14. Table `incident_action`

Colonnes :

```text
id
reference
incident_id
categorie_reference
titre_court
objectif
capacite_cible_reference
produit_cible_reference
contrat_reference
contrat_version
operation_reference
ressource_reference
priorite_reference
etat
cree_par_reference
assignee_reference
decision_reference
preuve_requise
idempotency_key
echeance
cree_le
commence_le
termine_le
```

États :

```text
PROPOSEE
AUTORISEE
PRETE
EN_COURS
EN_ATTENTE
EXECUTEE
VERIFIEE
ECHOUEE
ANNULEE
COMPENSEE
```

Une action exécutée n’est pas automatiquement vérifiée.

## 15. Table `incident_action_tentative`

Colonnes :

```text
id
incident_action_id
numero_tentative
demandee_le
demandee_par_reference
commencee_le
terminee_le
resultat
code_erreur
message_minimal
correlation_id
preuve_reference
```

Règles :

- nombre maximal de tentatives gouverné ;
- pas de nouvelle tentative automatique pour une opération non idempotente ;
- erreur sensible expurgée ;
- corrélation avec le contrat cible ;
- pas d’appel arbitraire hors opération enregistrée.

## 16. Table `incident_action_accuse`

Colonnes :

```text
id
incident_action_id
producteur_reference
statut
resultat_reference
instant_effet
ressource_reference
preuve_reference
empreinte_resultat
recu_le
correlation_id
```

Un accusé contradictoire :

- ne remplace pas le précédent ;
- ouvre une divergence ;
- peut réélever la sévérité ;
- exige un rapprochement explicite.

## 17. Table `incident_communication`

Colonnes :

```text
id
reference
incident_id
type_reference
audience_reference
canal_reference
classification
statut
resume_minimal
modele_reference
version_modele
prepare_par_reference
valide_par_reference
decision_reference
preuve_reference
preparee_le
validee_le
emise_le
correlation_id
```

Types :

```text
INTERNE_EQUIPE
INTERNE_DIRECTION
SATELLITE_AFFECTE
UTILISATEURS_AFFECTES
PARTENAIRE
FOURNISSEUR
PUBLIQUE
REGULATEUR_REFERENCE
```

La première version ne doit pas intégrer de canal réglementaire propre à un pays sans étude séparée.

Une communication publique exige validation et n’est jamais émise automatiquement.

## 18. Tables de liaisons

### `incident_preuve`

```text
incident_id
preuve_reference
role_preuve
classification
ajoutee_par_reference
ajoutee_le
```

Rôles :

```text
DETECTION
CONFIRMATION
IMPACT
ACTION
RETABLISSEMENT
CLOTURE
REVUE
```

### `incident_decision`

```text
incident_id
decision_reference
role_decision
ajoutee_le
```

### `incident_risque`

```text
incident_id
risque_reference
role_liaison
reevaluation_requise
```

### `incident_exception`

```text
incident_id
exception_reference
affectee
suspendue
revoquee
motif
```

Une exception potentiellement liée à la cause doit être réévaluée ; elle ne reste pas active par défaut.

## 19. Table `incident_relation`

Relations minimales :

```text
CAUSE_POSSIBLE_DE
CAUSE_CONFIRMEE_DE
CONSEQUENCE_DE
DOUBLON_DE
RECURRENCE_DE
REMPLACE
REGROUPE_DANS
LIE_A
```

Contraintes :

- pas de relation vers soi-même ;
- pas de cycle pour `DOUBLON_DE` et `REGROUPE_DANS` ;
- une cause confirmée exige une preuve ou un motif d’analyse ;
- la causalité peut rester `INDETERMINEE` ;
- le système n’invente pas une cause pour permettre la clôture.

## 20. Table `incident_retablissement`

Colonnes :

```text
id
reference
incident_id
actif_reference
critere_reference
valeur_attendue
valeur_constatee
unite_reference
fenetre_observation
resultat
source_reference
preuve_reference
verifie_par_reference
verifie_le
expire_le
```

Critères possibles :

```text
SERVICE_DISPONIBLE
INTEGRITE_VERIFIEE
CLE_ROTATION_EFFECTIVE
SESSIONS_REVOQUEES
SAUVEGARDE_RESTAURABLE
JOURNAL_COHERENT
EVENEMENTS_RESORBES
CONTRAT_COMPATIBLE
SURVEILLANCE_STABLE
```

Un critère expiré doit être revérifié avant clôture.

## 21. Table `incident_revue`

Colonnes :

```text
id
reference
incident_id
statut
portee
faits_confirmes
faits_incertains
cause_statut
cause_reference
ce_qui_a_fonctionne
ce_qui_a_echoue
impact_final
controle_manquant
risque_reference
preparee_par_reference
validee_par_reference
decision_reference
preuve_reference
ouverte_le
terminee_le
```

Statuts :

```text
A_PREPARER
EN_COURS
A_VALIDER
VALIDEE
```

La revue doit distinguer faits, hypothèses et inconnues.

## 22. Table `incident_lecon`

Colonnes :

```text
id
reference
incident_id
categorie_reference
enseignement
applicable_a_reference
priorite_reference
preuve_reference
cree_le
```

Une leçon ne modifie pas automatiquement une politique ou un contrat.

## 23. Table `incident_action_corrective`

Colonnes :

```text
id
reference
incident_id
lecon_id
proprietaire_reference
capacite_cible_reference
contrat_reference
operation_reference
echeance
etat
risque_reference
decision_reference
preuve_reference
terminee_le
```

États :

```text
PROPOSEE
ACCEPTEE
EN_COURS
BLOQUEE
TERMINEE
VERIFIEE
ANNULEE
```

Une action corrective durable peut devenir un traitement de risque dans `CAP-CORE-017`.

## 24. Table `incident_idempotence`

Pour les commandes sensibles :

```text
cle
operation_reference
acteur_reference
incident_reference
empreinte_requete
statut
reponse_reference
cree_le
expire_le
```

La même clé avec une requête différente retourne `409`.

## 25. Table `incident_projection_publique`

Désactivée par défaut.

Champs possibles :

```text
incident_reference
titre_public
resume_public
etat_public
impact_public
debut_public
retablissement_public
mise_a_jour_le
preuve_publique_reference
publie_par_reference
```

Interdictions :

- identité d’un utilisateur ;
- détail d’une vulnérabilité exploitable ;
- clé ou jeton ;
- adresse interne ;
- topologie ;
- pièce ou journal brut ;
- spéculation présentée comme un fait.

## 26. Contraintes PostgreSQL

Mettre en place :

- clés étrangères internes ;
- contraintes `CHECK` ;
- index sur référence, état, sévérité, realm, organisation, produit et dates ;
- unicité des références ;
- index partiels sur incidents ouverts ;
- triggers append-only sur chronologie ;
- contrôle d’absence de cycle pour relations structurantes ;
- verrouillage transactionnel lors des transitions critiques ;
- migrations réentrantes et transactionnelles.

Les références externes ne deviennent pas nécessairement des clés étrangères interbases ; elles sont validées par les services souverains avant écriture.

## 27. Compatibilité SQLite

SQLite doit permettre :

- création du schéma ;
- tests de comportement ;
- vérification des transitions ;
- déduplication ;
- garde append-only ;
- export/import contrôlé.

Toute différence avec PostgreSQL doit être documentée et couverte par l’exercice PostgreSQL réel.

## 28. Rétention

Proposition initiale :

```text
signaux non rattachés et bruit : 365 jours
charges minimales expirables : 90 jours selon classification
chronologie : 10 ans minimum ou politique active
incidents clos : conservation gouvernée
preuves : selon CAP-CORE-015
communications : selon classification et finalité
```

La purge :

- ne supprime jamais la référence d’un incident ;
- ne casse pas une preuve ou un paquet de clôture ;
- conserve un marqueur de purge ;
- est autorisée, tracée et testée ;
- ne s’exécute pas si la politique de rétention est indisponible.

## 29. Sauvegarde et restauration

Le magasin rejoint `CAP-CORE-019`.

La restauration doit préserver :

- références ;
- séquences ;
- chronologie ;
- relations ;
- idempotence encore valide ;
- états d’actions ;
- preuves liées ;
- clôtures et réouvertures.

Après restauration :

1. vérifier l’intégrité du dump ;
2. vérifier les chaînes append-only ;
3. comparer avec les événements `CAP-CORE-014` plus récents ;
4. réconcilier sans écraser silencieusement ;
5. produire une preuve de restauration ;
6. ouvrir un incident si une divergence non résolue existe.

## 30. Invariants de données

```text
INV-INC-001 : une référence d’incident est immuable
INV-INC-002 : un signal ne confirme jamais seul un incident
INV-INC-003 : chaque état courant provient du dernier cycle valide
INV-INC-004 : aucune chronologie n’est modifiée ou supprimée
INV-INC-005 : aucune action n’appelle une opération hors contrat actif
INV-INC-006 : aucune clôture sans revue validée
INV-INC-007 : aucune clôture sans critère de rétablissement vérifié
INV-INC-008 : aucune baisse de sévérité sans motif et preuve
INV-INC-009 : aucun parent de realm n’accède implicitement aux enfants
INV-INC-010 : aucune donnée secrète dans le magasin
INV-INC-011 : aucune communication publique automatique
INV-INC-012 : aucune exception active n’est présumée sûre après incident
INV-INC-013 : aucune décision n’est antidatée
INV-INC-014 : un accusé contradictoire ouvre une divergence
INV-INC-015 : une restauration ne ressuscite pas un état périmé sans rapprochement
```
