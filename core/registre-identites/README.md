# CAP-CORE-001 — Identity Registry

Le module sert deux régimes de vérité sans les mélanger :

- `entite`, `etat_entite` et `denomination` sont dérivées du corpus par
  `RegistreNormes\Ingestion` et peuvent être reconstruites ;
- les tables créées par `SchemaInscription::migrer()` constituent le registre
  persistant et ne figurent jamais dans la liste de suppression de l'index.

En exploitation, le registre persistant utilise une connexion dédiée :

```text
IDENTITY_REGISTRY_URL=postgresql://…
```

SQLite local est disponible avec `IDENTITY_REGISTRY_PATH`. Cette valeur ne
doit jamais désigner le fichier `SQLITE_PATH` de l'index reconstructible.

`Ctr01` accepte les deux connexions :

```php
$ctr01 = new Ctr01($indexDerive, Magasin::connecter());
```

Le second argument reste optionnel pour la compatibilité et les tests de
migration. Même dans ce mode, une réingestion ne supprime pas les tables
persistantes.

Les commandes d'inscription et de rattachement exigent une politique, un
producteur responsable, une source et une preuve. Elles ne sont pas exposées
comme CRUD HTTP. Le contrôleur Laravel ne publie que des lectures sous session ;
la lecture de l'assurance est en outre limitée à l'identité concernée ou à
l'autorité canonique.

Garde :

```bash
php core/registre-identites/tests/identite_p3.php
```
