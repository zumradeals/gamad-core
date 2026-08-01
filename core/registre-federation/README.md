# core/registre-federation — CAP-CORE-022

Fédération des satellites : ouvrir une identité GAMAD authentifiée sur un
produit, et remettre au satellite une preuve d’accès bornée.

## Ce que le module fait

```text
identité authentifiée (CAP-CORE-005)
→ décision (CAP-CORE-004) et preuve (CAP-CORE-013), dans la couche applicative
→ lien produit idempotent (CAP-CORE-001)
→ jeton fédéré borné à un satellite
→ vérification à usage unique par ce satellite
→ révocation, déconnexion locale ou globale
```

## Ce qu’il ne fait pas

Il ne crée aucun compte métier, ne connaît ni plan, ni quota, ni abonnement, ni
contenu. Il n’écrit aucune règle d’autorisation : la politique technique
`POL-FEDERATION-SATELLITES-V1` est portée par l’index et évaluée par
CAP-CORE-004.

## Les quatre bornes d’un jeton

| Borne | Valeur |
|---|---|
| Audience | un seul produit ; un jeton GamaDrive est inutilisable par Wasplex |
| Durée | 30 à 300 secondes, 120 par défaut, jamais reconductible |
| Portées | liste close, aujourd’hui `ouverture_session_locale` seule |
| Rattachement | session Core émettrice et niveau d’assurance de cette session |

Un jeton est à usage unique. Aucune valeur de jeton n’est conservée : seule son
empreinte SHA-256 l’est.

## Magasin

Les jetons vivent dans le magasin d’exploitation de CAP-CORE-005
(`MAGASIN_URL` / `MAGASIN_PATH`). Ce choix est délibéré : la validité d’un jeton
dépend en permanence de la session Core qui l’a produit, et la jointure locale
sur `session_ouverte` est ce qui rend la déconnexion globale réellement
opposable.

Migration : `php artisan core:fondation:migrer`. La readiness vérifie la
présence de `jeton_federe`.

## Épreuves

```bash
php core/registre-federation/tests/federation_p3.php
php apps/console-laravel/tests/Integration/federation_v1_p1.php
```

La fiche de capacité, avec l’état réel et les manques, est dans
[`docs/capacites/CAP-CORE-022-satellite-federation.md`](../../docs/capacites/CAP-CORE-022-satellite-federation.md).
