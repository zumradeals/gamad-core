# Instructions opérationnelles — GAMAD Core

Ce fichier explique comment travailler dans le dépôt. Il ne gouverne pas GAMAD et ne remplace pas la documentation métier de `docs/`.

## 1. Avant de modifier le Core

Pour toute mission :

1. lire `docs/00-vision-gamad-core.md` ;
2. lire la fiche de la capacité concernée dans `docs/capacites/` ;
3. lire, selon le besoin, les documents Fédération, Matching, sécurité ou exploitation ;
4. inspecter le code, les migrations, les routes, les contrats et les tests existants ;
5. définir le résultat observable de bout en bout ;
6. distinguer ce qui est déjà opérationnel, partiel, hérité, démonstratif ou absent.

Ne reconstruis pas une règle active à partir des anciens textes Genesis II présents dans l’historique Git. Les fichiers actifs de `docs/` décrivent la cible actuelle.

## 2. Ne jamais inventer

Ne jamais inventer silencieusement :

- une capacité ;
- un contrat ;
- une permission ;
- une donnée commune ;
- une relation entre produits ;
- une preuve ;
- un état de production ;
- un niveau d’assurance ;
- une formule de Matching ;
- une admission de satellite ;
- une réussite de test ou de déploiement.

Lorsqu’une décision manque, signale-la clairement. Pour une décision secondaire et réversible, choisis l’option la plus simple et documente-la.

## 3. Respecter les frontières

- le Core connaît l’identité commune, pas tous les profils métier ;
- chaque satellite conserve ses données détaillées et ses règles métier ;
- aucun accès direct à la base d’un autre produit ;
- toute coopération passe par un contrat, un événement, une attestation ou un signal autorisé ;
- le Matching rapproche, mais ne crée pas l’identité, ne facture pas et ne décide pas seul d’une action sensible ;
- le Portail ouvre l’écosystème, mais ne remplace pas les satellites ;
- un jeton destiné à un satellite ne doit pas être accepté par un autre.

## 4. Construire une capacité réelle

Une capacité n’est pas terminée parce qu’un module, un écran ou un fichier existe.

Vérifier selon le périmètre :

- entrées et validation ;
- autorisation ;
- persistance ;
- contrats et événements ;
- idempotence ;
- résultat visible ;
- erreurs et mode dégradé ;
- journalisation ;
- sécurité ;
- tests unitaires, de contrat et d’intégration ;
- sauvegarde et restauration lorsque des données sont possédées ;
- comportement réel en production lorsqu’il est revendiqué.

## 5. Héritage Genesis II

Le code peut encore contenir des noms, commentaires ou modules historiques liés aux anciens registres documentaires.

Ces références ne définissent plus le produit.

Lorsqu’un composant hérité est touché :

1. déterminer s’il fournit encore une fonction utile ;
2. conserver la fonction utile ;
3. retirer la dépendance aux actes, lois, adoptions et fichiers supprimés ;
4. renommer progressivement selon sa responsabilité réelle ;
5. adapter les tests à un comportement technique, pas à une conformité documentaire.

Ne supprime pas un composant opérationnel uniquement parce que son ancien nom est normatif.

## 6. Git

- travailler sur une branche dédiée ;
- ne jamais réécrire l’historique de `main` ;
- ne jamais force-pousser ;
- préserver les changements humains non liés ;
- montrer le diff, les tests et les risques ;
- ne fusionner dans `main` qu’après instruction explicite du dirigeant ;
- ne jamais déposer un secret dans Git.

## 7. Compte rendu

```text
Tâche :
Branche :
Capacité concernée :
Résultat observable :
Fichiers modifiés :
Contrats ou événements touchés :
Tests exécutés :
Résultats :
État réel :
Risques et éléments différés :
Décisions encore nécessaires :
Action suivante :
```

Le but est de faire fonctionner GAMAD Core et ses satellites comme un écosystème cohérent, pas de produire un système juridique autour du code.
