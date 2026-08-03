# CAP-CORE-016 — API, console, contrats, audit et tests

## 1. Principe d’exposition

L’API et la console exposent uniquement les métadonnées de gouvernance.

Elles ne doivent jamais exposer :

- une valeur secrète ;
- une clé privée ;
- une phrase secrète ;
- un mot de passe ;
- un jeton ;
- le contenu d’un credential systemd ;
- le contenu d’un fichier `0600` ;
- une variable d’environnement résolue ;
- un trousseau privé ;
- une sortie brute de commande cryptographique.

La résolution du matériel reste une opération PHP interne et bornée.

Aucune route du type suivant ne doit exister :

```text
GET /api/v1/secrets/{reference}/valeur
POST /api/v1/secrets/export
GET /api/v1/cles-privees
```

---

## 2. API v1 — lectures de métadonnées

Ajouter au minimum :

```text
GET /api/v1/secrets-cles
GET /api/v1/secrets-cles/{reference}
GET /api/v1/secrets-cles/{reference}/versions
GET /api/v1/secrets-cles/{reference}/versions/{version}
GET /api/v1/secrets-cles/{reference}/usages
GET /api/v1/secrets-cles/{reference}/dependances
GET /api/v1/secrets-cles/{reference}/rotations
GET /api/v1/secrets-cles/{reference}/compromissions
GET /api/v1/fournisseurs-secrets
GET /api/v1/fournisseurs-secrets/{reference}/diagnostic
GET /api/v1/secrets-cles/diagnostic
```

Les réponses doivent masquer :

- handle fournisseur ;
- chemin complet ;
- nom de variable sensible ;
- identifiant d’infrastructure ;
- information non nécessaire à l’acteur.

Selon autorisation, retourner seulement :

```json
{
  "reference": "SEC-GAMAD-APP-KEY",
  "type": "CLE_APPLICATION",
  "environnement": "PRODUCTION",
  "etat": "ACTIVE_ECRITURE",
  "version": "3",
  "rotation_prevue_le": "2026-11-01T00:00:00Z",
  "fournisseur": "FOU-GAMAD-SYSTEMD-001",
  "materiel_exportable": false
}
```

---

## 3. API v1 — commandes de gouvernance

Ajouter :

```text
POST /api/v1/secrets-cles
POST /api/v1/secrets-cles/{reference}/versions
POST /api/v1/secrets-cles/{reference}/versions/{version}/verification
POST /api/v1/secrets-cles/{reference}/versions/{version}/activation
POST /api/v1/secrets-cles/{reference}/usages
POST /api/v1/secrets-cles/{reference}/rotations
POST /api/v1/rotations-secrets/{reference}/validation
POST /api/v1/rotations-secrets/{reference}/execution
POST /api/v1/secrets-cles/{reference}/versions/{version}/suspension
POST /api/v1/secrets-cles/{reference}/versions/{version}/revocation
POST /api/v1/secrets-cles/{reference}/versions/{version}/compromission
POST /api/v1/secrets-cles/{reference}/versions/{version}/destruction
POST /api/v1/fournisseurs-secrets
POST /api/v1/fournisseurs-secrets/{reference}/verification
```

Les commandes reçoivent uniquement :

- références ;
- métadonnées ;
- handles opaques ;
- plans ;
- motifs ;
- preuves ;
- jamais la valeur.

La création réelle du matériel doit être effectuée :

- par le fournisseur ;
- par un outil d’exploitation spécialisé ;
- ou par une commande CLI interactive protégée qui écrit directement dans le fournisseur sans passer par HTTP.

Même dans ce cas, la valeur ne doit pas être affichée sauf lorsque le protocole exige une remise unique contrôlée.

---

## 4. Codes HTTP

- `200` : lecture ou opération idempotente réussie ;
- `201` : ressource de gouvernance créée ;
- `202` : rotation asynchrone acceptée ;
- `400` : requête mal formée ;
- `401` : session absente ;
- `403` : action refusée ;
- `404` : ressource inconnue ou invisible ;
- `409` : conflit d’état, version ou rotation ;
- `422` : métadonnées invalides, dépendance bloquante ou plan incomplet ;
- `423` : ressource verrouillée par rotation ou compromission ;
- `503` : registre, autorisation, audit ou fournisseur indispensable indisponible.

Aucun message d’erreur ne doit révéler :

- si une valeur précise existe ;
- son format ;
- sa longueur ;
- son chemin ;
- son contenu ;
- un fragment de configuration.

---

## 5. Contrats CAP-CORE-009

Créer et activer les contrats réellement livrés.

Contrats candidats :

```text
CTR-GAMAD-SECRETS-METADONNEES-LIRE
CTR-GAMAD-SECRETS-VERSION-DECLARER
CTR-GAMAD-SECRETS-VERSION-ACTIVER
CTR-GAMAD-SECRETS-USAGE-DECLARER
CTR-GAMAD-SECRETS-ROTATION-PLANIFIER
CTR-GAMAD-SECRETS-ROTATION-EXECUTER
CTR-GAMAD-SECRETS-COMPROMISSION-DECLARER
CTR-GAMAD-SECRETS-MATERIEL-PUBLIC-LIRE
CTR-GAMAD-SECRETS-RESOLUTION-INTERNE
```

Le contrat de résolution interne doit préciser :

- producteur : `CAP-CORE-016` ;
- consommateurs explicitement déclarés ;
- contexte obligatoire ;
- finalité ;
- realm ;
- environnement ;
- opération ;
- aucune exposition HTTP du matériel ;
- erreurs fermées ;
- audit sans valeur.

Les schémas doivent comporter une garde empêchant l’ajout futur d’un champ :

```text
value
secret
private_key
password
passphrase
token
credential_content
```

---

## 6. Vocabulaire CAP-CORE-010

Ajouter ou vérifier les termes nécessaires :

- types de secret ;
- types de fournisseur ;
- états de version ;
- modes d’usage ;
- stratégies de rotation ;
- algorithmes autorisés ;
- environnements ;
- classifications ;
- niveaux de compromission ;
- résultats de vérification ;
- codes d’erreur.

Règle : ajouter un algorithme au vocabulaire ne le rend pas automatiquement autorisé en production.

Le code conserve une liste de compatibilité et des bornes de sécurité.

---

## 7. Politique CAP-CORE-007

`POL-SECRETS-CLES-V1` doit être créée dans le registre des politiques.

La politique doit couvrir exactement les actions appelées par l’application.

Exigences :

- actions canoniques ;
- correspondance exacte ;
- refus par défaut ;
- restrictions par rôle, realm et environnement ;
- assurance renforcée pour les actions sensibles ;
- aucune règle permettant d’obtenir le matériel ;
- simulation réussie avant activation ;
- test de contre-épreuve.

---

## 8. Console

Créer une entrée :

```text
Secrets & clés
```

### 8.1 Tableau de bord

Afficher :

- ressources actives ;
- rotations à venir ;
- rotations en retard ;
- versions expirées ;
- fournisseurs dégradés ;
- usages orphelins ;
- versions compromises ;
- dépendances bloquant une destruction ;
- références encore sur fournisseur de transition ;
- dernière vérification ;
- aucune valeur.

### 8.2 Fiche d’une ressource

Afficher :

- référence ;
- nom ;
- type ;
- finalité ;
- propriétaire ;
- source ;
- realm ;
- environnement ;
- classification ;
- politique de rotation ;
- versions ;
- états ;
- usages ;
- dépendances ;
- rotations ;
- compromissions ;
- matériel public autorisé.

### 8.3 Fiche d’une version

Afficher :

- version ;
- fournisseur ;
- handle masqué ;
- algorithme ;
- empreinte publique ;
- dates ;
- état ;
- usages ;
- dépendances ;
- diagnostic ;
- historique ;
- actions autorisées.

### 8.4 Écran de rotation

Permettre :

- création du plan ;
- inventaire des consommateurs ;
- choix de stratégie ;
- simulation ;
- validation ;
- exécution ;
- suivi des étapes ;
- retour arrière ;
- rapport final.

Aucune zone de texte libre ne doit accepter une valeur secrète.

### 8.5 Compromission

Permettre une déclaration rapide avec :

- version ;
- niveau ;
- source ;
- motif ;
- impact présumé ;
- confirmation ;
- aucune valeur.

L’action doit être protégée mais ne doit pas devenir impossible en situation d’urgence.

### 8.6 Confirmation renforcée

Exiger une confirmation explicite pour :

- activation ;
- révocation ;
- compromission ;
- destruction ;
- retrait d’un fournisseur ;
- exécution d’une rotation de production.

La console appelle les mêmes cas d’usage que l’API.

Aucune écriture directe en base.

---

## 9. CLI d’exploitation

Créer des commandes bornées, par exemple :

```text
core:secrets:bootstrap
core:secrets:diagnostiquer
core:secrets:fournisseurs-verifier
core:secrets:rotation-simuler
core:secrets:rotation-executer
core:secrets:version-compromettre
core:secrets:version-detruire
```

Règles :

- aucune valeur en argument de ligne de commande ;
- préférer fichiers protégés, stdin contrôlé ou fournisseur ;
- masquer les sorties ;
- `--no-interaction` refusé pour une destruction réelle sans mécanisme de validation ;
- aucune commande générique `dump` ;
- aucun export de masse ;
- environnement explicite ;
- confirmation de production ;
- audit obligatoire.

---

## 10. Audit CAP-CORE-013

Ajouter au minimum :

```text
SECRET_REFERENCE_INSCRITE
FOURNISSEUR_SECRET_INSCRIT
FOURNISSEUR_SECRET_VERIFIE
VERSION_SECRET_DECLAREE
VERSION_SECRET_VERIFIEE
USAGE_SECRET_DECLARE
VERSION_SECRET_ACTIVEE
VERSION_SECRET_SUSPENDUE
VERSION_SECRET_REVOQUEE
VERSION_SECRET_COMPROMISE
VERSION_SECRET_DETRUITE
ROTATION_SECRET_PLANIFIEE
ROTATION_SECRET_VALIDEE
ROTATION_SECRET_COMMENCEE
ETAPE_ROTATION_SECRET_REUSSIE
ETAPE_ROTATION_SECRET_ECHOUEE
ROTATION_SECRET_REUSSIE
ROTATION_SECRET_ECHOUEE
RESOLUTION_SECRET_ACCEPTEE
RESOLUTION_SECRET_REFUSEE
OPERATION_SECRET_REFUSEE
```

Chaque événement d’audit contient :

- acteur ;
- action ;
- ressource ;
- version ;
- consommateur ;
- realm ;
- environnement ;
- finalité ;
- résultat ;
- politique ;
- preuve ;
- corrélation ;
- date.

Il ne contient jamais :

- valeur ;
- clé privée ;
- phrase secrète ;
- mot de passe ;
- token ;
- handle complet lorsque sensible ;
- chemin complet inutile ;
- sortie brute de fournisseur.

---

## 11. Événements CAP-CORE-014

Les événements communs sont limités à ce qui est nécessaire aux consommateurs autorisés.

Exemples :

```text
VERSION_SECRET_ACTIVEE
ROTATION_SECRET_REQUISE
ROTATION_SECRET_REUSSIE
ROTATION_SECRET_ECHOUEE
VERSION_SECRET_REVOQUEE
VERSION_SECRET_COMPROMISE
FOURNISSEUR_SECRET_DEGRADE
```

La charge doit respecter le contrat d’événement et la minimisation.

Une compromission ne doit jamais révéler le secret compromis.

---

## 12. Tests de capacité

Créer :

```text
core/registre-secrets-cles/tests/secrets_cles_p3.php
```

Épreuves minimales :

1. migration du magasin ;
2. PostgreSQL obligatoire en production ;
3. SQLite isolé ;
4. bootstrap sans valeur ;
5. bootstrap idempotent ;
6. empreinte bootstrap ;
7. référence unique ;
8. type canonique ;
9. propriétaire connu ;
10. source active ;
11. realm actif ;
12. environnement canonique ;
13. finalité explicite ;
14. colonne de valeur absente ;
15. métadonnée ressemblant à un secret refusée ;
16. fournisseur fichier `0600` valide ;
17. fichier trop permissif refusé ;
18. lien symbolique refusé selon politique ;
19. fichier trop volumineux refusé ;
20. credential systemd valide ;
21. variable de transition explicitement marquée ;
22. fallback silencieux refusé ;
23. version unique ;
24. handle non vide ;
25. clé publique acceptée ;
26. clé privée refusée ;
27. activation sans vérification refusée ;
28. une seule version active en écriture ;
29. ancienne version basculée en lecture ;
30. version lecture refusée pour chiffrer ;
31. ancienne version acceptée pour déchiffrer un artefact lié ;
32. version expirée refusée en écriture ;
33. version suspendue refusée ;
34. version révoquée refusée ;
35. version compromise refusée ;
36. version détruite refusée ;
37. usage sans consommateur refusé ;
38. usage sans finalité refusé ;
39. usage wildcard refusé ;
40. usage autre realm refusé ;
41. usage autre environnement refusé ;
42. usage expiré refusé ;
43. décision absente refusée ;
44. fournisseur indisponible refusé fermé ;
45. résolution sans sérialisation ;
46. aucune valeur dans résultat public ;
47. aucune valeur dans exception ;
48. aucune valeur dans audit ;
49. aucune valeur dans événement ;
50. plan de rotation obligatoire ;
51. plan sans consommateurs refusé ;
52. plan sans retour arrière refusé ;
53. validation avant exécution ;
54. étapes idempotentes ;
55. échec conservant ancienne version ;
56. rotation réussie activant nouvelle version ;
57. dépendance empêchant destruction ;
58. expiration de dépendance autorisant destruction ;
59. fournisseur confirmant destruction ;
60. compromission bloquant immédiatement ;
61. compromission auditée ;
62. événement de compromission minimal ;
63. rotation `APP_KEY` avec ancienne clé lisible ;
64. cache de configuration reconstruit ;
65. passkeys réelles non affectées par rotation des descripteurs factices ;
66. rotation GPG conservant lecture d’anciens lots ;
67. rotation PostgreSQL sans mot de passe dans les arguments ;
68. rotation SSH bornée ;
69. rotation FTP sans secret dans logs ;
70. lecture de clé publique autorisée ;
71. export de clé privée impossible ;
72. concurrence sur activation ;
73. rollback si audit échoue ;
74. readiness détectant fournisseur dégradé ;
75. sauvegarde/restauration des métadonnées ;
76. restauration sans prétendre restaurer les secrets externes ;
77. analyse statique du dépôt sans secret ;
78. configuration Laravel mise en cache ;
79. contre-épreuve démontrant que la garde sait échouer.

Chaque invariant de sécurité doit avoir sa contre-épreuve.

---

## 13. Tests d’intégration Laravel

Créer au minimum :

```text
apps/console-laravel/tests/Integration/secrets_cles_v1_p1.php
apps/console-laravel/tests/Integration/secrets_cles_console_p1.php
apps/console-laravel/tests/Integration/secrets_cles_fournisseurs_p1.php
apps/console-laravel/tests/Integration/secrets_cles_rotation_p1.php
apps/console-laravel/tests/Integration/secrets_cles_compromission_p1.php
```

Adapter et conserver verts :

- authentification ;
- passkeys ;
- sessions ;
- produits ;
- sources ;
- politiques ;
- contrats ;
- organisations ;
- realms ;
- événements ;
- fédération ;
- continuité ;
- OpenAPI ;
- import SQLite ;
- PostgreSQL ;
- configuration mise en cache.

---

## 14. Scénarios d’intégration obligatoires

### 14.1 Secret fichier pilote

1. créer un fichier factice `0600` ;
2. inscrire le fournisseur ;
3. inscrire la ressource ;
4. déclarer une version ;
5. vérifier ;
6. déclarer un usage ;
7. activer ;
8. résoudre dans un service interne ;
9. vérifier qu’aucune sortie publique ne contient la valeur ;
10. suspendre ;
11. constater le refus.

### 14.2 Rotation pilote

1. version 1 active ;
2. dépendance historique ;
3. version 2 déclarée ;
4. plan de double lecture ;
5. simulation ;
6. validation ;
7. activation version 2 ;
8. version 1 en lecture ;
9. nouveaux usages sur version 2 ;
10. lecture historique sur version 1 ;
11. destruction version 1 refusée ;
12. fermeture de la dépendance ;
13. destruction confirmée.

### 14.3 Compromission

1. version active ;
2. déclaration confirmée ;
3. blocage immédiat ;
4. événement minimal ;
5. audit ;
6. rotation d’urgence ;
7. nouvelle version active ;
8. ancienne version jamais réactivée.

### 14.4 Sauvegarde hors machine

Utiliser uniquement des secrets factices ou une cible d’essai isolée.

Prouver :

- nouveau lot chiffré avec nouvelle version ;
- ancien lot toujours déchiffrable avec ancienne version ;
- aucune valeur dans les rapports ;
- dépendances de rétention correctement fermées.

---

## 15. Analyse de sécurité du dépôt

Ajouter une garde de recherche :

- motifs de clés privées ;
- JWT complets ;
- mots de passe dans URI ;
- secrets AWS ;
- tokens GitHub ;
- phrases secrètes ;
- fichiers `.env` suivis ;
- fichiers de clés privées ;
- sorties de tests contenant des secrets factices interdits.

La garde doit utiliser des valeurs canaris synthétiques pour prouver sa capacité à échouer.

Elle ne remplace pas un outil spécialisé externe, mais elle doit protéger les erreurs évidentes du dépôt.

---

## 16. CI

Ajouter une entrée :

```text
CAP-CORE-016 (secrets et clés)
```

La CI vérifie :

- garde de capacité ;
- intégrations ;
- analyse de dépôt ;
- politiques ;
- contrats ;
- événements ;
- passkeys ;
- continuité ;
- SQLite ;
- PostgreSQL ;
- sauvegarde et restauration ;
- configuration mise en cache ;
- syntaxe PHP et shell ;
- absence de secret dans les artefacts.

Aucun secret réel de production ne doit être nécessaire à la CI.
