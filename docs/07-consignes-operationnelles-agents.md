# Consignes opérationnelles pour les agents

Ce document décrit la méthode de travail cible de GAMAD Core. Il ne constitue ni une loi, ni un acte, ni une preuve d’implémentation.

Le fichier racine `CLAUDE.md` applique désormais ces consignes. Son contenu opérationnel peut évoluer avec le dépôt sans modifier les empreintes historiques du corpus Genesis II.

## 1. Avant de modifier le dépôt

1. lire `README.md` et `docs/README.md` ;
2. lire le document métier ou la capacité concernée ;
3. inspecter le code, les migrations, les contrats et les tests ;
4. identifier les consommateurs et les dépendances ;
5. vérifier l’état réel avant toute suppression.

Une capacité n’existe pas seulement parce qu’un document la décrit.

## 2. Méthode de travail

```text
comprendre le résultat attendu
→ inspecter l’existant
→ identifier les frontières Core / satellite
→ implémenter le parcours complet
→ tester
→ corriger
→ rapporter honnêtement l’état réel
→ mettre à jour la documentation utile
```

Une question est nécessaire seulement lorsqu’une décision produit réellement plusieurs résultats incompatibles. Pour une ambiguïté secondaire, choisir l’option la plus réversible et l’indiquer.

## 3. Interdictions

Ne jamais :

- inventer un prix, une formule, un droit, une permission ou une preuve ;
- déclarer réussi un test qui a échoué ;
- déclarer implémentée une capacité seulement documentée ;
- masquer une dépendance ou une régression ;
- placer un secret dans Git ;
- réécrire l’historique de `main` ;
- créer une Constitution, une loi ou un acte d’adoption pour piloter un chantier ;
- supprimer `genesis-ii/` tant que des modules ou tests en dépendent ;
- supprimer des tests pour obtenir artificiellement du vert.

## 4. Branches et intégration

- travailler sur une branche dédiée ;
- garder les commits cohérents ;
- ne pas fusionner dans `main` sans instruction explicite ;
- ne pas déployer ou détruire des données réelles sans instruction explicite ;
- séparer documentation, migration runtime et suppression finale.

## 5. Tests et états

Avant de présenter un chantier comme terminé :

- exécuter les tests des modules modifiés ;
- exécuter les intégrations affectées ;
- vérifier les migrations et configurations ;
- signaler les tests non exécutés ;
- utiliser un état honnête : `ABSENT`, `DÉMONSTRATIF`, `PARTIEL`, `IMPLÉMENTÉ`, `EXPLOITÉ`, `HÉRITÉ À MIGRER`, `CONTRADICTOIRE` ou `À VÉRIFIER`.

La documentation n’est pas une preuve d’exécution. Un test local n’est pas une preuve de déploiement.

## 6. Frontière Core / satellites

Le Core possède les responsabilités communes : identité, authentification, autorisation, fédération, contrats transversaux, événements, audit, continuité et Matching partagé.

Chaque satellite conserve son compte local, ses données métier, ses transactions, ses abonnements, ses quotas, ses interfaces et ses règles économiques.

Le Core peut consommer des signaux autorisés pour une finalité explicite. Il ne devient pas une base universelle de tous les dossiers métier.

## 7. Matching

Le Matching produit des correspondances contextualisées. Il ne produit pas une réputation humaine universelle, ne facture pas et n’exécute pas les règles économiques des satellites.

## 8. Transition hors Genesis II

1. documentation simple sans changement runtime ;
2. remplacement des lectures de `genesis-ii/` module par module ;
3. suppression de l’ancien corpus lorsque toutes les dépendances utiles ont disparu.

Chaque parseur historique reste présent jusqu’à ce que son remplaçant soit fonctionnel et testé.

## 9. Compte rendu attendu

Indiquer à la fin :

- les fichiers modifiés ;
- les fonctions obtenues ;
- les tests exécutés et leurs sorties ;
- les limites ;
- les décisions produit restantes ;
- les actions non réalisées.
