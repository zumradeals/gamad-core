# Registre des routes protégées par le bootstrap administratif (ADR-0011 / ADR-0012)

Ce registre recense toutes les routes protégées exclusivement par le mécanisme de **bootstrap administratif** (`ScopeAuthorizationMiddleware` + `EnvironmentAuthorizationService`, voir ADR-0011), en attendant la livraison du contexte Access Control (Masterplan Phase 2, sous-phase 6).

Chaque route d'écriture — et, par prudence, chaque route exposée — ajoutée avant la sous-phase 6 doit apparaître ici. Ce registre est un livrable obligatoire de toute sous-phase livrée avant Access Control (ADR-0012).

---

## Identity Registry (`src/IdentityRegistry/Http/IdentityRoutes.php`)

| Route | Scope requis | Contexte propriétaire |
|---|---|---|
| `POST /identities` | `core.identity.register` | Identity Registry |
| `POST /identities/bulk` | `core.identity.register` | Identity Registry |
| `GET /identities` | `core.identity.read` | Identity Registry |
| `GET /identities/{identityId}` | `core.identity.read` | Identity Registry |
| `POST /identities/{identityId}/{transition}` | `core.identity.lifecycle.manage` | Identity Registry |

## Administration (`src/Shared/Http/AdministrativeRoutes.php`)

| Route | Scope requis | Contexte propriétaire |
|---|---|---|
| `GET /admin/runtime/health` | `core.runtime.health.read` | Shared (Admin Runtime) |
| `GET /admin/runtime/outbox` | `core.outbox.dashboard.read` | Shared (Admin Runtime) |
| `GET /admin/runtime/dead-letters` | `core.outbox.dead_letter.read` | Shared (Admin Runtime) |
| `GET /admin/runtime/dead-letters/{messageId}` | `core.outbox.dead_letter.read` | Shared (Admin Runtime) |
| `POST /admin/runtime/dead-letters/{messageId}/replay` | `core.outbox.dead_letter.replay` | Shared (Admin Runtime) |

---

## Mise à jour de ce registre

Toute route ajoutée à l'Identity Registry, à `Shared`, ou à un futur bounded context (Organizations, Memberships) avant la livraison d'Access Control doit être ajoutée à ce tableau au moment de son introduction, dans la même Pull Request.
