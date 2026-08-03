# CAP-CORE-021 — Modèle des demandes, résultats, segments et activations

## 1. Principes du modèle

Le magasin du Matching conserve uniquement ce qui est nécessaire pour :

- prouver la demande ;
- reproduire l’exécution ;
- expliquer le résultat ;
- protéger et expirer les segments ;
- contrôler les activations ;
- traiter une contestation ;
- mesurer la qualité.

Il ne devient pas une copie durable des dossiers métier des sources.

## 2. Tables principales

Créer au minimum :

```text
matching_contexte
matching_profil_execution
matching_profil_critere
matching_demande
matching_objet
matching_critere_demande
matching_signal
matching_execution
matching_candidat
matching_evaluation_critere
matching_resultat
matching_facteur
matching_segment
matching_segment_membre
matching_activation
matching_activation_mesure
matching_contestation
matching_reexamen
matching_jeu_evaluation
matching_comparaison_politique
matching_cycle
matching_outbox
```

Chaque nom final respecte les conventions existantes du dépôt.

## 3. `matching_contexte`

Représente un domaine d’usage autorisé.

Champs minimaux :

```text
reference
code_canonique
nom
finalite
politique_reference
politique_version
classification
supervision_humaine
score_autorise
segment_autorise
activation_autorisee
mesure_autorisee
etat
valide_depuis
valide_jusqua
source_reference
preuve_reference
created_at
```

Contraintes :

- référence immuable ;
- une seule version active par contexte et realm compatible ;
- aucun contexte actif sans politique `CAP-CORE-007` ;
- aucune activation si `activation_autorisee=false` ;
- aucune production de score si le contexte n’autorise qu’une qualification booléenne ou catégorielle.

## 4. `matching_profil_execution`

Projection compilée d’une politique active.

Champs :

```text
reference
contexte_reference
politique_reference
politique_version
contrat_reference
contrat_version
algorithme_code
algorithme_version
plan_canonique_json
plan_hash
preuve_reference
compile_le
active_le
retire_le
etat
```

Le profil n’est pas une politique souveraine. Il est une compilation reproductible de la politique détenue par `CAP-CORE-007`.

Règles :

- plan canonicalisé ;
- empreinte `CAP-CORE-015` ;
- immuable après activation ;
- aucune activation sans simulation réussie ;
- toute divergence entre politique et plan rend le profil inutilisable ;
- aucun code exécutable libre dans le JSON.

## 5. `matching_profil_critere`

Champs :

```text
profil_reference
critere_reference
ordre
operateur
valeur_type
obligatoire
poids
seuil
traitement_inconnu
traitement_contradictoire
fraicheur_max_secondes
sources_autorisees_json
explication_code
facteur_public_autorise
exclusion_dure
```

Valeurs possibles de `traitement_inconnu` :

```text
INDETERMINE
IGNORER_AVEC_TRACE
ECHEC_CRITERE
REFUS_EXECUTION
```

Un critère obligatoire non établi ne peut pas être silencieusement considéré comme satisfait.

## 6. `matching_demande`

Représente la demande normalisée d’un consommateur.

Champs :

```text
reference
idempotency_key
consommateur_produit
consommateur_organisation
contexte_reference
finalite_reference
realm_reference
environnement
politique_reference
politique_version
profil_execution_reference
objet_principal_type
objet_principal_reference
population_reference
mode_resultat
limite_resultats
classification
etat
soumise_par
mandat_reference
autorisation_reference
contrat_reference
contrat_version
correlation_id
expire_le
created_at
updated_at
```

États :

```text
PREPARATION
SOUMISE
VALIDEE
REFUSEE
EN_ATTENTE_SOURCES
EN_EXECUTION
TERMINEE
PARTIELLE
EN_ECHEC
ANNULEE
EXPIREE
```

Transitions en ajout seul dans `matching_cycle`.

## 7. Modes de résultat

Liste initiale :

```text
QUALIFICATION_UNITAIRE
CORRESPONDANCE
CLASSEMENT
ESTIMATION_AGREGEE
SEGMENT_PROTEGE
APPARTENANCE_SEGMENT
COMPARAISON_POLITIQUE
```

Une demande ne peut pas changer de mode après validation.

## 8. `matching_objet`

Représente une référence d’offre, besoin, programme, ressource ou entité à apparier.

Champs :

```text
reference_interne
demande_reference
role_objet
objet_type
objet_reference_externe
source_reference
contrat_reference
version_objet
empreinte_objet
valide_depuis
valide_jusqua
classification
snapshot_minimal_json
```

`role_objet` :

```text
OFFRE
BESOIN
ENTITE
PROGRAMME
RESSOURCE
CANDIDAT
```

Le snapshot reste minimal, validé par schéma et expirant. Aucun document complet n’est copié.

## 9. `matching_critere_demande`

Conserve les critères demandés après validation contre le profil.

Champs :

```text
demande_reference
critere_reference
operateur
valeur_normalisee_json
obligatoire
poids_effectif
origine
source_exigee
ordre
```

Origines :

```text
POLITIQUE
CONSOMMATEUR_AUTORISE
OBJET_SOURCE
OBLIGATION_REALM
RESTRICTION_RISQUE
```

Le consommateur ne peut pas ajouter un critère absent du profil actif.

## 10. `matching_signal`

Signal matérialisé, minimal et temporaire.

Champs :

```text
reference
sujet_type
sujet_reference
signal_code
valeur_type
valeur_normalisee_json
source_reference
source_revision
finalite_reference
realm_reference
contrat_reference
contrat_version
observation_le
valide_jusqua
confiance_source
preuve_reference
classification
statut
recu_le
expire_le
```

Statuts :

```text
VALIDE
PERIME
REVOQUE
CONTRADICTOIRE
INUTILISABLE
SUPPRIME_LOGIQUEMENT
```

Règles :

- source active ;
- finalité autorisée ;
- schéma valide ;
- durée obligatoire ;
- pas de secret ;
- pas de document complet ;
- pas de réutilisation entre finalités ;
- suppression ou anonymisation conforme à l’expiration et aux obligations ;
- les signaux sensibles ou rares restent interrogés à la demande plutôt que matérialisés.

## 11. Interrogation à la demande

Pour une donnée détaillée ou sensible, le magasin conserve seulement :

```text
question_reference
source_reference
contrat_reference
question_hash
reponse_code
niveau_preuve
valide_jusqua
preuve_reference
```

Exemple :

```text
Question : le permis est-il vérifié pour cette finalité ?
Réponse : OUI / NON / INDETERMINE
Validité : date
```

Le document du permis n’entre pas dans le Matching.

## 12. `matching_execution`

Champs :

```text
reference
demande_reference
profil_execution_reference
algorithme_code
algorithme_version
jeu_donnees_hash
plan_hash
demarre_le
termine_le
etat
candidats_total
candidats_evalues
candidats_refuses
resultats_total
signaux_utilises
signaux_inconnus
signaux_contradictoires
preuve_reference
correlation_id
erreur_code
```

États :

```text
PLANIFIEE
EN_COURS
TERMINEE
PARTIELLE
EN_ECHEC
ANNULEE
```

Une exécution terminée ne peut pas être modifiée. Une reprise crée une nouvelle exécution liée.

## 13. `matching_candidat`

Champs :

```text
execution_reference
candidat_reference
sujet_type
sujet_reference
realm_reference
admis_evaluation
motif_refus_code
donnees_snapshot_hash
```

La table est fortement restreinte. Les APIs consommateurs ne retournent pas les références membres sauf contrat exceptionnel explicitement adopté, testé et autorisé.

## 14. `matching_evaluation_critere`

Champs :

```text
execution_reference
candidat_reference
critere_reference
etat_evaluation
valeur_observee_hash
source_reference
observation_le
fraicheur
confiance_source
contribution_score
motif_code
preuve_reference
```

États :

```text
SATISFAIT
DEFAVORABLE
NON_ETABLI
CONTRADICTOIRE
INTERDIT
NON_APPLICABLE
```

Aucune valeur personnelle détaillée n’est nécessaire dans l’explication standard.

## 15. `matching_resultat`

Champs :

```text
reference
execution_reference
candidat_reference
resultat_type
classe_resultat
pertinence
confiance
rang
facteurs_favorables_nombre
facteurs_defavorables_nombre
facteurs_inconnus_nombre
obligations_json
non_decisionnel
politique_reference
politique_version
source_set_hash
preuve_reference
expire_le
created_at
```

Classes initiales :

```text
CORRESPONDANCE_FORTE
CORRESPONDANCE_PROBABLE
CORRESPONDANCE_PARTIELLE
NON_CORRESPONDANT
INDETERMINE
INTERDIT
```

Règles :

- `pertinence` et `confiance` sont distinctes ;
- `non_decisionnel=true` par défaut et obligatoire ;
- aucune classe n’est une valeur générale de l’entité ;
- le résultat expire ;
- le rang n’est valable que dans l’exécution concernée ;
- une correction de source ne réécrit pas le résultat : elle déclenche un réexamen.

## 16. `matching_facteur`

Champs :

```text
resultat_reference
critere_reference
nature
code_explication
importance
source_reference
public_autorise
ordre
```

Nature :

```text
FAVORABLE
DEFAVORABLE
NON_ETABLI
CONTRADICTOIRE
RESTRICTION
```

L’explication ne doit pas exposer une donnée sensible sous couvert de transparence.

## 17. `matching_segment`

Champs :

```text
reference
demande_reference
execution_reference
contexte_reference
consommateur_produit
finalite_reference
realm_reference
politique_reference
politique_version
population_nombre
membres_hash
classification
export_brut_autorise
verification_appartenance_autorisee
activation_autorisee
etat
cree_le
active_le
expire_le
suspendu_le
revoque_le
preuve_reference
```

États :

```text
PREPARATION
ACTIF
SUSPENDU
EXPIRE
REVOQUE
```

Règles :

- référence opaque ;
- expiration obligatoire ;
- `export_brut_autorise=false` par défaut ;
- pas de prolongation silencieuse ;
- renouvellement par nouvelle demande ou décision prévue par politique ;
- l’expiration rend immédiatement toute activation inutilisable ;
- un segment n’est pas une nouvelle identité ni une organisation.

## 18. `matching_segment_membre`

Champs :

```text
segment_reference
membre_token
sujet_reference_interne
ajoute_par_resultat
valide_depuis
valide_jusqua
statut
preuve_reference
```

Le `membre_token` est opaque et lié au segment, au consommateur et à la finalité. Le mécanisme exact de tokenisation utilise une opération autorisée de `CAP-CORE-016` ou une construction cryptographique validée ; aucune clé n’est stockée ici.

Statuts :

```text
ACTIF
RETIRE
EXPIRE
REVOQUE
```

## 19. Vérification d’appartenance

Entrée :

```text
segment
membre_token ou reference autorisee
consommateur
finalite
realm
date
```

Sortie minimale :

```text
APPARTIENT
N_APPARTIENT_PAS
INDETERMINE
INTERDIT
segment_expire_le
obligations
preuve
```

La sortie n’explique pas automatiquement les raisons profondes de qualification.

## 20. `matching_activation`

Champs :

```text
reference
segment_reference
consommateur_produit
consommateur_organisation
contexte_reference
finalite_reference
realm_reference
environnement
contrat_reference
contrat_version
decision_reference
autorisation_reference
obligations_json
quota
usage_autorise
etat
demande_le
autorise_le
active_le
expire_le
termine_le
revoque_le
preuve_reference
```

États :

```text
DEMANDEE
REFUSEE
AUTORISEE
ACTIVE
SUSPENDUE
TERMINEE
EXPIREE
REVOQUEE
EN_ECHEC
```

Une activation n’autorise que l’usage exact prévu. Elle ne transfère pas la propriété du segment.

## 21. Obligations d’activation

Codes initiaux :

```text
NO_RAW_EXPORT
NO_REUSE
PURPOSE_BOUND
CONSUMER_BOUND
REALM_BOUND
EXPIRES_AT
NO_AUTOMATED_FINAL_DECISION
HUMAN_REVIEW_REQUIRED
AGGREGATED_REPORTING_ONLY
DELETE_LOCAL_CACHE_AT
CONTESTATION_CHANNEL_REQUIRED
```

Toute obligation est canonique dans `CAP-CORE-010` et contractuelle dans `CAP-CORE-009`.

## 22. `matching_activation_mesure`

Champs :

```text
reference
activation_reference
mesure_code
valeur_numerique
valeur_categorielle
population_reference
fenetre_debut
fenetre_fin
source_reference
contrat_reference
preuve_reference
classification
recu_le
```

Les mesures distinguent :

- utilité ;
- performance métier ;
- couverture ;
- faux positifs ;
- faux négatifs lorsqu’ils peuvent être établis ;
- cas indéterminés ;
- plaintes ;
- effets indésirables.

Une mesure Wasplex ne modifie jamais automatiquement une politique commune.

## 23. `matching_contestation`

Champs :

```text
reference
resultat_reference
segment_reference
activation_reference
contestant_reference
motif_code
description_minimale
source_contestee
ouvert_le
etat
responsable
realm_reference
classification
preuve_initiale
```

États :

```text
OUVERTE
RECEVABLE
NON_RECEVABLE
EN_INSTRUCTION
CORRECTION_SOURCE_ATTENDUE
REEXECUTION_ATTENDUE
RESOLUE
REJETEE
CLOTUREE
```

Une contestation ne modifie pas directement une source souveraine.

## 24. `matching_reexamen`

Champs :

```text
reference
contestation_reference
ancienne_execution
nouvelle_execution
sources_corrigees_json
politique_reference
politique_version
resultat_ancien
resultat_nouveau
ecart_json
decision_reference
preuve_reference
termine_le
```

Le dossier indique si le résultat a été :

```text
CONFIRME
MODIFIE
ANNULE
DEVENU_INDETERMINE
DEVENU_INTERDIT
```

## 25. Jeux d’évaluation

`matching_jeu_evaluation` conserve uniquement des jeux synthétiques, anonymisés ou juridiquement autorisés.

Champs :

```text
reference
contexte_reference
version
source_reference
population_type
cas_nombre
classification
empreinte
preuve_reference
valide_depuis
valide_jusqua
etat
```

Aucun jeu de production n’est copié dans Git.

## 26. Comparaison de politique

`matching_comparaison_politique` :

```text
reference
contexte
politique_a
version_a
politique_b
version_b
jeu_evaluation
population_nombre
resultats_changes
portee_delta
indetermines_delta
faux_positifs_delta
faux_negatifs_delta
couverture_delta
equite_ecarts_json
risques_reference
preuve_reference
created_at
```

Une comparaison ne déclenche aucune activation automatique.

## 27. Outbox

`matching_outbox` garantit la publication transactionnelle des événements vers `CAP-CORE-014`.

Aucune perte silencieuse entre :

- création de résultat ;
- création de segment ;
- activation ;
- expiration ;
- contestation ;
- réexamen.

## 28. Rétention

Définir dans la politique et l’exploitation :

- signaux : durée la plus courte entre source, finalité et contrat ;
- snapshots : durée nécessaire à la reproductibilité, avec minimisation ;
- résultats : durée du contexte plus période de contestation ;
- membres de segment : jusqu’à expiration, révocation ou obligation plus stricte ;
- mesures : agrégées dès que possible ;
- preuves : selon la politique de preuve ;
- audit : géré par `CAP-CORE-013`.

La purge est testée, idempotente et auditée.

## 29. Immutabilité et correction

Immuables après finalisation :

- politique et version utilisées ;
- plan d’exécution ;
- empreinte du jeu de données ;
- résultat ;
- facteurs ;
- preuve ;
- chronologie de segment ;
- chronologie d’activation.

Une correction crée :

- un nouveau signal ;
- une nouvelle exécution ;
- un nouveau résultat ;
- un lien de remplacement ou réexamen.

## 30. Isolation

Toutes les tables portent ou héritent d’un realm et d’une classification.

Les index, requêtes et autorisations empêchent :

- lecture transversale non autorisée ;
- segment global implicite ;
- activation dans un autre pays ;
- réutilisation d’un membre token par un autre consommateur ;
- agrégation révélant une petite population sensible.

## 31. Petites populations

La politique définit un seuil minimal d’agrégation.

Sous ce seuil :

- estimation agrégée refusée ou arrondie selon contrat ;
- aucune liste ;
- aucune explication permettant une réidentification ;
- trace d’audit ;
- résultat `POPULATION_TROP_REDUITE`.

Le seuil exact est versionné par contexte, pas codé arbitrairement dans le contrôleur.

## 32. Sauvegarde et restauration

Le magasin est inclus dans `CAP-CORE-019`.

Après restauration :

- comparer les expirations à l’heure réelle ;
- ne jamais réactiver un segment expiré ;
- ne jamais ressusciter une activation révoquée ;
- rapprocher les événements `CAP-CORE-014` ;
- vérifier les preuves ;
- recalculer les projections dérivées ;
- produire un rapport signé.
