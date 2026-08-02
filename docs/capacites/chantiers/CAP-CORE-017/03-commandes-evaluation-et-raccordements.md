# CAP-CORE-017 — Commandes, évaluations et raccordements

## 1. Bootstrap initial

Créer une ressource versionnée :

```text
core/registre-risques/resources/bootstrap-risques-v1.json
```

Le bootstrap peut contenir uniquement :

- les méthodes de cotation explicitement adoptées ;
- les catégories et dimensions déjà confirmées ;
- les références de statuts et stratégies ;
- les exigences dérogeables ou non dérogeables réellement lues depuis les registres actifs ;
- les risques déjà constatés et validés par un responsable compétent.

Le bootstrap ne doit pas transformer automatiquement :

- chaque avertissement documentaire ;
- chaque erreur de test ;
- chaque `TODO` ;
- chaque limite historique ;
- chaque mention du mot « risque »

en risque actif.

Commande :

```text
php artisan gamad:risques-bootstrap
```

Options minimales :

```text
--dry-run
--verify-only
--force-reference
--source=<reference>
```

Le bootstrap doit être :

- idempotent ;
- signé ou protégé par empreinte ;
- refusé si le contenu diverge d’une version déjà appliquée ;
- traçable ;
- sans secret ;
- compatible PostgreSQL et SQLite.

---

## 2. Commandes du registre des risques

Interface métier minimale :

```text
creerRisque
modifierBrouillonRisque
ajouterRevisionRisque
soumettreRisqueEvaluation
evaluerRisqueInherent
declarerControleRisque
verifierControleRisque
evaluerRisqueResiduel
assignerProprietaireRisque
creerTraitementRisque
ajouterActionTraitement
demarrerTraitement
marquerActionTerminee
marquerTraitementBloque
terminerTraitement
planifierRevueRisque
realiserRevueRisque
accepterRisque
fermerRisque
reouvrirRisque
archiverRisque
lierRisques
```

Chaque commande reçoit au minimum :

```text
acteur
organisation
realm
finalite
politique
mandat
correlation_id
idempotency_key
```

Les commandes sensibles exigent une preuve ou une décision selon la politique.

---

## 3. Création d’un risque

Entrée minimale :

```json
{
  "titre": "Indisponibilité prolongée du fournisseur de secrets",
  "organisation": "ORG-...",
  "realm": "RLM-...",
  "categorie": "CONTINUITE",
  "scenario": "...",
  "cause": "...",
  "consequence": "...",
  "actifs": ["CAP-CORE-016"],
  "source": "SRC-...",
  "classification": "INTERNE"
}
```

La création ne doit pas accepter :

- un score ;
- un niveau final ;
- un état `ACCEPTE` ;
- une décision fictive ;
- une exception déjà active.

Le risque commence en `PREPARATION`.

---

## 4. Évaluation

### Évaluation inhérente

L’évaluateur fournit :

- les réponses structurées de vraisemblance ;
- les impacts par dimension ;
- les hypothèses ;
- les sources ;
- les preuves disponibles.

Le moteur calcule :

- score ;
- niveau ;
- seuil dépassé ;
- obligations de traitement ;
- nécessité éventuelle d’une décision.

### Évaluation résiduelle

Elle exige :

- une évaluation inhérente valide ;
- les contrôles existants ;
- leur efficacité ;
- une date de vérification ;
- des preuves encore valides ;
- la même méthode ou une migration explicitement justifiée.

Un contrôle déclaré mais non vérifié ne réduit pas le score.

### Recalcul

Une nouvelle méthode active ne réécrit pas les anciennes évaluations.

Une commande distincte peut produire une nouvelle évaluation comparative :

```text
reevaluerAvecMethode(risque, methode, version)
```

---

## 5. Résolution du niveau courant

Requête :

```text
resoudreRisque(reference, date?)
```

Retour minimal :

```text
reference
revision
etat
organisation
realm
proprietaire
niveau_inherent
niveau_residuel
methode
prochaine_revue
traitements_ouverts
exceptions_actives
preuves
```

Une donnée absente est déclarée absente ; le service ne fabrique jamais un niveau par défaut.

---

## 6. Traitements et actions

Une action de traitement peut être :

```text
MANUELLE
CONTRACTUELLE
VERIFICATION
SURVEILLANCE
MIGRATION
ROTATION
RESTAURATION
FORMATION
```

Pour une action contractuelle :

- contrat `CAP-CORE-009` actif ;
- version exacte ;
- opération exacte ;
- cible exacte ;
- schéma d’entrée validé ;
- autorisation `CAP-CORE-004` ;
- décision `CAP-CORE-008` si requise ;
- idempotence ;
- accusé d’exécution ;
- preuve `CAP-CORE-015` si critique.

`CAP-CORE-017` n’exécute aucune commande SQL dans la base de la capacité cible.

---

## 7. Acceptation d’un risque

Commande :

```text
accepterRisque(reference, evaluation_residuelle, decision, jusqu_au)
```

Conditions minimales :

- évaluation résiduelle courante ;
- propriétaire actif ;
- seuil de tolérance résolu ;
- décision formelle lorsque requise ;
- date de prochaine revue ;
- absence de traitement obligatoire non pris en compte ;
- preuve vérifiable ;
- aucun contrôle non dérogeable contourné.

L’acceptation :

- ne ferme pas automatiquement le risque ;
- n’efface aucun traitement ;
- n’empêche pas une réévaluation ;
- expire ou doit être revue selon politique ;
- ne vaut pas exception.

---

# Commandes d’exception

## 8. Interface minimale

```text
creerDemandeException
modifierDemandeException
ajouterExigenceException
definirPerimetreException
ajouterMesureCompensatoire
evaluerDemandeException
soumettreExceptionDecision
rattacherDecisionException
activerException
suspendreException
reprendreException
revoquerException
expirerExceptionsEchues
cloreException
creerDemandeRenouvellement
resoudreException
verifierConditionsException
enregistrerUsageException
```

---

## 9. Création d’une demande d’exception

Entrée minimale :

```text
titre
motif
risque associé
exigence exacte
organisation
realm
produit ou capacité
environnement
sujet
ressource
opération
finalité
date de début souhaitée
date de fin souhaitée
mesures compensatoires
```

Refus immédiats :

- exigence inconnue ;
- exigence non dérogeable ;
- référence approchée ;
- périmètre global ;
- date rétroactive ;
- durée supérieure au maximum ;
- risque absent ;
- absence de mesure obligatoire ;
- secret détecté ;
- demandeur non autorisé.

---

## 10. Évaluation d’une exception

L’évaluation doit comparer :

```text
risque actuel sans exception
risque créé ou aggravé par l’exception
réduction apportée par les mesures compensatoires
risque résiduel final
seuil de tolérance
conditions de la politique
```

Le service retourne :

```text
conclusion
conditions manquantes
mesures insuffisantes
niveau résiduel
seuil dépassé
mode de décision requis
preuve requise
```

Il ne retourne jamais `PERMIS`.

---

## 11. Décision d’exception

La demande est transmise à `CAP-CORE-008` via un contrat actif.

Le dossier de décision doit inclure au minimum :

- question exacte ;
- exigence ciblée ;
- justification ;
- périmètre ;
- durée ;
- risque associé ;
- évaluations ;
- mesures compensatoires ;
- conséquences d’un refus ;
- conditions d’arrêt ;
- proposition d’effet ;
- preuve attendue.

Après décision, `rattacherDecisionException` vérifie :

- référence ;
- résultat ;
- autorité ;
- mandat à la date de prise ;
- organisation ;
- realm ;
- périmètre ;
- dates ;
- preuve ;
- absence d’annulation ou de remplacement incompatible.

Aucune simple chaîne `APPROUVEE` fournie par l’appelant ne suffit.

---

## 12. Activation

Une exception approuvée n’est active que si :

- la date de début est atteinte ;
- la date de fin n’est pas dépassée ;
- la décision est valide ;
- toutes les mesures obligatoires sont actives ;
- les preuves ne sont pas expirées ;
- l’exigence reste dérogeable ;
- la politique active permet ce type d’exception ;
- l’organisation, le produit et le realm sont actifs ;
- aucune révocation n’existe ;
- aucune décision de remplacement ne s’y oppose.

Une activation produit :

- transition `ACTIVE` ;
- audit ;
- événement minimal ;
- preuve si requise ;
- recalcul du prochain contrôle ;
- planification de l’expiration.

---

## 13. Résolution d’une exception

Requête interne :

```text
resoudreException(
  exigence,
  sujet,
  ressource,
  operation,
  organisation,
  realm,
  produit,
  capacite,
  environnement,
  finalite,
  date
)
```

Retour :

```json
{
  "trouvee": true,
  "active": true,
  "exception": "EXC-...",
  "exigence": "REQ-...",
  "perimetre_conforme": true,
  "conditions_conformes": true,
  "valide_du": "...",
  "valide_au": "...",
  "decision": "DEC-...",
  "risque": "RSK-...",
  "mesures": ["MCO-..."],
  "preuve": "PRV-..."
}
```

Ou un refus précis :

```text
EXCEPTION_ABSENTE
EXCEPTION_HORS_PERIMETRE
EXCEPTION_NON_ACTIVE
EXCEPTION_EXPIREE
EXCEPTION_SUSPENDUE
EXCEPTION_REVOQUEE
EXIGENCE_NON_DEROGEABLE
MESURE_COMPENSATOIRE_INVALIDE
DECISION_INVALIDE
DEPENDANCE_INDISPONIBLE
```

Cette réponse est un fait gouverné, pas une permission.

La capacité consommatrice transmet ce fait à sa politique `CAP-CORE-007`, puis `CAP-CORE-004` rend la décision d’autorisation.

---

## 14. Expiration automatique

Commande planifiée :

```text
php artisan gamad:risques-verifier-echeances
```

Fréquence minimale :

```text
toutes les 5 minutes en production
```

La résolution à la volée doit néanmoins vérifier directement la date ; elle ne dépend pas uniquement du planificateur.

Le planificateur :

- expire les exceptions échues ;
- suspend celles dont une mesure obligatoire est invalide ;
- détecte les revues en retard ;
- détecte les traitements en retard ;
- publie les événements ;
- crée les alertes ;
- reste idempotent ;
- ne prolonge jamais une exception.

---

## 15. Renouvellement

Une exception expirée ou proche de l’expiration n’est jamais prolongée par mise à jour de sa date.

Commande :

```text
creerDemandeRenouvellement(exception_source)
```

Elle crée :

- une nouvelle référence ;
- une nouvelle évaluation ;
- une nouvelle vérification des mesures ;
- une nouvelle décision ;
- une nouvelle période ;
- un lien `REMPLACE` ou `RENOUVELLE`.

L’ancienne exception reste immuable.

---

## 16. Suspension et révocation

### Suspension

Temporaire, lorsqu’une condition n’est plus remplie :

- mesure compensatoire expirée ;
- preuve indisponible ;
- dépendance suspendue ;
- revue urgente ;
- doute sur le périmètre.

La reprise exige une nouvelle vérification complète.

### Révocation

Définitive pour cette exception :

- décision de révocation ;
- incident grave ;
- abus ;
- extension de périmètre constatée ;
- information initiale fausse ;
- exigence devenue non dérogeable ;
- risque résiduel devenu inacceptable.

Une exception révoquée ne peut pas être réactivée.

---

## 17. Intégration CAP-CORE-004

Le moteur d’autorisation ne doit pas appeler automatiquement le registre des risques pour toutes les actions.

Chaque politique qui tient compte d’une exception doit le déclarer explicitement :

```text
exception_admise = true
exigence_reference = ...
resolution_exception_obligatoire = true
non_derogeable = false
```

L’évaluation exacte doit porter :

- référence d’exception ;
- décision ;
- preuve ;
- périmètre ;
- expiration ;
- mesures ;
- date de résolution.

Sans résolution valide : refus par défaut.

---

## 18. Intégration CAP-CORE-007

Créer ou compléter :

```text
POL-RISQUES-EXCEPTIONS-V1
```

La politique doit porter :

- actions autorisées ;
- rôles ;
- niveaux d’assurance ;
- seuils ;
- durées maximales ;
- exigences dérogeables ;
- exigences non dérogeables ;
- séparation des fonctions ;
- conditions d’acceptation ;
- preuves exigées ;
- périodicités de revue.

`CAP-CORE-017` conserve un snapshot de la politique utilisée mais ne la remplace pas.

---

## 19. Intégration CAP-CORE-008

Décisions minimales :

```text
ACCEPTER_RISQUE
REFUSER_ACCEPTATION_RISQUE
APPROUVER_EXCEPTION
REFUSER_EXCEPTION
RENOUVELER_EXCEPTION
REVOQUER_EXCEPTION
IMPOSER_TRAITEMENT
CLOTURER_RISQUE_CRITIQUE
```

Chaque décision produit un effet contractuel, jamais une écriture directe interbase.

---

## 20. Intégration CAP-CORE-013

Auditer au minimum :

```text
RISQUE_CREE
RISQUE_REVISE
RISQUE_EVALUE
CONTROLE_RISQUE_DECLARE
CONTROLE_RISQUE_VERIFIE
TRAITEMENT_RISQUE_CREE
ACTION_TRAITEMENT_TERMINEE
RISQUE_ACCEPTE
RISQUE_FERME
RISQUE_REOUVERT
DEMANDE_EXCEPTION_CREEE
EXIGENCE_EXCEPTION_AJOUTEE
MESURE_COMPENSATOIRE_AJOUTEE
EXCEPTION_EVALUEE
EXCEPTION_SOUMISE_DECISION
DECISION_EXCEPTION_RATTACHEE
EXCEPTION_ACTIVEE
EXCEPTION_SUSPENDUE
EXCEPTION_REPRISE
EXCEPTION_EXPIREE
EXCEPTION_REVOQUEE
USAGE_EXCEPTION_REFUSE
PAQUET_RISQUE_EXPORTE
```

Aucune trace ne contient :

- secret ;
- clé privée ;
- jeton ;
- détail exploitable non nécessaire ;
- pièce complète.

---

## 21. Intégration CAP-CORE-014

Événements minimaux :

```text
RISQUE_OUVERT
NIVEAU_RISQUE_CHANGE
TRAITEMENT_RISQUE_EN_RETARD
REVUE_RISQUE_ECHUE
RISQUE_ACCEPTE
RISQUE_FERME
RISQUE_REOUVERT
DEMANDE_EXCEPTION_SOUMISE
EXCEPTION_APPROUVEE
EXCEPTION_ACTIVEE
EXCEPTION_EXPIRATION_PROCHE
EXCEPTION_SUSPENDUE
EXCEPTION_EXPIREE
EXCEPTION_REVOQUEE
MESURE_COMPENSATOIRE_INEFFICACE
```

Les charges transportent des références minimales, jamais la description complète d’une vulnérabilité.

---

## 22. Intégration CAP-CORE-015

Créer des paquets vérifiables pour :

- évaluation critique ;
- acceptation de risque ;
- exception approuvée ;
- exception renouvelée ;
- exception révoquée ;
- fermeture de risque critique.

Le paquet inclut :

```text
références
révision
evaluation
méthode
périmètre
décision
conditions
mesures
validité
empreinte
signature
```

Il n’inclut aucune clé privée ni document externe complet.

---

## 23. Intégration CAP-CORE-018 future

Préparer les contrats sans coder `CAP-CORE-018` :

```text
lierIncidentRisque
signalerMaterialisationRisque
reevaluerApresIncident
signalerExceptionContributrice
```

La fiche `CAP-CORE-018` décidera du comportement final.

Aucun faux module incident ne doit être créé dans ce chantier.
