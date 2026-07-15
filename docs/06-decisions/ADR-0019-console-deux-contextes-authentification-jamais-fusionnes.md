# ADR-0019 — La console porte deux contextes d'authentification, jamais fusionnés

**Statut :** Accepté
**Date :** 2026-07-15
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD
**Référence :** ADR-0011, ADR-0016, ADR-0018

---

## Contexte

La console doit désormais parler à deux familles de routes protégées différemment : les routes admin/runtime (token bootstrap, ADR-0011) et les routes Persons (session personne, ADR-0018). Rien ne doit laisser penser que l'un donne accès à l'autre — ADR-0011 et ADR-0018 sont deux mécanismes volontairement séparés, et la console ne doit pas devenir l'endroit où cette séparation s'efface par commodité d'interface.

---

## Décision

1. La session PHP de la console conserve **deux emplacements de créance indépendants** : `admin_token` et `person_session_token`. Aucune fonction ne doit jamais les lire l'un à la place de l'autre.
2. `CoreApiClient` expose deux méthodes distinctes (`callAsAdmin(...)` et `callAsPerson(...)`), jamais une méthode générique qui choisirait implicitement laquelle utiliser.
3. Deux écrans de connexion distincts et visuellement différenciés : « Connexion admin » (token collé, existant) et « Connexion opérateur » (email + mot de passe, nouveau). Se connecter sur l'un ne connecte jamais automatiquement l'autre.
4. Un opérateur peut être connecté aux deux en même temps, aux deux, à un seul, ou à aucun — chaque écran de la console déclare explicitement duquel il a besoin, et redirige vers l'écran de connexion correspondant si absent.
5. La déconnexion (« Déconnexion ») propose désormais un choix : déconnexion admin, déconnexion opérateur, ou les deux — jamais une déconnexion globale silencieuse qui surprendrait l'un des deux contextes.

---

## Conséquences

- Aucun raccourci n'efface la frontière entre gouvernance technique du Core (admin) et identité d'une personne réelle — cohérent avec GENESIS-010 §C (authentification ≠ autorisation, et ici : administration du Core ≠ identité personnelle).

---

## Note d'implémentation

Le formulaire « Connexion opérateur » authentifie via l'identifiant de la personne (`person_id`, format `GAM-{REALM}-PER-xxxxxx`) et le mot de passe, conformément au contrat `POST /auth/login` de `openapi/persons-and-accounts-v1.yaml` (`LoginRequest.person_id`). `PersonId` (voir `src/PersonsAndAccounts/Domain/PersonId.php`) valide strictement ce format et rejette toute autre valeur, y compris une adresse de contact — il n'existe aucune route de résolution par contact/email dans ce contrat, et cette Directive n'en crée pas. Le champ du formulaire est donc libellé « Identifiant personne », pas « email », pour ne jamais laisser croire à l'opérateur qu'une adresse de contact suffirait à se connecter.
