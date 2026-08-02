# CAP-CORE-008 — Commandes, instruction, décision et exécution

## 1. Service principal

Créer `RegistreDecisions` avec des commandes explicites, transactionnelles et idempotentes lorsque cela est possible.

Aucune méthode générique du type :

```text
executer(string $action, array $donnees)
```

Les commandes doivent porter le vocabulaire réel du domaine.

---

## 2. Création du dossier

### `ouvrirDossier()`

Entrées minimales :

```text
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
source_principale_reference
contrat_reference facultatif
correlation_id
```

Vérifications :

- identité connue ;
- organisation active ;
- realm actif ;
- type, mode, finalité et domaine connus de `CAP-CORE-010` ;
- ressource réellement résolue par son propriétaire ;
- source active et utilisable pour la finalité ;
- autorisation `ouvrir-dossier-decision` ;
- absence de secret ;
- objet et résumé bornés ;
- aucun dossier actif identique si une clé d’idempotence est fournie.

Résultat :

- création en `PREPARATION` ;
- référence stable ;
- trace d’audit ;
- aucun événement externe tant que le dossier n’est pas soumis.

---

## 3. Définition de la question

### `definirQuestion()`

Autorisé seulement en `PREPARATION` ou `EN_INSTRUCTION` avant gel.

Vérifications :

- structure conforme ;
- action proposée canonique ;
- ressource cohérente avec le dossier ;
- aucune condition exécutable ;
- aucun secret ;
- taille bornée ;
- empreinte calculée.

Une révision de question ne supprime pas l’ancienne.

---

## 4. Gestion des options

### `ajouterOption()`

### `retirerOptionAvantGel()`

### `ordonnerOptions()`

Règles :

- références uniques ;
- code canonique facultatif ;
- libellé et description bornés ;
- retrait seulement avant `PRETE_A_DECIDER` ;
- l’historique du retrait est conservé ;
- l’option « ne rien faire » est requise lorsque le type de décision le prévoit ;
- aucune option sélectionnée avant la décision finale.

---

## 5. Gestion des pièces

### `ajouterPiece()`

### `classerPiece()`

### `retirerPieceAvantGel()`

Vérifications :

- source ou preuve existante ;
- finalité compatible ;
- realm compatible ;
- classification compatible avec le lecteur ;
- référence externe bornée ;
- aucune URL libre téléchargée ;
- aucune copie du contenu ;
- preuve obligatoire pour une pièce déclarée critique.

Une pièce retirée reste visible dans l’historique avec son motif.

---

## 6. Participants et compétence

### `ajouterParticipant()`

Entrées :

```text
identite
organisation
role
instant_competence
compte_dans_quorum
```

Le service appelle `CAP-CORE-003` pour résoudre :

- fonction ;
- mandat ;
- délégation éventuelle ;
- limites ;
- date de début ;
- date de fin ;
- organisation ;
- realm ou périmètre.

Puis il enregistre uniquement les références et le résultat de compétence.

### `reverifierCompetence()`

Obligatoire :

- avant passage `PRETE_A_DECIDER` ;
- immédiatement avant adoption ;
- après tout changement de date de décision ;
- après événement de révocation de mandat reçu de `CAP-CORE-014`.

Une compétence devenue invalide n’efface pas les positions déjà exprimées, mais ces positions ne comptent plus pour l’adoption future.

### `retirerParticipantAvantGel()`

Possible avant le gel ; l’historique reste conservé.

---

## 7. Soumission

### `soumettreDossier()`

Préconditions :

- état `PREPARATION` ;
- question présente ;
- type, mode, domaine et finalité valides ;
- organisation et realm actifs ;
- ressource résolue ;
- source principale valide ;
- au moins une autorité candidate ;
- politique d’instruction applicable ;
- décision CAP-CORE-004 permettant la soumission ;
- preuve d’audit enregistrable.

Effets :

- état `SOUMISE` ;
- publication `DOSSIER_DECISION_SOUMIS` dans `CAP-CORE-014` ;
- ouverture de l’instruction ;
- aucune décision finale créée.

Idempotence : même dossier et même clé de soumission retournent la même transition.

---

## 8. Ouverture de l’instruction

### `ouvrirInstruction()`

Passe de `SOUMISE` à `EN_INSTRUCTION`.

Enregistre :

- instructeur ;
- mandat ;
- politique ;
- échéance d’instruction ;
- pièces attendues ;
- participants attendus ;
- corrélation.

L’instructeur ne devient pas automatiquement décideur.

---

## 9. Demande d’avis

### `demanderAvis()`

Crée ou active un participant `CONSULTE`.

Le contrat précise :

- question ;
- périmètre ;
- date limite ;
- pièces autorisées ;
- classification ;
- format de réponse ;
- finalité.

Aucun envoi de dossier complet à un satellite.

### `enregistrerAvis()`

L’avis est :

- structuré ;
- lié à une identité ;
- horodaté ;
- protégé par une preuve si critique ;
- non compté comme vote sauf rôle décideur distinct.

---

## 10. Expression d’une position

### `exprimerPosition()`

Préconditions :

- dossier `EN_INSTRUCTION` ou `PRETE_A_DECIDER` tant que la séance n’est pas close ;
- participant connu ;
- identité authentifiée ;
- rôle compatible ;
- mandat encore valide ;
- position canonique ;
- option connue lorsque nécessaire ;
- autorisation CAP-CORE-004 ;
- motif obligatoire selon la position.

Effets :

- nouvelle ligne append-only ;
- position précédente conservée ;
- audit ;
- événement minimal facultatif, sans divulguer le vote si la politique le restreint.

Le premier chantier ne supporte pas le vote secret.

---

## 11. Calcul du quorum

Créer `CalculateurQuorum`.

Entrées :

- participants compétents ;
- positions finales ;
- mode ;
- règle de quorum canonique ;
- instant de décision.

Règles initiales :

```text
AUTORITE_UNIQUE
- exactement une autorité compétente
- une position finale

MAJORITE_SIMPLE
- présents compétents >= seuil de présence
- POUR > CONTRE

MAJORITE_ABSOLUE
- POUR > moitié de tous les décideurs compétents attendus

UNANIMITE
- tous les décideurs compétents ont voté POUR

PRISE_ACTE
- autorité compétente
- fait et preuve présents
```

Les règles viennent de `CAP-CORE-010` et sont paramétrées par une politique active, pas par une expression dynamique stockée.

Le résultat du calcul contient :

```text
quorum_attendu
quorum_present
quorum_atteint
voix_pour
voix_contre
abstentions
participants_incompetents
positions_absentes
motifs_de_blocage
```

---

## 12. Préparation à décider

### `declarerPretADecider()`

Préconditions :

- instruction ouverte ;
- question complète ;
- options conformes ;
- pièces obligatoires présentes ;
- sources et preuves vérifiables ;
- participants établis ;
- compétence revérifiée ;
- quorum potentiellement atteignable ;
- effets proposés conformes aux contrats ;
- conditions structurées ;
- aucune divergence critique non résolue.

Effets :

- état `PRETE_A_DECIDER` ;
- gel de la question, des options, des pièces, des participants attendus, des conditions et des effets proposés ;
- calcul d’une empreinte provisoire ;
- aucune adoption automatique.

Pour modifier ensuite le dossier :

- rouvrir l’instruction avec motif ;
- créer une nouvelle révision ;
- invalider le gel précédent ;
- conserver tout l’historique.

---

## 13. Adoption par autorité unique

### `adopterParAutorite()`

Entrées :

```text
decision_reference
option_reference
resultat
motif_principal
prise_le
valide_a_partir_de
expire_le facultatif
identite_decideur
idempotency_key
```

Préconditions :

- état `PRETE_A_DECIDER` ;
- mode `AUTORITE_UNIQUE` ou `PRISE_D_ACTE` ;
- autorité authentifiée ;
- mandat valide à `prise_le` ;
- CAP-CORE-004 permet `adopter-decision` ;
- résultat compatible avec le type ;
- option compatible ;
- conditions cohérentes ;
- effets conformes ;
- horodatage dans la tolérance ;
- fournisseur de preuve disponible.

Transaction :

1. verrouiller le dossier ;
2. revérifier absence de résultat ;
3. revérifier compétence ;
4. figer le paquet canonique ;
5. calculer l’empreinte ;
6. demander une preuve signée à `CAP-CORE-015` ;
7. enregistrer le résultat ;
8. enregistrer les motifs ;
9. passer `DECIDEE` ;
10. écrire l’audit ;
11. préparer l’outbox événementielle.

Aucun résultat ne doit être enregistré si la preuve obligatoire échoue.

---

## 14. Adoption collégiale

### `adopterDecisionCollegiale()`

Préconditions supplémentaires :

- mode `COLLEGIALE` ;
- quorum atteint ;
- positions finales figées ;
- règle de majorité satisfaite ;
- président ou autorité de clôture compétente ;
- aucune position postérieure à l’instant de clôture ;
- calcul reproductible.

Le résultat adopté doit dériver du calcul.

Une surcharge manuelle du résultat est interdite.

Les éventuels dissentiments et réserves sont intégrés au paquet de décision.

---

## 15. Rejet, ajournement et sans suite

### `rejeterDecision()`

Crée un résultat `REFUSEE` avec motif obligatoire.

Aucun effet exécutable.

### `ajournerDecision()`

Crée un résultat `AJOURNEE` avec :

- motif ;
- conditions de réexamen ;
- date de réexamen facultative ;
- aucune modification future du résultat.

Un nouveau dossier peut découler de la décision ajournée.

### `classerSansSuite()`

Résultat `SANS_SUITE`, réservé aux cas prévus par politique.

Une simple inactivité ne classe jamais automatiquement sans suite.

---

## 16. Mise en vigueur

### `mettreEnVigueur()`

Sépare la prise de décision de son entrée en vigueur.

Préconditions :

- décision `DECIDEE` ;
- résultat compatible ;
- date d’effet atteinte ;
- conditions préalables satisfaites ;
- décision non annulée, remplacée ou expirée ;
- preuves toujours vérifiables ;
- autorisation CAP-CORE-004 ;
- décision formelle supplémentaire si la matrice l’exige.

Effets :

- état `EN_VIGUEUR` ;
- effets `EN_ATTENTE` ou `BLOQUE_CONDITION` passent `PRET` selon les conditions ;
- événement `DECISION_MISE_EN_VIGUEUR` ;
- aucun appel direct aux bases cibles.

Une mise en vigueur immédiate peut être réalisée dans la même transaction logique que l’adoption, mais les deux faits restent distincts et auditables.

---

## 17. Exécution des effets

Créer un orchestrateur borné `ExecuterEffetsDecision`.

Il ne contient pas de logique métier cible.

Pour chaque effet `PRET` :

1. résoudre le contrat actif ;
2. vérifier producteur et consommateur ;
3. revérifier la décision et sa preuve ;
4. vérifier l’idempotency key ;
5. appeler l’adaptateur contractuel de la capacité cible ;
6. enregistrer le résultat minimal ;
7. enregistrer une preuve si requise ;
8. publier l’événement de résultat ;
9. avancer l’état global.

Modes initiaux :

```text
SYNCHRONE_INTERNE
ASYNCHRONE_EVENEMENT
MANUEL_ATTESTE
```

### SYNCHRONE_INTERNE

Appel d’un service applicatif du monolithe, jamais d’un dépôt SQL étranger.

### ASYNCHRONE_EVENEMENT

Publication d’un événement contractuel ; l’accusé d’exécution revient plus tard.

### MANUEL_ATTESTE

Une opération humaine ou externe est confirmée par une preuve et un accusé structuré.

---

## 18. Accusé d’exécution

### `accuserExecution()`

Entrées :

```text
effet_reference
resultat
code_resultat
preuve_reference
evenement_reference
idempotency_key
correlation_id
```

Vérifications :

- effet connu ;
- appelant déclaré dans le contrat ;
- realm et organisation compatibles ;
- preuve valide ;
- idempotency key exacte ;
- résultat compatible ;
- aucun secret ;
- aucune réponse complète.

Un accusé identique est idempotent.

Un accusé contradictoire est refusé et diagnostiqué.

---

## 19. Rapprochement des effets

### `rapprocherEffets()`

Compare :

- effets attendus ;
- accusés reçus ;
- état réel lisible depuis les capacités propriétaires ;
- preuves ;
- événements ;
- échéances.

Résultats :

```text
COHERENT
PARTIEL
EN_RETARD
CONTRADICTOIRE
INVERIFIABLE
```

Le rapprochement ne modifie pas l’état métier cible.

Il peut :

- enregistrer un diagnostic ;
- émettre une alerte opérationnelle ;
- proposer une relance ;
- ouvrir plus tard un risque ou incident via les capacités concernées.

---

## 20. Nouvelle tentative

### `relancerEffet()`

Autorisée seulement pour :

```text
ECHEC_TEMPORAIRE
```

Exige :

- politique de reprise ;
- délai minimal ;
- plafond de tentatives ;
- autorisation ;
- décision supplémentaire si l’effet est sensible et que la matrice l’exige.

Un `ECHEC_DEFINITIF` ne se relance pas sans nouvelle décision.

---

## 21. Compensation

### `demanderCompensation()`

Une compensation est un nouvel effet contractuel, lié à la décision initiale.

Elle exige :

- opération de compensation déclarée ;
- motif ;
- preuve ;
- autorisation ;
- éventuellement nouvelle décision formelle.

Le registre ne « rollback » jamais directement les bases d’autres capacités.

---

## 22. Expiration

### `expirerDecision()`

Possible lorsque :

- `expire_le` est atteint ;
- décision non déjà annulée ou remplacée ;
- politique d’expiration applicable.

Effets :

- aucun nouvel effet ne démarre ;
- effets déjà exécutés restent historiques ;
- effets non exécutés passent `ANNULE` ou restent visibles comme non exécutés selon la politique ;
- événement d’expiration ;
- aucune inversion automatique d’un effet passé.

Une inversion exige une décision compensatoire.

---

## 23. Annulation

### `annulerParDecision()`

L’annulation d’une décision exige une nouvelle décision adoptée de type `ANNULATION`.

Vérifications :

- décision cible existante ;
- compétence ;
- motif ;
- effets déjà exécutés inventoriés ;
- compensations éventuelles ;
- absence de cycle ;
- preuve signée.

La décision annulée passe `ANNULEE` mais reste intégralement lisible.

---

## 24. Remplacement

### `remplacerParDecision()`

La nouvelle décision :

- référence l’ancienne ;
- indique les dispositions remplacées ;
- définit sa date d’effet ;
- traite les effets en cours ;
- possède sa propre preuve.

L’ancienne passe `REMPLACEE` à la date d’effet de la nouvelle.

Aucune réécriture de l’ancienne.

---

## 25. Rectification

### `rectifierParDecision()`

Réservée à une erreur matérielle clairement démontrée.

La rectification :

- ne change pas silencieusement le sens ;
- identifie le champ erroné ;
- fournit la valeur correcte ;
- fournit la preuve de l’erreur ;
- produit une nouvelle décision liée ;
- ne réécrit pas le paquet original.

Un changement de fond est un remplacement, pas une rectification.

---

## 26. Paquet de décision

### `genererPaquetDecision()`

Dérive un paquet depuis le registre :

```text
manifest.json
question.json
options.json
participants.json
positions.json
resultat.json
motifs.json
conditions.json
effets.json
preuves.json
verification.json
README.txt
```

Règles :

- JSON canonique ;
- manifeste signé par `CAP-CORE-015` ;
- aucune clé privée ;
- aucun secret ;
- pièces externes référencées, non téléchargées par défaut ;
- classification respectée ;
- export public distinct et minimisé ;
- paquet reproductible pour une même décision.

---

## 27. Vérification d’un paquet

### `verifierPaquetDecision()`

Vérifie :

- structure ;
- manifeste ;
- empreintes ;
- preuve ;
- clé et version historique ;
- autorité ;
- mandat à la date ;
- quorum ;
- cohérence du résultat ;
- contrats des effets ;
- liens d’annulation et remplacement ;
- état actuel et état à la date demandée.

Résultat structuré :

```text
INTEGRE
SIGNATURE_VALIDE
AUTORITE_VALIDE_A_LA_DATE
QUORUM_VALIDE
EN_VIGUEUR_A_LA_DATE
EFFETS_COHERENTS
AVERTISSEMENTS
DIVERGENCES
```

Une signature valide ne masque jamais une divergence métier.

---

## 28. Requêtes

Créer au minimum :

```text
resoudreDecision(reference, date?)
listerDecisions(filtres)
resoudreHistorique(reference)
resoudreQuestion(reference)
resoudreOptions(reference)
resoudreParticipants(reference)
resoudrePositions(reference)
resoudreResultat(reference)
resoudreConditions(reference)
resoudreEffets(reference)
resoudreExecutions(reference)
resoudreDecisionsConcernant(ressource)
resoudreDecisionsParOrganisation(organisation)
resoudreDecisionsParRealm(realm)
resoudreDecisionsEnAttente()
resoudreEffetsEnRetard()
resoudreDecisionExigee(operation, ressource, date)
diagnostiquerRegistre()
```

Toutes les lectures appliquent l’autorisation, la classification et l’isolation des realms.

---

## 29. Bootstrap

Créer :

```text
php artisan core:decisions:bootstrap
```

Le bootstrap inscrit :

- `POL-DECISIONS-V1` ;
- actions d’autorisation ;
- types, modes, résultats, positions et liens dans `CAP-CORE-010` ;
- contrats internes et HTTP dans `CAP-CORE-009` ;
- schémas d’événements dans `CAP-CORE-014` ;
- types de preuve dans `CAP-CORE-015` ;
- matrice initiale d’exigences de décision ;
- aucun dossier de décision fictif.

Le fichier bootstrap est versionné et protégé par empreinte.

Il est idempotent.

Une divergence provoque un refus explicite, jamais un écrasement silencieux.

---

## 30. Commandes Artisan

Prévoir :

```text
core:decisions:bootstrap
core:decisions:diagnostiquer
core:decisions:verifier {reference}
core:decisions:rapprocher-effets [--reference=]
core:decisions:expirer
core:decisions:exporter {reference} [--public]
core:decisions:importer-paquet {fichier} --verification-seule
```

L’import de paquet ne crée jamais une décision canonique étrangère sans procédure de reconnaissance explicite.

Par défaut, `importer-paquet` vérifie seulement.

---

## 31. Pannes et fermeture sûre

- registre indisponible : aucune décision formelle ;
- CAP-CORE-003 indisponible : aucune adoption ;
- CAP-CORE-004 indisponible : aucune opération ;
- CAP-CORE-006 indisponible : aucune adoption exigeant une source ;
- CAP-CORE-009 indisponible : aucun effet nouveau ;
- CAP-CORE-012 indisponible : aucune décision inter-realm ;
- CAP-CORE-015 indisponible : aucune décision critique adoptée ;
- CAP-CORE-014 indisponible : outbox conservée, décision locale possible seulement si publication différable selon politique ;
- journal d’audit indisponible : aucune écriture ;
- cible métier indisponible : décision conservée, effet en échec temporaire ;
- accusé ambigu : état contradictoire, pas de succès présumé.

---

## 32. Cas d’intégration obligatoires

### Cas A — Suspension d’un produit

1. ouvrir un dossier ;
2. instruire ;
3. vérifier le mandat ;
4. adopter ;
5. signer ;
6. mettre en vigueur ;
7. transmettre l’effet à `CAP-CORE-011` ;
8. recevoir l’accusé ;
9. vérifier l’état produit ;
10. marquer l’effet exécuté.

### Cas B — Rejet

1. soumettre une proposition ;
2. exprimer des positions ;
3. rejeter ;
4. produire la preuve ;
5. constater qu’aucun effet n’est créé.

### Cas C — Mandat expiré

1. préparer une décision ;
2. laisser expirer le mandat ;
3. tenter l’adoption ;
4. constater le refus ;
5. renouveler ou déléguer via `CAP-CORE-003` ;
6. revérifier ;
7. adopter seulement avec autorité valide.

### Cas D — Annulation

1. exécuter une décision ;
2. adopter une décision d’annulation ;
3. identifier les effets déjà produits ;
4. exiger une compensation explicite ;
5. conserver les deux décisions et leurs preuves.

### Cas E — Décision inter-realm

1. viser deux realms ;
2. tenter sans franchissement ;
3. constater le refus ;
4. établir le franchissement dans `CAP-CORE-012` ;
5. vérifier les mandats ;
6. adopter et exécuter dans la portée autorisée uniquement.
