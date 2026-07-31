# Fiches de capacités

Une capacité est une aptitude durable du Core, réutilisable par plusieurs produits.

Elle n’est ni un fichier Markdown, ni nécessairement un microservice. Une capacité peut être implémentée par plusieurs modules et magasins, mais elle doit présenter une responsabilité cohérente.

## Contenu d’une fiche

Chaque fiche créée pendant la migration doit contenir :

```text
Référence :
Nom :
Objectif :
Problème résolu :
Produits consommateurs :
Responsabilité du Core :
Ce qui reste dans les satellites :
Données possédées :
Données exclues :
Commandes :
Requêtes :
Événements :
Dépendances :
Autorisations :
Comportement en panne :
Sauvegarde et restauration :
Code actuel :
Tests actuels :
État réel :
Manques :
Prochain chantier :
```

## Statuts

- `ABSENT`
- `DÉMONSTRATIF`
- `PARTIEL`
- `IMPLÉMENTÉ`
- `EXPLOITÉ`
- `HÉRITÉ À MIGRER`
- `CONTRADICTOIRE`
- `À VÉRIFIER`

Un statut doit être fondé sur l’inspection du code et des tests. Il ne doit jamais être repris automatiquement d’un ancien acte Genesis II.

## Catalogue initial

Le catalogue de travail se trouve dans [`CATALOGUE.md`](CATALOGUE.md).

Les fiches individuelles seront ajoutées ou complétées au début de la migration technique de chaque capacité. Cette méthode évite de produire vingt-deux documents détaillés à partir d’informations non vérifiées.