# GAMAD Core — Consignes opérationnelles

Ce fichier guide les agents humains et artificiels qui travaillent dans ce dépôt. Il ne constitue ni une loi, ni un acte d’adoption, ni une preuve d’implémentation.

## 1. Références actives

Avant tout chantier :

1. lire `README.md` et `docs/README.md` ;
2. lire le document de capacité ou d’architecture concerné ;
3. inspecter le code, les migrations, les contrats, les configurations et les tests ;
4. identifier les consommateurs et les dépendances ;
5. vérifier l’état réel avant toute modification ou suppression.

Les documents Genesis II restent temporairement présents pour les composants qui en dépendent encore. Ils sont historiques et transitoires ; ils ne doivent pas servir à créer de nouveaux blocages, lois ou actes.

## 2. Méthode de travail

```text
comprendre le résultat demandé
→ inspecter l’existant
→ identifier les frontières Core / satellite
→ choisir une solution défendable et réversible
→ implémenter le parcours complet
→ tester
→ corriger
→ rapporter honnêtement l’état réel
→ mettre à jour la documentation utile
```

Ne demande pas une autorisation déjà donnée. Une question est nécessaire seulement lorsqu’une décision produit réellement plusieurs résultats incompatibles.

## 3. Règles absolues

Ne jamais :

- inventer un prix, une formule, un droit, une permission, une donnée ou une preuve ;
- déclarer réussi un test qui a échoué ;
- déclarer implémentée une capacité seulement documentée ;
- masquer une dépendance, une erreur ou une régression ;
- inscrire un secret dans Git ;
- réécrire l’historique de `main` ;
- étendre ses propres permissions ;
- supprimer des tests pour obtenir artificiellement du vert ;
- supprimer `genesis-ii/` tant que des composants utiles en dépendent ;
- créer une Constitution, une loi, un acte ou un registre pour remplacer le code demandé.

## 4. Branches et actions externes

- travailler sur une branche dédiée ;
- garder les commits cohérents et vérifiables ;
- ne pas fusionner dans `main` sans instruction explicite du dirigeant ;
- ne pas déployer, détruire ou modifier des données réelles sans instruction explicite ;
- séparer les changements documentaires, les migrations runtime et les suppressions finales.

## 5. Tests et vérité

Avant de présenter un chantier comme terminé :

- exécuter les tests des modules modifiés ;
- exécuter les intégrations et gardes affectées ;
- vérifier les migrations, configurations et chemins d’exploitation ;
- signaler les tests non exécutés et les outils absents ;
- indiquer les limites et les décisions produit restantes.

États autorisés : `ABSENT`, `DÉMONSTRATIF`, `PARTIEL`, `IMPLÉMENTÉ`, `EXPLOITÉ`, `HÉRITÉ À MIGRER`, `CONTRADICTOIRE` et `À VÉRIFIER`.

Un test local n’est pas une preuve de déploiement. Un document n’est pas une preuve d’exécution.

## 6. Frontière Core / satellites

Le Core porte les responsabilités communes : identité, authentification, autorisation, organisations et mandats communs, fédération, contrats transversaux, événements, audit, continuité et Matching partagé.

Chaque satellite conserve son compte produit local, ses données métier détaillées, ses transactions, ses abonnements, ses quotas, ses interfaces et ses règles économiques.

Le Core peut utiliser des signaux autorisés pour une finalité explicite. Il ne devient pas une base universelle regroupant tous les dossiers métier.

## 7. Matching

Le Matching produit des correspondances contextualisées entre personnes, organisations, besoins, offres et institutions. Il ne crée pas une réputation humaine universelle, ne facture pas et n’exécute pas les règles économiques des satellites.

## 8. Transition hors Genesis II

La transition suit cet ordre :

1. documentation opérationnelle simple ;
2. remplacement des lectures de `genesis-ii/` module par module avec tests verts ;
3. suppression des parseurs devenus inutiles ;
4. suppression finale du corpus lorsque plus aucune dépendance runtime utile ne subsiste.

Chaque parseur historique reste présent jusqu’à ce que son remplaçant soit fonctionnel et testé.

## 9. Compte rendu attendu

Indiquer à la fin :

- les fichiers modifiés ;
- les fonctions obtenues ;
- les tests exécutés et leurs sorties réelles ;
- les limites ;
- les décisions restantes ;
- les actions non réalisées.
