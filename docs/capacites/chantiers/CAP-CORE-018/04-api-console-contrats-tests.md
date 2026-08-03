# CAP-CORE-018 — API, console, contrats, audit et tests

## 1. Politique d’autorisation

Créer et activer :

```text
POL-INCIDENTS-V1
```

Actions minimales :

```text
lister-incidents
consulter-incident
consulter-incident-restreint
declarer-candidat-incident
ingerer-signal-incident
demarrer-triage-incident
confirmer-incident
rejeter-candidat-incident
marquer-doublon-incident
modifier-description-incident
requalifier-severite-incident
ajouter-impact-incident
ajouter-actif-incident
nommer-participant-incident
attribuer-role-incident
retirer-role-incident
preparer-plan-reponse-incident
demander-action-incident
relancer-action-incident
accuser-action-incident
verifier-action-incident
demarrer-confinement-incident
declarer-incident-contenu
demarrer-eradication-incident
declarer-incident-eradique
demarrer-retablissement-incident
verifier-retablissement-incident
demarrer-surveillance-incident
preparer-communication-incident
valider-communication-incident
emettre-communication-incident
declarer-resolution-incident
ouvrir-revue-incident
valider-revue-incident
cloturer-incident
reouvrir-incident
regrouper-incidents
exporter-paquet-incident
verifier-paquet-incident
consulter-diagnostics-incidents
appliquer-retention-incidents
```

Les actions sont des références exactes de `CAP-CORE-010`.

La politique contrôle au minimum :

- identité ;
- organisation ;
- fonction ;
- mandat ;
- realm ;
- produit ;
- capacité ;
- sévérité ;
- type d’incident ;
- état ;
- classification ;
- environnement ;
- assurance de session ;
- rôle dans l’incident ;
- finalité ;
- séparation entre commandant, exécuteur et validateur de clôture.

Le fondateur ne reçoit aucune permission implicite hors politique.

## 2. Assurance de session

Exigences minimales proposées :

```text
lecture ordinaire autorisée : selon classification
triage : A1 ou supérieur
confirmation et requalification : A2
nomination du commandant : A2
communication publique : A3 si disponible
clôture SEV-1 ou SEV-2 : A3 si disponible
restauration de production : selon politique CAP-CORE-019
révocation ou destruction de clé : selon politique CAP-CORE-016
```

Les niveaux exacts restent dans `POL-INCIDENTS-V1` et sont testés.

Une session par code de secours ne suffit pas seule pour les opérations critiques si la politique exige un facteur plus fort.

## 3. API v1 — incidents

Routes minimales :

```text
GET    /api/v1/incidents
POST   /api/v1/incidents
GET    /api/v1/incidents/{reference}
GET    /api/v1/incidents/{reference}/historique
GET    /api/v1/incidents/{reference}/chronologie
PUT    /api/v1/incidents/{reference}/description
POST   /api/v1/incidents/{reference}/triage
POST   /api/v1/incidents/{reference}/confirmation
POST   /api/v1/incidents/{reference}/rejet
POST   /api/v1/incidents/{reference}/doublon
POST   /api/v1/incidents/{reference}/severite
POST   /api/v1/incidents/{reference}/reouverture
POST   /api/v1/incidents/{reference}/regroupement
```

Règles :

- validation stricte ;
- limites de taille ;
- idempotency key pour les créations et transitions sensibles ;
- `404` pour référence inconnue ;
- `409` pour concurrence ou clé réutilisée différemment ;
- `422` pour transition invalide ;
- `403` pour autorisation refusée ;
- `503` pour dépendance souveraine indisponible ;
- aucune donnée secrète dans la réponse ;
- projection adaptée à la classification.

## 4. API v1 — signaux

```text
POST /api/v1/signaux-incidents
GET  /api/v1/signaux-incidents/{reference}
POST /api/v1/signaux-incidents/{reference}/rattachement
POST /api/v1/signaux-incidents/{reference}/quarantaine
POST /api/v1/signaux-incidents/{reference}/bruit
```

L’ingestion machine utilise un contrat de service authentifié et une audience bornée.

Un producteur ne peut pas :

- confirmer un incident ;
- changer la sévérité finale ;
- lire tous les incidents ;
- rattacher un signal à un autre realm sans autorisation ;
- envoyer une charge contenant un secret.

## 5. API v1 — impacts et actifs

```text
GET  /api/v1/incidents/{reference}/impacts
POST /api/v1/incidents/{reference}/impacts
POST /api/v1/incidents/{reference}/impacts/{impact}/remplacement
GET  /api/v1/incidents/{reference}/actifs
POST /api/v1/incidents/{reference}/actifs
POST /api/v1/incidents/{reference}/actifs/{actif}/etat
```

Une estimation doit porter :

```text
valeur
unite
confiance
source
date
preuve
```

## 6. API v1 — équipe et rôles

```text
GET    /api/v1/incidents/{reference}/participants
POST   /api/v1/incidents/{reference}/participants
DELETE /api/v1/incidents/{reference}/participants/{participant}
GET    /api/v1/incidents/{reference}/roles
POST   /api/v1/incidents/{reference}/roles
POST   /api/v1/incidents/{reference}/roles/{role}/fin
POST   /api/v1/incidents/{reference}/commandant/remplacement
```

La réponse indique le mandat vérifié à la date de prise de rôle.

## 7. API v1 — actions de réponse

```text
GET  /api/v1/incidents/{reference}/actions
POST /api/v1/incidents/{reference}/actions
POST /api/v1/incidents/{reference}/actions/{action}/autorisation
POST /api/v1/incidents/{reference}/actions/{action}/execution
POST /api/v1/incidents/{reference}/actions/{action}/accuse
POST /api/v1/incidents/{reference}/actions/{action}/verification
POST /api/v1/incidents/{reference}/actions/{action}/relance
POST /api/v1/incidents/{reference}/actions/{action}/annulation
POST /api/v1/incidents/{reference}/actions/{action}/compensation
```

L’endpoint d’exécution interne vérifie exactement :

- action préenregistrée ;
- cible ;
- contrat actif ;
- version ;
- opération ;
- schéma ;
- ressource ;
- autorisation ;
- décision ;
- preuve attendue ;
- idempotence.

Aucun endpoint n’accepte une commande shell, SQL ou URL arbitraire.

## 8. API v1 — phases

```text
POST /api/v1/incidents/{reference}/confinement/debut
POST /api/v1/incidents/{reference}/confinement/validation
POST /api/v1/incidents/{reference}/eradication/debut
POST /api/v1/incidents/{reference}/eradication/validation
POST /api/v1/incidents/{reference}/retablissement/debut
POST /api/v1/incidents/{reference}/retablissement/verification
POST /api/v1/incidents/{reference}/surveillance/debut
POST /api/v1/incidents/{reference}/resolution
```

Chaque transition retourne :

```text
état précédent
état nouveau
acteur
mandat
politique
preuves
critères satisfaits
critères manquants
corrélation
```

## 9. API v1 — communications

```text
GET  /api/v1/incidents/{reference}/communications
POST /api/v1/incidents/{reference}/communications
POST /api/v1/incidents/{reference}/communications/{communication}/validation
POST /api/v1/incidents/{reference}/communications/{communication}/emission
POST /api/v1/incidents/{reference}/communications/{communication}/annulation
GET  /api/v1/incidents/{reference}/projection-publique
```

Règles :

- audience explicite ;
- contenu minimisé ;
- faits et hypothèses séparés ;
- validation requise ;
- communication publique désactivée par défaut ;
- aucune émission vers un canal externe sans contrat dédié ;
- aucune donnée personnelle ou technique exploitable inutile.

## 10. API v1 — revue et clôture

```text
GET  /api/v1/incidents/{reference}/revue
POST /api/v1/incidents/{reference}/revue/ouverture
PUT  /api/v1/incidents/{reference}/revue
POST /api/v1/incidents/{reference}/revue/validation
GET  /api/v1/incidents/{reference}/lecons
POST /api/v1/incidents/{reference}/lecons
GET  /api/v1/incidents/{reference}/actions-correctives
POST /api/v1/incidents/{reference}/actions-correctives
POST /api/v1/incidents/{reference}/cloture
```

La clôture retourne :

```json
{
  "incident": "INC-...",
  "etat": "CLOS",
  "severite_finale": "SEV-2",
  "retablissement": "VERIFIE",
  "revue": "REV-...",
  "preuve_cloture": "PRV-...",
  "risques": ["RSK-..."],
  "actions_correctives": ["ACTC-..."],
  "correlation_id": "COR-..."
}
```

## 11. API v1 — vérification et export

```text
GET  /api/v1/incidents/{reference}/verification
POST /api/v1/incidents/{reference}/verification
GET  /api/v1/incidents/{reference}/paquet
POST /api/v1/incidents/paquets/verification
GET  /api/v1/incidents/diagnostics
```

Règles de paquet :

- archive non exécutable ;
- noms de fichiers sûrs ;
- aucune traversée de chemin ;
- taille bornée ;
- manifeste signé ;
- aucune extraction automatique non sécurisée ;
- aucune pièce externe incluse par défaut ;
- projection selon classification.

## 12. OpenAPI

Mettre à jour :

```text
apps/console-laravel/openapi/core-v1.yaml
```

La CI vérifie :

- route sans opération OpenAPI ;
- opération fantôme ;
- méthode divergente ;
- schéma divergent ;
- erreur non documentée ;
- action d’autorisation divergente ;
- réponse contenant un champ interdit ;
- endpoint critique sans idempotence ;
- endpoint public sans projection spécifique.

## 13. Contrats CAP-CORE-009

Enregistrer au minimum :

```text
CTR-INC-01 — Ingestion des signaux d’incident
CTR-INC-02 — Dossier et cycle de l’incident
CTR-INC-03 — Impacts, actifs et équipe de réponse
CTR-INC-04 — Actions de confinement, éradication et rétablissement
CTR-INC-05 — Accusés et vérification des actions
CTR-INC-06 — Communications d’incident
CTR-INC-07 — Revue, résolution et clôture
CTR-INC-08 — Paquet et vérification d’incident
CTR-INC-09 — Projection publique minimale
```

Chaque contrat précise :

- producteur ;
- consommateurs ;
- finalité ;
- opérations ;
- schémas ;
- erreurs ;
- classification ;
- realm ;
- idempotence ;
- rétention ;
- autorisation ;
- audit ;
- preuve ;
- données minimales ;
- données interdites.

## 14. Événements CAP-CORE-014

Types minimaux :

```text
SIGNAL_INCIDENT_RECU
CANDIDAT_INCIDENT_OUVERT
TRIAGE_INCIDENT_COMMENCE
CANDIDAT_INCIDENT_REJETE
INCIDENT_MARQUE_DOUBLON
INCIDENT_CONFIRME
SEVERITE_INCIDENT_MODIFIEE
IMPACT_INCIDENT_CONSTATE
ACTIF_INCIDENT_AFFECTE
COMMANDANT_INCIDENT_NOMME
ACTION_INCIDENT_PREPAREE
ACTION_INCIDENT_EXECUTEE
ACTION_INCIDENT_ECHOUEE
INCIDENT_ENTRE_EN_CONFINEMENT
INCIDENT_CONTENU
INCIDENT_ENTRE_EN_ERADICATION
INCIDENT_ERADIQUE
INCIDENT_ENTRE_EN_RETABLISSEMENT
INCIDENT_RETABLI
SURVEILLANCE_INCIDENT_DEMARREE
RECURRENCE_INCIDENT_DETECTEE
COMMUNICATION_INCIDENT_VALIDEE
COMMUNICATION_INCIDENT_EMISE
INCIDENT_RESOLU
REVUE_INCIDENT_VALIDEE
INCIDENT_CLOS
INCIDENT_ROUVERT
DIVERGENCE_INCIDENT_DETECTEE
```

Les événements transportent principalement :

```text
incident
organisation
realm
produit
capacité
type
état
sévérité
preuve
corrélation
```

Ils ne transportent jamais chronologie complète, journaux bruts, secrets, pièces, contenu détaillé d’une communication ou données utilisateur complètes.

## 15. Audit CAP-CORE-013

Traces minimales :

```text
SIGNAL_INCIDENT_INGERE
CANDIDAT_INCIDENT_DECLARE
TRIAGE_INCIDENT_PRIS
INCIDENT_CONFIRME
INCIDENT_REJETE
INCIDENT_REGROUPE
SEVERITE_INCIDENT_REQUALIFIEE
IMPACT_INCIDENT_AJOUTE
ROLE_INCIDENT_ATTRIBUE
ACTION_INCIDENT_DEMANDEE
ACTION_INCIDENT_EXECUTEE
ACTION_INCIDENT_RELANCEE
PHASE_INCIDENT_TRANSITIONNEE
COMMUNICATION_INCIDENT_PREPAREE
COMMUNICATION_INCIDENT_VALIDEE
COMMUNICATION_INCIDENT_EMISE
RESOLUTION_INCIDENT_DECLAREE
REVUE_INCIDENT_VALIDEE
CLOTURE_INCIDENT_TENTEE
INCIDENT_CLOS
INCIDENT_ROUVERT
PAQUET_INCIDENT_EXPORTE
PAQUET_INCIDENT_VERIFIE
OPERATION_INCIDENT_REFUSEE
```

Chaque trace contient :

- acteur ;
- action ;
- ressource ;
- résultat ;
- politique ;
- mandat ;
- décision ;
- preuve ;
- corrélation ;
- incident ;
- aucun secret ;
- aucune charge brute.

## 16. Intégration CAP-CORE-015

Preuves minimales :

```text
preuve de confirmation
preuve de requalification SEV-1/SEV-2
preuve d’action critique
preuve de rétablissement
checkpoint de chronologie
preuve de communication publique
paquet de clôture
preuve de restauration du magasin
```

La signature valide prouve l’intégrité et l’origine technique de l’artefact selon la clé, pas la vérité absolue des affirmations.

## 17. Console d’administration

Créer une entrée :

```text
Incidents
```

### Tableau de bord

Afficher :

- candidats à trier ;
- incidents ouverts par sévérité ;
- `SEV-1` sans commandant ;
- incidents sans mise à jour récente ;
- actions critiques en retard ;
- actions en échec ;
- communications à valider ;
- incidents en surveillance ;
- incidents résolus sans revue ;
- critères de rétablissement expirés ;
- preuves non vérifiables ;
- récurrences ;
- incidents par organisation, realm, produit et capacité.

### Liste

Filtres :

```text
référence
état
sévérité
type
organisation
realm
produit
capacité
environnement
classification
commandant
date de début
date de dernière activité
preuve
```

### Fiche incident

Onglets :

```text
Résumé
Signaux
Impacts
Actifs
Équipe
Chronologie
Plan de réponse
Actions
Décisions
Risques et exceptions
Communications
Rétablissement
Surveillance
Revue
Leçons
Preuves
Historique
Vérification
```

### Écran de triage

Afficher :

- signaux ;
- incidents similaires ;
- actifs possibles ;
- impacts connus ;
- inconnues ;
- sévérité calculée ;
- motifs de confirmation ou rejet ;
- exigence de commandant ;
- preuves.

Aucun bouton ne confirme sans contrôle de politique et assurance de session.

### Écran de réponse

Afficher :

- phase actuelle ;
- objectifs ;
- actions ;
- responsables ;
- contrats ;
- décisions ;
- tentatives ;
- accusés ;
- preuves ;
- dépendances ;
- plan de retour arrière.

### Écran de clôture

Avant confirmation :

```text
impacts terminés
critères de rétablissement
fenêtre de surveillance
revue validée
cause connue ou inconnue
risques réévalués
exceptions réexaminées
actions correctives
preuve de clôture
mandat du validateur
```

La console utilise les mêmes services applicatifs que l’API et ne fait aucune écriture SQL directe.

## 18. Projection utilisateur ou satellite

Une projection limitée peut indiquer :

```text
référence publique
service affecté
état public
impact public
heure de début
heure de rétablissement
prochaine mise à jour
```

Elle ne doit pas révéler :

- cause exploitable ;
- identité d’un opérateur ;
- topologie ;
- adresse interne ;
- preuve restreinte ;
- clé ;
- journal ;
- détail de vulnérabilité.

## 19. Tests de capacité

Créer :

```text
core/registre-incidents/tests/incidents_p3.php
```

Épreuves minimales :

1. migration PostgreSQL ;
2. migration SQLite ;
3. PostgreSQL obligatoire en production ;
4. absence de fallback silencieux ;
5. bootstrap idempotent ;
6. empreinte bootstrap valide ;
7. création d’un candidat ;
8. référence unique ;
9. organisation inconnue refusée ;
10. realm inconnu refusé ;
11. type inconnu refusé ;
12. source inconnue refusée ;
13. signal valide ingéré ;
14. signal dupliqué idempotent ;
15. même clé avec charge différente refusée ;
16. signal contenant mot de passe refusé ;
17. signal contenant jeton refusé ;
18. signal hors contrat refusé ;
19. signal d’un producteur non autorisé refusé ;
20. candidat automatique sans confirmation ;
21. triage avec bail ;
22. second triage concurrent refusé ;
23. reprise après expiration du bail ;
24. questionnaire avec inconnues ;
25. confirmation sans impact refusée ;
26. confirmation avec menace immédiate gouvernée ;
27. rejet motivé ;
28. doublon lié au principal ;
29. cycle de doublon acyclique ;
30. calcul déterministe de sévérité ;
31. sévérité selon impacts ;
32. hausse de sévérité ;
33. baisse sans preuve refusée ;
34. baisse avec faits nouveaux ;
35. `SEV-1` sans commandant refusé ;
36. identité inactive refusée comme commandant ;
37. mandat expiré refusé ;
38. rôle retiré sans effacer contributions ;
39. realm parent sans accès enfant ;
40. autre organisation refusée ;
41. impact estimé avec confiance ;
42. correction d’impact append-only ;
43. actif de type produit validé ;
44. référence d’actif arbitraire refusée ;
45. chronologie append-only PostgreSQL ;
46. chronologie append-only SQLite ;
47. saisie tardive conservée ;
48. contradiction conservée ;
49. chaîne d’empreintes cohérente ;
50. action avec contrat actif ;
51. action sans contrat refusée ;
52. opération inconnue refusée ;
53. commande shell libre refusée ;
54. SQL libre refusé ;
55. URL arbitraire refusée ;
56. action non idempotente non relancée ;
57. action idempotente relancée dans la limite ;
58. accusé valide ;
59. accusé contradictoire ouvre divergence ;
60. action exécutée non automatiquement vérifiée ;
61. passage en confinement ;
62. contenu sans actions critiques refusé ;
63. passage en éradication ;
64. éradication avec cause connue ;
65. cause inconnue bloque `ERADIQUE` ;
66. décision permet stratégie alternative gouvernée ;
67. démarrage du rétablissement ;
68. critère de rétablissement valide ;
69. critère expiré refusé ;
70. rétablissement partiel ;
71. rétablissement complet ;
72. fenêtre de surveillance ;
73. récurrence pendant surveillance ;
74. résolution avant fin de fenêtre refusée ;
75. résolution avec actions critiques ouvertes refusée ;
76. revue ouverte ;
77. faits et hypothèses séparés ;
78. revue sans cause forcée ;
79. action corrective créée ;
80. risque lié réévalué ;
81. exception liée suspendue ;
82. exception non automatiquement maintenue ;
83. communication interne ;
84. communication publique sans décision refusée ;
85. communication publique validée ;
86. communication contenant secret refusée ;
87. communication contenant topologie sensible refusée ;
88. émission échouée visible ;
89. clôture sans revue refusée ;
90. clôture sans preuve refusée ;
91. clôture avec critères complets ;
92. paquet de clôture signé ;
93. paquet falsifié refusé ;
94. archive avec traversée de chemin refusée ;
95. archive exécutable refusée ;
96. réouverture après récurrence ;
97. ancienne clôture conservée ;
98. regroupement de deux incidents ;
99. références secondaires conservées ;
100. fusion inter-realm refusée sans autorisation ;
101. idempotence de confirmation ;
102. concurrence de transition ;
103. audit de confirmation ;
104. audit sans secret ;
105. événement minimal ;
106. événement sans charge brute ;
107. publication au moins une fois dédupliquée ;
108. perte du journal central et reprise outbox ;
109. CAP-CORE-004 indisponible : refus ;
110. CAP-CORE-008 indisponible pour décision requise : attente/refus ;
111. CAP-CORE-015 indisponible pour clôture : refus ;
112. CAP-CORE-016 compromise : vérification historique ;
113. CAP-CORE-017 indisponible à clôture : refus ;
114. CAP-CORE-019 indisponible pendant restauration : action en attente ;
115. politique retirée : aucune permission ;
116. session A1 insuffisante pour opération A2 ;
117. session de secours insuffisante pour clôture critique ;
118. OpenAPI conforme ;
119. contrats actifs ;
120. codes d’erreur conformes ;
121. console liste ;
122. console triage ;
123. console réponse ;
124. console clôture ;
125. masquage classification ;
126. projection publique désactivée par défaut ;
127. projection publique minimisée ;
128. readiness verte ;
129. readiness rouge si magasin indisponible ;
130. sauvegarde PostgreSQL ;
131. restauration isolée ;
132. séquences préservées ;
133. chronologie préservée ;
134. rapprochement événements post-restauration ;
135. restauration ne ferme pas un incident plus récent ;
136. configuration Laravel mise en cache ;
137. purge du bruit selon rétention ;
138. purge ne casse pas paquet de clôture ;
139. métriques sans cardinalité dangereuse ;
140. exercice complet SEV-2.

Minimum attendu : **140 épreuves** dans la garde de capacité, plus les intégrations HTTP, console et PostgreSQL.

## 20. Intégrations Laravel

Créer au minimum :

```text
apps/console-laravel/tests/Integration/incidents_api_p1.php
apps/console-laravel/tests/Integration/incidents_console_p1.php
apps/console-laravel/tests/Integration/incidents_contrats_p1.php
apps/console-laravel/tests/Integration/incidents_restauration_p1.php
```

## 21. CI

Raccorder à :

```text
.github/workflows/core-operational-tests.yml
```

Contrôles :

- syntaxe PHP ;
- tests capacité ;
- intégrations Laravel ;
- tests OpenAPI ;
- analyse des contrats ;
- analyse des secrets ;
- PostgreSQL réel ;
- configuration cache ;
- sauvegarde/restauration ;
- diff documentaire ;
- garde de catalogue.

Aucun test antérieur ne doit être supprimé ou neutralisé pour faire passer la capacité.
