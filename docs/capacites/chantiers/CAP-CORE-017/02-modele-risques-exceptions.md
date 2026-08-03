# CAP-CORE-017 — Modèle des risques et exceptions

## 1. Principes du modèle

Le modèle doit permettre de répondre de manière datée à quatre questions :

```text
Quel risque est connu ?
Comment a-t-il été évalué ?
Que fait-on pour le traiter ?
Quelle exception exacte était active à cet instant ?
```

Les données historisées ne sont jamais réécrites silencieusement.

Une modification importante produit une nouvelle révision ou une nouvelle évaluation.

---

## 2. Table `risque`

Champs minimaux :

```text
id
reference                 unique, RSK-...
titre
organisation_reference
realm_reference
proprietaire_reference
categorie_reference
classification_reference
criticite_courante
etat_courant
source_reference
cree_le
cree_par
mis_a_jour_le
```

Règles :

- `reference` immuable ;
- organisation et realm obligatoires ;
- propriétaire obligatoire avant soumission ;
- aucune description complète de vulnérabilité exploitable dans les champs de liste ;
- `criticite_courante` dérivée de la dernière évaluation valide, jamais saisie librement ;
- l’état courant est résolu depuis le cycle, pas modifié directement.

---

## 3. Table `risque_revision`

Champs minimaux :

```text
id
risque_id
numero_revision
scenario
cause
menace_reference
actif_reference
consequence
hypotheses_json
perimetre_json
classification_reference
source_reference
preuve_reference
cree_le
cree_par
gele_le
```

Une révision gelée est immuable.

Une nouvelle révision est obligatoire lorsque changent :

- le scénario ;
- le périmètre ;
- la cause principale ;
- les conséquences ;
- les actifs concernés ;
- la classification ;
- les hypothèses structurantes.

---

## 4. Table `risque_cycle`

États minimaux :

```text
PREPARATION
A_EVALUER
OUVERT
SOUS_TRAITEMENT
SOUS_SURVEILLANCE
ACCEPTE
FERME
REOUVERT
ARCHIVE
```

Transitions principales :

```text
PREPARATION → A_EVALUER
A_EVALUER → OUVERT
OUVERT → SOUS_TRAITEMENT
OUVERT → SOUS_SURVEILLANCE
OUVERT → ACCEPTE
SOUS_TRAITEMENT → SOUS_SURVEILLANCE
SOUS_TRAITEMENT → FERME
SOUS_SURVEILLANCE → SOUS_TRAITEMENT
SOUS_SURVEILLANCE → ACCEPTE
ACCEPTE → REOUVERT
FERME → REOUVERT
REOUVERT → SOUS_TRAITEMENT
FERME → ARCHIVE
```

Règles :

- `ACCEPTE` exige une décision lorsque la politique le demande ;
- `FERME` exige une évaluation résiduelle et une preuve de traitement ;
- un risque ne peut pas être archivé avec une exception active ;
- la fermeture ne supprime rien ;
- une matérialisation ultérieure peut rouvrir le risque.

Champs :

```text
id
risque_id
etat
date_effet
motif
acteur_reference
decision_reference
preuve_reference
correlation_id
```

---

## 5. Table `methode_evaluation_risque`

Le registre doit porter la référence et la version de la méthode utilisée.

Champs minimaux :

```text
id
reference
version
libelle
modele_reference
niveaux_vraisemblance_json
niveaux_impact_json
regle_calcul_json
seuils_json
source_reference
etat
active_le
retiree_le
empreinte
```

La méthode ne doit pas être modifiable après activation.

Toute nouvelle méthode est une nouvelle version.

Une évaluation historique conserve toujours la version exacte de sa méthode.

---

## 6. Table `evaluation_risque`

Champs minimaux :

```text
id
reference                 unique, EVA-RSK-...
risque_id
risque_revision_id
methode_reference
methode_version
type_evaluation            INHERENTE ou RESIDUELLE
vraisemblance_reference
impact_reference
score
niveau_reference
justification
hypotheses_json
controle_snapshot_json
evalue_le
evaluateur_reference
prochaine_revue_le
preuve_reference
empreinte
```

Règles :

- le score est calculé par le moteur ;
- l’API ne reçoit jamais directement `score` ou `niveau` comme vérité ;
- une évaluation résiduelle exige des contrôles vérifiés ;
- une évaluation n’est jamais modifiée ;
- une erreur produit une nouvelle évaluation rectificative liée ;
- la prochaine revue est obligatoire pour un risque non fermé.

---

## 7. Dimensions d’impact

Dimensions candidates à confirmer dans `CAP-CORE-010` :

```text
CONFIDENTIALITE
INTEGRITE
DISPONIBILITE
AUTHENTICITE
TRACABILITE
CONTINUITE
FINANCIER
REGLEMENTAIRE
REPUTATION
UTILISATEURS
ECOSYSTEME
```

Créer `evaluation_impact_dimension` :

```text
id
evaluation_id
dimension_reference
niveau_reference
justification
```

Le niveau global est calculé selon la méthode active ; il n’est pas nécessairement une simple moyenne.

---

## 8. Table `controle_risque`

Référence un contrôle réellement présent :

```text
id
reference
risque_id
exigence_reference
capacite_reference
produit_reference
realm_reference
environnement_reference
etat
niveau_efficacite_reference
verifie_le
verifie_par
preuve_reference
expire_le
```

Règles :

- pas de contrôle fictif ;
- une déclaration non vérifiée ne réduit pas automatiquement le risque résiduel ;
- un contrôle expiré n’est plus compté comme efficace ;
- une preuve peut provenir de `CAP-CORE-015` ;
- l’exigence doit être une référence exacte.

---

## 9. Table `traitement_risque`

Champs minimaux :

```text
id
reference                 TRT-RSK-...
risque_id
strategie                  EVITER, REDUIRE, TRANSFERER, ACCEPTER, SURVEILLER
objectif
responsable_reference
date_debut
echeance
etat
priorite_reference
decision_reference
preuve_reference
```

États :

```text
PREVU
EN_COURS
BLOQUE
TERMINE
ABANDONNE
EN_RETARD
```

`ACCEPTER` n’est pas un raccourci :

- évaluation résiduelle requise ;
- justification requise ;
- durée ou date de revue requise ;
- décision requise selon seuil ;
- surveillance requise.

---

## 10. Table `action_traitement_risque`

Champs minimaux :

```text
id
reference                 ACT-RSK-...
traitement_id
libelle
contrat_reference
contrat_version
operation_reference
cible_reference
responsable_reference
echeance
etat
idempotency_key
preuve_attendue_reference
preuve_realisee_reference
terminee_le
```

La capacité ne modifie jamais directement la base d’une autre capacité.

Une action technique est exécutée par la capacité propriétaire via un contrat actif.

---

## 11. Table `revue_risque`

Champs minimaux :

```text
id
reference
risque_id
prevue_le
realisee_le
reviseur_reference
resultat
motif
nouvelle_evaluation_reference
nouveau_traitement_reference
decision_reference
preuve_reference
```

Résultats :

```text
INCHANGE
AGGRAVE
REDUIT
A_REEVALUER
A_FERMER
A_REOUVRIR
```

Une revue en retard déclenche une alerte et un événement minimal.

---

## 12. Table `lien_risque`

Permet les relations :

```text
CAUSE_DE
CONTRIBUE_A
DEPEND_DE
AGGRAVE
REDUIT
REMPLACE
DUPLIQUE_DE
MATERIALISE_PAR_INCIDENT
```

Champs :

```text
id
risque_source_id
relation_reference
risque_cible_id
incident_reference
preuve_reference
cree_le
```

Les cycles interdits doivent être détectés pour `REMPLACE` et `DUPLIQUE_DE`.

---

# Partie exceptions

## 13. Table `demande_exception`

Champs minimaux :

```text
id
reference                 EXC-...
titre
organisation_reference
realm_reference
produit_reference
capacite_reference
environnement_reference
demandeur_reference
proprietaire_reference
risque_reference
motif
classification_reference
souhaitee_du
souhaitee_au
cree_le
```

Règles :

- risque associé obligatoire ;
- durée demandée bornée ;
- pas de date de début rétroactive ;
- demandeur et décideur séparés lorsque la politique l’exige ;
- une demande n’est jamais une exception active.

---

## 14. Table `exception_exigence`

Une exception peut cibler plusieurs exigences seulement lorsqu’elles sont explicitement liées au même scénario et au même périmètre.

Champs :

```text
id
demande_exception_id
exigence_reference
source_capacite_reference
contrat_reference
contrat_version
operation_reference
derogeable_snapshot
condition_snapshot_json
```

Règles :

- `derogeable_snapshot=false` bloque la soumission ;
- référence exacte obligatoire ;
- aucune expression générique ;
- la version de politique ou contrat est conservée ;
- une mise à jour future de la politique ne change pas silencieusement l’historique.

---

## 15. Table `exception_perimetre`

Champs minimaux :

```text
id
demande_exception_id
sujet_reference
ressource_reference
organisation_reference
realm_reference
produit_reference
capacite_reference
environnement_reference
operation_reference
finalite_reference
```

Au moins un sujet, une ressource et une opération doivent être précisément définis.

Les portées globales sont refusées, sauf type explicitement prévu par politique et décision renforcée ; cette possibilité ne doit pas être livrée au premier passage `GO`.

---

## 16. Table `mesure_compensatoire`

Champs minimaux :

```text
id
reference                 MCO-...
demande_exception_id
libelle
exigence_reference
responsable_reference
date_debut
echeance
frequence_verification
etat
preuve_attendue_reference
derniere_preuve_reference
derniere_verification_le
expire_le
```

États :

```text
PREVUE
ACTIVE
INEFFICACE
EXPIREE
RETIRÉE
```

Une mesure obligatoire absente, expirée ou inefficace empêche l’activation ou suspend l’exception selon la politique.

---

## 17. Table `evaluation_exception`

Champs :

```text
id
reference
demande_exception_id
evaluation_risque_reference
risque_sans_exception_reference
risque_avec_exception_reference
risque_apres_compensation_reference
compatibilite_politique
conditions_satisfaites
conclusion
justification
evaluateur_reference
evalue_le
preuve_reference
```

Conclusions :

```text
FAVORABLE
FAVORABLE_SOUS_CONDITIONS
DEFAVORABLE
INCOMPLETE
```

Une conclusion favorable ne vaut pas approbation.

---

## 18. Table `exception_decision`

Champs :

```text
id
demande_exception_id
decision_reference
resultat_snapshot
prise_le
valide_a_partir_de
valide_jusqu_au
preuve_reference
verifiee_le
```

Règles :

- décision `CAP-CORE-008` obligatoire ;
- résultat compatible avec l’état demandé ;
- dates de la décision ne peuvent élargir le périmètre ;
- la durée active est la plus courte entre demande, politique et décision ;
- une décision annulée ou remplacée est réévaluée immédiatement.

---

## 19. Table `exception_cycle`

États :

```text
PREPARATION
SOUMISE
EN_EVALUATION
EN_DECISION
REFUSEE
APPROUVEE
ACTIVE
SUSPENDUE
EXPIREE
REVOQUEE
REMPLACEE
CLOTUREE
```

Transitions principales :

```text
PREPARATION → SOUMISE
SOUMISE → EN_EVALUATION
EN_EVALUATION → EN_DECISION
EN_EVALUATION → PREPARATION
EN_DECISION → REFUSEE
EN_DECISION → APPROUVEE
APPROUVEE → ACTIVE
ACTIVE → SUSPENDUE
SUSPENDUE → ACTIVE
ACTIVE → EXPIREE
ACTIVE → REVOQUEE
SUSPENDUE → EXPIREE
APPROUVEE → REVOQUEE
ACTIVE → REMPLACEE
EXPIREE → CLOTUREE
REFUSEE → CLOTUREE
REVOQUEE → CLOTUREE
```

Règles :

- `APPROUVEE` signifie que la décision existe ;
- `ACTIVE` signifie que date, conditions et mesures sont réellement valides ;
- l’expiration est automatique et irréversible ;
- un renouvellement crée une nouvelle demande et une nouvelle décision ;
- aucune réactivation d’une exception expirée ;
- aucune réécriture de l’historique.

---

## 20. Table `usage_exception`

Enregistre uniquement l’usage minimal nécessaire au contrôle, sans dupliquer tout l’audit.

Champs :

```text
id
reference
exception_id
consommateur_reference
contrat_reference
operation_reference
ressource_reference
correlation_id
utilisee_le
resultat
preuve_audit_reference
```

Résultats :

```text
RESOLUE_ACTIVE
REFUSEE_HORS_PERIMETRE
REFUSEE_EXPIREE
REFUSEE_SUSPENDUE
REFUSEE_NON_DEROGEABLE
REFUSEE_CONDITIONS
```

La volumétrie et la rétention doivent être bornées. Le journal complet reste `CAP-CORE-013`.

---

## 21. Contraintes transversales

- références uniques ;
- dates UTC ;
- clés étrangères internes ;
- références externes vérifiées via services, jamais par jointure interbase ;
- concurrence optimiste ou verrouillage explicite pour transitions sensibles ;
- idempotency keys sur commandes ;
- `CHECK` pour états, scores et dates ;
- date de fin strictement postérieure à la date de début ;
- aucune colonne de secret ;
- aucune pièce binaire ;
- aucun HTML non nettoyé ;
- limites de longueur et de taille JSON ;
- classification obligatoire ;
- corrélation et preuve sur transitions critiques.

---

## 22. Projection publique

Aucun risque ou détail d’exception n’est public par défaut.

Une projection publique éventuelle doit être explicitement autorisée et limitée à :

```text
reference
titre neutralisé
organisation ou produit public
niveau agrégé
état
mesure générale
prochaine revue
```

Ne jamais publier :

- détails d’exploitation ;
- chemins internes ;
- vulnérabilités exploitables ;
- mesures compensatoires précises ;
- secrets ;
- identités non publiques ;
- positions individuelles ;
- motifs confidentiels.
