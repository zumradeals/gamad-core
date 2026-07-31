# GAMAD Core — Consignes opérationnelles

Ce fichier explique comment travailler dans le dépôt. Il ne définit ni une loi, ni une autorité, ni un statut de capacité.

## 1. Avant de modifier le dépôt

1. lire `README.md` ;
2. lire `docs/README.md` ;
3. lire le document métier ou la fiche de capacité concernée ;
4. inspecter le code, les migrations, les contrats et les tests existants ;
5. vérifier les dépendances réelles avant toute suppression ou refonte.

Ne déduis jamais qu’une capacité existe seulement parce qu’un document la décrit.

## 2. Méthode de travail

Pour un ordre clair :

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

Une question n’est nécessaire que lorsqu’une décision produit réellement plusieurs résultats incompatibles. Pour une ambiguïté secondaire, choisir l’option la plus réversible et l’indiquer.

## 3. Interdictions

Ne jamais :

- inventer un prix, une formule, un droit, une permission ou une preuve ;
- déclarer réussi un test qui a échoué ;
- déclarer implémentée une capacité qui n’est que documentée ;
- masquer une dépendance ou une régression ;
- placer un secret dans Git ;
- réécrire l’historique de `main` ;
- créer une Constitution, une loi, un acte d’adoption ou un registre normatif pour piloter un chantier ;
- supprimer `genesis-ii/` tant que des modules ou tests en dépendent encore ;
- supprimer des tests pour obtenir artificiellement un état vert.

## 4. Branches et intégration

- travailler sur une branche dédiée ;
- garder les commits cohérents et vérifiables ;
- ne pas fusionner dans `main` sans instruction explicite du dirigeant ;
- ne pas déployer ni détruire des données réelles sans instruction explicite couvrant cette action ;
- séparer les refontes documentaires des migrations runtime lorsqu’elles présentent des risques différents.

## 5. Tests et état réel

Avant de présenter un chantier comme terminé :

- exécuter les tests propres aux modules modifiés ;
- exécuter les tests d’intégration affectés ;
- vérifier les migrations et les dépendances de configuration ;
- signaler les tests non exécutés et la raison ;
- classer honnêtement le résultat : `IMPLÉMENTÉ`, `PARTIEL`, `DÉMONSTRATIF`, `ABSENT`, `HÉRITÉ À MIGRER` ou `CONTRADICTOIRE`.

La documentation ne constitue pas une preuve d’exécution. Les tests ne constituent pas non plus une preuve de déploiement.

## 6. Frontière Core / satellites

Le Core possède les responsabilités communes : identité, authentification, autorisation, fédération, contrats transversaux, événements, audit, continuité et Matching partagé.

Chaque satellite conserve notamment :

- son compte produit local ;
- ses données métier détaillées ;
- ses transactions ;
- ses abonnements et quotas ;
- ses interfaces ;
- ses règles économiques et opérationnelles.

Le Core peut consommer des signaux autorisés et limités pour une finalité explicite. Il ne doit pas devenir une base universelle contenant tous les dossiers métier des satellites.

## 7. Matching

Le Matching produit des correspondances contextualisées entre personnes, organisations, besoins, offres, institutions et signaux autorisés.

Il ne doit pas produire une réputation humaine universelle. Il ne facture pas, ne rémunère pas et n’exécute pas les règles économiques propres à Wasplex ou à un autre satellite.

## 8. Transition hors Genesis II

La migration suit trois étapes distinctes :

1. installer une documentation active simple sans toucher au runtime ;
2. remplacer les lectures de `genesis-ii/` par des données et contrats techniques explicites, module par module ;
3. supprimer l’ancien corpus seulement lorsque la recherche des dépendances runtime ne retourne plus aucun consommateur utile.

Pendant la deuxième étape, toute modification doit conserver ou améliorer les tests existants. Aucun parseur historique ne doit être retiré avant que son remplaçant soit fonctionnel et testé.

## 9. Compte rendu attendu

À la fin d’un chantier, indiquer clairement :

- les fichiers modifiés ;
- les fonctions réellement obtenues ;
- les tests exécutés et leurs sorties ;
- les limites restantes ;
- les décisions produit encore nécessaires ;
- les actions non réalisées.