# CAP-CORE-021 — API, console, contrats et tests

## 1. Politique d’autorisation

Créer et activer dans `CAP-CORE-007` :

```text
POL-MATCHING-V1
```

Actions minimales :

```text
consulter-contextes-matching
consulter-politiques-matching
compiler-politique-matching
simuler-politique-matching
comparer-politiques-matching
soumettre-demande-matching
consulter-demande-matching
annuler-demande-matching
executer-matching
consulter-resultat-matching
expliquer-resultat-matching
construire-segment-matching
consulter-segment-matching
verifier-appartenance-segment
activer-segment-matching
suspendre-segment-matching
revoquer-segment-matching
terminer-activation-matching
enregistrer-mesure-matching
ouvrir-contestation-matching
instruire-contestation-matching
reexaminer-resultat-matching
exporter-paquet-matching
verifier-paquet-matching
consulter-diagnostics-matching
consulter-rapport-qualite-matching
consulter-rapport-equite-matching
gerer-signaux-matching
gerer-jeux-evaluation-matching
```

La politique contrôle au minimum :

- identité ;
- assurance de session ;
- organisation ;
- mandat ;
- produit consommateur ;
- contexte ;
- finalité ;
- realm ;
- environnement ;
- classification ;
- politique et version ;
- contrat et version ;
- type de résultat ;
- taille demandée ;
- export brut ;
- supervision humaine ;
- risque, exception et incident bloquants.

Le fondateur ne reçoit pas une autorisation implicite hors politique.

## 2. Assurance de session

Niveaux minimaux à inscrire dans la politique :

```text
lecture agrégée ordinaire : niveau adapté à la classification
soumission de demande : A1 ou supérieur
consultation de résultat nominatif autorisé : A2
activation de segment : A2
modification de contexte ou de profil : A2
adoption d’une politique sensible : A3 si disponible
export exceptionnel de membres : A3 + décision formelle + contrat spécifique
révocation globale ou action d’urgence : A3 si disponible
```

Les valeurs exactes suivent les niveaux réellement définis par `CAP-CORE-005`.

## 3. API v1 — contextes et profils

Routes minimales :

```text
GET  /api/v1/matching/contextes
GET  /api/v1/matching/contextes/{reference}
GET  /api/v1/matching/contextes/{reference}/profils
GET  /api/v1/matching/profils/{reference}
POST /api/v1/matching/profils/compilation
POST /api/v1/matching/profils/simulation
POST /api/v1/matching/profils/comparaison
```

Aucun endpoint ne permet d’écrire une politique librement dans le magasin du Matching.

La compilation reçoit une référence de politique `CAP-CORE-007` active ou candidate selon l’opération.

## 4. API v1 — demandes

```text
GET    /api/v1/matching/demandes
POST   /api/v1/matching/demandes
GET    /api/v1/matching/demandes/{reference}
GET    /api/v1/matching/demandes/{reference}/historique
POST   /api/v1/matching/demandes/{reference}/validation
POST   /api/v1/matching/demandes/{reference}/execution
POST   /api/v1/matching/demandes/{reference}/annulation
GET    /api/v1/matching/demandes/{reference}/statut
```

Corps minimal de création :

```json
{
  "consommateur": "PRD-...",
  "contexte": "WASPLEX_AUDIENCE",
  "finalite": "...",
  "realm": "RLM-...",
  "environnement": "PRODUCTION",
  "objet": {
    "type": "OFFRE",
    "reference": "...",
    "source": "SRC-..."
  },
  "population": {
    "mode": "REFERENCE_CONTRACTUELLE",
    "reference": "..."
  },
  "mode_resultat": "SEGMENT_PROTEGE",
  "criteres": []
}
```

Règles :

- `Idempotency-Key` obligatoire ;
- limites de taille ;
- critères canoniques ;
- aucune valeur secrète ;
- aucune URL libre à interroger ;
- aucune expression SQL ;
- aucun code exécutable ;
- refus si le produit, contexte, source, contrat ou realm est incompatible.

## 5. API v1 — résultats

```text
GET  /api/v1/matching/resultats/{reference}
GET  /api/v1/matching/resultats/{reference}/explication
GET  /api/v1/matching/resultats/{reference}/verification
POST /api/v1/matching/resultats/{reference}/reexamen
```

Projection standard :

```json
{
  "reference": "MATCH-...",
  "contexte": "WASPLEX_AUDIENCE",
  "classe": "CORRESPONDANCE_PROBABLE",
  "pertinence": 0.84,
  "confiance": 0.77,
  "facteurs_favorables": [],
  "facteurs_defavorables": [],
  "facteurs_non_etablis": [],
  "obligations": ["NO_RAW_EXPORT", "NO_REUSE"],
  "non_decisionnel": true,
  "expire_le": "...",
  "preuve": "PRV-..."
}
```

`pertinence` et `confiance` peuvent être `null` lorsque le contexte ne les autorise pas ou que le résultat est indéterminé.

## 6. API v1 — segments

```text
GET  /api/v1/matching/segments
POST /api/v1/matching/segments
GET  /api/v1/matching/segments/{reference}
GET  /api/v1/matching/segments/{reference}/statistiques
POST /api/v1/matching/segments/{reference}/appartenance
POST /api/v1/matching/segments/{reference}/activation
POST /api/v1/matching/segments/{reference}/suspension
POST /api/v1/matching/segments/{reference}/revocation
POST /api/v1/matching/segments/{reference}/expiration
```

Aucune route standard :

```text
GET /api/v1/matching/segments/{reference}/membres
```

Un export nominatif exceptionnel nécessite un contrat séparé, une décision formelle, une politique spécifique, un besoin démontré et des tests dédiés. Il ne fait pas partie du premier `GO`.

## 7. API v1 — activations et mesures

```text
GET  /api/v1/matching/activations
GET  /api/v1/matching/activations/{reference}
POST /api/v1/matching/activations/{reference}/accuse
POST /api/v1/matching/activations/{reference}/suspension
POST /api/v1/matching/activations/{reference}/terminaison
POST /api/v1/matching/activations/{reference}/revocation
POST /api/v1/matching/activations/{reference}/mesures
GET  /api/v1/matching/activations/{reference}/mesures
```

Les mesures sont minimisées, agrégées et contractuelles.

## 8. API v1 — contestations

```text
GET  /api/v1/matching/contestations
POST /api/v1/matching/contestations
GET  /api/v1/matching/contestations/{reference}
POST /api/v1/matching/contestations/{reference}/recevabilite
POST /api/v1/matching/contestations/{reference}/instruction
POST /api/v1/matching/contestations/{reference}/correction-source
POST /api/v1/matching/contestations/{reference}/reexecution
POST /api/v1/matching/contestations/{reference}/resolution
POST /api/v1/matching/contestations/{reference}/cloture
```

La personne ou organisation ne reçoit que l’explication autorisée. La contestation ne révèle pas les dossiers d’autres membres ou les mécanismes de sécurité sensibles.

## 9. API v1 — diagnostics et qualité

```text
GET  /api/v1/matching/diagnostics
GET  /api/v1/matching/qualite
GET  /api/v1/matching/equite
GET  /api/v1/matching/dependances
GET  /api/v1/matching/preparation
POST /api/v1/matching/reconciliation
POST /api/v1/matching/expiration
POST /api/v1/matching/purge
```

Les endpoints d’exploitation sont réservés et ne retournent aucun membre de segment.

## 10. Codes d’erreur

Codes minimaux :

```text
MATCHING_CONTEXT_UNKNOWN
MATCHING_CONTEXT_INACTIVE
MATCHING_PURPOSE_NOT_ALLOWED
MATCHING_CONSUMER_NOT_ALLOWED
MATCHING_PRODUCT_INACTIVE
MATCHING_REALM_NOT_ALLOWED
MATCHING_CROSS_REALM_DENIED
MATCHING_POLICY_UNKNOWN
MATCHING_POLICY_INACTIVE
MATCHING_POLICY_DIVERGENT
MATCHING_CONTRACT_UNKNOWN
MATCHING_CONTRACT_INACTIVE
MATCHING_SOURCE_UNKNOWN
MATCHING_SOURCE_UNUSABLE
MATCHING_SIGNAL_EXPIRED
MATCHING_SIGNAL_CONTRADICTORY
MATCHING_CRITERION_UNKNOWN
MATCHING_CRITERION_FORBIDDEN
MATCHING_CRITERION_NOT_ALLOWED
MATCHING_REQUIRED_DATA_UNKNOWN
MATCHING_POPULATION_TOO_SMALL
MATCHING_LIMIT_EXCEEDED
MATCHING_RESULT_EXPIRED
MATCHING_SEGMENT_EXPIRED
MATCHING_SEGMENT_SUSPENDED
MATCHING_SEGMENT_REVOKED
MATCHING_ACTIVATION_DENIED
MATCHING_RAW_EXPORT_FORBIDDEN
MATCHING_REUSE_FORBIDDEN
MATCHING_HUMAN_REVIEW_REQUIRED
MATCHING_RISK_BLOCKED
MATCHING_INCIDENT_BLOCKED
MATCHING_NON_REPRODUCIBLE
MATCHING_IDEMPOTENCY_CONFLICT
MATCHING_DEPENDENCY_UNAVAILABLE
```

HTTP :

```text
400 requête mal formée
401 non authentifié
403 refus d’autorisation ou de finalité
404 référence inconnue
409 conflit, état ou idempotence
410 résultat ou segment expiré
422 critère, transition ou schéma invalide
429 limite ou débit dépassé
503 dépendance souveraine indisponible
```

## 11. Contrats CAP-CORE-009

Enregistrer au minimum :

```text
CTR-MAT-01 — Soumission d’une demande de Matching
CTR-MAT-02 — Résolution de population candidate
CTR-MAT-03 — Acquisition de signaux matérialisés
CTR-MAT-04 — Interrogation minimale d’une source
CTR-MAT-05 — Exécution et résultat de Matching
CTR-MAT-06 — Explication d’un résultat
CTR-MAT-07 — Construction et vérification de segment
CTR-MAT-08 — Activation d’un segment
CTR-MAT-09 — Mesure d’une activation
CTR-MAT-10 — Contestation et réexamen
CTR-MAT-11 — Comparaison de politiques
CTR-MAT-12 — Preuve et paquet de Matching
```

Chaque contrat précise :

- producteur ;
- consommateurs ;
- finalité ;
- contextes ;
- realms ;
- opérations ;
- schémas ;
- erreurs ;
- idempotence ;
- classification ;
- rétention ;
- données minimales ;
- données interdites ;
- obligations d’autorisation ;
- obligations de preuve ;
- supervision humaine ;
- non-réutilisation ;
- expiration.

## 12. OpenAPI

Mettre à jour :

```text
apps/console-laravel/openapi/core-v1.yaml
```

La CI détecte :

- route sans opération OpenAPI ;
- opération fantôme ;
- méthode divergente ;
- schéma divergent ;
- code d’erreur absent ;
- route de membres de segment interdite ;
- résultat sans `non_decisionnel` ;
- segment sans expiration ;
- activation sans finalité ;
- endpoint ne déclarant pas son action d’autorisation.

## 13. Politique de Matching et CAP-CORE-007

Créer les règles de `POL-MATCHING-V1` dans le registre existant.

Aucune table `matching_policy` souveraine concurrente.

Les versions portent :

- contextes ;
- critères ;
- poids ;
- seuils ;
- exclusions ;
- traitement des inconnus ;
- sources ;
- obligations ;
- durées ;
- supervision ;
- tests attendus.

Activation d’une nouvelle version :

```text
brouillon
→ validation syntaxique
→ compilation
→ simulation
→ comparaison
→ rapport de qualité et d’équité
→ décision si sensible
→ activation atomique
```

## 14. Événements CAP-CORE-014

Produire les événements minimaux définis dans la partie 03.

Enveloppe :

```text
reference
contexte
consommateur
realm
finalite
etat
politique
contrat
preuve
correlation
expiration
```

Jamais :

- liste des membres ;
- attributs personnels ;
- critères sensibles détaillés ;
- documents ;
- secrets ;
- token actif d’un membre.

## 15. Audit CAP-CORE-013

Traces minimales :

```text
DEMANDE_MATCHING_RECUE
DEMANDE_MATCHING_VALIDEE
DEMANDE_MATCHING_REFUSEE
POLITIQUE_MATCHING_COMPILEE
POLITIQUE_MATCHING_SIMULEE
POLITIQUES_MATCHING_COMPAREES
EXECUTION_MATCHING_DEMARREE
EXECUTION_MATCHING_TERMINEE
EXECUTION_MATCHING_ECHOUEE
RESULTAT_MATCHING_CONSULTE
EXPLICATION_MATCHING_CONSULTEE
SEGMENT_MATCHING_CREE
APPARTENANCE_SEGMENT_VERIFIEE
ACTIVATION_MATCHING_DEMANDEE
ACTIVATION_MATCHING_AUTORISEE
ACTIVATION_MATCHING_REFUSEE
MESURE_MATCHING_RECUE
SEGMENT_MATCHING_SUSPENDU
SEGMENT_MATCHING_EXPIRE
SEGMENT_MATCHING_REVOQUE
CONTESTATION_MATCHING_OUVERTE
RESULTAT_MATCHING_REEXAMINE
PAQUET_MATCHING_EXPORTE
OPERATION_MATCHING_REFUSEE
```

Chaque trace contient acteur, action, ressource, résultat, politique, contrat, source, realm, mandat, autorisation, preuve et corrélation, sans contenu sensible complet.

## 16. Preuves CAP-CORE-015

Preuves minimales :

- profil compilé ;
- simulation ;
- comparaison de politiques ;
- exécution ;
- manifeste de segment ;
- activation ;
- expiration ;
- réexamen ;
- rapport de qualité ;
- rapport d’équité ;
- paquet pilote.

Une preuve valide n’atteste pas que la correspondance est moralement ou juridiquement correcte. Elle atteste le contenu, la version, l’intégrité et la reproductibilité technique déclarée.

## 17. Console d’administration

Créer une entrée :

```text
Matching
```

### Tableau de bord

Afficher :

- contextes actifs ;
- demandes du jour ;
- exécutions en attente ;
- exécutions en échec ;
- résultats par classe ;
- segments actifs ;
- segments proches de l’expiration ;
- activations actives ;
- sources indisponibles ;
- politiques divergentes ;
- contestations ouvertes ;
- petites populations refusées ;
- anomalies de qualité ou d’équité ;
- consommateurs pilotes ;
- fraîcheur des signaux.

### Écran Contextes

Afficher :

```text
contexte
finalite
politique active
consommateurs
sources
realms
résultats autorisés
supervision
état
```

### Écran Demandes

Onglets :

```text
Résumé
Objet
Population
Critères
Sources
Autorisation
Exécutions
Résultats
Segments
Audit
Preuves
```

### Écran Politique

Permet :

- voir la version souveraine ;
- compiler ;
- simuler ;
- comparer ;
- consulter les divergences ;
- afficher les critères interdits ;
- demander une décision d’activation.

Aucune édition directe de politique hors des services `CAP-CORE-007`.

### Écran Résultat

Afficher :

- classe ;
- pertinence ;
- confiance ;
- facteurs ;
- inconnus ;
- sources autorisées ;
- politique ;
- expiration ;
- nature non décisionnelle ;
- preuve ;
- contestation éventuelle.

### Écran Segment

Afficher :

- population agrégée ;
- empreinte des membres ;
- consommateur ;
- finalité ;
- realm ;
- obligations ;
- expiration ;
- activations ;
- état ;
- preuve.

Ne jamais afficher la liste complète des membres dans la console initiale.

### Écran Qualité et équité

Afficher :

- jeu d’évaluation ;
- couverture ;
- indéterminés ;
- faux positifs et faux négatifs lorsque mesurables ;
- différences contextualisées ;
- population et limites ;
- alertes ;
- décisions et risques associés.

## 18. Projection consommateur

Chaque consommateur reçoit une projection propre au contrat.

Wasplex peut recevoir :

- référence de segment ;
- taille agrégée ;
- expiration ;
- obligations ;
- vérification d’appartenance ;
- statistiques agrégées.

Il ne reçoit pas automatiquement :

- noms ;
- emails ;
- téléphones ;
- dossiers d’identité ;
- documents ;
- raisons sensibles ;
- autres campagnes ;
- signaux d’autres finalités.

## 19. Tests de capacité

Créer :

```text
core/moteur-matching/tests/matching_p3.php
```

Et dans la console :

```text
apps/console-laravel/tests/Integration/matching_api_p1.php
apps/console-laravel/tests/Integration/matching_console_p1.php
apps/console-laravel/tests/Integration/matching_wasplex_p1.php
apps/console-laravel/tests/Integration/matching_second_consumer_p1.php
apps/console-laravel/tests/Integration/matching_postgresql_p0.php
```

## 20. Épreuves minimales

Au moins **160 épreuves**, comprenant les catégories suivantes.

### Fondation

1. migration PostgreSQL ;
2. migration SQLite ;
3. PostgreSQL obligatoire en production ;
4. aucun fallback silencieux ;
5. magasin isolé ;
6. readiness verte ;
7. readiness rouge si magasin indisponible ;
8. bootstrap idempotent ;
9. aucune personne créée par bootstrap ;
10. aucun segment de production créé par bootstrap.

### Contextes et politiques

11. contexte actif valide ;
12. contexte inconnu refusé ;
13. contexte suspendu refusé ;
14. politique inconnue refusée ;
15. politique inactive refusée ;
16. divergence politique-plan refusée ;
17. compilation déterministe ;
18. plan hash stable ;
19. code exécutable libre refusé ;
20. critère non canonique refusé ;
21. critère interdit refusé ;
22. proxy interdit détecté par contre-épreuve ;
23. simulation obligatoire ;
24. comparaison obligatoire pour version sensible ;
25. activation sans décision quand requise refusée.

### Sources et signaux

26. source active acceptée ;
27. source suspendue refusée ;
28. mauvaise finalité refusée ;
29. mauvais realm refusé ;
30. signal expiré refusé ;
31. signal révoqué refusé ;
32. signal contradictoire visible ;
33. absence de preuve traitée selon politique ;
34. donnée sans date refusée ;
35. donnée sans durée refusée ;
36. donnée secrète refusée ;
37. document complet refusé ;
38. événement dupliqué idempotent ;
39. requête à la demande minimale ;
40. source ne transmet pas le document original.

### Demandes

41. demande valide ;
42. idempotence même contenu ;
43. conflit même clé contenu différent ;
44. produit inactif refusé ;
45. consommateur non autorisé refusé ;
46. mandat absent refusé ;
47. realm parent sans droit refusé ;
48. finalité absente refusée ;
49. contrat absent refusé ;
50. limite de critères ;
51. limite de candidats ;
52. expression SQL refusée ;
53. URL arbitraire refusée ;
54. regex consommateur refusée ;
55. annulation avant exécution ;
56. annulation après finalisation refusée.

### Évaluation déterministe

57. opérateur EQ ;
58. opérateur IN ;
59. comparaison de dates ;
60. comparaison d’unités normalisées ;
61. critère dur satisfait ;
62. critère dur défavorable ;
63. obligatoire non établi → indéterminé ;
64. contradictoire → traitement explicite ;
65. interdit → arrêt ;
66. score exact reproductible ;
67. confiance distincte ;
68. aucun critère disponible → score null ;
69. arrondi stable ;
70. classement stable ;
71. départage stable ;
72. aucun attribut caché dans le classement ;
73. même entrée même résultat ;
74. date de référence différente explicitement tracée ;
75. politique différente produit résultat lié à sa version.

### Explication

76. facteurs favorables ;
77. facteurs défavorables ;
78. facteurs non établis ;
79. source sensible masquée ;
80. explication sans donnée personnelle profonde ;
81. `non_decisionnel=true` ;
82. résultat expiré non présenté comme actuel ;
83. confiance faible visible ;
84. pertinence élevée ne cache pas l’incertitude ;
85. paquet d’explication vérifiable.

### Segments

86. segment protégé créé ;
87. expiration obligatoire ;
88. export brut false par défaut ;
89. absence de route membres ;
90. token lié au segment ;
91. token inutilisable par autre consommateur ;
92. token inutilisable pour autre finalité ;
93. token inutilisable dans autre realm ;
94. petite population refusée ;
95. empreinte des membres stable ;
96. membre retiré ;
97. segment suspendu ;
98. segment expiré ;
99. segment révoqué ;
100. restauration ne réactive pas un segment expiré.

### Activations

101. activation valide ;
102. activation sans autorisation refusée ;
103. activation sans obligations refusée ;
104. activation autre produit refusée ;
105. activation autre finalité refusée ;
106. activation autre realm refusée ;
107. accusé idempotent ;
108. double activation évitée ;
109. quota contractuel ;
110. expiration immédiate ;
111. révocation ;
112. produit suspendu suspend l’activation ;
113. incident bloquant suspend l’activation ;
114. risque bloquant refuse l’activation ;
115. segment expiré refuse toute vérification active.

### Mesures et qualité

116. mesure valide ;
117. mesure hors finalité refusée ;
118. mesure nominative non contractuelle refusée ;
119. mesure dupliquée idempotente ;
120. agrégation ;
121. couverture ;
122. indéterminés ;
123. faux positifs mesurables ;
124. faux négatifs mesurables ;
125. absence de label honnêtement signalée ;
126. rapport qualité signé ;
127. mesure Wasplex ne modifie pas la politique ;
128. différence statistique ne déclenche pas seule une décision automatique.

### Équité et risques

129. groupe pertinent autorisé ;
130. création d’attribut sensible non autorisée refusée ;
131. différence de couverture détectée ;
132. domination de source détectée ;
133. boucle d’auto-renforcement testée ;
134. critère sans rapport refusé ;
135. critère non dérogeable reste interdit ;
136. rapport d’équité signé ;
137. risque créé lors d’anomalie majeure ;
138. incident possible déclenché selon seuil adopté.

### Contestations

139. contestation ouverte ;
140. contestant non autorisé refusé ;
141. source souveraine sollicitée ;
142. correction sans réécriture historique ;
143. réexécution ;
144. résultat confirmé ;
145. résultat modifié ;
146. résultat devenu indéterminé ;
147. activation gelée si politique l’exige ;
148. clôture sans preuve refusée.

### Exploitation et sécurité

149. audit sans secret ;
150. événement sans membres ;
151. preuve valide ;
152. preuve falsifiée refusée ;
153. reprise après crash worker ;
154. pas de double résultat après reprise ;
155. timeout ;
156. purge ;
157. sauvegarde ;
158. restauration ;
159. pilote Wasplex réel ;
160. deuxième consommateur réel utilisant les mêmes contrats.

Ajouter toutes les contre-épreuves nécessaires au-delà de ce minimum.

## 21. Tests Wasplex

Le pilote doit démontrer :

```text
offre réelle de test autorisée
→ demande normalisée
→ segment protégé
→ estimation agrégée
→ vérification d’appartenance
→ application des règles Wasplex
→ mesure agrégée
→ expiration
→ impossibilité de réutilisation
```

Ne pas connecter des données personnelles de production pendant la validation initiale sans cadre explicite.

## 22. Deuxième consommateur

Le deuxième pilote doit :

- être un produit réel ;
- utiliser les mêmes contrats fondamentaux ;
- avoir un contexte distinct ou une finalité distincte ;
- prouver que le moteur n’est pas spécifique à Wasplex ;
- respecter les mêmes mécanismes de source, autorisation, explication et expiration.

Un test artificiel dans le même code Wasplex ne constitue pas un deuxième consommateur.
