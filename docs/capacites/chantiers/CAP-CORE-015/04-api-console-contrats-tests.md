# CAP-CORE-015 — INTEGRITY PROOFS
# PARTIE 4/5 — API, CONSOLE, CONTRATS ET TESTS

---

## 1. Principes d’exposition

L’API publique ne doit jamais devenir un service permettant :

- de lire un fichier arbitraire du serveur ;
- de demander la signature d’un contenu libre ;
- de choisir une clé privée ;
- d’extraire un secret ;
- de signer un payload sans contrat ;
- d’utiliser le Core comme oracle cryptographique ;
- de contourner `CAP-CORE-016` ;
- de scanner toutes les preuves de tous les realms.

Les commandes d’émission passent par des services applicatifs gouvernés.

L’API la plus largement exposable est l’API de **vérification d’une preuve déjà émise**, avec des réponses minimales.

---

## 2. API v1 — lectures

Routes proposées :

```text
GET /api/v1/preuves
GET /api/v1/preuves/{reference}
GET /api/v1/preuves/{reference}/etat
GET /api/v1/preuves/{reference}/empreintes
GET /api/v1/preuves/{reference}/signatures
GET /api/v1/preuves/{reference}/manifeste
GET /api/v1/preuves/{reference}/attestation
GET /api/v1/preuves/{reference}/checkpoint
GET /api/v1/preuves/{reference}/verifications
GET /api/v1/preuves/{reference}/liens
GET /api/v1/preuves/{reference}/paquets
```

Règles :

- authentification ;
- autorisation ;
- realm ;
- classification ;
- finalité ;
- contrat ;
- pagination bornée ;
- filtres en liste close ;
- aucun filtre SQL libre ;
- aucun secret ;
- aucune clé privée ;
- aucune charge métier complète.

---

## 3. API v1 — émission interne gouvernée

Routes possibles :

```text
POST /api/v1/preuves
POST /api/v1/preuves/{reference}/empreintes
POST /api/v1/preuves/{reference}/signatures
POST /api/v1/attestations
POST /api/v1/manifestes
POST /api/v1/checkpoints
```

Ces routes ne doivent pas accepter un contenu binaire arbitraire.

Modèles autorisés :

- contenu JSON inline sous limite ;
- référence d’artefact déjà connu ;
- référence d’un fichier dans un répertoire d’exploitation fermé ;
- manifeste construit par un service propriétaire ;
- checkpoint fourni par une capacité intégrée.

La signature porte toujours sur le contexte reconstruit côté serveur.

---

## 4. API v1 — vérification

Routes :

```text
POST /api/v1/preuves/{reference}/verification
POST /api/v1/preuves/verification-paquet
POST /api/v1/preuves/verification-lot
```

### 4.1 Vérification par empreinte présentée

Entrée :

```json
{
  "algorithme": "SHA-256",
  "empreinte": "..."
}
```

Réponse minimale :

```json
{
  "preuve": "PRF-GAMAD-...",
  "resultat": "VALIDE",
  "empreinte_concordante": true,
  "signature_presente": true,
  "signature_valide": true,
  "preuve_utilisable": true,
  "verifie_le": "..."
}
```

### 4.2 Vérification de contenu inline

Autorisée uniquement pour :

- JSON borné ;
- texte borné ;
- contrat explicitement autorisé ;
- taille inférieure à la limite.

Le contenu n’est pas persisté par défaut.

Le journal n’enregistre que son empreinte.

### 4.3 Vérification de paquet

Le paquet est :

- versionné ;
- borné ;
- validé avant extraction ;
- protégé contre path traversal ;
- sans exécution ;
- sans dépendance réseau automatique ;
- vérifié entièrement hors de son contenu déclaratif.

### 4.4 Vérification de lot

- nombre maximal ;
- durée maximale ;
- réponse asynchrone si volume élevé ;
- référence de traitement ;
- aucune fuite entre realms ;
- résultats individuels.

---

## 5. API v1 — cycle

```text
POST /api/v1/preuves/{reference}/suspension
POST /api/v1/preuves/{reference}/revocation
POST /api/v1/preuves/{reference}/compromission
POST /api/v1/preuves/{reference}/archivage
POST /api/v1/preuves/{reference}/export
```

Règles :

- niveau d’assurance élevé pour compromission ;
- confirmation explicite ;
- motif codé ;
- date d’effet ;
- impact présenté avant validation ;
- aucune suppression ;
- audit obligatoire ;
- événement minimal après commit.

---

## 6. Codes HTTP

Utiliser de manière stable :

```text
200 lecture ou vérification exécutée
201 preuve ou résultat créé
202 traitement asynchrone accepté
400 requête mal formée
401 session absente
403 opération refusée
404 preuve inconnue ou invisible
409 conflit d’idempotence ou état incompatible
413 contenu ou paquet trop volumineux
415 format non supporté
422 preuve, signature, manifeste ou dossier invalide
429 limite atteinte
503 registre, fournisseur de clé ou dépendance indisponible
```

Une preuve invalide n’est pas forcément une erreur HTTP.

Exemple :

```text
HTTP 200
resultat = SIGNATURE_INVALIDE
```

La requête a été traitée correctement ; la preuve a échoué.

---

## 7. Contrats CAP-CORE-009

Créer ou versionner les contrats nécessaires.

Candidats :

```text
CTR-GAMAD-PREUVE-RESOUDRE
CTR-GAMAD-PREUVE-VERIFIER
CTR-GAMAD-PREUVE-PAQUET-VERIFIER
CTR-GAMAD-PREUVE-EMETTRE
CTR-GAMAD-PREUVE-REVOQUER
CTR-GAMAD-MANIFESTE-RESOUDRE
CTR-GAMAD-ATTESTATION-RESOUDRE
EVT-GAMAD-PREUVE-EMISE
EVT-GAMAD-PREUVE-REVOQUEE
EVT-GAMAD-PREUVE-COMPROMISE
EVT-GAMAD-VERIFICATION-ECHOUEE
```

Chaque contrat précise :

- producteur ;
- consommateurs ;
- finalité ;
- realm ;
- opérations ;
- schémas ;
- erreurs ;
- autorisation ;
- audit ;
- durée ;
- classification ;
- minimisation.

La signature brute ne doit être retournée que lorsqu’elle est nécessaire à une vérification autonome.

---

## 8. OpenAPI

Mettre à jour :

```text
apps/console-laravel/openapi/core-v1.yaml
```

Puis vérifier la cohérence avec `CAP-CORE-009`.

La CI doit échouer en cas de :

- route absente ;
- opération fantôme ;
- méthode divergente ;
- `operationId` dupliqué ;
- schéma divergent ;
- code d’erreur divergent ;
- action d’autorisation divergente ;
- format de preuve non déclaré ;
- champ secret dans un schéma exposé.

---

## 9. Console d’administration

Créer une entrée :

```text
Preuves d’intégrité
```

### 9.1 Tableau de bord

Afficher :

- preuves émises sur 24 h et 7 jours ;
- preuves signées et non signées ;
- preuves actives, expirées, suspendues, révoquées et compromises ;
- vérifications réussies, invalides et indéterminées ;
- manifestes ;
- checkpoints ;
- clés utilisées par référence seulement ;
- preuves affectées par une compromission ;
- dernière preuve de sauvegarde ;
- dernière attestation de restauration ;
- âge du dernier checkpoint critique ;
- état du fournisseur cryptographique ;
- état du magasin.

### 9.2 Fiche de preuve

Afficher :

- référence ;
- type ;
- sujet ;
- producteur ;
- organisation ;
- realm ;
- finalité ;
- source ;
- contrat ;
- représentation ;
- empreintes ;
- signatures ;
- clé et version publiques ;
- état courant ;
- expiration ;
- liens ;
- vérifications ;
- audit ;
- aucun secret.

### 9.3 Fiche manifeste

Afficher :

- type ;
- racine ;
- signature ;
- membres ;
- tailles ;
- empreintes ;
- membres absents ou divergents ;
- preuve de remplacement ;
- attestation liée.

### 9.4 Émission

La console peut permettre :

- empreinte d’un JSON borné ;
- preuve d’un artefact prédéfini ;
- checkpoint ;
- manifeste de sauvegarde ;
- attestation de restauration ;
- aucune signature de texte libre.

Les choix de clés sont filtrés par `CAP-CORE-016`.

### 9.5 Vérification

Permettre :

- saisie d’une empreinte ;
- dépôt d’un paquet borné ;
- vérification d’une preuve ;
- comparaison d’un manifeste ;
- lecture des divergences ;
- export d’un rapport minimal.

### 9.6 Opérations sensibles

Suspension, révocation et compromission exigent :

- confirmation ;
- niveau d’assurance suffisant ;
- motif ;
- date d’effet ;
- estimation de l’impact ;
- aucun bouton d’effacement.

La console appelle les mêmes services applicatifs que l’API.

---

## 10. Audit CAP-CORE-013

Événements minimaux :

```text
PREUVE_PREPAREE
EMPREINTE_PREUVE_EMISE
SIGNATURE_PREUVE_DEMANDEE
SIGNATURE_PREUVE_EMISE
SIGNATURE_PREUVE_REFUSEE
PREUVE_ACTIVEE
ATTESTATION_EMISE
MANIFESTE_EMIS
CHECKPOINT_EMIS
PREUVE_VERIFIEE
PREUVE_INVALIDE_DETECTEE
PREUVE_SUSPENDUE
PREUVE_REVOQUEE
COMPROMISSION_PREUVE_DECLAREE
PAQUET_PREUVE_EXPORTE
BOOTSTRAP_PREUVES_EXECUTE
OPERATION_PREUVE_REFUSEE
```

Chaque trace inclut :

- acteur ;
- action ;
- preuve ;
- type ;
- sujet ;
- realm ;
- résultat ;
- politique ;
- décision ;
- clé/version par référence ;
- corrélation ;
- empreinte lorsque nécessaire ;
- jamais l’artefact complet ;
- jamais la clé privée ;
- jamais un secret.

---

## 11. Événements CAP-CORE-014

Publier seulement les événements utiles aux consommateurs autorisés.

```text
PREUVE_EMISE
PREUVE_REVOQUEE
PREUVE_COMPROMISE
MANIFESTE_EMIS
CHECKPOINT_EMIS
VERIFICATION_ECHOUEE_CRITIQUE
```

Payload recommandé :

```json
{
  "preuve_reference": "PRF-GAMAD-...",
  "type_preuve": "MANIFESTE",
  "sujet_type": "SAUVEGARDE",
  "sujet_reference": "BKP-...",
  "realm_reference": "RLM-...",
  "etat": "ACTIVE",
  "cree_le": "..."
}
```

Ne pas publier :

- signature brute sans nécessité ;
- certificat complet sans contrat ;
- artefact ;
- données métier ;
- secret ;
- chemin physique interne.

---

## 12. Tests de capacité

Créer :

```text
core/registre-preuves/tests/preuves_p3.php
```

Épreuves minimales :

1. migration du magasin ;
2. PostgreSQL obligatoire en production ;
3. SQLite local isolé ;
4. référence unique ;
5. producteur obligatoire ;
6. realm actif ;
7. source utilisable ;
8. finalité active ;
9. contrat externe obligatoire ;
10. type de preuve canonique ;
11. préparation idempotente ;
12. idempotency key contradictoire refusée ;
13. JSON canonique déterministe ;
14. ordre des clés sans effet ;
15. ordre des listes préservé ;
16. Unicode normalisé ;
17. valeur JSON non finie refusée ;
18. octets bruts inchangés ;
19. texte traité brut par défaut ;
20. SHA-256 exact ;
21. SHA-512 exact ;
22. MD5 refusé ;
23. SHA-1 refusé ;
24. longueur d’empreinte vérifiée ;
25. comparaison constante utilisée ;
26. contenu inline borné ;
27. chemin absolu refusé ;
28. path traversal refusé ;
29. secret dans métadonnées refusé ;
30. clé privée refusée ;
31. jeton refusé ;
32. preuve non signée explicitement identifiée ;
33. contexte signé déterministe ;
34. signature Ed25519 valide ;
35. signature modifiée invalide ;
36. contexte modifié invalide ;
37. clé différente invalide ;
38. clé non autorisée refusée ;
39. clé hors realm refusée ;
40. clé hors environnement refusée ;
41. usage de clé incompatible refusé ;
42. clé retirée normalement vérifie l’historique ;
43. clé compromise avant signature affecte la confiance ;
44. clé compromise après signature selon politique ;
45. preuve active après vérification immédiate ;
46. panne fournisseur laissant preuve préparée ;
47. reprise idempotente après panne ;
48. absence de double signature logique ;
49. signature non vérifiable empêchant activation ;
50. manifeste vide refusé ;
51. membres dupliqués refusés ;
52. chemin de membre invalide refusé ;
53. racine de manifeste déterministe ;
54. membre modifié détecté ;
55. membre manquant détecté ;
56. membre supplémentaire détecté ;
57. ordre significatif respecté ;
58. ordre non significatif normalisé ;
59. attestation conforme ;
60. attestation hors schéma refusée ;
61. champ supplémentaire refusé ;
62. attestation critique non signée refusée ;
63. checkpoint journal conforme ;
64. tête divergente détectée ;
65. séquence divergente détectée ;
66. vérification valide ;
67. empreinte divergente ;
68. signature invalide ;
69. preuve expirée ;
70. preuve suspendue ;
71. preuve révoquée ;
72. preuve compromise ;
73. vérification indéterminée sans artefact ;
74. résultat de vérification immuable ;
75. révocation en ajout seul ;
76. preuve révoquée non réactivable ;
77. lien de remplacement ;
78. cycle `DERIVE_DE` refusé ;
79. paquet exportable valide ;
80. paquet avec path traversal refusé ;
81. paquet avec clé privée refusé ;
82. paquet modifié invalide ;
83. export borné par classification ;
84. lecture inter-realm refusée ;
85. realm parent non omniscient ;
86. contrat inactif refusé ;
87. audit sans contenu complet ;
88. événement sans signature brute ;
89. bootstrap idempotent ;
90. bootstrap sans signature rétroactive ;
91. baseline actuelle reprise comme empreinte non signée ;
92. checkpoint CAP-CORE-013 ;
93. manifeste CAP-CORE-019 ;
94. attestation de restauration ;
95. compatibilité empreintes CAP-CORE-009 ;
96. concurrence sur idempotence ;
97. transaction et rollback audit ;
98. configuration Laravel mise en cache ;
99. sauvegarde/restauration du registre ;
100. contre-épreuve démontrant que la garde sait échouer.

Chaque règle de sécurité doit avoir une contre-épreuve.

---

## 13. Tests d’intégration Laravel

Créer au minimum :

```text
apps/console-laravel/tests/Integration/preuves_v1_p1.php
apps/console-laravel/tests/Integration/preuves_console_p1.php
apps/console-laravel/tests/Integration/preuves_signature_p1.php
apps/console-laravel/tests/Integration/preuves_manifestes_p1.php
apps/console-laravel/tests/Integration/preuves_verification_p1.php
apps/console-laravel/tests/Integration/preuves_continuite_p1.php
```

Conserver verts :

- API générale ;
- console UX ;
- accès ;
- identités ;
- produits ;
- sources ;
- politiques ;
- contrats ;
- vocabulaire ;
- organisations ;
- realms ;
- événements ;
- secrets et clés ;
- fédération ;
- continuité ;
- OpenAPI ;
- import SQLite ;
- PostgreSQL ;
- configuration mise en cache.

---

## 14. Scénario d’intégration obligatoire — preuve simple

1. créer une preuve préparée ;
2. fournir un JSON borné ;
3. canonicaliser ;
4. calculer SHA-256 ;
5. vérifier avec le même JSON dans un ordre de clés différent ;
6. obtenir `VALIDE` ;
7. modifier un champ ;
8. obtenir `EMPREINTE_DIVERGENTE` ;
9. vérifier que le contenu n’est pas journalisé.

---

## 15. Scénario obligatoire — signature et rotation

1. sélectionner une version de clé active dans `CAP-CORE-016` ;
2. préparer une preuve ;
3. demander la signature ;
4. vérifier immédiatement ;
5. activer ;
6. vérifier publiquement avec la clé publique ;
7. faire tourner la clé ;
8. vérifier que l’ancienne preuve reste vérifiable ;
9. émettre une nouvelle preuve avec la nouvelle clé ;
10. déclarer une compromission datée ;
11. vérifier l’impact temporel ;
12. confirmer qu’aucune clé privée n’a quitté le fournisseur.

---

## 16. Scénario obligatoire — sauvegarde/restauration

1. créer un lot de sauvegarde de test ;
2. calculer les empreintes ;
3. construire le manifeste ;
4. signer le manifeste ;
5. chiffrer et transporter hors machine de test ;
6. récupérer ;
7. vérifier archive et manifeste ;
8. restaurer dans des bases isolées ;
9. exécuter les contrôles de `CAP-CORE-019` ;
10. émettre une attestation de restauration ;
11. signer ;
12. lier `RESTAURE_DEPUIS` ;
13. vérifier le paquet complet ;
14. falsifier un dump de test ;
15. confirmer le refus avant restauration.

---

## 17. Scénario obligatoire — journal

1. enregistrer des événements d’audit ;
2. vérifier la chaîne ;
3. obtenir la tête et le nombre ;
4. créer un checkpoint ;
5. signer ;
6. vérifier ;
7. falsifier la copie d’un événement dans une base isolée ;
8. constater la divergence ;
9. ne pas modifier le journal de production ;
10. éviter toute boucle de checkpoints.

---

## 18. Tests de sécurité

Vérifier explicitement :

- aucune clé privée dans les réponses ;
- aucune clé privée en base ;
- aucun secret dans les logs ;
- aucun secret dans l’audit ;
- aucun secret dans les événements ;
- aucun chemin arbitraire ;
- aucune lecture `/etc/passwd` ou équivalent ;
- aucun SSRF depuis un artefact URL ;
- aucun zip-slip ;
- aucune décompression illimitée ;
- aucune signature de texte libre ;
- aucun choix arbitraire de fournisseur ;
- aucun algorithme faible ;
- aucune lecture inter-realm ;
- aucune vérification déclarée valide en cas de dépendance inconnue.

---

## 19. Tests de performance

Mesurer :

- empreinte en flux d’un artefact représentatif ;
- manifeste de taille maximale ;
- vérification d’un lot maximal ;
- pagination des preuves ;
- recherche par sujet ;
- impact des résultats de vérification ;
- contention sur émission idempotente.

Définir des seuils réalistes fondés sur le serveur cible.

Ne pas inventer un SLA sans mesure.

---

## 20. Garde CI

Ajouter :

```text
CAP-CORE-015 (integrity proofs)
```

à `.github/workflows/core-operational-tests.yml`.

La garde doit exécuter :

- syntaxe ;
- tests de capacité ;
- intégrations Laravel ;
- OpenAPI/contrats ;
- SQLite ;
- PostgreSQL ;
- configuration mise en cache ;
- sauvegarde/restauration ;
- scénario cryptographique ;
- scan de secrets ;
- contre-épreuve.

Ne désactiver aucun contrôle existant.
