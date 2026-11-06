# Module Réseau du Core — parcours Besoins

## Vision

Une personne peut exprimer un besoin, trouver de l’aide et avancer avec une autre personne. Le premier lot couvre ce parcours personnel de bout en bout. Les parcours collectifs, les projets et les opérations financières relèvent de lots distincts.

## Conception

GAMAD est une application unique avec une seule base. Le module Réseau utilise directement le compte et les données administrés par le Core. Le Core décide de l’identité, de la session, des droits et de leur révocation. La console Core administre les paramètres du module. Le profil visible est une présentation des données du compte selon leur visibilité.

| Étape | Règle observable |
| --- | --- |
| Publier et découvrir | Un membre actif publie un besoin en son nom. Le besoin est une publication. Le fil, les filtres et le détail respectent les mêmes règles de visibilité et de modération. |
| Proposer | Un autre membre actif qui peut voir le besoin et qui peut interagir avec son auteur propose son aide. Une proposition en attente reste privée entre les personnes concernées. Son auteur peut la retirer. |
| Décider | L’auteur du besoin peut accepter ou refuser une proposition en attente. La première acceptation place le besoin en cours. Une décision répétée ou périmée ne crée aucun second échange. |
| Échanger | Chaque proposition acceptée ouvre un échange privé entre le proposant et l’acceptant. Le Core vérifie les accès à chaque lecture et envoi, y compris le blocage et la révocation. |
| Terminer | L’auteur peut déclarer le besoin résolu ou le clore sans résolution. Les propositions en attente sont refusées dans la même opération. Les échanges acceptés restent accessibles aux participants tant que leurs droits le permettent. |

## Recette des écrans

- Sur un écran assez large, le fil et les vues principales de travail exploitent au moins 1 400 px utiles. Vérifier à 1 600 px de fenêtre et au-delà. Les formulaires et les messages gardent une colonne de lecture confortable.
- Sur une fenêtre de 390 px, effectuer le même parcours sans défilement horizontal et avec des actions utilisables au toucher et au clavier.
- Avec deux comptes Core, publier depuis le fil, trouver le besoin, proposer de l’aide, accepter, retrouver l’échange, puis terminer le besoin. Les intitulés et états visibles doivent permettre d’avancer sans consigne externe. Vérifier également les états vides, erreurs et confirmations.
- La recette visuelle vérifie la lisibilité, la hiérarchie des actions, la cohérence de la typographie et des espacements sur les vues du parcours.

## Point de départ de l’implémentation

- Réutiliser les opérations déjà présentes dans `ContenusGamad`, `PropositionsAideGamad` et `ConversationAideGamad`. Elles couvrent les transitions du besoin, la décision sur les propositions et la séparation des échanges par proposition. L’acceptant réel est conservé dans le registre.
- Les routes Besoins exposent déjà ces opérations en JSON. La prochaine tranche raccorde le parcours depuis le fil à des écrans utilisables pour les deux rôles, en s’appuyant sur les services existants et sur les habilitations Core.
- `apps/console-laravel/tests/Integration/compte_gamad_p1.php` exerce le parcours HTTP et ses protections. La recette des écrans reste à exécuter sur l’interface raccordée.
