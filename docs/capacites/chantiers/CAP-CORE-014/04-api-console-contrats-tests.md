# CAP-CORE-014 — API, CONSOLE, CONTRATS, AUDIT ET TESTS

Cette partie complète les trois premières parties.

---

## 1. Contrats CAP-CORE-009

Créer et activer des contrats versionnés pour les opérations de `CAP-CORE-014`.

Contrats techniques minimaux :

```text
CTR-GAMAD-EVENEMENT-PUBLIER
CTR-GAMAD-EVENEMENT-LIRE
CTR-GAMAD-ABONNEMENT-GERER
CTR-GAMAD-LIVRAISON-LIRE
CTR-GAMAD-LIVRAISON-ACCUSER
CTR-GAMAD-LIVRAISON-REFUSER
CTR-GAMAD-EVENEMENT-REJOUER
CTR-GAMAD-LETTRE-MORTE-GERER
```

Créer également les contrats `EVENEMENT` métier réellement raccordés, par exemple après inventaire :

```text
EVT-GAMAD-PRODUIT-ACTIVE
EVT-GAMAD-PRODUIT-SUSPENDU
EVT-GAMAD-PRODUIT-RETIRE
EVT-GAMAD-SOURCE-SUSPENDUE
EVT-GAMAD-SOURCE-RETIREE
EVT-GAMAD-POLITIQUE-VERSION-ACTIVEE
EVT-GAMAD-CONTRAT-VERSION-DEPRECIEE
EVT-GAMAD-ORGANISATION-SUSPENDUE
EVT-GAMAD-REALM-FERME
EVT-GAMAD-LIEN-FEDERE-REVOQUE
```

Les références exactes doivent suivre `CAP-CORE-010` et les conventions du dépôt.

Chaque contrat d’événement doit définir :

- producteur ;
- consommateurs autorisés ;
- finalité ;
- type canonique ;
- version ;
- schéma de l’enveloppe ;
- schéma de la charge ;
- champs obligatoires ;
- classification ;
- realm attendu ;
- erreurs ;
- rétention ;
- compatibilité ;
- idempotence ;
- audit obligatoire.

Aucun événement ne doit être publié tant que son contrat n’est pas actif et conforme.

### Évolution de contrat

Une nouvelle version de charge doit suivre l’analyse de compatibilité de `CAP-CORE-009`.

Cas de rupture :

- suppression d’un champ utilisé ;
- changement de type ;
- ajout d’un champ obligatoire ;
- changement de sens d’un code ;
- changement de realm ;
- réduction des consommateurs ;
- modification de finalité ;
- modification de classification ;
- suppression d’une erreur attendue.

Une rupture exige un plan de migration et une période de coexistence explicite.

---

## 2. Vocabulaire CAP-CORE-010

Ajouter ou réutiliser les vocabulaires canoniques pour :

- types d’événements ;
- états d’outbox ;
- états d’abonnement ;
- états de livraison ;
- résultats de tentative ;
- modes de livraison ;
- classifications ;
- codes d’erreur ;
- états de rejeu ;
- types de causalité ;
- portées de realm.

Règle :

> Ajouter un terme au vocabulaire ne crée pas automatiquement un comportement dans le routeur.

Le code doit continuer à utiliser une liste close des transitions autorisées.

---

## 3. Routes API v1 — producteurs

Les routes de publication sont réservées aux producteurs techniques authentifiés.

Routes proposées :

```text
POST /api/v1/evenements/publications
GET  /api/v1/evenements/publications/{producteur}/{idempotence}
```

La première route peut être interne au monolithe si tous les producteurs sont dans le même déploiement. Elle doit néanmoins utiliser le même service applicatif et les mêmes validations que tout relais futur.

Requête de publication :

- enveloppe ;
- charge ;
- empreintes ;
- référence d’idempotence.

Réponse :

- référence d’événement ;
- séquence ;
- reçu ;
- date d’acceptation ;
- `signee: false` tant que les capacités de signature ne sont pas livrées.

Codes :

- `201` première acceptation ;
- `200` rejeu idempotent déjà accepté ;
- `403` producteur non autorisé ;
- `404` contrat/source/realm inconnu ou invisible ;
- `409` conflit d’idempotence ou version ;
- `422` enveloppe ou charge invalide ;
- `503` dépendance critique indisponible.

---

## 4. Routes API v1 — événements

Lectures minimales :

```text
GET /api/v1/evenements
GET /api/v1/evenements/{reference}
GET /api/v1/evenements/{reference}/charge
```

Filtres autorisés :

- séquence début/fin ;
- date début/fin ;
- type exact ;
- contrat exact ;
- producteur exact ;
- realm exact ;
- sujet exact ;
- corrélation exacte.

Règles :

- pagination obligatoire ;
- aucun filtre approximatif ;
- aucune recherche plein texte sur la charge ;
- charge retournée uniquement au consommateur autorisé ;
- enveloppe seule pour l’autorité lorsque la charge n’est pas nécessaire ;
- une charge purgée retourne un état explicite `CHARGE_EXPIREE` ;
- aucune donnée d’un autre realm.

---

## 5. Routes API v1 — abonnements

```text
GET   /api/v1/abonnements
POST  /api/v1/abonnements
GET   /api/v1/abonnements/{reference}
PATCH /api/v1/abonnements/{reference}
POST  /api/v1/abonnements/{reference}/types
POST  /api/v1/abonnements/{reference}/producteurs
POST  /api/v1/abonnements/{reference}/realms
POST  /api/v1/abonnements/{reference}/activation
POST  /api/v1/abonnements/{reference}/suspension
POST  /api/v1/abonnements/{reference}/retrait
GET   /api/v1/abonnements/{reference}/retard
GET   /api/v1/abonnements/{reference}/curseur
```

Règles :

- consommateur résolu depuis la session ou le contexte fédéré, pas depuis un corps libre non vérifié ;
- modification seulement en `PREPARATION`, sauf paramètres explicitement révisables ;
- aucune extension silencieuse ;
- activation confirmée ;
- suspension et retrait confirmés ;
- codes d’erreur structurés.

---

## 6. Routes API v1 — livraisons PULL

```text
GET  /api/v1/abonnements/{reference}/livraisons
POST /api/v1/abonnements/{reference}/livraisons/accuses
POST /api/v1/abonnements/{reference}/livraisons/refus-temporaires
POST /api/v1/abonnements/{reference}/livraisons/refus-definitifs
```

### Lecture

Paramètres :

- `limite` ;
- `bail_secondes` ;
- curseur facultatif.

Réponse :

- référence du bail ;
- expiration ;
- livraisons ordonnées ;
- enveloppes ;
- charges autorisées ;
- métadonnée `rejeu` ;
- prochain curseur indicatif.

### Accusé

Le corps contient uniquement :

- bail ;
- références de livraison ;
- résultat canonique ;
- corrélation.

### Refus

Le corps contient :

- bail ;
- livraisons ;
- code d’erreur canonique ;
- délai demandé éventuel ;
- motif borné.

Ne jamais accepter un stack trace, un dump de payload ou une réponse complète du consommateur dans le journal central.

---

## 7. Routes API v1 — rejeu et lettres mortes

```text
GET  /api/v1/rejeux
POST /api/v1/rejeux
GET  /api/v1/rejeux/{reference}
POST /api/v1/rejeux/{reference}/validation
POST /api/v1/rejeux/{reference}/annulation

GET  /api/v1/lettres-mortes
GET  /api/v1/lettres-mortes/{reference}
POST /api/v1/lettres-mortes/{reference}/relance
POST /api/v1/lettres-mortes/{reference}/cloture
```

Règles :

- volume estimé affiché avant validation ;
- bornes obligatoires ;
- aucune portée universelle ;
- autorisation spécifique ;
- rate limiting renforcé ;
- aucune relance massive non confirmée ;
- rapport final.

---

## 8. OpenAPI et registre des contrats

Mettre à jour :

```text
apps/console-laravel/openapi/core-v1.yaml
```

Puis enregistrer les projections dans `CAP-CORE-009`.

La CI doit détecter :

- route Laravel absente d’OpenAPI ;
- opération OpenAPI fantôme ;
- méthode divergente ;
- schéma divergent ;
- code d’erreur divergent ;
- action d’autorisation divergente ;
- contrat d’événement sans producteur ;
- contrat sans consommateur pour une diffusion externe.

---

## 9. Console d’administration

Créer une entrée `Événements` dans la console.

### Tableau de bord

Afficher :

- événements publiés sur 1 h, 24 h et 7 jours ;
- outboxes en attente ;
- âge de la plus ancienne outbox ;
- abonnements actifs et suspendus ;
- livraisons disponibles ;
- baux expirés ;
- lettres mortes ;
- rejeux actifs ;
- retard par consommateur ;
- état de la chaîne d’intégrité ;
- dépendances indisponibles.

### Fiche d’événement

Afficher :

- enveloppe ;
- contrat/version ;
- producteur ;
- source ;
- realm ;
- finalité ;
- sujet ;
- corrélation ;
- causalité ;
- empreintes ;
- abonnements destinataires ;
- état des livraisons ;
- charge seulement si autorisée.

### Fiche d’abonnement

Afficher :

- consommateur ;
- organisation ;
- realm ;
- finalité ;
- types ;
- producteurs ;
- état ;
- curseur ;
- retard ;
- tentatives ;
- lettres mortes ;
- actions autorisées.

### Écran lettres mortes

Permettre :

- filtrage ;
- lecture du code d’erreur ;
- consultation des tentatives ;
- relance confirmée ;
- clôture motivée ;
- aucun effacement.

### Écran rejeu

Permettre :

- simulation du volume ;
- demande ;
- validation ;
- suivi ;
- annulation avant exécution ;
- rapport final.

La console appelle les mêmes services applicatifs que l’API.

Aucune écriture directe en base.

---

## 10. Événements d’audit CAP-CORE-013

Ajouter au minimum :

```text
OUTBOX_EVENEMENT_PREPAREE
OUTBOX_EVENEMENT_PUBLIEE
OUTBOX_EVENEMENT_EN_ECHEC
EVENEMENT_COMMUN_ACCEPTE
EVENEMENT_COMMUN_REFUSE
ABONNEMENT_EVENEMENT_CREE
ABONNEMENT_EVENEMENT_ACTIVE
ABONNEMENT_EVENEMENT_SUSPENDU
ABONNEMENT_EVENEMENT_RETIRE
LOT_LIVRAISONS_ACCORDE
LIVRAISONS_ACCUSEES
LIVRAISONS_REFUSEES_TEMPORAIREMENT
LIVRAISONS_REFUSEES_DEFINITIVEMENT
LIVRAISON_MISE_EN_LETTRE_MORTE
LETTRE_MORTE_RELANCEE
REJEU_EVENEMENTS_DEMANDE
REJEU_EVENEMENTS_VALIDE
REJEU_EVENEMENTS_EXECUTE
CHARGE_EVENEMENT_PURGEE
OPERATION_EVENEMENT_REFUSEE
```

Chaque trace contient au minimum :

- acteur ;
- action ;
- ressource ;
- résultat ;
- politique ;
- preuve ;
- corrélation ;
- événement ou abonnement concerné ;
- empreinte de charge lorsque pertinente ;
- aucune charge utile.

---

## 11. Sécurité et minimisation

### Champs interdits

Refuser toute charge contenant ou ressemblant à :

- mot de passe ;
- code de secours ;
- clé privée ;
- secret d’API ;
- cookie ;
- en-tête Authorization ;
- JWT ;
- jeton fédéré ;
- challenge WebAuthn ;
- empreinte d’authentificateur ;
- numéro de carte bancaire ;
- dossier médical ;
- document complet ;
- profil complet.

### Références plutôt que données

Préférer :

```json
{
  "produit_reference": "PRD-GAMAD-003",
  "nouvel_etat": "SUSPENDU"
}
```

à une copie complète de la fiche produit.

### Taille

Définir :

- taille maximale d’enveloppe ;
- taille maximale de charge ;
- taille maximale de lot ;
- nombre maximal de types par abonnement ;
- nombre maximal de realms ;
- plage maximale d’un rejeu.

Les limites doivent être configurables dans des bornes sûres, pas désactivables par une valeur arbitraire.

---

## 12. Tests de capacité

Créer :

```text
core/journal-evenements/tests/evenements_p3.php
```

Épreuves minimales :

1. migration du magasin central ;
2. migration d’outbox dans un magasin producteur ;
3. PostgreSQL obligatoire en production ;
4. SQLite isolé ;
5. enveloppe valide ;
6. type inconnu refusé ;
7. contrat absent refusé ;
8. version inactive refusée ;
9. producteur non déclaré refusé ;
10. source inactive refusée ;
11. realm inactif refusé ;
12. finalité absente refusée ;
13. charge hors schéma refusée ;
14. champ supplémentaire refusé ;
15. secret refusé ;
16. jeton refusé ;
17. taille excessive refusée ;
18. outbox dans la transaction métier ;
19. rollback métier supprimant l’outbox ;
20. commit métier conservant l’outbox ;
21. journal central indisponible sans perte d’outbox ;
22. publication après retour du journal ;
23. idempotence après rejeu ;
24. crash après acceptation centrale sans doublon ;
25. séquence monotone ;
26. chaîne d’empreintes valide ;
27. mutation d’événement refusée ;
28. falsification détectée ;
29. charge et enveloppe cohérentes ;
30. abonnement créé en préparation ;
31. activation sans type refusée ;
32. activation sans producteur refusée ;
33. consommateur non déclaré refusé ;
34. wildcard refusé ;
35. filtre SQL libre impossible ;
36. realm parent non omniscient ;
37. franchissement explicite accepté ;
38. franchissement absent refusé ;
39. événement routé une seule fois par abonnement ;
40. aucun abonnement correspondant sans perte de l’événement ;
41. lecture par propriétaire ;
42. lecture par autre consommateur refusée ;
43. lot borné ;
44. bail borné ;
45. concurrence sur lecture sans double bail ;
46. bail expiré libéré ;
47. accusé idempotent ;
48. accusé contradictoire refusé ;
49. accusé hors bail refusé ;
50. curseur contigu ;
51. accusé hors ordre ne sautant rien ;
52. refus temporaire ;
53. délai plafonné ;
54. nouvelle tentative ;
55. plafond de tentatives ;
56. lettre morte ;
57. relance gouvernée ;
58. historique d’échec conservé ;
59. rejeu borné ;
60. rejeu sans autorisation refusé ;
61. rejeu hors realm refusé ;
62. rejeu ne créant pas un nouvel événement ;
63. marqueur de rejeu présent ;
64. déduplication consommateur pilote ;
65. charge expirée non lue ;
66. purge trop précoce refusée ;
67. purge autorisée conservant l’empreinte ;
68. audit sans payload ;
69. restauration conservant séquences et curseurs ;
70. restauration conservant lettres mortes ;
71. configuration Laravel en cache ;
72. contre-épreuve démontrant que la garde sait échouer.

Chaque règle de sécurité doit avoir sa contre-épreuve.

---

## 13. Tests d’intégration Laravel

Créer au minimum :

```text
apps/console-laravel/tests/Integration/evenements_v1_p1.php
apps/console-laravel/tests/Integration/evenements_console_p1.php
apps/console-laravel/tests/Integration/evenements_outbox_p1.php
apps/console-laravel/tests/Integration/evenements_consommateur_p1.php
apps/console-laravel/tests/Integration/evenements_rejeu_p1.php
```

Adapter et conserver verts :

- API générale ;
- console UX ;
- identités ;
- produits ;
- sources ;
- politiques ;
- contrats ;
- organisations ;
- realms ;
- fédération ;
- accès ;
- continuité ;
- import SQLite ;
- PostgreSQL ;
- configuration mise en cache ;
- OpenAPI/contrats.

---

## 14. Scénario d’intégration obligatoire

1. activer un consommateur pilote ;
2. créer un abonnement limité à un type et un realm ;
3. activer l’abonnement ;
4. exécuter une vraie commande métier produisant une outbox ;
5. couper le journal central ;
6. constater l’outbox en attente ;
7. rétablir le journal ;
8. publier ;
9. lire un lot ;
10. vérifier enveloppe et charge ;
11. simuler un crash avant accusé ;
12. laisser le bail expirer ;
13. relire la même livraison ;
14. dédupliquer localement ;
15. accuser ;
16. constater l’avancement du curseur ;
17. produire un deuxième événement ;
18. le refuser temporairement ;
19. atteindre la lettre morte ;
20. corriger la cause ;
21. relancer ;
22. accuser ;
23. demander un rejeu borné ;
24. vérifier que l’événement original garde la même référence ;
25. restaurer les magasins dans des bases isolées ;
26. reprendre la consommation sans perte ni doublon logique.

---

## 15. Consommateur de conformité

Créer une petite implémentation de référence dans les tests ou outils de développement.

Elle doit posséder une inbox locale :

```text
evenement_recu
- evenement_reference unique
- contrat_reference
- contrat_version
- recu_le
- traite_le nullable
- resultat
```

Règles :

- insertion idempotente ;
- événement déjà reçu détecté ;
- traitement métier simulé après insertion ;
- accusé seulement après persistance locale ;
- reprise après crash ;
- aucun payload dans les logs.

Cette inbox démontre la livraison au moins une fois et la déduplication.

Elle n’appartient pas au magasin central de `CAP-CORE-014`.

---

## 16. Tests de charge raisonnables

Sans transformer ce chantier en plateforme massive, éprouver :

- publication par lots ;
- plusieurs producteurs ;
- plusieurs abonnements ;
- concurrence de workers ;
- 10 000 événements en environnement de test ;
- reprise après interruption au milieu d’un lot ;
- latence et mémoire bornées ;
- index utilisés pour les lectures principales.

Documenter les mesures sans inventer une capacité industrielle non testée.
