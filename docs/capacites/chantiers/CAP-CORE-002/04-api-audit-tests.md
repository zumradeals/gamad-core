- politique ;
- audit ;
- durée ;
- compatibilité ;
- classification.

Préserver le contrat de compatibilité utilisé par `Ctr01::resoudreLiensOrganisations()`.

---

## 20. API

Lectures minimales :

```text
GET /api/v1/organisations
GET /api/v1/organisations/{reference}
GET /api/v1/organisations/{reference}/structure
GET /api/v1/organisations/{reference}/unites
GET /api/v1/organisations/{reference}/relations
GET /api/v1/organisations/{reference}/affiliations
GET /api/v1/organisations/{reference}/fonctions
GET /api/v1/identites/{reference}/organisations
POST /api/v1/organisations/{reference}/appartenance/verification
POST /api/v1/organisations/{reference}/representation/verification
```

Commandes minimales :

```text
POST  /api/v1/organisations
PATCH /api/v1/organisations/{reference}
POST  /api/v1/organisations/{reference}/activation
POST  /api/v1/organisations/{reference}/suspension
POST  /api/v1/organisations/{reference}/dissolution
POST  /api/v1/organisations/{reference}/retrait
POST  /api/v1/organisations/{reference}/identifiants
POST  /api/v1/organisations/{reference}/unites
POST  /api/v1/organisations/{reference}/relations
POST  /api/v1/organisations/{reference}/affiliations
POST  /api/v1/organisations/{reference}/fonctions
POST  /api/v1/organisations/{reference}/affiliations/{affiliation}/activation
POST  /api/v1/organisations/{reference}/affiliations/{affiliation}/suspension
POST  /api/v1/organisations/{reference}/affiliations/{affiliation}/fermeture
```

Règles HTTP :

- HTTPS en production ;
- session obligatoire ;
- limites de fréquence ;
- validation stricte ;
- `403` refus ;
- `404` inconnu ou invisible ;
- `409` conflit ;
- `422` dossier invalide ;
- `503` dépendance indispensable indisponible ;
- aucune donnée confidentielle dans une réponse publique.

Mettre à jour OpenAPI via les contrats de `CAP-CORE-009`.

---

## 21. Console

Créer un écran `Organisations`.

L’autorité doit pouvoir :

- consulter les organisations ;
- filtrer par état, type et classification ;
- ouvrir une fiche ;
- inscrire ;
- modifier ;
- activer ;
- suspendre ;
- dissoudre ;
- retirer ;
- déclarer un identifiant externe ;
- créer une unité ;
- visualiser la structure ;
- déclarer une relation organisationnelle ;
- proposer une affiliation ;
- activer, suspendre ou fermer une affiliation ;
- créer une fonction interne ;
- vérifier une représentation ;
- consulter l’historique et les preuves.

La fiche organisation doit séparer visuellement :

- identité canonique ;
- fiche organisationnelle ;
- état ;
- dénominations ;
- identifiants ;
- unités ;
- relations ;
- affiliations ;
- fonctions internes ;
- mandats vérifiés ;
- classifications ;
- sources ;
- audit.

Ne jamais afficher :

- secrets ;
- mots de passe ;
- jetons ;
- dossiers RH détaillés ;
- pièces justificatives complètes ;
- données métier des satellites.

La console ne doit jamais écrire directement en base.

---

## 22. Classification et minimisation

Les champs doivent être classifiés via `CAP-CORE-010`.

Niveaux initiaux selon le vocabulaire actif :

- public écosystème ;
- interne ;
- confidentiel ;
- restreint ;
- secret Core lorsque réellement nécessaire.

Règles :

- dénomination et état peuvent être publics selon politique ;
- identifiants externes peuvent être restreints ;
- affiliations individuelles ne sont pas publiques par défaut ;
- mandats sont exposés selon contrat et finalité ;
- aucune liste complète d’employés à un produit sans contrat et autorisation ;
- Matching ne reçoit que les signaux minimaux autorisés ;
- aucune donnée n’est exposée parce qu’elle existe dans le registre.

---

## 23. Audit

Événements minimaux dans `CAP-CORE-013` :

- `ORGANISATION_INSCRITE` ;
- `ORGANISATION_MODIFIEE` ;
- `ORGANISATION_ACTIVEE` ;
- `ORGANISATION_SUSPENDUE` ;
- `ORGANISATION_DISSOUTE` ;
- `ORGANISATION_RETIREE` ;
- `IDENTIFIANT_ORGANISATION_DECLARE` ;
- `IDENTIFIANT_ORGANISATION_FERME` ;
- `UNITE_ORGANISATION_CREEE` ;
- `UNITE_ORGANISATION_DEPLACEE` ;
- `UNITE_ORGANISATION_FERMEE` ;
- `RELATION_ORGANISATION_DECLAREE` ;
- `RELATION_ORGANISATION_FERMEE` ;
- `AFFILIATION_ORGANISATION_PROPOSEE` ;
- `AFFILIATION_ORGANISATION_ACTIVEE` ;
- `AFFILIATION_ORGANISATION_SUSPENDUE` ;
- `AFFILIATION_ORGANISATION_FERMEE` ;
- `FONCTION_ORGANISATION_CREEE` ;
- `REPRESENTATION_ORGANISATION_VERIFIEE` ;
- `OPERATION_ORGANISATION_REFUSEE`.

Inclure au minimum :

- acteur ;
- organisation ;
- identité éventuelle ;
- unité éventuelle ;
- affiliation éventuelle ;
- mandat éventuel ;
- action ;
- résultat ;
- politique ;
- source ;
- preuve ;
- `correlation_id` ;
- date.

Ne jamais inclure :

- secret ;
- jeton ;
- mot de passe ;
- donnée RH détaillée ;
- pièce justificative complète.

Une commande sensible qui ne peut pas être auditée doit échouer et revenir en arrière.

---

## 24. Transactions et concurrence

Garanties obligatoires :

- transaction unique par commande locale ;
- rollback si audit en échec ;
- unicité protégée par la base ;
- idempotence ;
- contrôle de concurrence sur cycles ;
- absence de cycle structurel ;
- absence de cycle hiérarchique entre organisations ;
- aucune affiliation partiellement active ;
- aucune dissolution partielle ;
- aucune modification d’historique ;
- PostgreSQL et SQLite fonctionnellement équivalents.

Les écritures intercapacités ne doivent pas prétendre être atomiques entre plusieurs bases.

Règle :

- exiger d’abord les références existantes ;
- écrire localement ;
- auditer ;
- exposer un état explicite en cas de dépendance ultérieure indisponible ;
- ne jamais masquer une opération partielle.

---

## 25. Comportement en panne

- registre d’organisations indisponible : `503` ;
- registre d’identités indisponible pendant une commande : refus fermé ;
- registre des mandats indisponible : représentation non opposable ;
- registre des sources indisponible : commande refusée ;
- registre des politiques indisponible : refus fermé ;
- registre des contrats indisponible pour une projection critique : refus ou dégradation explicitement documentée ;
- vocabulaire indisponible : aucune nouvelle écriture ;
- journal indisponible : rollback ;
- organisation inconnue : aucune approximation ;
- relation inconnue : aucun alias implicite ;
- mandat absent : affiliation possible selon politique, représentation refusée.

Aucun repli vers les anciennes tables de `CAP-CORE-001` ne doit masquer une panne après migration.

---

## 26. Readiness

Vérifier :

- connexion au magasin ;
- tables ;
- version du schéma ;
- bootstrap ;
- unicité identité/organisation ;
- cohérence des cycles ;
- absence de cycles de structure ;
- références de vocabulaire valides ;
- contrats critiques actifs ;
- politique d’administration active ;
- compatibilité de la projection `CAP-CORE-001` ;
- absence de migration en attente.

Une incohérence critique doit rendre la readiness négative.

---

## 27. Tests de capacité

Créer :

```text
core/registre-organisations/tests/organisations_p3.php
```

Épreuves minimales :

1. bootstrap des organisations existantes ;
2. bootstrap des relations existantes ;
3. bootstrap idempotent ;
4. empreinte bootstrap ;
5. identité organisation obligatoire ;
6. identité de mauvais type refusée ;
7. identité déjà liée refusée ;
8. référence unique ;
9. création en préparation ;
10. activation gouvernée ;
11. refus sans politique ;
12. refus sans preuve ;
13. refus d’auto-activation ;
14. suspension opposable ;
15. dissolution terminale ;
16. retrait sans suppression ;
17. référence non réutilisable ;
18. révisions en ajout seul ;
19. identifiant externe unique ;
20. identifiant non vérifié explicite ;
21. unité créée ;
22. parent de même organisation ;
23. cycle d’unité refusé ;
24. déplacement historisé ;
25. fermeture d’unité ;
26. descendants non fermés silencieusement ;
27. relation entre organisations ;
28. auto-relation refusée ;
29. cycle hiérarchique refusé ;
30. pourcentage borné ;
31. affiliation proposée ;
32. affiliation activée ;
33. affiliation suspendue ;
34. affiliation fermée ;
35. affiliation ne crée aucun mandat ;
36. dirigeant sans mandat non opposable ;
37. représentant sans mandat non opposable ;
38. mandat actif opposable ;
39. mandat expiré non opposable ;
40. mandat indisponible non opposable ;
41. identité suspendue non utilisable ;
42. organisation suspendue non utilisable ;
43. classification respectée ;
44. produit non autorisé refusé ;
45. aucun accès universel implicite ;
46. vocabulaire inconnu refusé ;
47. aucun rapprochement flou ;
48. aucun secret dans le schéma ;
49. aucun dossier RH détaillé ;
50. projection `Ctr01` compatible ;
51. `Ctr01` ne lit plus directement l’ancienne table après migration ;
52. reconstruction de baseline sans perte ;
53. rollback si audit échoue ;
54. concurrence activation ;
55. concurrence affiliation ;
56. sauvegarde/restauration ;
57. PostgreSQL ;
58. SQLite ;
59. configuration Laravel mise en cache ;
60. contre-épreuve démontrant que la garde sait échouer.

Chaque règle de sécurité doit avoir une contre-épreuve.

---

## 28. Tests d’intégration

Créer :

```text
apps/console-laravel/tests/Integration/organisations_v1_p1.php
apps/console-laravel/tests/Integration/organisations_console_p1.php
apps/console-laravel/tests/Integration/organisations_ctr01_compat_p1.php
```

Conserver verts :

- identités ;
- mandats ;
- autorisation ;
- sources ;
- politiques ;
- contrats ;
- vocabulaire ;
- produits ;
- fédération ;
- audit ;
- API générale ;
- console ;
- config cache ;
- import SQLite ;
- PostgreSQL ;
- sauvegarde ;
- restauration.

Scénario HTTP minimal :

1. requête sans session refusée ;
2. acteur non autorisé refusé ;
3. identité organisation inscrite ou résolue ;
4. fiche organisation créée ;
5. activation ;
6. unité créée ;
7. affiliation proposée ;
8. affiliation activée ;
9. appartenance positive ;
10. représentation négative sans mandat ;
11. mandat valide ;
12. représentation positive ;
13. suspension affiliation ;
14. représentation refusée ;
15. relation interorganisationnelle créée ;
16. cycle refusé ;
17. suspension organisation ;
18. nouvelles affiliations refusées ;
19. dissolution ;
20. historique lisible ;
21. route historique `CAP-CORE-001` compatible ;
22. restauration et mêmes résultats.

---

## 29. PostgreSQL, sauvegarde et restauration

Intégrer le magasin au socle.

Mettre à jour :

- création de base ;