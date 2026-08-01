# CAP-CORE-022 — Satellite Federation

Fiche établie par inspection du code, des gardes et de la CI. Elle décrit ce
qui existe, pas ce qui est souhaité.

**Référence :** `CAP-CORE-022`

**Nom :** Fédération des satellites

**Objectif :** relier le Compte GAMAD authentifié aux comptes produit locaux,
et remettre au satellite une preuve d’accès bornée.

**Problème résolu :** le Core savait authentifier une personne mais ne savait
pas l’ouvrir sur un produit. Chaque satellite aurait donc dû réinventer son
propre lien d’identité, sans borne commune ni révocation commune.

**Produits consommateurs :** GamaDrive (`PRD-GAMAD-002`) comme pilote. Wasplex
(`PRD-GAMAD-003`) et IKOMA (`PRD-GAMAD-004`) restent des partenaires externes
non entérinés : leur état dérivé ne porte pas la reconnaissance, et le Core
refuse de les fédérer tant qu’il en est ainsi.

**Responsabilité du Core :** décider l’ouverture (CAP-CORE-004), provisionner un
lien produit minimal de façon idempotente (CAP-CORE-001), émettre un jeton borné
à un satellite, le vérifier une fois, le révoquer, et prouver le parcours
(CAP-CORE-013).

**Ce qui reste dans les satellites :** le compte produit local, le rôle métier,
le plan, l’abonnement, les quotas, les préférences, les contenus, les
transactions et l’historique. Le Core n’en connaît rien et n’a aucun endroit où
les mettre.

**Données possédées :**

- `jeton_federe` dans le magasin d’accès — audience, identité, lien produit,
  type de relation, portées, niveau d’assurance, empreinte de la session Core
  émettrice, référence de preuve, émission, expiration, consommation,
  révocation ;
- le lien produit lui-même reste la propriété de CAP-CORE-001
  (`relation_produit`), y compris le sujet local opaque du satellite.

**Données exclues :** aucune valeur de jeton n’est conservée — seule son
empreinte SHA-256 l’est. Aucune donnée économique, aucun profil, aucun jugement.

**Commandes :**

- `Federation::ouvrir()` — provisionnement idempotent puis émission d’un jeton ;
- `Federation::verifierJeton()` — vérification et consommation à usage unique ;
- `Federation::revoquerAcces()` — clôture du lien et fermeture des jetons ;
- `Federation::revoquerJetonsDeSession()` / `fermerJetonsDeSession()` —
  déconnexion globale.

**Requêtes :**

- `Federation::catalogueProduits()` — satellites connus et fédérables ;
- `Federation::resoudreAcces()` — vue Portail d’une identité : produit, état
  d’activation, niveau d’accès, dernière ouverture.

**Événements :** `FEDERATION` dans le journal opérationnel —
`DECISION_OUVERTURE_PRODUIT`, `OUVERTURE_PRODUIT`,
`OUVERTURE_PRODUIT_REFUSEE`, `VERIFICATION_JETON_FEDERE`,
`DECISION_REVOCATION_ACCES`, `REVOCATION_ACCES_PRODUIT`. Aucun jeton n’y figure,
seulement sa référence.

**Dépendances :** CAP-CORE-001 (identités et liens produits), CAP-CORE-004
(décision), CAP-CORE-005 (session Core et magasin d’accès), CAP-CORE-013
(preuve), CAP-CORE-007 (politique technique portée par l’index).

**Autorisations :** `POL-FEDERATION-SATELLITES-V1`, portée par la source
versionnée `core/registre-normes/resources/index-baseline-v1.json` et évaluée
par CAP-CORE-004. Le module n’écrit aucune règle. Au-delà de la décision, le
code oppose ses propres bornes : on ouvre pour soi-même ou par l’autorité
d’inscription ; on ne vérifie un jeton que si l’on est le satellite
destinataire ; on ne révoque qu’en tant que porteur, satellite concerné ou
autorité.

**Bornes du jeton :** audience unique, durée de 30 à 300 secondes (120 par
défaut), portée unique `ouverture_session_locale`, usage unique, rattachement à
la session Core et au niveau d’assurance qui l’ont produit.

**Comportement en panne :** une décision ou une preuve impossible ferme
l’ouverture (`503`). Un jeton dont la session Core est fermée, dont le lien
produit est clos ou dont l’audience diffère est refusé, sans être consommé au
préjudice de son destinataire légitime.

**Sauvegarde et restauration :** les jetons vivent dans le magasin d’accès,
couvert par `ops/core-foundation`. `php artisan core:fondation:migrer` applique
la migration ; la readiness la vérifie.

**Code actuel :** `core/registre-federation/`,
`apps/console-laravel/app/Application/Federation/AccesSatellites.php`,
`apps/console-laravel/app/Http/Controllers/Api/V1/FederationController.php`,
routes `/api/v1/produits*`, contrat `apps/console-laravel/openapi/core-v1.yaml`.

**Tests actuels :**

- `core/registre-federation/tests/federation_p3.php` — 16 épreuves et
  contre-épreuves, raccordée à la CI ;
- `apps/console-laravel/tests/Integration/federation_v1_p1.php` — parcours HTTP
  complet sur le pilote, raccordé à la CI.

**État réel :** `IMPLÉMENTÉ` — le parcours d’ouverture, de vérification, de
révocation et de déconnexion globale est éprouvé de bout en bout sur GamaDrive.
Aucun satellite réel ne le consomme encore : l’exploitation reste à établir.

**Manques :**

- aucune intégration réelle côté GamaDrive V2 ; le satellite est joué par sa
  session Core dans les épreuves ;
- pas de vue console web pour le porteur — l’accès passe par l’API ;
- pas de suspension temporaire distincte de la révocation ;
- pas de publication d’événement vers les satellites (CAP-CORE-014 reste
  `PARTIEL`) : un satellite n’est pas notifié d’une révocation, il la constate
  au refus du jeton suivant.

**Prochain chantier :** intégration réelle de GamaDrive V2, puis publication
d’événements de révocation vers les satellites.
