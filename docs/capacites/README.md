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

- `GO`
- `NO GO`

`GO` signifie que le comportement nécessaire à la production est codé,
éprouvé et raccordé à l’exploitation. `NO GO` couvre tout le reste, y compris
les anciens statuts nuancés (`ABSENT`, `DÉMONSTRATIF`, `PARTIEL`,
`IMPLÉMENTÉ`, `EXPLOITÉ`, `CONTRADICTOIRE`, `À VÉRIFIER`), retirés du
vocabulaire de ce catalogue. Un statut doit être fondé sur l’inspection du
code, des tests et de la CI. Il ne doit jamais être déduit d’un document.

## Catalogue initial

Le catalogue de travail se trouve dans [`CATALOGUE.md`](CATALOGUE.md).

Les fiches individuelles sont ajoutées ou complétées au début du chantier technique de chaque capacité. Cette méthode évite de produire vingt-deux documents détaillés à partir d’informations non vérifiées.

Fiches disponibles :

- [`CAP-CORE-001-identity-registry.md`](CAP-CORE-001-identity-registry.md)
- [`CAP-CORE-006-sources-registry.md`](CAP-CORE-006-sources-registry.md)
- [`CAP-CORE-011-products-registry.md`](CAP-CORE-011-products-registry.md)
- [`CAP-CORE-022-satellite-federation.md`](CAP-CORE-022-satellite-federation.md)