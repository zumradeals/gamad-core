# Registre des normes — service `CTR-04` (`CAP-CORE-007`)

Premier module de code canonique de GAMAD Core, ouvert après le constat de `G0`
(`ADOPTION-0025`) sur la conception adoptée `ADOPTION-0026`, la pile adoptée
`ADOPTION-0027` et la conception d'implémentation adoptée `ADOPTION-0028`.

Ce module expose le contrat `CTR-04` **en lecture et attestation seulement** :
il ne modifie jamais le corpus. Les fichiers Git restent la source de vérité ;
la base n'est qu'un **index dérivé**, reconstructible à volonté.

## Ce qu'il fait

- **Ingestion dérivée** — lit les actes d'adoption, l'index et les fichiers
  canoniques du dépôt, et en construit un index relationnel. Sens unique
  (fichiers → base), empreintes recalculées et non recopiées (`INV-1`),
  idempotente (`INV-5`).
- **`resoudre_norme`** — résout une norme et son statut, y compris à une date
  passée (reconstruction temporelle, `INV-3`/`INV-6`).
- **`verifier_integrite`** — recalcule l'empreinte réelle de chaque fichier et
  la compare à l'empreinte déclarée par l'acte le plus récent.
- **`resoudre_index`** — reconstruit l'ensemble des actes à partir des fichiers
  primaires et le compare à l'index dérivé.
- **Tableau de bord web** — vue en lecture seule (`public/index.php`).

## Invariants portés (voir la conception d'implémentation, Titre II)

`INV-1` empreinte exacte · `INV-3` historique en ajout seul · `INV-4` adoption
distincte de la publication · `INV-5` index dérivé, jamais autoritatif ·
`INV-6` supersession traçable. L'ajout seul est tenu par le code (aucun
`UPDATE`/`DELETE` sur `statut`, `adoption`, `relation_evolution`) et, en
déploiement, durci par les privilèges PostgreSQL du rôle applicatif.

## Exécuter en local

Aucune dépendance, aucun secret. PHP 8.2+ avec `pdo_sqlite` suffit.

```bash
# Preuve P3 de reconstruction temporelle (doit sortir 0)
php core/registre-normes/tests/temporel_p3.php

# Tableau de bord (puis ouvrir http://127.0.0.1:8080)
php -S 127.0.0.1:8080 -t core/registre-normes/public
```

L'index SQLite se construit tout seul au premier accès, depuis le corpus.

## Déployer sur Railway — premier regard (SQLite, zéro secret)

Le plus simple pour **voir** le Core : aucune base à provisionner, l'index se
reconstruit à chaque démarrage depuis les fichiers du dépôt.

1. Railway → **New Project** → **Deploy from GitHub repo** → `zumradeals/gamad-core`.
2. Ne **pas** régler de « Root Directory » : le service a besoin du corpus
   `genesis-ii/` présent à la racine.
3. Le fichier `nixpacks.toml` à la racine fixe PHP et la commande de démarrage.
   Si l'autodétection diffère, régler à la main la **Start Command** :
   `php -S 0.0.0.0:$PORT -t core/registre-normes/public`.
4. Déployer → ouvrir l'URL publique `…up.railway.app` → le tableau de bord.

> Extension PostgreSQL : inutile pour ce premier regard (SQLite). Si l'image PHP
> de Railway manque `pdo_sqlite`, l'indiquer et la configuration sera ajustée.

## Étape suivante — persistance PostgreSQL

Pour un index persistant : ajouter le plugin **PostgreSQL** (Railway fournit
`DATABASE_URL`), le service le détecte automatiquement. **`DATABASE_URL` est un
secret** : le consigner au registre autonome des accès et secrets de l'autorité
(`ADOPTION-0025`, Art. 3.a), jamais dans le dépôt.

## Ce que ce module n'est pas

Il ne rend aucune capacité opérationnelle au sens fort, n'admet aucun produit,
n'accepte aucun risque et ne constate pas `G0`. C'est un miroir consultable et
vérifiable du Core, en lecture seule.
