# CAP-CORE-012 — API, CONSOLE, CONTRATS, AUDIT ET TESTS

Cette partie complète les trois premières parties de la note.

---

## 46. Contrats à inscrire dans CAP-CORE-009

Créer au minimum un contrat interne stable :

```text
CTR-12 — Realms Registry
```

Référence canonique exacte à déterminer selon les conventions réellement livrées par `CAP-CORE-009` et `CAP-CORE-010`.

Le contrat interne doit décrire au minimum :

- résolution d’un realm ;
- résolution par identité ;
- résolution par code ;
- lecture de l’état ;
- lecture de la hiérarchie ;
- lecture des organisations ;
- lecture des produits ;
- vérification de portée ;
- motifs de refus ;
- comportement en panne.

Créer également un contrat HTTP pour l’API v1.

Les schémas doivent être inscrits dans le registre des contrats et projetés ou vérifiés contre OpenAPI.

Tout changement ultérieur de :

- champ obligatoire ;
- type ;
- code d’état ;
- motif de refus ;
- méthode ;
- chemin ;
- autorisation ;
- portée de realm ;

doit être analysé par `CAP-CORE-009`.

---

## 47. API de lecture

Ajouter au minimum :

```text
GET /api/v1/realms
GET /api/v1/realms/{reference}
GET /api/v1/realms/{reference}/historique
GET /api/v1/realms/{reference}/relations
GET /api/v1/realms/{reference}/parents
GET /api/v1/realms/{reference}/enfants
GET /api/v1/realms/{reference}/perimetres
GET /api/v1/realms/{reference}/identifiants-externes
GET /api/v1/realms/{reference}/organisations
GET /api/v1/realms/{reference}/produits
GET /api/v1/realms/{reference}/contrats
GET /api/v1/realms/{reference}/franchissements
GET /api/v1/realms/{reference}/verification
POST /api/v1/realms/{reference}/portee
```

Filtres de liste possibles :

- type ;
- état ;
- organisation ;
- produit ;
- parent ;
- dimension ;
- valeur de périmètre ;
- date ;
- classification.

Les filtres doivent être validés et bornés.

Aucune recherche approximative ne doit identifier un realm pour une opération sensible.

---

## 48. API de commande

Ajouter au minimum :

```text
POST  /api/v1/realms
PATCH /api/v1/realms/{reference}
POST  /api/v1/realms/{reference}/activation
POST  /api/v1/realms/{reference}/suspension
POST  /api/v1/realms/{reference}/fermeture
POST  /api/v1/realms/{reference}/retrait
POST  /api/v1/realms/{reference}/relations
POST  /api/v1/realms/{reference}/relations/{relation}/fermeture
POST  /api/v1/realms/{reference}/perimetres
POST  /api/v1/realms/{reference}/perimetres/{perimetre}/fermeture
POST  /api/v1/realms/{reference}/identifiants-externes
POST  /api/v1/realms/{reference}/organisations
POST  /api/v1/realms/{reference}/organisations/{rattachement}/fermeture
POST  /api/v1/realms/{reference}/produits
POST  /api/v1/realms/{reference}/produits/{rattachement}/fermeture
POST  /api/v1/realms/{reference}/contrats
POST  /api/v1/realms/{reference}/contrats/{rattachement}/fermeture
POST  /api/v1/realms/{reference}/franchissements
POST  /api/v1/realms/{reference}/franchissements/{franchissement}/fermeture
POST  /api/v1/realms/{reference}/verifications
```

Réduire le nombre de routes lorsque plusieurs commandes peuvent être représentées proprement par un contrat unique sans ambiguïté.

Ne pas créer un endpoint générique permettant d’écrire arbitrairement dans n’importe quelle table.

---

## 49. Règles HTTP

- HTTPS obligatoire en production ;
- session obligatoire ;
- décision `CAP-CORE-004` obligatoire ;
- audit `CAP-CORE-013` obligatoire ;
- validation stricte ;
- limites de fréquence ;
- pagination ;
- taille des corps bornée ;
- aucun secret dans les réponses ;
- aucun détail interne de base de données ;
- aucun rapprochement approximatif de référence ;
- aucune migration implicite.

Codes minimaux :

- `200` lecture ou commande idempotente réussie ;
- `201` inscription réussie ;
- `202` uniquement lorsqu’un traitement asynchrone réel existe ;
- `204` fermeture réussie sans corps ;
- `400` requête mal formée ;
- `401` session absente ou invalide ;
- `403` action refusée ;
- `404` realm ou dépendance invisible/inconnue ;
- `409` conflit de référence, cycle, état ou période ;
- `422` dossier sémantiquement invalide ;
- `429` limite de fréquence ;
- `503` dépendance indispensable indisponible.

Les erreurs doivent utiliser les codes canoniques de `CAP-CORE-010` et les schémas de `CAP-CORE-009`.

---

## 50. OpenAPI

Mettre à jour :

```text
apps/console-laravel/openapi/core-v1.yaml
```

ou la projection équivalente générée par `CAP-CORE-009`.

La CI doit vérifier :

- routes Laravel présentes ;
- méthodes cohérentes ;
- `operationId` uniques ;
- schémas cohérents avec les contrats ;
- erreurs documentées ;
- références de vocabulaire cohérentes ;
- aucune route fantôme ;
- aucune route active non documentée sur le périmètre livré.

---

## 51. Console d’administration

Créer un écran `Realms` dans la console GAMAD.

L’autorité doit pouvoir :

- consulter les realms ;
- filtrer par type, état, organisation, produit et périmètre ;
- ouvrir une fiche ;
- inscrire un realm ;
- créer son identité lorsque le cas d’usage orchestré est disponible ;
- modifier les métadonnées ;
- activer ;
- suspendre ;
- fermer ;
- retirer ;
- ajouter une relation ;
- fermer une relation ;
- déclarer un périmètre ;
- déclarer un identifiant externe ;
- rattacher une organisation ;
- détacher une organisation ;
- rattacher un produit ;
- détacher un produit ;
- rattacher un contrat ;
- déclarer un franchissement ;
- enregistrer une vérification ;
- exécuter une vérification de portée ;
- consulter l’historique.

La fiche doit afficher distinctement :

- référence realm ;
- identité canonique ;
- code canonique ;
- type ;
- état ;
- classification ;
- organisation responsable ;
- période ;
- parents ;
- enfants ;
- autres relations ;
- périmètres ;
- identifiants externes ;
- organisations rattachées ;
- produits rattachés ;
- contrats rattachés ;
- franchissements ;
- vérification courante ;
- derniers événements d’audit.

---

## 52. Vue hiérarchique

La console doit proposer une vue simple de la hiérarchie.

Elle ne doit pas dépendre d’une bibliothèque lourde si une liste indentée suffit.

La vue doit :

- distinguer parent/enfant ;
- signaler les realms suspendus ou fermés ;
- afficher les relations non hiérarchiques séparément ;
- ne jamais masquer une boucle détectée ;
- limiter la profondeur affichée ;
- éviter une récursion non bornée.

Une erreur de cycle doit rendre un diagnostic visible et la readiness négative si elle touche des données actives.

---

## 53. Confirmations sensibles

Exiger une confirmation explicite pour :

- activation ;
- suspension ;
- fermeture ;
- retrait ;
- rattachement d’une organisation responsable ;
- rattachement d’un produit administrateur ;
- création d’un franchissement `PERMET` ;
- fermeture d’un franchissement `REFUSE` ;
- remplacement d’un parent ;
- déclaration d’équivalence opérationnelle.

La console ne doit jamais écrire directement dans les tables.

Elle appelle le même cas d’usage gouverné que l’API.

---

## 54. Visibilité

La visibilité d’un realm dépend :

- de sa classification ;
- de son état ;
- de l’acteur ;
- de son organisation ;
- du produit appelant ;
- de la politique active.

Règles minimales :

- realms actifs et publiables : visibles selon politique ;
- realms en préparation : autorité et responsables autorisés ;
- realms suspendus : autorité, responsables et consommateurs impactés selon contrat ;
- realms fermés ou retirés : lecture historique contrôlée ;
- détails de franchissement : visibilité plus restrictive ;
- aucune liste globale de tous les realms pour un produit sans autorisation explicite.

---

## 55. Audit

Écrire dans `CAP-CORE-013` au minimum :

- `REALM_INSCRIT` ;
- `REALM_MODIFIE` ;
- `REALM_ACTIVE` ;
- `REALM_SUSPENDU` ;
- `REALM_FERME` ;
- `REALM_RETIRE` ;
- `RELATION_REALM_DECLAREE` ;
- `RELATION_REALM_FERMEE` ;
- `PERIMETRE_REALM_DECLARE` ;
- `PERIMETRE_REALM_FERME` ;
- `IDENTIFIANT_REALM_DECLARE` ;
- `ORGANISATION_REALM_RATTACHEE` ;
- `ORGANISATION_REALM_DETACHEE` ;
- `PRODUIT_REALM_RATTACHE` ;
- `PRODUIT_REALM_DETACHE` ;
- `CONTRAT_REALM_RATTACHE` ;
- `CONTRAT_REALM_DETACHE` ;
- `FRANCHISSEMENT_REALM_DECLARE` ;
- `FRANCHISSEMENT_REALM_FERME` ;
- `VERIFICATION_REALM_ENREGISTREE` ;
- `PORTEE_REALM_VERIFIEE` ;
- `OPERATION_REALM_REFUSEE`.

Chaque événement doit porter au minimum :

- acteur ;
- action ;
- realm ;
- organisation éventuelle ;
- produit éventuel ;
- contrat éventuel ;
- realm source et cible éventuels ;
- finalité éventuelle ;
- résultat ;
- politique ;
- preuve ;
- `correlation_id` ;
- date.

Ne jamais inclure :

- secret ;
- jeton ;
- clé ;
- données métier ;
- payload inter-realm complet ;
- données personnelles inutiles.

Une commande sensible qui ne peut pas être auditée doit échouer et revenir en arrière.

---

## 56. Événements futurs CAP-CORE-014

`CAP-CORE-012` doit préparer des événements communs sans construire le transport de `CAP-CORE-014`.

Événements candidats :

- `realm.active` ;
- `realm.suspendu` ;
- `realm.ferme` ;
- `realm.retire` ;
- `realm.organisation.rattachee` ;
- `realm.produit.rattache` ;
- `realm.franchissement.modifie`.

Le chantier doit :

- enregistrer les contrats d’événements dans `CAP-CORE-009` lorsque pertinent ;
- utiliser les termes de `CAP-CORE-010` ;
- ne pas créer de bus, file ou webhook parallèle ;
- laisser `CAP-CORE-014` publier et consommer réellement ces événements.

---

## 57. Test de capacité

Créer :

```text
core/registre-realms/tests/realms_p3.php
```

Épreuves minimales :

1. bootstrap avec zéro realm historique ;
2. bootstrap avec realms historiques ;
3. bootstrap idempotent ;
4. empreinte de bootstrap vérifiée ;
5. identité de type `realm` obligatoire ;
6. identité d’un autre type refusée ;
7. identité déjà utilisée refusée ;
8. code canonique unique ;
9. type canonique actif ;
10. source active ;
11. inscription en `PREPARATION` ;
12. aucune activation automatique ;
13. première révision créée ;
14. modification en nouvelle révision ;
15. référence immuable ;
16. code immuable sans remplacement ;
17. activation gouvernée ;
18. activation refusée sans preuve ;
19. suspension opposable ;
20. fermeture opposable ;
21. retrait irréversible ;
22. référence non réutilisable ;
23. relation parent/enfant ;
24. auto-relation refusée ;
25. cycle direct refusé ;
26. cycle indirect refusé ;
27. chevauchement sans inclusion ;
28. successeur sans retrait automatique ;
29. périmètre canonique ;
30. dimension libre refusée pour la sécurité ;
31. identifiant externe explicite ;
32. conflit d’identifiant refusé ;
33. organisation active rattachée ;
34. organisation inactive refusée ;
35. mandat insuffisant refusé ;
36. rôle responsable sans mandat non opposable ;
37. produit actif rattaché ;
38. produit inactif refusé ;
39. environnement inconnu refusé ;
40. aucune URL recopiée ;
41. contrat actif rattaché ;
42. contrat suspendu refusé ;
43. franchissement absent refusé ;
44. franchissement permis explicite ;
45. franchissement refusé prioritaire ;
46. finalité inconnue refusée ;
47. wildcard universel refusé ;
48. vérification expirée signalée ;
49. auto-attestation forte refusée ;
50. contrôle de portée positif ;
51. contrôle de portée explicablement négatif ;
52. réponse de portée ne vaut pas autorisation ;
53. lecture datée ;
54. historique conservé ;
55. refus par défaut ;
56. registre indisponible ferme l’opération ;
57. rollback si audit échoue ;
58. aucune suppression physique ;
59. aucun secret dans le schéma ;
60. reconstruction de baseline sans perte ;
61. configuration mise en cache ;
62. concurrence sur activation ;
63. concurrence sur rattachement ;
64. contre-épreuve démontrant que la garde sait échouer.

Chaque règle de sécurité doit avoir une contre-épreuve.

---

## 58. Tests d’intégration

Créer au minimum :

```text
apps/console-laravel/tests/Integration/realms_v1_p1.php
apps/console-laravel/tests/Integration/realms_console_p1.php
apps/console-laravel/tests/Integration/realms_contracts_p1.php
```

Adapter et conserver verts :

- identité ;
- organisations ;
- mandats ;
- autorisation ;
- sources ;
- politiques ;
- contrats ;
- vocabulaire ;
- produits ;
- fédération ;
- console générale ;
- API générale ;
- configuration mise en cache ;
- import SQLite ;
- PostgreSQL ;
- sauvegarde ;
- restauration.

Scénario HTTP minimal :

1. lecture sans session refusée ;
2. acteur non autorisé refusé ;
3. identité realm créée ou résolue ;
4. realm inscrit ;
5. visible en préparation seulement aux acteurs autorisés ;
6. périmètre déclaré ;
7. organisation responsable rattachée avec mandat ;
8. produit actif rattaché ;
9. contrat rattaché ;
10. activation ;
11. contrôle de portée positif ;
12. autre produit refusé ;
13. autre realm refusé sans franchissement ;
14. franchissement déclaré ;
15. contrôle positif inter-realm ;
16. refus explicite ajouté ;
17. refus prioritaire ;
18. suspension ;
19. nouveaux usages refusés ;
20. historique lisible ;
21. fermeture ;
22. successeur déclaré ;
23. retrait ;
24. référence non réutilisable.

---

## 59. CI

Ajouter à :

```text
.github/workflows/core-operational-tests.yml
```

Une entrée :

```text
CAP-CORE-012 (realms)
```

La CI doit vérifier :

- garde realms ;
- identité ;
- organisations ;
- mandats ;
- autorisation ;
- sources ;
- politiques ;
- contrats ;
- vocabulaire ;
- produits ;
- fédération ;
- API ;
- console ;
- OpenAPI ;
- SQLite ;
- PostgreSQL réel ;
- sauvegarde ;
- restauration ;
- copie hors machine ;
- configuration mise en cache ;
- syntaxe PHP.

Ne neutraliser aucun test existant.
