# GAMAD unifié — retrait de la liaison et état du transfert

## Vision

La [décision propriétaire](GAMAD-UNIFIE-VISION-ET-REPRISE-2026-11-06.md) s'applique à Réseau. Le Core administre le compte unique et ses données, et gouverne l'accès aux fonctionnalités Réseau.

## Diagnostic au 6 novembre 2026

| Source | Responsabilité constatée | Suite |
| --- | --- | --- |
| Core, `main` à `7c30fd4` | CTR-01 inscrit la personne et ses identifiants. CTR-16 délivre et contrôle ses sessions. CAP-CORE-004 décide des droits soumis à politique. | Conserver les autorités Core. |
| Core, PR #89 à #94 | Diagnostic des magasins, inventaire des routes, normalisation transférée, contrôle UUID/BIGINT et validations hors ligne. | Conserver les outils de diagnostic et la normalisation. Les validations historiques sont utilisables hors exécution. |
| Core, PR #95 à #101 | Modèle de compte local, table de liaison, orchestration et gardes de liaison. | Retirer les chemins d'exécution de liaison et conserver les lignes historiques. |
| Core, PR #102 avant correction, `ff596ff` | La route personnelle exigeait CTR-16 et une correspondance locale. L'utilisateur Laravel de ce dépôt était un modèle d'amorçage BIGINT. | Lire le sujet Core directement. Retirer le fournisseur Laravel de ce dépôt. |
| [Source Réseau `83d1a1d`](https://github.com/zumradeals/gamad/tree/83d1a1df1496f322a5e718996c9420120d021ec0) | `User` UUID, `Profile`, informations de vérification, état de suspension, rôle et secrets sont gérés localement. `Credentials`, `Sessions` et `AccountCloser` agissent sur ce modèle. Les adaptateurs HTTP de session Core sont présents, sans appelant au commit audité. 56 migrations, dont 77 occurrences de déclarations explicites de clés étrangères vers `users`. | Transférer la responsabilité et les données des comptes au Core avec les fonctionnalités qui les consomment. Les tables Laravel des deux dépôts ont des types distincts. |

## Contrat de la route personnelle

`gamad.session` valide le jeton CTR-16 conservé dans la session de transport HTTP. Le contrôleur tire exclusivement sa référence du sujet authentifié. Le cas d'usage Core lit le registre souverain et exige une personne inscrite non provisoire, un identifiant de connexion vérifié et un état de cycle actif à la date effective. Le nom renvoyé est le libellé canonique. La lecture personnelle ne confère aucune habilitation de console. Les autres droits Réseau devront être appliqués dans le Core lors du transfert des opérations correspondantes.

La migration renomme la table de liaison historique. Elle conserve ses lignes, ses contraintes et les lignes historiques de `users`. Son retour arrière restaure le nom initial. Le modèle, le fournisseur Laravel et le seeder de démonstration ne peuvent plus exploiter ces comptes dans Core. La migration d'archive concerne le stockage Laravel de `gamad-core`, à identifier avant toute exécution.

## Suite sur installation neuve

Le 6 novembre 2026, le propriétaire a confirmé que les deux dépôts sont hors production et qu’aucune donnée historique n’est à reprendre. La migration de retrait des tables Laravel exige qu’elles soient vides. Le transport HTTP conserve sa table de sessions sans fournisseur de comptes Laravel.

Le parcours personnel de présentation issu de Réseau est désormais servi par le Core. La biographie, limitée à 280 caractères et normalisée comme dans le parcours source, est éditable via `/mon-profil`. Elle est stockée dans le registre persistant, par référence canonique et sans identifiant de compte supplémentaire. Réseau relit la même donnée via `/reseau/mon-compte`.

Les autres champs et parcours du profil source, ainsi que les autres fonctionnalités Réseau, restent à transférer. La présentation reste personnelle tant que la publication du profil et ses règles de visibilité n’ont pas été reprises.

## Conditions de transfert restantes

1. Inventorier les relations du schéma source et créer le schéma cible sur une base vide. Les relations de compte des fonctionnalités reprises doivent référencer directement la personne canonique.
2. Transférer les données de compte et leurs règles dans le Core. Reprendre les opérations de lecture et de modification de profil, vérification, suspension, habilitation et fermeture avec leurs contraintes existantes. Utiliser les références Core pour les nouveaux contenus et opérations.
3. Raccorder les routes et écritures métier à ces contrats et qualifier les parcours transférés en base avec des jeux de données issus du schéma historique. Préparer une bascule contrôlée et un retour arrière à partir d'un état vérifié.
