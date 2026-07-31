# CAP-CORE-016 — Secrets et clés

**État :** `IMPLÉMENTÉ PARTIEL`

## Objectif

Gérer les références, rotations, usages et incidents relatifs aux secrets et clés sans les publier.

## Code à inspecter

`core/registre-secrets`

## Hors périmètre

La valeur brute des secrets dans Git, les journaux ou les réponses d’API.

## Prochain résultat

Vérifier rotation, révocation, références externes et séparation des environnements.

Le statut ne change qu’après preuve par le code, les tests, les contrats consommés et, lorsque l’exploitation est revendiquée, les observations et restaurations réelles.
