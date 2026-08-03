# CAP-CORE-008 — API, console, contrats, audit et tests

## 1. Politique d’autorisation

Créer et activer :

```text
POL-DECISIONS-V1
```

Actions minimales :

```text
lister-decisions
consulter-decision
ouvrir-dossier-decision
modifier-dossier-avant-gel
soumettre-dossier-decision
ouvrir-instruction-decision
ajouter-piece-decision
ajouter-participant-decision
demander-avis-decision
exprimer-position-decision
declarer-decision-prete
adopter-decision
rejeter-decision
ajourner-decision
classer-decision-sans-suite
mettre-decision-en-vigueur
executer-effet-decision
accuser-execution-decision
relancer-effet-decision
annuler-decision
remplacer-decision
rectifier-decision
gerer-exigences-decision
exporter-paquet-decision
verifier-paquet-decision
consulter-diagnostics-decisions
```

Les actions doivent être des références canoniques exactes de `CAP-CORE-010`.

La politique doit contrôler au minimum :

- identité ;
- organisation ;
- rôle ;
- mandat ;
- realm ;
- finalité ;
- classification ;
- type de décision ;
- état du dossier ;
- assurance de session ;
- séparation éventuelle entre instructeur, décideur et exécuteur.

Le fondateur n’obtient pas une permission implicite hors politique.

---

## 2. Assurance de session

Niveaux minimaux :

```text
lecture ordinaire autorisée : selon politique
ouverture et instruction : A1 ou supérieur
expression d’une position : A2
adoption, annulation, remplacement : A2 minimum
compromission de clé, restauration production, arrêt d’urgence : A3 si disponible
```

Les niveaux exacts doivent être inscrits dans la politique et testés.

Une session ouverte avec un code de secours ne doit pas suffire seule à adopter une décision critique si la politique exige un facteur fort.

---

## 3. API v1 — dossiers

Routes minimales :

```text
GET    /api/v1/decisions
POST   /api/v1/decisions
GET    /api/v1/decisions/{reference}
GET    /api/v1/decisions/{reference}/historique
PUT    /api/v1/decisions/{reference}/question
POST   /api/v1/decisions/{reference}/options
DELETE /api/v1/decisions/{reference}/options/{option}
POST   /api/v1/decisions/{reference}/pieces
DELETE /api/v1/decisions/{reference}/pieces/{piece}
POST   /api/v1/decisions/{reference}/participants
DELETE /api/v1/decisions/{reference}/participants/{participant}
POST   /api/v1/decisions/{reference}/soumission
POST   /api/v1/decisions/{reference}/instruction
```

Règles :

- validation stricte ;
- limites de taille ;
- idempotency key sur les commandes sensibles ;
- aucune donnée secrète ;
- aucune pièce téléchargée automatiquement ;
- `404` pour une référence inconnue ;
- `409` pour une concurrence ou une référence déjà utilisée ;
- `422` pour une transition invalide ;
- `403` pour une autorisation refusée ;
- `503` si une dépendance souveraine est indisponible.

---

## 4. API v1 — instruction et positions

```text
POST /api/v1/decisions/{reference}/avis-demandes
POST /api/v1/decisions/{reference}/avis
POST /api/v1/decisions/{reference}/positions
GET  /api/v1/decisions/{reference}/participants
GET  /api/v1/decisions/{reference}/positions
GET  /api/v1/decisions/{reference}/quorum
POST /api/v1/decisions/{reference}/pret-a-decider
POST /api/v1/decisions/{reference}/reouverture-instruction
```

La réponse de quorum doit préciser :

```text
mode
règle
participants attendus
participants compétents
participants incompétents
positions finales
seuil
quorum atteint
motifs de blocage
```

Aucun endpoint ne doit permettre de forcer manuellement `quorum_atteint=true`.

---

## 5. API v1 — décision

```text
POST /api/v1/decisions/{reference}/adoption
POST /api/v1/decisions/{reference}/rejet
POST /api/v1/decisions/{reference}/ajournement
POST /api/v1/decisions/{reference}/sans-suite
POST /api/v1/decisions/{reference}/mise-en-vigueur
POST /api/v1/decisions/{reference}/expiration
POST /api/v1/decisions/{reference}/annulation
POST /api/v1/decisions/{reference}/remplacement
POST /api/v1/decisions/{reference}/rectification
```

L’adoption retourne :

```json
{
  "decision": "DEC-...",
  "etat": "DECIDEE",
  "resultat": "APPROUVEE",
  "prise_le": "...",
  "valide_a_partir_de": "...",
  "option_retenue": "OPT-...",
  "empreinte": "...",
  "preuve": "PRV-...",
  "effets": ["DEF-..."],
  "correlation_id": "COR-..."
}
```

La réponse ne contient aucune clé privée, aucun jeton et aucun document complet.

---

## 6. API v1 — effets et exécutions

```text
GET  /api/v1/decisions/{reference}/effets
GET  /api/v1/decisions/{reference}/executions
POST /api/v1/decisions/{reference}/effets/{effet}/execution
POST /api/v1/decisions/{reference}/effets/{effet}/accuse
POST /api/v1/decisions/{reference}/effets/{effet}/relance
POST /api/v1/decisions/{reference}/effets/{effet}/compensation
POST /api/v1/decisions/{reference}/rapprochement
```

L’endpoint d’exécution interne ne doit pas devenir une passerelle permettant d’appeler une opération arbitraire.

Il vérifie exactement :

- effet préenregistré ;
- contrat et version ;
- opération ;
- cible ;
- schéma ;
- autorisation ;
- preuve ;
- idempotency key.

---

## 7. API v1 — exigences

```text
GET    /api/v1/exigences-decisions
POST   /api/v1/exigences-decisions
GET    /api/v1/exigences-decisions/{reference}
POST   /api/v1/exigences-decisions/{reference}/activation
POST   /api/v1/exigences-decisions/{reference}/suspension
POST   /api/v1/exigences-decisions/{reference}/retrait
GET    /api/v1/exigences-decisions/resolution
```

La résolution reçoit :

```text
contrat
version
operation
action
ressource
organisation
realm
date
```

Elle retourne :

```text
decision_formelle_requise
mode_minimal
type_requis
preuve_requise
quorum
source
```

Elle ne retourne jamais `PERMIS` ou `REFUSE` : l’autorisation reste à `CAP-CORE-004`.

---

## 8. API v1 — vérification et export

```text
GET  /api/v1/decisions/{reference}/verification
POST /api/v1/decisions/{reference}/verification
GET  /api/v1/decisions/{reference}/paquet
GET  /api/v1/decisions/{reference}/projection-publique
POST /api/v1/decisions/paquets/verification
```

Règles :

- export complet réservé ;
- projection publique explicitement définie ;
- aucune pièce externe incluse par défaut ;
- paquet signé ;
- taille bornée ;
- archive non exécutable ;
- noms de fichiers sûrs ;
- pas de chemin relatif sortant ;
- pas d’extraction automatique non sécurisée.

---

## 9. OpenAPI et CAP-CORE-009

Mettre à jour :

```text
apps/console-laravel/openapi/core-v1.yaml
```

Enregistrer dans `CAP-CORE-009` au minimum :

```text
CTR-DEC-01 — Dossiers de décision
CTR-DEC-02 — Instruction et positions
CTR-DEC-03 — Adoption et résultat
CTR-DEC-04 — Mise en vigueur et effets
CTR-DEC-05 — Accusés d’exécution
CTR-DEC-06 — Exigences de décision
CTR-DEC-07 — Vérification et paquet de décision
```

Chaque contrat précise :

- producteur ;
- consommateurs ;
- finalité ;
- opérations ;
- schémas ;
- erreurs ;
- obligations d’autorisation ;
- obligation d’audit ;
- obligation de preuve ;
- realm ;
- idempotence ;
- durée ;
- classification ;
- données minimales.

La CI détecte :

- route sans opération OpenAPI ;
- opération fantôme ;
- méthode divergente ;
- schéma divergent ;
- code d’erreur divergent ;
- action d’autorisation divergente ;
- contrat d’effet sans cible ;
- effet sans contrat actif ;
- décision critique sans obligation de preuve.

---

## 10. Événements CAP-CORE-014

Types minimaux :

```text
DOSSIER_DECISION_OUVERT
DOSSIER_DECISION_SOUMIS
INSTRUCTION_DECISION_OUVERTE
PIECE_DECISION_AJOUTEE
PARTICIPANT_DECISION_AJOUTE
COMPETENCE_DECIDEUR_INVALIDEE
POSITION_DECISION_EXPRIMEE
DECISION_PRETE_A_ETRE_PRISE
DECISION_ADOPTEE
DECISION_REJETEE
DECISION_AJOURNEE
DECISION_CLASSEE_SANS_SUITE
DECISION_MISE_EN_VIGUEUR
EFFET_DECISION_PRET
EFFET_DECISION_EXECUTE
EFFET_DECISION_EN_ECHEC
EFFET_DECISION_COMPENSE
DECISION_EXPIREE
DECISION_ANNULEE
DECISION_REMPLACEE
DECISION_RECTIFIEE
DIVERGENCE_EXECUTION_DECISION_DETECTEE
```

Les événements ne transportent jamais :

- texte complet du dossier ;
- positions détaillées si classification restreinte ;
- pièces ;
- secrets ;
- données métier complètes.

Ils transportent principalement :

```text
reference décision
organisation
realm
type
état
résultat minimal
ressource
preuve
corrélation
```

---

## 11. Audit CAP-CORE-013

Événements d’audit minimaux :

```text
DOSSIER_DECISION_OUVERT
QUESTION_DECISION_DEFINIE
OPTION_DECISION_AJOUTEE
PIECE_DECISION_REFERENCEE
PARTICIPANT_DECISION_AJOUTE
COMPETENCE_DECIDEUR_VERIFIEE
DOSSIER_DECISION_SOUMIS
INSTRUCTION_DECISION_OUVERTE
AVIS_DECISION_DEMANDE
AVIS_DECISION_ENREGISTRE
POSITION_DECISION_EXPRIMEE
DOSSIER_DECISION_GELE
ADOPTION_DECISION_TENTEE
DECISION_ADOPTEE
DECISION_REJETEE
DECISION_AJOURNEE
DECISION_MISE_EN_VIGUEUR
EFFET_DECISION_EXECUTE
ACCUSE_EXECUTION_DECISION_RECU
EFFET_DECISION_RELANCE
DECISION_EXPIREE
DECISION_ANNULEE
DECISION_REMPLACEE
DECISION_RECTIFIEE
PAQUET_DECISION_EXPORTE
PAQUET_DECISION_VERIFIE
OPERATION_DECISION_REFUSEE
```

Chaque trace contient :

- acteur ;
- action ;
- ressource ;
- résultat ;
- politique ;
- mandat ;
- preuve ;
- corrélation ;
- décision concernée ;
- aucun contenu secret ;
- aucune pièce complète.

---

## 12. Console d’administration

Créer une entrée :

```text
Décisions
```

### Tableau de bord

Afficher :

- dossiers en préparation ;
- dossiers soumis ;
- instructions en retard ;
- décisions prêtes ;
- décisions adoptées aujourd’hui ;
- décisions à mettre en vigueur ;
- décisions proches de l’expiration ;
- effets en attente ;
- effets en échec ;
- effets contradictoires ;
- mandats devenus invalides ;
- preuves non vérifiables ;
- décisions par organisation et realm.

### Liste

Filtres :

```text
référence
organisation
realm
type
domaine
ressource
état
résultat
autorité
date
preuve
exécution
```

### Fiche décision

Onglets :

```text
Résumé
Question
Options
Pièces
Participants
Positions
Résultat
Motifs
Conditions
Effets
Exécutions
Preuves
Historique
Vérification
```

Les données sensibles sont masquées selon classification et autorisation.

### Écran d’instruction

Permet :

- ajouter une référence de pièce ;
- demander un avis ;
- vérifier la compétence ;
- visualiser les manques ;
- préparer les effets ;
- simuler le quorum ;
- geler le dossier.

### Écran de décision

Avant confirmation, afficher :

```text
question exacte
option retenue
résultat
motif
conditions
effets
organisation
realm
mandat
quorum
preuve attendue
date d’effet
expiration
```

La confirmation exige une nouvelle vérification d’assurance de session pour les décisions critiques.

### Écran des effets

Afficher :

- cible ;
- contrat ;
- opération ;
- état ;
- tentatives ;
- accusés ;
- preuve ;
- divergence ;
- relance autorisée ou non.

La console appelle les mêmes services applicatifs que l’API.

Aucune écriture SQL directe.

---

## 13. Projection publique

Une décision peut avoir un résumé public seulement si :

- la politique le permet ;
- la classification le permet ;
- les données sont minimisées ;
- les positions individuelles ne sont pas divulguées sans base ;
- les pièces ne sont pas exposées ;
- la preuve publique est vérifiable sans révéler de secret.

Champs possibles :

```text
reference
type
organisation
realm
objet
résultat
date de décision
date d’effet
état actuel
référence de preuve publique
```

Aucune publication automatique universelle.

---

## 14. Tests de capacité

Créer :

```text
core/registre-decisions/tests/decisions_p3.php
```

Épreuves minimales :

1. migration PostgreSQL ;
2. migration SQLite ;
3. PostgreSQL obligatoire en production ;
4. aucun fallback silencieux ;
5. création d’un dossier valide ;
6. référence unique ;
7. organisation inconnue refusée ;
8. organisation inactive refusée ;
9. realm inconnu refusé ;
10. realm inactif refusé ;
11. type inconnu refusé ;
12. mode inconnu refusé ;
13. finalité inconnue refusée ;
14. ressource inconnue refusée ;
15. source inactive refusée ;
16. secret dans le résumé refusé ;
17. question valide ;
18. question sans action refusée ;
19. question excessive refusée ;
20. révision avant gel ;
21. modification après gel refusée ;
22. option valide ;
23. doublon d’option refusé ;
24. option exécutable refusée ;
25. retrait avant gel conservant l’historique ;
26. pièce par référence ;
27. URL téléchargée automatiquement impossible ;
28. pièce hors finalité refusée ;
29. pièce hors realm refusée ;
30. participant connu ;
31. participant inconnu refusé ;
32. mandat valide ;
33. mandat expiré non compté ;
34. délégation hors portée refusée ;
35. observateur hors quorum ;
36. consulté hors vote ;
37. soumission incomplète refusée ;
38. soumission valide ;
39. soumission idempotente ;
40. instruction ouverte ;
41. instructeur non décideur automatique ;
42. avis demandé ;
43. avis enregistré ;
44. position POUR ;
45. position CONTRE ;
46. position RESERVE sans motif refusée ;
47. nouvelle position conservant l’ancienne ;
48. position après clôture refusée ;
49. autorité unique valide ;
50. autorité unique multiple refusée ;
51. majorité simple calculée ;
52. majorité absolue calculée ;
53. unanimité calculée ;
54. quorum non atteint ;
55. participant incompétent exclu ;
56. gel valide ;
57. gel incomplet refusé ;
58. adoption autorité unique ;
59. adoption collégiale ;
60. adoption sans mandat refusée ;
61. adoption sans autorisation refusée ;
62. adoption sans preuve refusée ;
63. adoption antidatée refusée ;
64. adoption concurrente unique ;
65. résultat immuable ;
66. motifs immuables ;
67. options immuables ;
68. positions immuables ;
69. rejet sans effet ;
70. ajournement immuable ;
71. sans suite gouverné ;
72. mise en vigueur différée ;
73. condition préalable non satisfaite ;
74. condition satisfaite ;
75. effet sans contrat refusé ;
76. effet avec contrat inactif refusé ;
77. effet avec schéma invalide refusé ;
78. effet contenant un secret refusé ;
79. effet prêt ;
80. exécution idempotente ;
81. accusé identique idempotent ;
82. accusé contradictoire refusé ;
83. échec temporaire ;
84. relance bornée ;
85. plafond de tentatives ;
86. échec définitif sans relance ;
87. compensation contractuelle ;
88. rapprochement cohérent ;
89. rapprochement partiel ;
90. rapprochement contradictoire ;
91. expiration ;
92. expiration sans inversion automatique ;
93. annulation par nouvelle décision ;
94. annulation directe refusée ;
95. remplacement sans cycle ;
96. cycle de remplacement refusé ;
97. rectification matérielle ;
98. changement de fond déguisé refusé ;
99. décision inter-realm sans franchissement refusée ;
100. décision inter-realm autorisée et bornée ;
101. realm parent non omniscient ;
102. paquet déterministe ;
103. paquet signé ;
104. paquet falsifié refusé ;
105. signature valide mais mandat invalide signalé ;
106. projection publique minimisée ;
107. classification respectée ;
108. audit sans contenu complet ;
109. événement sans pièce ;
110. restauration du magasin ;
111. historique conservé après restauration ;
112. effets en attente conservés ;
113. configuration Laravel mise en cache ;
114. contre-épreuve démontrant que la garde sait échouer.

Chaque invariant de sécurité possède sa contre-épreuve.

---

## 15. Tests d’intégration Laravel

Créer au minimum :

```text
apps/console-laravel/tests/Integration/decisions_v1_p1.php
apps/console-laravel/tests/Integration/decisions_console_p1.php
apps/console-laravel/tests/Integration/decisions_effets_p1.php
apps/console-laravel/tests/Integration/decisions_preuves_p1.php
apps/console-laravel/tests/Integration/decisions_realms_p1.php
```

Conserver verts :

- authentification ;
- identités ;
- organisations ;
- autorités et mandats ;
- autorisation ;
- produits ;
- sources ;
- politiques ;
- contrats ;
- vocabulaire ;
- realms ;
- audit ;
- événements ;
- secrets et clés ;
- preuves ;
- continuité ;
- fédération ;
- OpenAPI ;
- PostgreSQL ;
- import SQLite ;
- configuration mise en cache.

---

## 16. Scénario E2E obligatoire

1. créer une organisation et un realm actifs ;
2. établir un mandat de décideur ;
3. ouvrir un dossier visant un produit pilote ;
4. ajouter question, options, source et preuve ;
5. ajouter le décideur ;
6. soumettre ;
7. ouvrir l’instruction ;
8. exprimer une position ;
9. geler ;
10. adopter une suspension temporaire ;
11. produire la preuve signée ;
12. mettre en vigueur ;
13. publier l’événement ;
14. exécuter la transition via `CAP-CORE-011` ;
15. recevoir l’accusé ;
16. vérifier l’état réel ;
17. rapprocher ;
18. exporter le paquet ;
19. vérifier le paquet hors du flux d’adoption ;
20. restaurer le registre sur une base isolée ;
21. revérifier la décision et l’exécution.
