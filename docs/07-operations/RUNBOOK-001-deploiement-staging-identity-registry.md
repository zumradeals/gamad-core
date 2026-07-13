# RUNBOOK-001 — Déploiement staging de l'Identity Registry

**Statut :** Opérationnel
**Périmètre :** Identity Registry + noyau `Shared` (Outbox, Audit, Admin Runtime), environnement de validation interne (staging), hors production.
**Audience :** opérateur n'ayant jamais lu le code source du dépôt `gamad-core`.
**Référence :** DIRECTIVE-001, ADR-0011 à ADR-0014.

---

## 0. Ce que vous allez déployer

Trois composants applicatifs, plus une base PostgreSQL :

| Composant | Rôle | Commande de démarrage |
|---|---|---|
| `admin-api` | Sert l'API HTTP (Identity Registry + Admin Runtime) | `php -S 0.0.0.0:8080 -t public public/index.php` |
| `outbox-worker` | Publie les événements de domaine en attente | `php bin/outbox-worker` |
| Healthcheck externe | Sonde `GET /admin/runtime/health` | à configurer sur votre supervision (voir §7) |

Ce déploiement **ne** connecte **aucun** produit métier (Drive, ERP, OPS...) en écriture réelle. Voir §9.

---

## 1. Pré-requis

Avant de commencer, vérifiez que vous disposez de :

- **PHP 8.4 CLI** avec les extensions `pdo_pgsql`, `pcntl`, `openssl`.
- **PostgreSQL 17**, accessible depuis l'environnement où tourneront `admin-api` et `outbox-worker`.
- Un **fournisseur OIDC** réel pour l'authentification de l'API d'administration — ou, en staging pur uniquement, le test-IdP fourni dans `tools/test-idp/` (**jamais en production**).
- **Docker + Docker Compose**, si vous déployez en conteneurs (recommandé — un `Dockerfile` est déjà prêt à la racine du dépôt).
- Les valeurs suivantes, fournies par l'Orchestrateur pour l'environnement cible :
  - DSN PostgreSQL de staging (`GAMAD_PG_DSN`), utilisateur et mot de passe.
  - Issuer, audience et JWKS URI du fournisseur OIDC de staging (`GAMAD_OIDC_ISSUER`, `GAMAD_OIDC_AUDIENCE`, `GAMAD_OIDC_JWKS_URI`).

> Ces valeurs ne sont pas fixées dans ce runbook : elles dépendent de l'infrastructure de staging retenue par l'Orchestrateur et doivent être injectées à l'étape 3.

---

## 2. Provisionner la base de données

Les migrations SQL se trouvent dans `database/migrations/`, numérotées `001_...sql` à `010_...sql`. Elles doivent être appliquées **dans l'ordre** sur la base PostgreSQL cible.

- **En développement local**, `docker-compose.yml` applique automatiquement toutes les migrations au premier démarrage du conteneur `postgres`, via `docker-entrypoint-initdb.d`. Rien à faire manuellement dans ce cas.
- **Sur une base de staging déjà existante**, appliquez chaque fichier dans l'ordre numérique, par exemple :

  ```sh
  for migration in database/migrations/*.sql; do
    psql "$GAMAD_PG_DSN_PSQL" -v ON_ERROR_STOP=1 -f "$migration"
  done
  ```

  où `$GAMAD_PG_DSN_PSQL` est la chaîne de connexion `psql` (format `postgresql://user:password@host:port/dbname`) équivalente au DSN PDO fourni par l'Orchestrateur.

---

## 3. Construire l'image

```sh
docker build -t gamad-core:staging .
```

---

## 4. Configurer l'environnement

1. Dupliquer le fichier `.env.example` (racine du dépôt) en `.env.staging` :

   ```sh
   cp .env.example .env.staging
   ```

2. Renseigner dans `.env.staging` :
   - `GAMAD_PG_DSN`, `GAMAD_PG_USER`, `GAMAD_PG_PASSWORD` — instance PostgreSQL cible (fournies par l'Orchestrateur, §1).
   - `GAMAD_OIDC_ISSUER`, `GAMAD_OIDC_AUDIENCE`, `GAMAD_OIDC_JWKS_URI` — fournisseur OIDC de staging (fournies par l'Orchestrateur, §1). Les trois doivent être renseignées ensemble ; si elles restent vides, l'API bascule sur le mécanisme de bootstrap statique `GAMAD_ADMIN_TOKENS_JSON` (ADR-0011 — provisoire, usage interne uniquement).
   - `GAMAD_HEALTHCHECK_TOKEN` — un secret **dédié** au healthcheck externe, distinct de tout token d'usage administratif ou métier. Il doit correspondre à une entrée de `GAMAD_ADMIN_TOKENS_JSON` (ou à un token OIDC) portant le scope `core.runtime.health.read`.
   - `GAMAD_ADMIN_RATE_LIMIT` / `GAMAD_ADMIN_RATE_WINDOW_SECONDS` — valeurs par défaut : 120 requêtes / 60 s. Ajuster selon le volume attendu.

3. Ne jamais committer `.env.staging` — il contient des secrets réels.

---

## 5. Lancer les trois composants

Manifeste de référence durci (accès en lecture seule, `cap_drop: ALL`, `no-new-privileges`) : `deploy/admin-api.production.yml`. Adaptez-le pour staging ou utilisez-le tel quel en pointant vers les variables de `.env.staging`.

1. **`admin-api`** — sert `public/index.php`, expose les routes Identity Registry et Admin Runtime sur le port `8080`.
2. **`outbox-worker`** — exécute `bin/outbox-worker` en tâche de fond continue ; publie les événements de domaine en attente.
3. **Healthcheck externe** — configurez votre supervision (uptime monitor, load balancer, etc.) pour interroger périodiquement :

   ```
   GET /admin/runtime/health
   Authorization: Bearer <GAMAD_HEALTHCHECK_TOKEN>
   ```

   Une réponse `200` indique un service sain.

---

## 6. Valider le déploiement — smoke test

Exécutez :

```sh
sh tools/security-smoke.sh
```

- En première validation, il peut tourner tel quel contre le test-IdP local fourni (`tools/test-idp/`).
- En staging réel, adaptez les variables d'environnement du script à votre fournisseur OIDC cible.

Ce script vérifie automatiquement :
- l'émission d'un token ;
- l'accès autorisé avec token valide (`200`) ;
- le refus d'accès sans token (`401`) ;
- la rotation des clés JWKS ;
- le déclenchement effectif de la limite de débit (`429`).

Le script se termine en erreur (`set -eu`) si l'une de ces vérifications échoue — aucune interprétation manuelle n'est nécessaire.

---

## 7. Valider la chaîne d'audit

```sh
composer audit:verify
```

(équivalent direct : `php bin/audit-verify`)

Sur une base neuve, la commande doit retourner une chaîne d'audit intègre (`valid: true`). Si `valid: false`, ne pas poursuivre — signaler l'incident à l'Architecte avant toute mise à disposition.

---

## 8. Valider la santé des workers

```sh
composer health:summary
composer worker:health
```

Ces deux commandes doivent s'exécuter sans erreur et rapporter un état sain pour `outbox-worker`. Si `worker:health` rapporte un worker « stale », vérifiez que le composant `outbox-worker` de l'étape 5 tourne bien et que son heartbeat atteint PostgreSQL.

---

## 9. Test fonctionnel minimal

1. **Enregistrer une identité** via l'API. Le contrat exact est défini dans `openapi/identity-registry-v1.yaml`. Exemple :

   ```sh
   curl -sS -X POST "http://<host-staging>:8080/identities" \
     -H "Authorization: Bearer <token avec le scope core.identity.register>" \
     -H "Content-Type: application/json" \
     -H "Idempotency-Key: staging-smoke-$(date +%s)" \
     -d '{"identity_type":"service"}'
   ```

   Réponse attendue : `201`, avec un corps JSON contenant `identity_id` (format `GAM-<TYPE>-<numéro>`), `identity_type`, `status` (`draft` à l'enregistrement) et `registered_at`.

2. **Vérifier qu'elle apparaît dans une recherche** :

   ```sh
   curl -sS "http://<host-staging>:8080/identities?type=service" \
     -H "Authorization: Bearer <token avec le scope core.identity.read>"
   ```

   L'identité créée à l'étape précédente doit figurer dans `items`.

3. **Vérifier qu'un événement `IdentityRegistered` a été publié** :

   ```sh
   composer outbox:dashboard
   ```

   (équivalent direct : `php bin/outbox-dashboard`) — l'événement correspondant à l'identité créée doit apparaître comme publié.

---

## Ce que ce déploiement permet

- Enregistrer, rechercher et faire transiter l'état d'identités réelles.
- Observer le comportement de l'Outbox, de l'audit et du rate limiting en conditions réelles.
- Servir de cible d'intégration à la sous-phase suivante (Persons and User Accounts), sans exposer quoi que ce soit à un produit métier ou à un utilisateur final.

## Ce que ce déploiement ne permet pas encore

- Créer une organisation, associer une personne, vérifier une permission métier, révoquer un accès — le client contractuel de référence de la Phase 3 du Masterplan reste hors de portée tant que les sous-phases 2 à 13 ne sont pas livrées.
- Aucun produit (Drive, ERP, OPS...) ne doit pointer vers cet environnement en écriture réelle.

## En cas d'échec à une étape

| Étape en échec | Action |
|---|---|
| §2 Migrations | Ne pas poursuivre. Vérifier que les 10 fichiers ont été appliqués dans l'ordre numérique et qu'aucun n'a échoué silencieusement. |
| §6 Smoke test | Ne pas ouvrir l'accès à des utilisateurs. Consulter la sortie du script — chaque vérification (token, 401, 429, rotation JWKS) est nommée explicitement dans les logs. |
| §7 Audit | Ne pas poursuivre. Une chaîne d'audit invalide (`valid: false`) doit être signalée à l'Architecte avant toute autre action. |
| §8 Santé des workers | Vérifier que `outbox-worker` tourne effectivement (§5) et que sa connexion PostgreSQL est active. |
