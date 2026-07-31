# Documentation active de GAMAD Core

Cette documentation décrit directement le produit, ses frontières et les capacités à construire. Elle a remplacé l’ancien corpus normatif, supprimé du dépôt.

Elle ne prouve pas qu’une fonction est implémentée. L’état réel doit toujours être vérifié dans le code, les migrations, les tests et l’exploitation.

## Ordre de lecture

1. [`00-vision-ecosysteme-gamad.md`](00-vision-ecosysteme-gamad.md)
2. [`01-architecture-core-portail-satellites.md`](01-architecture-core-portail-satellites.md)
3. [`02-compte-gamad-et-federation.md`](02-compte-gamad-et-federation.md)
4. [`03-matching-transversal.md`](03-matching-transversal.md)
5. [`04-donnees-securite-et-finalites.md`](04-donnees-securite-et-finalites.md)
6. [`05-exploitation-continuite-et-preuves.md`](05-exploitation-continuite-et-preuves.md)
7. [`06-transition-hors-genesis-ii.md`](06-transition-hors-genesis-ii.md)
8. [`07-consignes-operationnelles-agents.md`](07-consignes-operationnelles-agents.md)
9. [`capacites/README.md`](capacites/README.md)
10. [`capacites/CATALOGUE.md`](capacites/CATALOGUE.md)
11. [`capacites/CAP-CORE-001-identity-registry.md`](capacites/CAP-CORE-001-identity-registry.md)

## `CLAUDE.md`

Le fichier racine `CLAUDE.md` porte les consignes opérationnelles appliquées dans le dépôt. Il reprend `07-consignes-operationnelles-agents.md` et évolue librement : aucun contrôle d’empreinte documentaire ne le fige plus.

## Règle de mise à jour

Un document doit répondre à une question opérationnelle claire :

- ce que le Core doit faire ;
- ce que le Core ne doit pas faire ;
- les données qu’il possède ;
- les contrats qu’il expose ;
- les produits qui le consomment ;
- l’état réel du code ;
- les manques et le prochain chantier.

Ne pas ajouter de loi, d’acte d’adoption, de décision normative ou de statut déclaratif pour remplacer une implémentation manquante.
