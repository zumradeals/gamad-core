# Journal opérationnel

Ce module technique conserve les événements produits par l'exploitation du
monolithe Laravel. Il ne remplace ni le corpus, ni l'index documentaire, ni
`CAP-CORE-014` (`core/journal-evenements`) — le registre commun des
événements communs entre produits, physiquement distinct de ce journal
d'audit privé. Voir `core/journal-evenements/README.md`.

Sa portée est volontairement étroite :

- ajout seul, renforcé par des triggers PostgreSQL/SQLite;
- chaîne d'empreintes SHA-256;
- expurgation des champs portant secrets, cookies, sessions ou jetons;
- corrélation entre authentification, mandat, autorisation et effet;
- vérification complète par `php artisan core:journal:verifier`.

En production :

```text
JOURNAL_OPERATIONNEL_URL=postgresql://…
```

En local et en CI seulement :

```text
JOURNAL_OPERATIONNEL_PATH=/chemin/journal.sqlite
```

L'empreinte chaînée prouve la cohérence interne et l'ordre observé. Elle n'est
pas une signature d'origine; les réponses API portent explicitement
`signee: false`.

Garde :

```text
php core/journal-operationnel/tests/fondation_operationnelle_p3.php
```
