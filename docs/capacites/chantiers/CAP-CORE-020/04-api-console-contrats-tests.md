# CAP-CORE-020 — API, console, contrats et tests

## 1. Politique d’autorisation

Créer et activer :

```text
POL-DIRECTORY-ATLAS-V1
```

Actions minimales :

```text
rechercher-annuaire
consulter-entree-annuaire
consulter-relations-atlas
analyser-impact-atlas
consulter-historique-atlas
consulter-endpoints-internes
consulter-divergences-atlas
consulter-diagnostics-atlas
lancer-collecte-atlas
lancer-reconciliation-atlas
rejouer-evenements-atlas
creer-instantane-atlas
exporter-atlas
publier-projection-annuaire
retirer-projection-publique
verifier-instantane-atlas
gerer-collecteurs-atlas
gerer-profils-visibilite-atlas
```

Les actions sont des références exactes de `CAP-CORE-010`.

La politique contrôle au minimum :

- identité et assurance de session ;
- organisation ;
- fonction et mandat ;
- realm ;
- produit ;
- classification ;
- type d’entrée ;
- type de relation ;
- finalité ;
- portée de la requête ;
- profondeur demandée ;
- export ou simple lecture ;
- profil de visibilité.

Le fondateur ne bénéficie d’aucune lecture implicite de tous les realms ni d’un accès automatique aux endpoints sensibles.

---

## 2. Assurance de session

Niveaux minimaux à inscrire dans la politique :

```text
lecture Directory ordinaire : A1 ou supérieur selon classification
analyse de graphe interne : A2 si elle révèle des dépendances sensibles
consultation endpoints internes : A2
réconciliation manuelle : A2
publication publique : A2 + décision si exigée
export d’un instantané sensible : A2 ou A3 selon portée
modification des profils de visibilité : A3 si disponible
```

Une session issue uniquement d’un code de secours ne suffit pas pour une publication ou un export critique si la politique exige un facteur fort.

---

## 3. API v1 — recherche Directory

Routes minimales :

```text
GET /api/v1/directory
GET /api/v1/directory/{reference}
GET /api/v1/directory/{reference}/historique
GET /api/v1/directory/{reference}/observations
GET /api/v1/directory/{reference}/endpoints
GET /api/v1/directory/{reference}/divergences
```

Filtres de `GET /api/v1/directory` :

```text
q
reference
type
capacite
organisation
realm
produit
contrat
source
etat_declare
etat_observe
fraicheur
completude
classification
page
par_page
```

Règles :

- pagination obligatoire ;
- `par_page` borné ;
- ordre déterministe ;
- recherche exacte prioritaire ;
- champs filtrés avant sérialisation ;
- aucune facette sensible non autorisée ;
- `404` si référence inconnue ou invisible selon politique ;
- `422` pour filtre invalide ;
- `503` si le magasin Atlas est indisponible.

La réponse indique :

```text
source
revision
observee_le
fraicheur
classification
projection
```

---

## 4. API v1 — Atlas et voisinage

```text
GET  /api/v1/atlas/{reference}/voisinage
POST /api/v1/atlas/impact
GET  /api/v1/atlas/impact/{reference}
GET  /api/v1/atlas/{reference}/chemins
GET  /api/v1/atlas/{reference}/dependances-amont
GET  /api/v1/atlas/{reference}/dependances-aval
```

Paramètres :

```text
sens
profondeur
types_relations
realm
instant
inclure_historique
limite_noeuds
```

Réponse :

```json
{
  "point_depart": "DIR-PRD-...",
  "instant": "...",
  "limites": {
    "profondeur": 3,
    "noeuds": 200,
    "tronque": false
  },
  "noeuds": [],
  "relations": [],
  "divergences": [],
  "fraicheur": {},
  "empreinte": "..."
}
```

Règles :

- résultat borné ;
- aucun parcours non autorisé ;
- contrôle de realm à chaque étape ;
- délai maximal ;
- annulation propre en dépassement ;
- pas de fuite par nœuds masqués ;
- aucune exécution d’action depuis une réponse Atlas.

---

## 5. API v1 — collectes et réconciliations

```text
GET  /api/v1/atlas/collecteurs
GET  /api/v1/atlas/collectes
GET  /api/v1/atlas/collectes/{reference}
POST /api/v1/atlas/collectes
GET  /api/v1/atlas/reconciliations
GET  /api/v1/atlas/reconciliations/{reference}
POST /api/v1/atlas/reconciliations
POST /api/v1/atlas/reconciliations/{reference}/reprise
```

Une demande contient seulement des paramètres autorisés :

```text
collecteur_reference
capacite_source
realm
mode
point_de_coupure
dry_run
idempotency_key
```

Interdictions :

- URL libre ;
- SQL libre ;
- commande shell ;
- chemin de fichier arbitraire ;
- credentials ;
- modification du contrat collecteur dans la requête.

---

## 6. API v1 — divergences

```text
GET  /api/v1/atlas/divergences
GET  /api/v1/atlas/divergences/{reference}
POST /api/v1/atlas/divergences/{reference}/analyse
POST /api/v1/atlas/divergences/{reference}/resolution
POST /api/v1/atlas/divergences/{reference}/faux-positif
```

Une résolution exige :

- motif canonique ;
- source corrigée ou justification ;
- décision si l’écart est accepté temporairement ;
- exception CAP-CORE-017 si une exigence dérogeable est concernée ;
- preuve ;
- autorisation ;
- idempotency key.

L’API ne permet pas de modifier directement l’état déclaré d’une source souveraine.

---

## 7. API v1 — instantanés et exports

```text
GET  /api/v1/atlas/instantanes
POST /api/v1/atlas/instantanes
GET  /api/v1/atlas/instantanes/{reference}
GET  /api/v1/atlas/instantanes/{reference}/paquet
POST /api/v1/atlas/instantanes/{reference}/verification
POST /api/v1/atlas/exports
GET  /api/v1/atlas/exports/{reference}
```

Un instantané exige :

```text
portee
profil_visibilite
realm
instant_de_coupure
finalite
preuve
```

Règles :

- paquet signé par CAP-CORE-015 ;
- taille maximale ;
- archive non exécutable ;
- noms de fichiers sûrs ;
- aucun chemin sortant ;
- aucune donnée hors profil ;
- expiration pour les exports temporaires ;
- journalisation de chaque téléchargement sensible.

---

## 8. API v1 — projections publiques

```text
GET    /api/v1/public/directory
GET    /api/v1/public/directory/{reference}
GET    /api/v1/atlas/projections-publiques
POST   /api/v1/atlas/projections-publiques
GET    /api/v1/atlas/projections-publiques/{reference}
POST   /api/v1/atlas/projections-publiques/{reference}/activation
POST   /api/v1/atlas/projections-publiques/{reference}/retrait
```

Les routes publiques ne sont actives que si :

```text
DIRECTORY_ATLAS_PUBLIC_ENABLED=true
```

et si la politique, la décision éventuelle et la projection signée sont valides.

Aucun fallback vers la fiche interne.

---

## 9. API v1 — diagnostics

```text
GET /api/v1/atlas/diagnostics
GET /api/v1/atlas/fraicheur
GET /api/v1/atlas/completude
GET /api/v1/atlas/readiness
GET /api/v1/atlas/metriques-resumees
```

Ces routes sont protégées sauf readiness publique minimale.

La readiness générale `/api/v1/health/ready` doit intégrer le magasin Atlas sans exposer de topologie sensible.

---

## 10. Codes d’erreur

Minimum :

```text
ATLAS_INDISPONIBLE
ENTREE_INCONNUE
ENTREE_NON_VISIBLE
TYPE_ENTREE_INVALIDE
RELATION_INVALIDE
REALM_INTERDIT
PROFONDEUR_EXCESSIVE
NOMBRE_NOEUDS_EXCESSIF
REQUETE_TROP_COUTEUSE
PROJECTION_PERIMEE
SOURCE_INDISPONIBLE
COLLECTEUR_INCONNU
COLLECTE_INCOMPLETE
RECONCILIATION_CONCURRENTE
DIVERGENCE_NON_RESOLUE
PROFIL_VISIBILITE_INVALIDE
PUBLICATION_INTERDITE
INSTANTANE_NON_SIGNE
PAQUET_INVALIDE
PREUVE_NON_VERIFIABLE
SCHEMA_INCOMPATIBLE
```

Les erreurs ne révèlent pas l’existence d’une entrée invisible.

---

## 11. OpenAPI et CAP-CORE-009

Mettre à jour :

```text
apps/console-laravel/openapi/core-v1.yaml
```

Enregistrer au minimum :

```text
CTR-ATL-01 — Recherche Directory
CTR-ATL-02 — Fiche et observations
CTR-ATL-03 — Relations et voisinage
CTR-ATL-04 — Analyse d’impact
CTR-ATL-05 — Collecte et réconciliation
CTR-ATL-06 — Divergences
CTR-ATL-07 — Instantanés et exports
CTR-ATL-08 — Projections publiques
CTR-ATL-09 — Diagnostics et fraîcheur
CTR-ATL-10 — Descripteurs et attestations de capacités
```

Chaque contrat décrit :

- producteur ;
- consommateurs ;
- finalité ;
- opérations ;
- schémas ;
- erreurs ;
- autorisation ;
- realm ;
- classification ;
- pagination ;
- limites de graphe ;
- fraîcheur ;
- idempotence ;
- audit ;
- preuve ;
- rétention.

La CI détecte :

- route sans contrat ;
- opération fantôme ;
- schéma divergent ;
- relation non canonique ;
- endpoint public exposant un champ interne ;
- collecteur sans contrat ;
- contrat actif absent de l’Atlas après réconciliation ;
- descripteur référençant un contrat inconnu ;
- analyse de graphe non bornée.

---

## 12. Événements CAP-CORE-014 produits

```text
ENTREE_ATLAS_CREEE
ENTREE_ATLAS_REVISEE
ENTREE_ATLAS_PERIMEE
ENTREE_ATLAS_RETIREE
RELATION_ATLAS_CREEE
RELATION_ATLAS_RETIREE
OBSERVATION_ATLAS_ENREGISTREE
COLLECTE_ATLAS_COMMENCEE
COLLECTE_ATLAS_REUSSIE
COLLECTE_ATLAS_ECHOUEE
RECONCILIATION_ATLAS_TERMINEE
DIVERGENCE_ATLAS_OUVERTE
DIVERGENCE_ATLAS_RESOLUE
INSTANTANE_ATLAS_CREE
PROJECTION_PUBLIQUE_ACTIVEE
PROJECTION_PUBLIQUE_RETIREE
```

Les événements transportent principalement :

```text
reference
source souveraine
type
realm
etat minimal
preuve
correlation
```

Ils ne transportent pas la topologie complète, les endpoints internes ni les exports.

---

## 13. Audit CAP-CORE-013

Tracer au minimum :

```text
RECHERCHE_DIRECTORY_SENSIBLE
FICHE_DIRECTORY_CONSULTEE
GRAPHE_ATLAS_CONSULTE
ANALYSE_IMPACT_DEMANDEE
COLLECTE_ATLAS_DEMANDEE
RECONCILIATION_ATLAS_DEMANDEE
DIVERGENCE_ATLAS_TRAITEE
INSTANTANE_ATLAS_CREE
EXPORT_ATLAS_DEMANDE
EXPORT_ATLAS_TELECHARGE
PROJECTION_PUBLIQUE_PROPOSEE
PROJECTION_PUBLIQUE_ACTIVEE
PROJECTION_PUBLIQUE_RETIREE
OPERATION_ATLAS_REFUSEE
```

Chaque trace contient :

- acteur ;
- action ;
- finalité ;
- realm ;
- portée ;
- résultat ;
- politique ;
- mandat ;
- corrélation ;
- preuve ;
- aucun contenu exporté complet.

---

## 14. Console d’administration

Créer une entrée :

```text
Directory & Atlas
```

### Tableau de bord

Afficher :

- nombre d’entrées par type ;
- relations actives ;
- projections fraîches, à surveiller et périmées ;
- collectes en échec ;
- réconciliations en retard ;
- divergences ouvertes ;
- entrées incomplètes ;
- incidents affectant des nœuds ;
- risques critiques projetés ;
- instantané récent ;
- état des collecteurs.

### Recherche Directory

Filtres :

```text
référence
nom
type
organisation
realm
produit
capacité
contrat
source
état déclaré
état observé
fraîcheur
classification
```

### Fiche d’entrée

Onglets :

```text
Résumé
Source et révision
États
Observations
Relations
Contrats
Realms
Endpoints
Responsabilités
Risques et incidents
Divergences
Historique
Preuves
```

### Vue Atlas

Fonctions :

- point de départ explicite ;
- profondeur réglable dans les limites ;
- filtres de relations ;
- sens amont/aval ;
- masquage selon droits ;
- affichage de fraîcheur ;
- signalement des relations périmées ;
- export borné.

La visualisation graphique doit avoir une alternative tabulaire accessible.

### Collectes

Afficher :

- source ;
- contrat ;
- point de coupure ;
- statut ;
- volumes ;
- erreurs ;
- durée ;
- reprise ;
- preuve.

### Divergences

Afficher les deux sources en projection minimale, jamais les secrets ni dossiers complets.

### Projections publiques

Prévisualisation exacte avant activation. Aucun bouton « publier tout ».

La console utilise les mêmes services applicatifs que l’API. Aucune écriture SQL directe.

---

## 15. Tests de capacité

Créer :

```text
core/annuaire-atlas/tests/directory_atlas_p3.php
```

Épreuves minimales :

1. migration PostgreSQL ;
2. migration SQLite ;
3. PostgreSQL obligatoire en production ;
4. aucun fallback silencieux ;
5. bootstrap idempotent ;
6. descripteur valide accepté ;
7. descripteur inconnu refusé ;
8. descripteur avec secret refusé ;
9. descripteur déclarant lui-même `GO` refusé ;
10. contrat inconnu refusé ;
11. type d’entrée inconnu refusé ;
12. référence canonique requise ;
13. création d’entrée ;
14. référence immuable ;
15. réattribution interdite ;
16. révision ajout-seul ;
17. révision identique dédupliquée ;
18. empreinte divergente crée une révision ;
19. état déclaré séparé de l’état observé ;
20. observation future refusée ;
21. observation expirée marquée périmée ;
22. fraîcheur recalculée ;
23. complétude par type ;
24. champ absent non inventé ;
25. relation exacte créée ;
26. relation floue refusée ;
27. relation sans source refusée ;
28. relation orpheline refusée ;
29. relation réflexive non autorisée refusée ;
30. relation inter-realm sans franchissement refusée ;
31. relation historique conservée ;
32. retrait en deux phases ;
33. collecte partielle ne retire rien ;
34. collecte complète retire l’élément absent ;
35. reprise après crash ;
36. lot idempotent ;
37. événement dupliqué idempotent ;
38. événement ancien n’écrase pas le nouveau ;
39. événement hors realm refusé ;
40. événement au schéma invalide mis en quarantaine ;
41. checkpoint monotone ;
42. réconciliation concurrente refusée ;
43. source indisponible ne retire rien ;
44. source retirée conserve historique ;
45. divergence état déclaré/observé ;
46. divergence de contrat ;
47. résolution sans preuve refusée ;
48. faux positif sans justification refusé ;
49. endpoint valide accepté ;
50. endpoint avec credentials refusé ;
51. endpoint interne masqué au public ;
52. endpoint non autorisé jamais sondé ;
53. recherche exacte ;
54. recherche textuelle ;
55. pagination stable ;
56. limite de page ;
57. filtre realm ;
58. filtre classification ;
59. fiche invisible retourne réponse non révélatrice ;
60. voisinage profondeur 1 ;
61. voisinage profondeur maximale ;
62. profondeur excessive refusée ;
63. nombre de nœuds excessif tronqué ou refusé ;
64. cycle de graphe borné ;
65. nœud masqué ne fuit pas ;
66. analyse amont ;
67. analyse aval ;
68. chemin contractuel exact ;
69. chemin incomplet non inventé ;
70. requête historique ;
71. historique lacunaire déclaré ;
72. profil interne ;
73. profil partenaire ;
74. profil public minimal ;
75. classification héritée ;
76. projection publique sans décision refusée lorsque requise ;
77. projection publique signée ;
78. retrait projection publique ;
79. aucun fallback vers fiche interne ;
80. instantané déterministe ;
81. instantané signé ;
82. instantané falsifié refusé ;
83. export borné ;
84. export sans secret ;
85. archive avec chemin sortant refusée ;
86. audit recherche sensible ;
87. audit export ;
88. événements minimisés ;
89. readiness verte ;
90. readiness dégradée si collecte en retard ;
91. magasin corrompu non prêt ;
92. diagnostic malgré source indisponible ;
93. vérification sans réparation ;
94. purge observations respecte rétention ;
95. preuve retenue après purge ;
96. sauvegarde ;
97. restauration ;
98. restauration ne ressuscite pas projection publique retirée sans rapprochement ;
99. configuration Laravel mise en cache ;
100. concurrence création entrée ;
101. concurrence révision ;
102. concurrence relation ;
103. concurrence checkpoint ;
104. requête trop coûteuse interrompue ;
105. injection SQL neutralisée ;
106. XSS neutralisée dans noms projetés ;
107. URL malveillante refusée ;
108. JSON surdimensionné refusé ;
109. champ secret détecté par nom ;
110. valeur de jeton détectée dans projection ;
111. parent realm sans accès enfant ;
112. franchissement autorisé visible seulement au profil autorisé ;
113. produit suspendu projeté ;
114. contrat suspendu impacte relations ;
115. incident actif projeté sans détails sensibles ;
116. risque projeté sans dossier complet ;
117. Matching ne reçoit que la projection autorisée ;
118. CAP-CORE-020 ne calcule aucun score de matching ;
119. CI détecte route sans OpenAPI ;
120. CI détecte descripteur invalide ;
121. CI détecte relation libre ;
122. exercice PostgreSQL réel ;
123. restauration PostgreSQL réelle ;
124. instantané après restauration ;
125. analyse d’impact après restauration ;
126. absence de lecture SQL inter-magasin ;
127. absence de scan réseau ;
128. absence de scan Markdown comme vérité ;
129. toutes les capacités GO restent vertes ;
130. rapport final reproductible.

Ce minimum peut être étendu, jamais réduit pour faire passer la CI.

---

## 16. Tests d’intégration Laravel

Créer au minimum :

```text
apps/console-laravel/tests/Integration/directory_atlas_api_p1.php
apps/console-laravel/tests/Integration/directory_atlas_console_p1.php
apps/console-laravel/tests/Integration/directory_atlas_authorization_p1.php
apps/console-laravel/tests/Integration/directory_atlas_public_p1.php
```

Tester :

- middleware HTTPS ;
- authentification ;
- autorisation ;
- assurance ;
- isolation realm ;
- sérialisation ;
- pagination ;
- limites de graphe ;
- routes publiques désactivées par défaut ;
- masquage des endpoints ;
- audit ;
- erreurs 403/404/409/422/429/503 ;
- absence de secret dans réponses et logs.

---

## 17. CI

Ajouter à `core-operational-tests.yml` :

```text
CAP-CORE-020 capacity guard
Directory/Atlas API integration
Directory/Atlas console integration
OpenAPI/contract conformity
descriptor validation
cross-store access prohibition
secret scanning
PostgreSQL exercise
backup/restore exercise
```

La contre-épreuve doit démontrer qu’au moins une falsification réelle échoue :

- relation inventée ;
- endpoint interne publié ;
- événement ancien écrasant une révision ;
- collecte partielle retirant des entrées ;
- profondeur de graphe illimitée ;
- descripteur déclarant un faux état de livraison.

---

## 18. Critère documentaire final

Après fusion, créer :

```text
docs/capacites/CAP-CORE-020-directory-atlas.md
```

La fiche finale décrit uniquement :

- code réellement livré ;
- collecteurs réellement raccordés ;
- contrats actifs ;
- données réellement projetées ;
- tests exécutés ;
- limites restantes ;
- état de déploiement ;
- preuve du passage à `GO`.

Ne pas recopier la présente note comme si tout avait été livré.