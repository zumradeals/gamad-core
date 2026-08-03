# CAP-CORE-017 — API, console, contrats et tests

## 1. Politique d’autorisation

Créer et activer :

```text
POL-RISQUES-EXCEPTIONS-V1
```

Actions minimales :

```text
lister-risques
consulter-risque
creer-risque
modifier-risque-brouillon
soumettre-risque-evaluation
evaluer-risque
assigner-proprietaire-risque
declarer-controle-risque
verifier-controle-risque
creer-traitement-risque
modifier-traitement-risque
demarrer-traitement-risque
terminer-action-traitement-risque
accepter-risque
fermer-risque
reouvrir-risque
planifier-revue-risque
realiser-revue-risque
creer-demande-exception
modifier-demande-exception
ajouter-exigence-exception
definir-perimetre-exception
ajouter-mesure-compensatoire
evaluer-exception
soumettre-exception-decision
rattacher-decision-exception
activer-exception
suspendre-exception
reprendre-exception
revoquer-exception
clore-exception
creer-renouvellement-exception
resoudre-exception
exporter-paquet-risque
verifier-paquet-risque
consulter-diagnostics-risques
```

La politique contrôle au minimum :

- identité ;
- organisation ;
- fonction ;
- mandat ;
- realm ;
- produit ;
- capacité ;
- environnement ;
- classification ;
- catégorie de risque ;
- niveau de risque ;
- type d’exception ;
- exigence ciblée ;
- niveau d’assurance de session ;
- séparation entre demandeur, évaluateur, propriétaire et décideur.

Le fondateur ne reçoit aucune exception implicite.

---

## 2. Assurance de session

Exigences minimales à confirmer par politique :

```text
lecture ordinaire autorisée          A1 ou selon classification
création et modification             A1
évaluation de risque                 A2
vérification de contrôle             A2
acceptation de risque élevé          A2 ou A3
soumission d’exception critique      A2
activation ou révocation             A2 ou A3
export d’un paquet complet           A2
```

Un code de secours seul ne suffit pas pour accepter un risque critique ou activer une exception sensible lorsque la politique exige un facteur fort.

---

## 3. API v1 — risques

Routes minimales :

```text
GET    /api/v1/risques
POST   /api/v1/risques
GET    /api/v1/risques/{reference}
GET    /api/v1/risques/{reference}/historique
POST   /api/v1/risques/{reference}/revisions
POST   /api/v1/risques/{reference}/soumission-evaluation
POST   /api/v1/risques/{reference}/evaluations-inherentes
POST   /api/v1/risques/{reference}/evaluations-residuelles
GET    /api/v1/risques/{reference}/evaluations
POST   /api/v1/risques/{reference}/proprietaire
POST   /api/v1/risques/{reference}/controles
POST   /api/v1/risques/{reference}/controles/{controle}/verification
POST   /api/v1/risques/{reference}/traitements
GET    /api/v1/risques/{reference}/traitements
POST   /api/v1/risques/{reference}/revues
POST   /api/v1/risques/{reference}/acceptation
POST   /api/v1/risques/{reference}/fermeture
POST   /api/v1/risques/{reference}/reouverture
GET    /api/v1/risques/{reference}/verification
GET    /api/v1/risques/{reference}/paquet
```

Règles :

- validation stricte ;
- références exactes ;
- tailles bornées ;
- idempotency key sur les écritures sensibles ;
- aucune saisie directe du score final ;
- aucune pièce binaire ;
- aucune URL téléchargée automatiquement ;
- aucun secret ;
- `404` référence inconnue ;
- `409` concurrence ou doublon ;
- `422` transition invalide ;
- `403` autorisation refusée ;
- `503` dépendance souveraine indisponible.

---

## 4. API v1 — traitements

```text
POST /api/v1/risques/{risque}/traitements/{traitement}/demarrage
POST /api/v1/risques/{risque}/traitements/{traitement}/blocage
POST /api/v1/risques/{risque}/traitements/{traitement}/actions
POST /api/v1/risques/{risque}/traitements/{traitement}/actions/{action}/execution
POST /api/v1/risques/{risque}/traitements/{traitement}/actions/{action}/accuse
POST /api/v1/risques/{risque}/traitements/{traitement}/terminaison
```

Une exécution contractuelle vérifie :

- contrat actif ;
- version ;
- opération ;
- cible ;
- schéma ;
- autorisation ;
- décision éventuelle ;
- preuve ;
- idempotence.

Aucun endpoint ne peut appeler une commande arbitraire.

---

## 5. API v1 — demandes d’exception

```text
GET    /api/v1/exceptions
POST   /api/v1/exceptions
GET    /api/v1/exceptions/{reference}
GET    /api/v1/exceptions/{reference}/historique
PUT    /api/v1/exceptions/{reference}
POST   /api/v1/exceptions/{reference}/exigences
DELETE /api/v1/exceptions/{reference}/exigences/{exigence}
POST   /api/v1/exceptions/{reference}/perimetres
DELETE /api/v1/exceptions/{reference}/perimetres/{perimetre}
POST   /api/v1/exceptions/{reference}/mesures
POST   /api/v1/exceptions/{reference}/mesures/{mesure}/verification
POST   /api/v1/exceptions/{reference}/evaluation
POST   /api/v1/exceptions/{reference}/soumission-decision
POST   /api/v1/exceptions/{reference}/decision
```

La création doit refuser :

- exigence non dérogeable ;
- périmètre générique ;
- durée excessive ;
- date rétroactive ;
- absence de risque ;
- absence de responsable ;
- données secrètes ;
- mesure obligatoire absente.

---

## 6. API v1 — cycle de l’exception

```text
POST /api/v1/exceptions/{reference}/activation
POST /api/v1/exceptions/{reference}/suspension
POST /api/v1/exceptions/{reference}/reprise
POST /api/v1/exceptions/{reference}/revocation
POST /api/v1/exceptions/{reference}/cloture
POST /api/v1/exceptions/{reference}/renouvellement
GET  /api/v1/exceptions/{reference}/verification
GET  /api/v1/exceptions/{reference}/paquet
```

L’activation retourne au minimum :

```json
{
  "exception": "EXC-...",
  "etat": "ACTIVE",
  "exigences": ["REQ-..."],
  "organisation": "ORG-...",
  "realm": "RLM-...",
  "produit": "PRD-...",
  "environnement": "PRODUCTION",
  "valide_du": "...",
  "valide_au": "...",
  "decision": "DEC-...",
  "risque": "RSK-...",
  "mesures": ["MCO-..."],
  "preuve": "PRV-..."
}
```

Aucune valeur secrète ou détail exploitable complet.

---

## 7. API interne de résolution

Route interne ou contrat applicatif :

```text
POST /api/v1/exceptions/resolution
```

Entrée :

```json
{
  "exigence": "REQ-...",
  "sujet": "IDN-...",
  "ressource": "...",
  "operation": "...",
  "organisation": "ORG-...",
  "realm": "RLM-...",
  "produit": "PRD-...",
  "capacite": "CAP-CORE-...",
  "environnement": "PRODUCTION",
  "finalite": "...",
  "date": "..."
}
```

La route :

- est réservée aux consommateurs inscrits ;
- vérifie un contrat actif ;
- ne renvoie pas les motifs confidentiels ;
- ne renvoie pas `PERMIS` ;
- renvoie le fait d’exception et ses bornes ;
- journalise le refus ou l’usage selon politique ;
- reste disponible en lecture lorsque les dépendances non critiques sont indisponibles ;
- refuse fermé si la validité ne peut pas être établie.

---

## 8. OpenAPI et CAP-CORE-009

Mettre à jour :

```text
apps/console-laravel/openapi/core-v1.yaml
```

Enregistrer au minimum :

```text
CTR-RSK-01 — Dossiers de risque
CTR-RSK-02 — Évaluations et cotation
CTR-RSK-03 — Contrôles et traitements
CTR-RSK-04 — Revues et acceptations
CTR-EXC-01 — Demandes d’exception
CTR-EXC-02 — Évaluation et décision d’exception
CTR-EXC-03 — Cycle et expiration
CTR-EXC-04 — Résolution d’exception
CTR-EXC-05 — Paquets et vérification
```

Chaque contrat précise :

- producteur ;
- consommateurs ;
- finalité ;
- données minimales ;
- opérations ;
- schémas ;
- erreurs ;
- autorisations ;
- mandat ;
- realm ;
- classification ;
- idempotence ;
- validité ;
- audit ;
- preuve ;
- comportement en panne.

La CI détecte :

- route absente ou fantôme ;
- schéma divergent ;
- action divergente ;
- code d’erreur divergent ;
- contrat de résolution sans consommateur ;
- exigence sans référence canonique ;
- exception sans contrat de décision ;
- action de traitement sans cible.

---

## 9. Console d’administration

Créer deux entrées reliées :

```text
Risques
Exceptions
```

### Tableau de bord Risques

Afficher :

- risques ouverts ;
- risques critiques ;
- risques sans propriétaire ;
- évaluations en retard ;
- revues échues ;
- traitements bloqués ;
- actions en retard ;
- niveaux aggravés récemment ;
- risques acceptés proches de leur revue ;
- risques par organisation, realm, produit et capacité.

### Liste des risques

Filtres :

```text
référence
titre
organisation
realm
produit
capacité
catégorie
niveau inhérent
niveau résiduel
état
propriétaire
prochaine revue
traitement
classification
```

### Fiche risque

Onglets :

```text
Résumé
Scénario
Révisions
Évaluations
Impacts
Contrôles
Traitements
Actions
Revues
Exceptions
Décisions
Preuves
Historique
Vérification
```

### Tableau de bord Exceptions

Afficher :

- demandes en préparation ;
- évaluations incomplètes ;
- décisions attendues ;
- exceptions approuvées non actives ;
- exceptions actives ;
- expirations sous 7 jours ;
- mesures compensatoires expirées ;
- exceptions suspendues ;
- demandes de renouvellement ;
- usages refusés hors périmètre.

### Fiche exception

Onglets :

```text
Résumé
Exigences
Périmètre
Risque
Évaluation
Mesures compensatoires
Décision
Validité
Usages
Preuves
Historique
Vérification
```

Avant activation, afficher clairement :

```text
ce qui est temporairement dérogé
ce qui reste obligatoire
qui est concerné
sur quelle ressource
pour quelle opération
pendant quelle période
quel risque est accepté
quelles mesures compensent
quelle décision autorise
```

La console ne propose jamais de bouton « rendre permanente ».

---

## 10. Notifications et alertes

Alertes minimales :

```text
RISQUE_CRITIQUE_SANS_PROPRIETAIRE
EVALUATION_RISQUE_EN_RETARD
REVUE_RISQUE_ECHUE
TRAITEMENT_RISQUE_BLOQUE
ACTION_RISQUE_EN_RETARD
EXCEPTION_DECISION_EN_ATTENTE
EXCEPTION_EXPIRATION_7J
EXCEPTION_EXPIRATION_24H
MESURE_COMPENSATOIRE_INVALIDE
EXCEPTION_SUSPENDUE_AUTOMATIQUEMENT
USAGE_EXCEPTION_HORS_PERIMETRE
```

Les notifications doivent respecter classification et realm.

---

## 11. Tests de capacité

Créer :

```text
core/registre-risques/tests/risques_exceptions_p3.php
```

Minimum : **120 épreuves**.

### Magasin et schéma

1. migration PostgreSQL ;
2. migration SQLite ;
3. production refuse SQLite ;
4. aucun fallback silencieux ;
5. références uniques ;
6. contraintes de dates ;
7. contraintes d’états ;
8. aucune colonne de secret ;
9. migrations idempotentes ;
10. concurrence sur transitions.

### Risques

11. création valide ;
12. organisation inconnue refusée ;
13. realm inconnu refusé ;
14. propriétaire inconnu refusé ;
15. révision immuable ;
16. nouvelle révision créée ;
17. score non accepté depuis client ;
18. calcul déterministe ;
19. méthode versionnée ;
20. ancienne méthode historique conservée ;
21. évaluation inhérente ;
22. évaluation résiduelle ;
23. contrôle non vérifié non déductible ;
24. contrôle expiré non déductible ;
25. preuve de contrôle requise ;
26. dimensions d’impact ;
27. seuil calculé ;
28. traitement créé ;
29. action contractuelle valide ;
30. action sans contrat refusée ;
31. acceptation sans décision refusée lorsque requise ;
32. acceptation avec décision valide ;
33. fermeture sans preuve refusée ;
34. fermeture avec exception active refusée ;
35. réouverture ;
36. revue planifiée ;
37. revue en retard ;
38. aggravation du niveau ;
39. réduction du niveau ;
40. liens acycliques.

### Exceptions

41. demande valide ;
42. risque absent refusé ;
43. exigence inconnue refusée ;
44. exigence non dérogeable refusée ;
45. exigence par sous-chaîne refusée ;
46. wildcard refusé ;
47. date rétroactive refusée ;
48. durée excessive refusée ;
49. mesure obligatoire absente ;
50. demandeur non autorisé ;
51. séparation demandeur/décideur ;
52. évaluation incomplète ;
53. évaluation favorable ;
54. favorable ne vaut pas approbation ;
55. décision absente refusée ;
56. décision falsifiée refusée ;
57. mandat expiré refusé ;
58. décision autre realm refusée ;
59. décision autre organisation refusée ;
60. décision annulée refusée ;
61. décision valide rattachée ;
62. activation avant date refusée ;
63. activation après date refusée ;
64. activation sans mesure refusée ;
65. activation valide ;
66. résolution exacte ;
67. autre sujet refusé ;
68. autre ressource refusée ;
69. autre opération refusée ;
70. autre environnement refusé ;
71. autre realm refusé ;
72. autre finalité refusée ;
73. autre version de contrat refusée ;
74. non-propagation au produit enfant ;
75. suspension manuelle ;
76. suspension automatique mesure expirée ;
77. reprise après vérification ;
78. reprise sans condition refusée ;
79. expiration automatique ;
80. résolution expirée refusée ;
81. aucune grâce implicite ;
82. révocation ;
83. réactivation après révocation refusée ;
84. renouvellement crée nouvelle référence ;
85. ancienne date immuable ;
86. renouvellement sans nouvelle décision refusé ;
87. non-rétroactivité ;
88. usage minimal enregistré ;
89. aucun contenu secret dans usage ;
90. idempotence activation.

### Intégrations

91. politique active requise ;
92. CAP004 continue de décider ;
93. exception seule ne produit pas PERMIS ;
94. décision CAP008 requise ;
95. preuve CAP015 vérifiée ;
96. clé privée absente ;
97. audit CAP013 ;
98. événement CAP014 ;
99. contrat CAP009 ;
100. vocabulaire CAP010 ;
101. realm isolé ;
102. produit suspendu bloque activation ;
103. organisation inactive bloque activation ;
104. source expirée signalée ;
105. panne CAP008 fail closed ;
106. panne CAP015 fail closed sur action critique ;
107. panne journal ne crée pas d’état partiel ;
108. outbox transactionnelle ;
109. corrélation préservée ;
110. paquet signé vérifiable.

### API, console et exploitation

111. routes authentifiées ;
112. validation stricte ;
113. erreurs documentées ;
114. console utilise services applicatifs ;
115. masquage classification ;
116. alerte expiration 7 jours ;
117. alerte expiration 24 heures ;
118. sauvegarde PostgreSQL ;
119. restauration complète ;
120. configuration Laravel mise en cache.

Ajouter des contre-épreuves spécifiques pour toute garde ajoutée.

---

## 12. Workflow CI

Raccorder à :

```text
.github/workflows/core-operational-tests.yml
```

La CI doit exécuter :

- tests unitaires ;
- tests de capacité ;
- tests PostgreSQL réels ;
- tests SQLite ;
- tests HTTP ;
- tests console ;
- tests de contrats ;
- vérification OpenAPI ;
- analyse des migrations ;
- recherche de secrets ;
- test d’expiration ;
- test de restauration ;
- non-régression des capacités déjà `GO`.
