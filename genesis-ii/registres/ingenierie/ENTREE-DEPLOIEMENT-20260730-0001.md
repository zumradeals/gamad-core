# ENTREE-DEPLOIEMENT-20260730-0001 — CORE OPERATIONAL FOUNDATION V1

**Statut : CONSTAT OPÉRATIONNEL NON NORMATIF**

Cette entrée complète factuellement
`REGISTRE-DES-DEPLOIEMENTS-0001.md` sans modifier ce projet historique scellé
par `ADOPTION-0020`. Elle ne prononce aucune adoption et n'établit pas `G0`.

| Champ | Constat |
|---|---|
| **Référence** | `DEP-GAMAD-CORE-20260730-0001` |
| **Version déployée** | `388fa746263a11552ca321714a7af06aa27106ef` sur `main` |
| **Environnement** | serveur local de production `console.dgafrique.com` — Nginx HTTPS, PHP 8.3 FPM, PostgreSQL 16 |
| **Autorisation** | instruction expresse de l'autorité du 30 juillet 2026 : pousser, fusionner et déployer sur ce serveur uniquement |
| **Exécutant** | Codex, sous l'instruction de l'autorité |
| **Date** | 30 juillet 2026 |
| **Résultat** | succès — liveness `VIVANT`, readiness `PRET`, quatre magasins PostgreSQL distincts |
| **Incidents** | deux défauts de raccordement et d'isolation détectés pendant la livraison ; aucun index incohérent n'a été écrit ; les artefacts de garde `ENTITE-DE-TEST` ont été retirés transactionnellement |
| **Corrections** | PR `#30`, `#31`, `#32` et `#33`, toutes contrôlées puis fusionnées |
| **Rollback** | non exécuté ; sauvegarde pré-déploiement vérifiée sous `/var/backups/gamad-core/pre-foundation-20260730T0135Z` |
| **Sauvegarde** | lot post-migration `/var/backups/gamad-core/daily/20260730T015005Z`, sommes vérifiées |
| **Restauration** | exercice réussi sur quatre bases isolées, lectures de contrôle réussies, bases d'exercice supprimées |
| **Observation** | `Identity Registry` possède sa base et son schéma mais contient zéro identité persistante ; son écriture reste fermée par défaut tant que `CAP-CORE-004` ne l'autorise pas |
| **Réserves** | authentification forte non encore livrée ; routage externe des alertes non installé ; opérateur et propriétaire opérationnels non formellement inscrits |

## Preuves techniques

- commit de fondation : `e20b92937daec2878a555f0997129c89e266d4dd`;
- correctif d'isolation : `04672d1c97be3acd5d39022e94a5448a2656adb9`;
- correctif du cache Laravel : `f53d1aec36089970d7cb9d9636fd238c28510b77`;
- automatismes d'exploitation : `388fa746263a11552ca321714a7af06aa27106ef`;
- sauvegardes quotidiennes, vérification horaire du journal et readiness toutes
  les cinq minutes activées par `systemd`.

## Complément du 30 juillet 2026 — durcissement P1 de la porte console

Ce complément est additif. Il ne modifie ni le constat du déploiement initial
ci-dessus, ni la portée de sa réserve sur l'authentification forte.

| Champ | Constat |
|---|---|
| **Version livrée** | `be77a5521f90b03a625b23448d04a730fa1b28ba` sur `main`, fusion de la PR `#35` |
| **Objet** | limitation des tentatives de connexion console, journalisation append-only des acceptations, refus, indisponibilités et blocages, révocation de toute session dont la preuve d'audit ne peut être produite |
| **Données protégées** | aucun secret, identifiant brut ou adresse IP brute n'est inscrit au journal par ce mécanisme |
| **Contrôles CI** | quatre workflows réussis sur le commit livré, dont `Core Operational Foundation` et les vingt preuves P3 |
| **Contrôles locaux** | `console_auth_p1.php`, `api_v1_p1.php` et `fondation_operationnelle_p3.php` établis |
| **Livraison** | cache Laravel reconstruit sous l'utilisateur applicatif, puis PHP 8.3 FPM rechargé ; aucune migration, dépendance ou modification de données requise |
| **Résultat** | succès — `/connexion` répond HTTP `200`, la route `POST /connexion` porte `throttle:10,1`, la readiness demeure `PRET` sur quatre magasins PostgreSQL |
| **Journal** | chaîne valide après livraison, cinq événements présents et tête `416f45af7029e6e23a005d2ebc5d134dd5d294249fd240510269b3d24a041040` |
| **Réserve maintenue** | ce P1 durcit la porte existante mais ne constitue pas, à lui seul, la livraison d'une authentification forte |
