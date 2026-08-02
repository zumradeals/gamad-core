# CAP-CORE-008 — Modèle de données et cycle

## 1. Principes du modèle

Le modèle doit permettre :

- de préparer une décision sans la confondre avec une décision prise ;
- de conserver l’autorité et le mandat vérifiés à la date pertinente ;
- de distinguer les options examinées du résultat adopté ;
- de représenter une décision individuelle ou collégiale ;
- de figer le dossier au moment de l’adoption ;
- d’attacher plusieurs effets à une même décision ;
- de suivre séparément la mise en vigueur et l’exécution ;
- de remplacer ou annuler sans réécriture ;
- de produire une preuve technique portable ;
- de diagnostiquer les décisions incomplètes, contradictoires ou non exécutées.

Le registre est relationnel, persistant et en ajout seul pour tout fait adopté.

---

## 2. Tables principales

Créer au minimum :

```text
decision_dossier
decision_cycle
decision_question
decision_option
decision_piece
decision_participant
decision_position
decision_resultat
decision_motif
decision_condition
decision_effet
decision_execution
decision_lien
decision_exigence
decision_preuve
decision_projection
```

Les noms exacts peuvent être adaptés aux conventions du dépôt, mais toutes les responsabilités doivent exister explicitement.

---

## 3. `decision_dossier`

Champs minimaux :

```text
id
reference
organisation_reference
realm_reference
type_reference
mode_reference
finalite_reference
domaine_reference
ressource_type
ressource_reference
objet
resume
propose_par_identite
propose_par_organisation
source_principale_reference
contrat_reference
contrat_version
correlation_id
cree_le
```

### Référence

Format :

```text
DEC-<ULID ou identifiant aléatoire ordonnable>
```

La référence est :

- unique ;
- immuable ;
- non réutilisable ;
- sans signification métier cachée ;
- sûre à exposer dans les contrats et événements.

### Ressource concernée

`ressource_type` et `ressource_reference` identifient l’objet principal :

```text
PRODUIT / PRD-...
POLITIQUE / POL-...
CONTRAT_VERSION / CTR-...@2.0.0
REALM / RLM-...
SECRET_VERSION / SEC-...@3
RISQUE / RSK-...
INCIDENT / INC-...
REJEU / RPJ-...
RESTAURATION / RST-...
CAPACITE / CAP-CORE-...
```

Aucune validation polymorphe aveugle : chaque type connu possède un résolveur explicite.

### Domaine

Le domaine indique la famille opérationnelle :

```text
GOUVERNANCE
SECURITE
CONTINUITE
CONTRATS
PRODUITS
REALMS
EVENEMENTS
RISQUES
INCIDENTS
EXPLOITATION
```

Il vient de `CAP-CORE-010`.

---

## 4. `decision_cycle`

Journal en ajout seul :

```text
id
decision_id
etat
date_effet
acteur_reference
mandat_reference
preuve_reference
motif
correlation_id
cree_le
```

### États

```text
PREPARATION
SOUMISE
EN_INSTRUCTION
PRETE_A_DECIDER
DECIDEE
EN_VIGUEUR
EXECUTION_PARTIELLE
EXECUTEE
EXPIREE
ANNULEE
REMPLACEE
RETIREE_AVANT_DECISION
```

### Transitions autorisées

```text
PREPARATION
→ SOUMISE
→ EN_INSTRUCTION
→ PRETE_A_DECIDER
→ DECIDEE
→ EN_VIGUEUR
→ EXECUTION_PARTIELLE
→ EXECUTEE
```

Branches :

```text
PREPARATION → RETIREE_AVANT_DECISION
SOUMISE → RETIREE_AVANT_DECISION
EN_INSTRUCTION → RETIREE_AVANT_DECISION

DECIDEE → EXPIREE
EN_VIGUEUR → EXPIREE
EXECUTION_PARTIELLE → EXPIREE

DECIDEE → ANNULEE
EN_VIGUEUR → ANNULEE
EXECUTION_PARTIELLE → ANNULEE
EXECUTEE → ANNULEE

DECIDEE → REMPLACEE
EN_VIGUEUR → REMPLACEE
EXECUTION_PARTIELLE → REMPLACEE
EXECUTEE → REMPLACEE
```

Une décision `DECIDEE` peut avoir un résultat `APPROUVEE`, `REFUSEE`, `AJOURNEE`, `PRISE_ACTE` ou `SANS_SUITE`.

Seules les décisions `APPROUVEE` ou `PRISE_ACTE` peuvent normalement passer `EN_VIGUEUR`, sauf type explicitement différent.

---

## 5. `decision_question`

Champs :

```text
id
decision_id
version_question
texte_structure
empreinte
cree_le
```

La question doit être structurée et bornée.

Exemple :

```json
{
  "action_proposee": "SUSPENDRE_PRODUIT",
  "ressource": "PRD-GAMAD-003",
  "motif_court": "clé de fédération compromise",
  "effet_demande": "suspension immédiate"
}
```

Interdictions :

- texte illimité ;
- HTML actif ;
- script ;
- condition exécutable ;
- secret ;
- document complet ;
- données personnelles excessives.

Une seule version de question peut être courante avant soumission.

Après `SOUMISE`, toute modification crée une nouvelle révision et renvoie le dossier à `EN_INSTRUCTION` si nécessaire.

Après `PRETE_A_DECIDER`, la question est figée.

---

## 6. `decision_option`

Champs :

```text
id
decision_id
reference
code_reference
libelle
description
effet_resume
ordre_affichage
retenue
empreinte
cree_le
```

Exemples :

```text
OPT-1 — Ne rien changer
OPT-2 — Suspendre pendant 24 heures
OPT-3 — Suspendre jusqu’à rotation de la clé
OPT-4 — Retirer définitivement le produit
```

Règles :

- au moins deux options pour une vraie délibération, sauf type `PRISE_ACTE` ;
- l’option « ne rien faire » doit être explicite lorsque pertinente ;
- une seule option retenue pour un résultat `APPROUVEE` ;
- aucune option retenue pour `REFUSEE` si la question porte sur une proposition unique ;
- options immuables après `PRETE_A_DECIDER` ;
- aucune option ne contient du code ou une requête SQL.

---

## 7. `decision_piece`

Champs :

```text
id
decision_id
role_piece
type_reference
reference_externe
source_reference
preuve_reference
contrat_reference
obligatoire
classification_reference
ajoutee_par
ajoutee_le
```

Rôles :

```text
SOURCE
RAPPORT
AVIS
PREUVE
CONTRAT
POLITIQUE
RISQUE
INCIDENT
TEST
RESTAURATION
AUTRE_REFERENCEE
```

Le registre ne copie pas le contenu de la pièce.

Il vérifie :

- source active ;
- preuve lisible ;
- classification compatible ;
- realm compatible ;
- finalité compatible ;
- absence de secret dans les métadonnées.

---

## 8. `decision_participant`

Champs :

```text
id
decision_id
identite_reference
organisation_reference
role_reference
mandat_reference
delegation_reference
competence_verifiee_le
competence_valide
motif_incompetence
compte_dans_quorum
cree_le
```

Rôles :

```text
DECIDEUR
PRESIDENT
RAPPORTEUR
CONSULTE
OBSERVATEUR
EXECUTEUR
VERIFICATEUR
```

Règles :

- un participant est une identité canonique ;
- l’organisation est explicite ;
- le mandat est résolu par `CAP-CORE-003` ;
- un mandat expiré ne compte jamais dans le quorum ;
- un observateur ne vote pas ;
- un consulté peut donner un avis sans décider ;
- un exécuteur n’est pas automatiquement décideur ;
- un participant ne peut pas être ajouté après la décision, sauf comme exécuteur d’un effet et dans une table distincte.

---

## 9. `decision_position`

Champs :

```text
id
decision_id
participant_id
option_id
position_reference
motif
preuve_reference
exprimee_le
correlation_id
```

Positions initiales :

```text
POUR
CONTRE
ABSTENTION
RESERVE
AVIS_FAVORABLE
AVIS_DEFAVORABLE
SANS_AVIS
```

Règles :

- une position finale par participant décideur ;
- nouvelle position avant clôture = nouvelle ligne, la dernière valide compte ;
- aucune modification après décision ;
- vote secret non pris en charge dans la première version ;
- motif obligatoire pour `RESERVE` ;
- position d’un participant incompétent conservée comme avis, jamais comptée ;
- chaque expression produit une trace `CAP-CORE-013`.

---

## 10. `decision_resultat`

Champs :

```text
id
decision_id
resultat_reference
option_retenue_id
prise_par_identite
prise_par_organisation
mandat_reference
mode_reference
quorum_attendu
quorum_atteint
voix_pour
voix_contre
abstentions
prise_le
prise_le_systeme
valide_a_partir_de
expire_le
empreinte_dossier
preuve_reference
cree_le
```

Une seule ligne de résultat par décision.

`prise_le` est la date déclarée de décision.

`prise_le_systeme` est l’horodatage serveur fiable.

Tolérance d’horloge bornée ; aucune antidate arbitraire.

### Autorité unique

- `prise_par_identite` obligatoire ;
- mandat valide à `prise_le` ;
- quorum non applicable ;
- position explicite obligatoire.

### Collégiale

- participants décideurs connus ;
- quorum calculé ;
- méthode de calcul figée ;
- positions figées ;
- résultat calculé de manière déterministe ;
- toute dérogation au calcul normal exige une nouvelle décision spécifique, pas un champ libre.

### Prise d’acte

- fait constaté référencé ;
- source et preuve obligatoires ;
- aucun effet métier implicite.

---

## 11. `decision_motif`

Champs :

```text
id
decision_id
type_motif
texte
source_reference
preuve_reference
ordre
cree_le
```

Types :

```text
PRINCIPAL
SECONDAIRE
DISSENTIMENT
RESERVE
URGENCE
CORRECTION
```

Règles :

- motif principal obligatoire ;
- texte borné ;
- aucun secret ;
- aucun contenu exécutable ;
- pas de simple « décision du dirigeant » sans fondement ;
- les motifs de dissentiment sont conservés ;
- les motifs ne peuvent pas être réécrits après adoption.

---

## 12. `decision_condition`

Champs :

```text
id
decision_id
reference
type_reference
description
echeance
responsable_reference
preuve_attendue_type
contrat_operation_reference
etat_reference
cree_le
```

Types initiaux :

```text
PREALABLE
SUSPENSIVE
RESOLUTOIRE
SUIVI
EXPIRATION
COMPENSATION
```

Le registre ne doit pas exécuter une condition textuelle.

Une condition est vérifiée par :

- un accusé explicite ;
- une preuve `CAP-CORE-015` ;
- un événement contractuel `CAP-CORE-014` ;
- une requête explicite vers la capacité propriétaire.

États :

```text
A_VERIFIER
SATISFAITE
NON_SATISFAITE
EXPIREE
ANNULEE
```

---

## 13. `decision_effet`

Champs :

```text
id
decision_id
reference
capacite_cible
produit_cible
contrat_reference
contrat_version
operation_reference
action_reference
ressource_type
ressource_reference
parametres_canoniques
empreinte_parametres
ordre_execution
obligatoire
compensation_operation_reference
etat_reference
cree_le
```

Règles :

- capacité cible xor produit cible ;
- contrat actif obligatoire ;
- opération déclarée dans `CAP-CORE-009` ;
- paramètres validés par le schéma du contrat ;
- aucun secret ;
- aucune commande shell ;
- aucune URL libre ;
- aucun SQL ;
- aucun accès direct à une base ;
- ordre explicite si plusieurs effets ;
- compensation décrite par une opération contractuelle, jamais par du code stocké.

États :

```text
EN_ATTENTE
BLOQUE_CONDITION
PRET
EN_EXECUTION
EXECUTE
ECHEC_TEMPORAIRE
ECHEC_DEFINITIF
ANNULE
COMPENSE
```

---

## 14. `decision_execution`

Journal en ajout seul :

```text
id
effet_id
tentative
executeur_reference
capacite_executrice
produit_executeur
correlation_id
idempotency_key
resultat_reference
code_resultat
message_borne
preuve_reference
evenement_reference
execute_le
cree_le
```

Résultats :

```text
ACCEPTEE
DEJA_EXECUTEE
REFUSEE
ECHEC_TEMPORAIRE
ECHEC_DEFINITIF
COMPENSEE
```

Règles :

- idempotency key stable par effet ;
- aucune réponse métier complète ;
- aucune stack trace ;
- aucune donnée sensible ;
- preuve de résultat obligatoire pour un effet critique ;
- `DEJA_EXECUTEE` est un succès idempotent si la ressource est dans l’état attendu ;
- un accusé contradictoire déclenche un diagnostic.

---

## 15. `decision_lien`

Champs :

```text
id
decision_source_id
decision_cible_id
type_reference
motif
preuve_reference
cree_le
```

Types :

```text
REMPLACE
ANNULE
RECTIFIE
COMPLETE
CONFIRME
PROLONGE
RESTREINT
EXECUTE
DECOULE_DE
```

Contraintes :

- pas de cycle `REMPLACE` ;
- pas d’auto-lien ;
- une décision remplacée ne redevient jamais en vigueur ;
- annulation et remplacement exigent une nouvelle décision adoptée ;
- rectification ne change jamais silencieusement les effets passés.

---

## 16. `decision_exigence`

Cette table indique quelles opérations exigent une décision formelle.

Champs :

```text
id
reference
contrat_reference
contrat_version
operation_reference
action_reference
type_decision_requis
mode_minimal
preuve_requise
quorum_reference
organisation_reference
realm_reference
active_du
active_au
source_reference
cree_le
```

Règles :

- versionnée ou historisée ;
- aucune wildcard universelle ;
- activation gouvernée ;
- source obligatoire ;
- `CAP-CORE-004` autorise sa gestion ;
- la présence d’une exigence ne donne aucune permission ;
- absence d’exigence signifie seulement qu’une décision formelle n’est pas imposée par ce registre.

---

## 17. `decision_preuve`

Champs :

```text
id
decision_id
role_reference
preuve_reference
empreinte_attendue
verifiee_le
resultat_verification
cree_le
```

Rôles :

```text
DOSSIER_FIGE
RESULTAT_SIGNE
PAQUET_EXPORTABLE
EFFET_EXECUTE
ANNULATION
REMPLACEMENT
```

Aucune signature ni clé privée dans cette table.

Toutes les preuves viennent de `CAP-CORE-015`.

---

## 18. `decision_projection`

Champs :

```text
id
decision_id
type_reference
format_reference
contenu_ou_reference
empreinte
preuve_reference
generee_le
```

Projections :

```text
JSON_CANONIQUE
RAPPORT_MARKDOWN
PAQUET_PREUVE
RECU_EXECUTION
RESUME_PUBLIC
```

La projection n’est jamais la source canonique.

Une projection publique doit être explicitement minimisée et autorisée.

---

## 19. Contraintes temporelles

- `valide_a_partir_de` ne précède pas arbitrairement `prise_le` ;
- `expire_le` est postérieur à `valide_a_partir_de` ;
- mandat valide à `prise_le` ;
- source valide à la date d’instruction pertinente ;
- contrat actif au moment de l’exécution ou version explicitement figée ;
- preuve vérifiable historiquement avec la version de clé active au moment de la signature ;
- décision expirée ne produit pas de nouvel effet ;
- effet déjà exécuté n’est pas annulé rétroactivement sans décision compensatoire.

---

## 20. Contraintes SQL

Prévoir au minimum :

- unicité des références ;
- une seule ligne `decision_resultat` par décision ;
- une seule option retenue ;
- XOR capacité/produit cible ;
- clés étrangères internes ;
- triggers refusant `UPDATE` et `DELETE` sur résultat, motifs, options, positions et effets après décision ;
- index sur organisation, realm, type, ressource, état, date de prise et effets non exécutés ;
- verrou transactionnel lors de l’adoption ;
- exclusion de deux résultats concurrents ;
- prévention de cycles de remplacement.

---

## 21. Canonicalisation du dossier

Le dossier figé contient exactement :

```text
reference
organisation
realm
type
mode
finalite
domaine
question
options
participants valides
positions finales
pièces référencées
résultat
motifs
conditions
effets
prise_le
valide_a_partir_de
expire_le
```

Les clés sont triées.

Les listes dont l’ordre n’est pas métier sont triées par référence.

Les dates sont normalisées en UTC ISO 8601.

Les nombres sont représentés sans ambiguïté.

Les champs absents et `null` ne doivent pas produire des empreintes différentes sans raison documentée.

La canonicalisation utilise le service commun défini par `CAP-CORE-015` lorsqu’il existe.

---

## 22. Invariants centraux

1. aucune décision sans organisation ;
2. aucune décision sans realm ;
3. aucune décision adoptée sans autorité compétente ;
4. aucun décideur compté sans mandat valide ;
5. aucune décision adoptée sans motif principal ;
6. aucune décision adoptée sans empreinte figée ;
7. aucune décision critique sans preuve signée ;
8. aucune décision collégiale sans quorum déterministe ;
9. aucun effet sans contrat actif ;
10. aucun effet exécuté directement par le registre ;
11. aucune réécriture après adoption ;
12. aucune annulation sans nouvelle décision ;
13. aucune décision inter-realm implicite ;
14. aucune donnée secrète ;
15. aucune suppression destructive ;
16. aucune décision ne vaut permission ;
17. aucune permission ne vaut décision ;
18. aucune preuve technique ne vaut vérité juridique automatique.
