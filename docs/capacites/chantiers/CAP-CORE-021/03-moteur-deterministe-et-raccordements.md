# CAP-CORE-021 — Moteur déterministe et raccordements

## 1. Principe du moteur initial

Le moteur initial est déterministe.

À politique, données, versions, date d’évaluation et configuration identiques, il produit le même résultat.

```text
mêmes entrées
+ même plan
+ mêmes sources
+ même instant de référence
= même résultat
```

Toute source non déterministe, modèle externe ou dépendance probabiliste est hors périmètre du premier `GO`.

## 2. Pipeline d’exécution

Pipeline minimal :

```text
1. recevoir la demande
2. authentifier et autoriser
3. valider consommateur, produit, organisation, realm et finalité
4. résoudre politique et contrat actifs
5. compiler ou charger le profil d’exécution
6. valider les critères demandés
7. résoudre la population candidate autorisée
8. collecter les signaux minimaux
9. vérifier source, fraîcheur, finalité et preuve
10. évaluer les critères
11. calculer pertinence et confiance
12. classer le résultat
13. produire les facteurs explicatifs
14. construire éventuellement un segment
15. produire preuve, audit et événements
16. expirer selon la politique
```

Chaque étape peut refuser sans appeler les suivantes.

## 3. Résolution de la population

La population candidate provient d’un contrat explicite :

- références fournies par le consommateur ;
- requête bornée à une source ;
- ensemble dérivé d’un realm et d’un type d’entité ;
- segment antérieur encore actif et réutilisable pour la même finalité ;
- offre ou besoin publié pour appariement.

Interdictions :

- `SELECT *` dans un autre magasin ;
- parcours illimité de toutes les identités ;
- population `GLOBAL` implicite ;
- exploration de l’Atlas comme base de profils ;
- ajout d’une personne parce que son nom ressemble à une cible ;
- fusion de populations de realms sans autorisation de franchissement.

## 4. Pré-filtrage obligatoire

Avant tout scoring :

- produit consommateur actif ;
- contexte actif ;
- politique active ;
- contrat actif ;
- finalité exacte ;
- realm compatible ;
- source active ;
- donnée non expirée ;
- absence de restriction bloquante ;
- candidat non révoqué ou retiré selon son registre souverain ;
- taille et classification autorisées.

Un candidat interdit n’entre pas dans le calcul de classement.

## 5. Opérateurs déterministes

Liste initiale fermée :

```text
EQ
NEQ
IN
NOT_IN
GT
GTE
LT
LTE
BETWEEN
CONTAINS_CANONICAL
INTERSECTS_CANONICAL
WITHIN_REALM
VALID_AT
EXISTS_VERIFIED
NOT_EXISTS_VERIFIED
```

Règles :

- aucune expression SQL libre ;
- aucune regex fournie par le consommateur ;
- aucune fonction PHP arbitraire ;
- aucune comparaison floue pour une condition de sécurité ou d’éligibilité ;
- les valeurs sont normalisées par `CAP-CORE-010` ;
- les dates sont évaluées à un instant explicite ;
- les unités sont canoniques.

## 6. Critères durs et critères pondérés

### Critère dur

Un critère dur ou `obligatoire=true` peut produire :

```text
SATISFAIT
DEFAVORABLE
NON_ETABLI
CONTRADICTOIRE
INTERDIT
```

Règle initiale :

- `DEFAVORABLE` sur exclusion dure → `NON_CORRESPONDANT` ;
- `NON_ETABLI` sur obligation → `INDETERMINE` sauf politique plus stricte ;
- `CONTRADICTOIRE` sur obligation → `INDETERMINE` ou refus ;
- `INTERDIT` → résultat `INTERDIT` et arrêt.

### Critère pondéré

Contribution normalisée :

```text
poids_effectif × valeur_evaluation
```

Valeurs initiales :

```text
SATISFAIT = 1
DEFAVORABLE = 0
NON_ETABLI = aucune contribution, traitement explicite
CONTRADICTOIRE = aucune contribution et réduction de confiance
NON_APPLICABLE = exclu du dénominateur si la politique l’autorise
```

Le code ne doit pas cacher le traitement du non établi derrière une valeur arbitraire de `0.5`.

## 7. Score de pertinence

Formule déterministe proposée :

```text
pertinence = somme(contributions admissibles)
             / somme(poids admissibles)
```

Le profil définit :

- poids ;
- critères admissibles ;
- exclusions du dénominateur ;
- seuils de classes ;
- précision d’arrondi ;
- comportement quand aucun critère pondéré n’est disponible.

Quand aucun calcul légitime n’est possible :

```text
classe = INDETERMINE
pertinence = null
```

Ne jamais retourner `0` comme faux score certain.

## 8. Niveau de confiance

La confiance mesure la qualité des éléments utilisés, pas la pertinence.

Composantes possibles, toutes versionnées :

- complétude des critères ;
- fraîcheur ;
- niveau de vérification de la source ;
- cohérence entre sources ;
- présence de preuve ;
- proportion de critères non établis ;
- stabilité temporelle.

Formule :

```text
confiance = agrégation déterministe des qualités de preuve
```

La politique fournit les coefficients et bornes.

Un score élevé avec confiance faible reste présenté comme incertain.

## 9. Classes de résultat

Seuils définis par politique.

Exemple structurel, sans imposer de chiffres universels :

```text
si exclusion dure         → NON_CORRESPONDANT
si critère interdit       → INTERDIT
si obligation non établie → INDETERMINE
sinon seuil_haut           → CORRESPONDANCE_FORTE
sinon seuil_moyen          → CORRESPONDANCE_PROBABLE
sinon seuil_bas            → CORRESPONDANCE_PARTIELLE
sinon                      → NON_CORRESPONDANT
```

Les valeurs numériques exactes sont propres au contexte et ne sont jamais partagées automatiquement entre publicité, B2B et éligibilité institutionnelle.

## 10. Classement

Le classement utilise dans l’ordre :

1. admissibilité ;
2. classe ;
3. pertinence ;
4. confiance ;
5. règles secondaires explicitement déclarées ;
6. référence stable comme dernier départage technique.

Interdictions :

- ordre aléatoire non prouvé ;
- priorité commerciale cachée ;
- achat d’un meilleur score ;
- départage selon un attribut absent de la politique ;
- ancienneté utilisée sans raison déclarée.

## 11. Explication

Chaque résultat explique au minimum :

```text
contexte
politique et version
classe
pertinence éventuelle
confiance éventuelle
facteurs favorables
facteurs défavorables
facteurs non établis
restrictions
sources ou catégories de sources autorisées
expiration
nature non décisionnelle
```

L’explication destinée au consommateur est une projection filtrée.

Elle ne révèle pas :

- donnée personnelle profonde ;
- source secrète ;
- règle de détection sensible ;
- identité d’un membre d’un segment ;
- détail permettant une réidentification ;
- incident non public ;
- risque ou exception non autorisé.

## 12. Construction d’un segment

Conditions :

- demande `TERMINEE` ou `PARTIELLE` explicitement admissible ;
- population suffisamment grande ;
- contexte autorisant les segments ;
- contrat d’activation ;
- expiration ;
- preuve ;
- obligations ;
- aucune erreur bloquante.

Étapes :

```text
sélectionner résultats admissibles
→ créer référence opaque
→ générer tokens de membres liés au segment
→ calculer empreinte des membres
→ enregistrer taille et restrictions
→ signer le manifeste
→ publier SEGMENT_MATCHING_CREE
```

La liste brute n’est pas retournée.

## 13. Activation

Une activation vérifie :

- segment actif ;
- consommateur identique ;
- finalité identique ;
- realm compatible ;
- produit actif ;
- contrat actif ;
- politique active ;
- autorisation `CAP-CORE-004` ;
- décision `CAP-CORE-008` si exigée ;
- aucun risque ou incident bloquant ;
- expiration future ;
- obligations acceptées.

Résultats :

```text
AUTORISEE
REFUSEE
INDETERMINEE
```

L’activation ne devient `ACTIVE` qu’après accusé du consommateur.

## 14. Activation Wasplex

Mode recommandé : vérification d’appartenance ou activation contrôlée.

```text
Wasplex présente une référence de campagne et un candidat
→ vérification du contrat et de la finalité
→ vérification du token ou de la référence autorisée
→ APPARTIENT / N_APPARTIENT_PAS / INDETERMINE / INTERDIT
→ Wasplex applique budget, quota, fréquence et calendrier
```

Le Matching ne décide pas si l’annonce doit effectivement être montrée à cet instant : Wasplex applique encore ses règles locales.

## 15. Estimation agrégée

Pour une demande d’audience :

```text
population_evaluee
population_admissible
population_indeterminee
population_exclue
intervalle ou marge seulement si méthode adoptée
fraicheur
sources
limites
```

Aucune estimation n’est présentée comme garantie de vues, clics ou ventes.

Sous le seuil de petite population, l’estimation est refusée ou protégée par la politique.

## 16. Mesure

Le consommateur peut retourner des événements minimaux :

```text
activation utilisee
opportunite presentee
interaction autorisee
resultat utile declare
non pertinence declaree
plainte
opt-out
anomalie
```

Règles :

- contrat actif ;
- agrégation ;
- finalité identique ;
- période ;
- aucune copie de contenu ou conversation complète ;
- aucune mise à jour automatique de politique ;
- aucune récompense commerciale cachée dans le score commun.

## 17. Comparaison de politiques

Avant activation d’une nouvelle version :

```text
compiler les deux versions
→ exécuter sur le même jeu d’évaluation
→ comparer classes, couverture, indéterminés et erreurs
→ comparer populations affectées
→ mesurer écarts par groupes pertinents et légitimes
→ produire rapport signé
→ transmettre à CAP-CORE-008 si décision exigée
```

Une nouvelle politique est refusée si :

- jeu d’évaluation absent ;
- preuve invalide ;
- critère interdit ;
- hausse inexpliquée des exclusions ;
- baisse de couverture non documentée ;
- différence non reproductible ;
- obligation de revue humaine non satisfaite.

## 18. Équité et biais

Le chantier doit tester :

- couverture ;
- faux positifs ;
- faux négatifs quand un label légitime existe ;
- cas indéterminés ;
- erreurs par territoire ou groupe pertinent ;
- effets de petite population ;
- proxies de critères interdits ;
- boucle d’auto-renforcement ;
- domination d’une source ;
- différence de qualité de données.

Une différence statistique n’est pas automatiquement une discrimination. Elle déclenche une analyse contextualisée, documentée et supervisée.

Le moteur ne doit pas créer lui-même des catégories sensibles pour effectuer un audit. Les jeux et méthodes doivent avoir une base autorisée.

## 19. Contestation

Parcours :

```text
contestation reçue
→ vérifier recevabilité et identité du demandeur
→ geler l’activation si politique l’exige
→ identifier source et résultat
→ demander correction à la source souveraine
→ attendre preuve de correction
→ réexécuter avec la politique historique ou actuelle selon le motif
→ comparer les résultats
→ décider des effets
→ notifier avec explication autorisée
```

Le Matching n’édite pas directement l’organisation, l’identité ou le dossier du satellite.

## 20. Raccordement aux sources

Deux modes :

### Signaux matérialisés

Pour données fréquentes, peu sensibles, normalisées et temporaires.

```text
CAP-CORE-014
→ validation contrat
→ validation source/finalité
→ insertion idempotente
→ expiration
```

### Requête à la demande

Pour données sensibles, rares ou coûteuses.

```text
Matching
→ requête minimale contractuelle
→ source répond OUI/NON/INDETERMINE + preuve + validité
→ Matching ne reçoit pas le document original
```

## 21. Sources minimales initiales

Ne déclarer que des sources réellement disponibles.

Sources possibles après vérification :

- identité et type d’entité ;
- organisation et territoire ;
- produit ;
- realm ;
- rôle ou mandat nécessaire ;
- offre ou besoin publié par le consommateur ;
- restrictions de risque ;
- signaux métier explicitement contractés.

Une source conceptuelle non implémentée ne doit pas être bootstrapée comme active.

## 22. Raccordement à CAP-CORE-020

L’Atlas sert à :

- trouver le propriétaire d’une source ;
- résoudre les contrats et consommateurs ;
- visualiser les dépendances ;
- analyser l’impact d’une politique ;
- confirmer les realms et endpoints autorisés.

Il ne fournit pas :

- les membres d’une population ;
- les attributs personnels ;
- un score ;
- une permission.

## 23. Raccordement aux événements

Événements consommés possibles :

```text
SOURCE_ACTIVEE
SOURCE_SUSPENDUE
SOURCE_RETIRÉE
PRODUIT_ACTIVE
PRODUIT_SUSPENDU
REALM_SUSPENDU
CONTRAT_ACTIVE
CONTRAT_SUSPENDU
POLITIQUE_ACTIVEE
POLITIQUE_SUSPENDUE
SIGNAL_MATCHING_PUBLIE
SIGNAL_MATCHING_REVOQUE
RISQUE_BLOQUANT_DECLARE
EXCEPTION_EXPIREE
INCIDENT_CONFIRME
INCIDENT_RESOLU
```

Les noms finaux sont canoniques dans `CAP-CORE-010`.

Événements produits :

```text
DEMANDE_MATCHING_SOUMISE
DEMANDE_MATCHING_REFUSEE
EXECUTION_MATCHING_DEMARREE
EXECUTION_MATCHING_TERMINEE
EXECUTION_MATCHING_EN_ECHEC
RESULTAT_MATCHING_PRODUIT
SEGMENT_MATCHING_CREE
SEGMENT_MATCHING_ACTIVE
SEGMENT_MATCHING_SUSPENDU
SEGMENT_MATCHING_EXPIRE
ACTIVATION_MATCHING_AUTORISEE
ACTIVATION_MATCHING_REFUSEE
ACTIVATION_MATCHING_TERMINEE
MESURE_MATCHING_RECUE
CONTESTATION_MATCHING_OUVERTE
REEXAMEN_MATCHING_TERMINE
ANOMALIE_MATCHING_DETECTEE
```

Les événements n’embarquent jamais la liste des membres.

## 24. Réaction aux changements

### Source suspendue

- bloquer les nouvelles exécutions dépendantes ;
- marquer les signaux inutilisables ;
- réévaluer les segments selon politique ;
- ne pas supprimer l’historique.

### Politique suspendue

- aucune nouvelle exécution ;
- activation existante suspendue si l’obligation l’impose ;
- conserver les résultats historiques avec leur version.

### Produit suspendu

- refuser les nouvelles demandes et activations ;
- suspendre ses activations ;
- ne pas transférer ses segments à un autre produit.

### Realm suspendu

- refuser les exécutions ;
- suspendre les segments et activations du realm ;
- ne pas étendre au parent.

### Incident confirmé

Appliquer les actions contractuelles définies :

- gel ;
- suspension ;
- révocation ;
- purge ;
- réexamen ;
- notification restreinte.

## 25. Idempotence

Idempotency keys obligatoires pour :

- soumission de demande ;
- ingestion de signal ;
- démarrage d’exécution ;
- création de segment ;
- activation ;
- mesure ;
- contestation ;
- réexamen.

Même clé et même contenu : retourner le même résultat.

Même clé et contenu différent : `409 IDEMPOTENCY_CONFLICT`.

## 26. Concurrence

Utiliser transactions et verrous adaptés pour empêcher :

- deux exécutions actives incompatibles ;
- double activation ;
- prolongation après expiration ;
- retrait et ajout simultané d’un membre ;
- réexamen concurrent produisant deux remplacements ;
- mesure dupliquée.

## 27. Limites de calcul

La configuration définit :

```text
MATCHING_MAX_CANDIDATES
MATCHING_MAX_CRITERIA
MATCHING_MAX_RESULTS
MATCHING_MAX_SEGMENT_MEMBERS
MATCHING_EXECUTION_TIMEOUT
MATCHING_MAX_CONCURRENT_RUNS
MATCHING_SIGNAL_RETENTION
MATCHING_RESULT_RETENTION
```

Les valeurs exactes sont déterminées par tests de charge et documentées dans le rapport final.

Le dépassement est refusé avant traitement ou exécuté en lots bornés selon le contrat. Aucun calcul illimité dans une requête HTTP.

## 28. Exécution asynchrone

Les petits cas unitaires peuvent être synchrones.

Les classements et segments utilisent une file interne gouvernée :

```text
SOUMISE
→ PLANIFIEE
→ EN_EXECUTION
→ TERMINEE / PARTIELLE / EN_ECHEC
```

Le worker :

- possède une identité de service ;
- est autorisé ;
- utilise une politique active ;
- renouvelle son bail ;
- reprend après crash ;
- ne double pas les résultats.

## 29. Reproductibilité

Commande :

```text
php artisan core:matching:reproduire MATCH-...
```

Elle vérifie :

- politique historique ;
- plan historique ;
- algorithme ;
- instant de référence ;
- signaux ou preuves disponibles ;
- empreintes.

Sortie :

```text
IDENTIQUE
DIVERGENT
NON_REPRODUCTIBLE_SOURCE_EXPIREE
NON_REPRODUCTIBLE_PREUVE_ABSENTE
INTERDIT
```

Le système ne fabrique pas un résultat identique lorsque les données historiques ne sont plus légitimement conservées.

## 30. Bootstrap

Le bootstrap initial crée uniquement :

- contextes réellement approuvés ;
- profils issus de politiques actives ;
- contrats existants ;
- consommateurs disponibles ;
- vocabulaires ;
- jeux synthétiques de test.

Il ne crée :

- aucune personne ;
- aucun segment de production ;
- aucun signal personnel fictif en production ;
- aucune politique fantôme ;
- aucun second consommateur imaginaire.

Commande idempotente :

```text
php artisan core:matching:bootstrap
```

## 31. Commandes console artisan

Créer au minimum :

```text
core:matching:migrer
core:matching:bootstrap
core:matching:diagnostiquer
core:matching:compiler-politique
core:matching:simuler-politique
core:matching:executer
core:matching:expirer
core:matching:purger
core:matching:reconcilier-evenements
core:matching:reproduire
core:matching:verifier-preuves
core:matching:rapport-qualite
core:matching:rapport-equite
core:matching:exercice-pilote
```

Toutes produisent des codes de sortie documentés et aucun secret.

## 32. Absence de machine learning dans le premier GO

La CI doit échouer si le chantier introduit sans décision :

- appel à un fournisseur d’IA ;
- téléchargement de modèle ;
- poids de réseau neuronal ;
- entraînement ;
- auto-optimisation ;
- réécriture automatique de politique ;
- dépendance à un service de recommandation externe.

Un futur chantier d’apprentissage contrôlé exigera :

- décision `CAP-CORE-008` ;
- risque `CAP-CORE-017` ;
- contrats ;
- provenance du modèle ;
- version et preuve ;
- données d’entraînement autorisées ;
- tests de dérive et biais ;
- mécanisme de retrait ;
- capacité de revenir au moteur déterministe.
