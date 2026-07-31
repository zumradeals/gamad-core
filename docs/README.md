# Documentation active de GAMAD Core

Cette documentation décrit directement le produit, ses frontières et les capacités à construire. Elle remplace progressivement la lecture des anciens textes Genesis II pour les nouveaux chantiers.

Elle ne prouve pas qu’une fonction est implémentée. L’état réel doit toujours être vérifié dans le code, les migrations, les tests et l’exploitation.

## Ordre de lecture

1. [`00-vision-ecosysteme-gamad.md`](00-vision-ecosysteme-gamad.md)
2. [`01-architecture-core-portail-satellites.md`](01-architecture-core-portail-satellites.md)
3. [`02-compte-gamad-et-federation.md`](02-compte-gamad-et-federation.md)
4. [`03-matching-transversal.md`](03-matching-transversal.md)
5. [`04-donnees-securite-et-finalites.md`](04-donnees-securite-et-finalites.md)
6. [`05-exploitation-continuite-et-preuves.md`](05-exploitation-continuite-et-preuves.md)
7. [`06-transition-hors-genesis-ii.md`](06-transition-hors-genesis-ii.md)
8. [`capacites/README.md`](capacites/README.md)
9. [`capacites/CATALOGUE.md`](capacites/CATALOGUE.md)

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