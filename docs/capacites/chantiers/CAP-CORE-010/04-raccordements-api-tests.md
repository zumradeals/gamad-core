## 18. Raccordement à CAP-CORE-006

Migrer les références de finalité de `CAP-CORE-006` vers des termes canoniques.

Exigences :

- conserver les références existantes comme codes ou alias lorsqu’elles sont valides ;
- aucune finalité libre pour une nouvelle autorisation ;
- une finalité inconnue est refusée ;
- une finalité retirée est refusée ;
- une finalité active mais non supportée par le consommateur est refusée ;
- les finalités historiques restent lisibles ;
- aucune migration destructrice.

`CAP-CORE-006` ne doit pas interroger le vocabulaire à chaque lecture historique.

Utiliser une projection active vérifiée.

---

## 19. Raccordement à CAP-CORE-007

Les actions, effets et états de politiques doivent référencer les vocabulaires actifs.

Exigences :

- `PERMET` et `REFUSE` stabilisés ;
- actions canoniques exactes ;
- aucune action libre dans une nouvelle règle ;
- aucune permission créée par un alias ;
- une action inconnue reste refusée ;
- le moteur conserve ses bornes locales ;
- l’activation d’une version de vocabulaire ne crée aucune nouvelle règle.

---

## 20. Raccordement à CAP-CORE-009

Chaque contrat actif doit pouvoir référencer :

- vocabulaire ;
- version minimale ou exacte ;
- termes utilisés ;
- comportement face à un terme inconnu ;
- compatibilité ;
- période de transition.

`CAP-CORE-009` doit détecter :

- terme retiré ;
- terme déprécié ;
- version non supportée ;
- enum divergent ;
- mapping manquant ;
- code inconnu dans OpenAPI ;
- contrat utilisant un libellé à la place du code.

---

## 21. Raccordement à CAP-CORE-011

Migrer et vérifier au minimum :

- types de produits ;
- états de produits ;
- environnements ;
- relations fédérées partagées.

Contraintes :

- les `CHECK` SQL restent en place ou sont générés de manière contrôlée ;
- un nouveau terme ne modifie pas automatiquement une contrainte de production ;
- une migration explicite et testée est requise ;
- les états non supportés restent refusés ;
- les transitions restent codées dans `CAP-CORE-011`.

Le vocabulaire définit les codes.

Le registre des produits définit les transitions et règles métier.

---

## 22. API

Lectures :

```text
GET /api/v1/vocabulaires
GET /api/v1/vocabulaires/{reference}
GET /api/v1/vocabulaires/{reference}/versions
GET /api/v1/vocabulaires/{reference}/versions/{version}
GET /api/v1/vocabulaires/{reference}/version-active
GET /api/v1/vocabulaires/{reference}/termes
GET /api/v1/termes/{reference}
GET /api/v1/termes/{reference}/usages
GET /api/v1/termes/{reference}/mappings
GET /api/v1/vocabulaires/{reference}/compatibilite
GET /api/v1/vocabulaires/{reference}/conformite
```

Commandes :

```text
POST /api/v1/vocabulaires
POST /api/v1/vocabulaires/{reference}/versions
POST /api/v1/vocabulaires/{reference}/versions/{version}/termes
POST /api/v1/termes/{reference}/libelles
POST /api/v1/termes/{reference}/alias
POST /api/v1/termes/{reference}/relations
POST /api/v1/termes/{reference}/mappings
POST /api/v1/termes/{reference}/usages
POST /api/v1/vocabulaires/{reference}/versions/{version}/soumission
POST /api/v1/vocabulaires/{reference}/versions/{version}/analyse
POST /api/v1/vocabulaires/{reference}/versions/{version}/activation
POST /api/v1/termes/{reference}/depreciation
POST /api/v1/termes/{reference}/retrait
POST /api/v1/vocabulaires/{reference}/versions/{version}/conformite
POST /api/v1/vocabulaires/{reference}/versions/{version}/projections
```

Règles HTTP :

- HTTPS ;
- session ;
- rate limiting ;
- validation stricte ;
- `403` refus ;
- `404` inconnu ou invisible ;
- `409` conflit ou rupture non traitée ;
- `422` vocabulaire invalide ;
- `503` dépendance indisponible.

Mettre à jour OpenAPI via `CAP-CORE-009`.

---

## 23. Console

Créer un écran `Vocabulaires`.

L’autorité doit pouvoir :

- consulter les vocabulaires ;
- filtrer par domaine, portée, propriétaire et état ;
- ouvrir une fiche ;
- voir les versions ;
- créer une version ;
- ajouter des termes ;
- ajouter des libellés ;
- déclarer des alias ;
- déclarer des relations ;
- déclarer des mappings ;
- voir les usages ;
- voir les contrats et politiques impactés ;
- soumettre ;
- analyser la compatibilité ;
- générer les projections ;
- voir les conformités ;
- activer ;
- déprécier ;
- retirer.

L’interface doit distinguer clairement :

- code machine ;
- référence du terme ;
- définition ;
- libellés ;
- alias ;
- usages ;
- support consommateur ;
- état ;
- remplaçant ;
- rupture éventuelle.

Une confirmation explicite est obligatoire pour :

- activation ;
- dépréciation ;
- retrait ;
- changement de mapping exact ;
- activation d’une rupture.

La console ne doit jamais écrire directement en base.

---

## 24. Audit

Événements minimaux dans `CAP-CORE-013` :

- `VOCABULAIRE_INSCRIT` ;
- `VERSION_VOCABULAIRE_CREEE` ;
- `TERME_AJOUTE` ;
- `LIBELLE_TERME_AJOUTE` ;
- `ALIAS_TERME_AJOUTE` ;
- `RELATION_TERME_DECLAREE` ;
- `MAPPING_TERME_DECLARE` ;
- `USAGE_TERME_DECLARE` ;
- `VERSION_VOCABULAIRE_SOUMISE` ;
- `COMPATIBILITE_VOCABULAIRE_ANALYSEE` ;
- `PROJECTION_VOCABULAIRE_GENEREE` ;
- `CONFORMITE_VOCABULAIRE_ENREGISTREE` ;
- `VERSION_VOCABULAIRE_ACTIVEE` ;
- `TERME_DEPRECIE` ;
- `TERME_RETIRE` ;
- `OPERATION_VOCABULAIRE_REFUSEE`.

Inclure :

- acteur ;
- vocabulaire ;
- version ;
- terme éventuel ;
- code ;
- action ;
- résultat ;
- politique ;
- preuve ;
- source ;
- empreinte ;
- `correlation_id` ;
- date.

Ne jamais inclure :

- secrets ;
- jetons ;
- données métier réelles ;
- profils ;
- documents privés.

---

## 25. Transactions et concurrence

Garanties :

- transaction unique par commande ;
- rollback si audit échoue ;
- activation atomique ;
- version immuable ;
- codes uniques ;
- alias non ambigus ;
- cycles hiérarchiques refusés ;
- analyse liée à l’empreinte exacte ;
- conformité liée à l’empreinte exacte ;
- projection liée à l’empreinte exacte ;
- aucune activation après modification ;
- verrouillage pendant activation ;
- PostgreSQL et SQLite équivalents.

---

## 26. Comportement en panne

- registre indisponible pendant administration : `503` ;
- autorisation indisponible : refus ;
- audit indisponible pendant commande : rollback ;
- source indisponible : activation refusée ;
- contrats indisponibles : activation d’une version ayant des usages contractuels refusée ;
- projection locale valide déjà active : lectures métier existantes peuvent continuer ;
- projection inconnue ou empreinte invalide : écriture utilisant les termes refusée ;
- terme inconnu : refus exact ;
- alias ambigu : refus ;
- mapping approximatif dans une décision de sécurité : refus ;
- ancienne version active : continue jusqu’à activation explicite de la nouvelle.

Aucune panne ne doit provoquer :

- traduction automatique ;
- rapprochement flou ;
- valeur par défaut permissive ;
- création de terme implicite.

---

## 27. Readiness

Vérifier :

- connexion ;
- tables ;
- version du schéma ;
- bootstrap ;
- version active unique ;
- empreintes ;
- codes uniques ;
- alias non ambigus ;
- absence de cycles ;
- sources actives ;
- projections obligatoires présentes ;
- conformités critiques valides ;
- absence de migration en attente.

Une dérive critique entre vocabulaire actif, contrat actif et projection locale doit rendre la readiness négative.

Une divergence non critique d’affichage produit une alerte explicite.

---

## 28. Tests de capacité

Créer :

```text
core/registre-vocabulaire/tests/vocabulaire_p3.php
```

Épreuves minimales :

1. bootstrap des vocabulaires actuels ;
2. bootstrap idempotent ;
3. empreinte bootstrap ;
4. référence unique ;
5. namespace unique ;
6. propriétaire connu ;
7. source active ;
8. version brouillon ;
9. code unique ;
10. définition obligatoire ;
11. type sémantique valide ;
12. libellé principal unique ;
13. locale valide ;
14. alias explicite ;
15. alias ambigu refusé ;
16. aucune résolution floue ;
17. aucune permission par alias ;
18. relation explicite ;
19. auto-relation refusée ;
20. cycle hiérarchique refusé ;
21. mapping exact ;
22. mapping approximatif visible ;
23. mapping approximatif refusé en sécurité ;
24. usage déclaré ;
25. consommateur connu ;
26. soumission immuable ;
27. ajout de libellé compatible ;
28. ajout de terme signalé pour enum fermé ;
29. changement de code rupture ;
30. changement de définition rupture ou adaptation selon impact ;
31. retrait de terme actif rupture ;
32. réutilisation de code refusée ;
33. terme déprécié avec remplaçant ;
34. retrait refusé avec contrat actif ;
35. analyse liée à l’empreinte ;
36. projection JSON ;
37. projection PHP ;
38. projection OpenAPI ;
39. projection SQL ;
40. dérive de projection détectée ;
41. activation refusée sans projection ;
42. activation refusée sans conformité ;
43. activation atomique ;
44. une version active ;
45. dernier snapshot valide utilisable en lecture ;
46. terme inconnu refusé en écriture ;
47. finalité libre refusée par `CAP-CORE-006` ;
48. action inconnue refusée par `CAP-CORE-007` ;
49. enum divergent détecté par `CAP-CORE-009` ;
50. types de produits préservés ;
51. états de produits préservés ;
52. types de sources préservés ;
53. niveaux de vérification préservés ;
54. niveaux d’assurance préservés ;
55. relations d’identité préservées ;
56. rollback si audit échoue ;
57. concurrence activation ;
58. sauvegarde/restauration ;
59. configuration Laravel mise en cache ;
60. contre-épreuve démontrant que la garde sait échouer.

Chaque règle de sécurité doit avoir une contre-épreuve.

---

## 29. Tests d’intégration

Créer :

```text
apps/console-laravel/tests/Integration/vocabulaire_v1_p1.php
apps/console-laravel/tests/Integration/vocabulaire_console_p1.php
apps/console-laravel/tests/Integration/vocabulaire_drift_p1.php
```

Conserver verts :

- identités ;
- produits ;
- sources ;
- politiques ;
- contrats ;
- autorisation ;
- fédération ;
- accès ;
- console ;
- OpenAPI ;
- configuration mise en cache ;
