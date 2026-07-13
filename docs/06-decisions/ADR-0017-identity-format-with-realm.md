# ADR-0017 — Format d'identité avec realm

**Statut :** Accepté
**Date :** 2026-07-13
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD
**Référence :** GENESIS-008 — Fédération des Cores souverains de GAMAD

---

## Contexte

GENESIS-008 établit que l'écosystème GAMAD repose sur plusieurs Cores souverains (realms), chacun opéré par une structure porteuse distincte, sans hiérarchie ni fusion de données entre eux.

Le format d'identité actuel de l'Identity Registry est :

```
GAM-{TYPE}-{NUMERO}
```

Exemple : `GAM-PER-000001`, validé par le motif `^GAM-[A-Z]{3}-[0-9]{6,}$`.

Ce format ne porte aucune information sur le realm d'origine. Dès qu'un second realm existe, deux Cores indépendants peuvent légitimement émettre `GAM-PER-000001` pour deux personnes different, sans aucun moyen de les distinguer. C'est une collision d'identité, pas un détail cosmétique — elle viole directement l'invariant GENESIS-003 §9.1 (« toute entité reconnue possède une identité persistante ») dès qu'elle est lue hors du contexte d'un seul realm.

Le realm actuellement en service (Abidjan, staging) a déjà émis des identités sous l'ancien format avant que cette décision ne soit prise. Cette réalité doit être traitée, pas ignorée.

---

## Décision

### 1. Nouveau format d'identité

```
GAM-{REALM}-{TYPE}-{NUMERO}
```

Exemple : `GAM-<REALM>-PER-000001`.

Motif de validation :

```
^GAM-[A-Z0-9]{2,6}-[A-Z]{3}-[0-9]{6,}$
```

### 2. Règles du code de realm

- 2 à 6 caractères alphanumériques majuscules.
- Attribué une seule fois, de façon définitive, à la création du realm — conformément à GENESIS-008 §5.1.
- Choisi par la structure porteuse et validé par l'autorité de gouvernance compétente, jamais généré automatiquement ou improvisé au moment du déploiement technique.
- Le code de realm identifie la structure porteuse, jamais un pays ou une zone géographique, conformément à GENESIS-008 §2.2.
- **Le code de realm de l'instance actuellement en service reste à attribuer formellement par l'Orchestrateur avant toute exécution de la Tâche 2 ci-dessous.** Cette Directive ne préjuge d'aucune valeur.

### 3. Implémentation

- La validation du realm suit le même patron que `AllowConfiguredIdentityTypesPolicy` déjà en place pour les types d'identité : une politique dédiée (`AllowConfiguredRealmPolicy` ou équivalent) vérifie, à la création d'une identité, que le realm utilisé correspond au realm configuré pour cette instance du Core. Un Core ne peut jamais émettre une identité sous un realm qui n'est pas le sien.
- Le realm de l'instance est une constante de configuration au démarrage (`GAMAD_CORE_REALM`), au même niveau que les autres variables d'environnement requises, jamais une valeur fournie par l'appelant de l'API.
- Le contrat OpenAPI (`openapi/identity-registry-v1.yaml`) est mis à jour : le motif du champ `identity_id` change, et un champ `realm` explicite est ajouté au schéma `Identity` pour que toute lecture d'une identité expose sans ambiguïté son Core d'origine.

### 4. Migration des identités existantes

- Les identités déjà émises par l'instance en service sont traitées comme relevant d'une **génération pré-fédération**.
- Une migration administrative unique réécrit leur identifiant en y insérant le code de realm une fois celui-ci attribué (point 2).
- Cette migration est elle-même un événement auditable : elle produit une entrée dans la chaîne d'audit documentant explicitement la réécriture, avec référence à cette ADR. Aucune modification silencieuse de données historiques n'est admise, conformément à GENESIS-003 §13.
- Cette migration constitue une tâche dédiée, à dicter séparément à Claude Code une fois le code de realm attribué — elle n'est pas couverte par cette ADR seule.

---

## Conséquences positives

- Élimine par construction toute collision d'identité entre realms, avant qu'un second realm n'existe réellement.
- Rend le Core d'origine d'une identité lisible directement dans son identifiant, sans requête supplémentaire.
- S'aligne sur un patron d'implémentation déjà éprouvé dans le code existant (politique de validation configurée), donc aucune nouvelle famille de mécanisme n'est introduite.
- Prépare, sans l'anticiper prématurément, le contrat de fédération de GENESIS-008 §5.2, qui aura besoin de cette distinction pour fonctionner.

---

## Contraintes

- Cette ADR introduit un changement de contrat public (`identity_id` pattern) sur une API déjà en service, même en staging. Toute automatisation externe qui aurait déjà pris une dépendance sur l'ancien format doit être révisée — à ce stade, seule la console d'exploitation (DIRECTIVE-002) est concernée.
- Le code de realm doit être attribué avant la Tâche 2 de mise en œuvre — aucune implémentation ne doit choisir une valeur par défaut arbitraire.
- La migration des identités existantes doit être testée sur une copie de la base avant exécution sur l'instance réelle.

---

## Options rejetées

### Code pays comme realm

Rejeté par GENESIS-008 §2.2 — un pays n'est pas un opérateur technique et GAMAD n'a reçu d'aucune nation l'autorité de représenter son identité numérique en son nom.

### UUID opaque sans structure lisible

Rejeté : un identifiant purement opaque perdrait la lisibilité humaine déjà obtenue avec le format actuel (`GAM-PER-000001` reste lisible et communicable), sans apporter de bénéfice que le realm structuré n'apporte déjà.

### Realm déterminé dynamiquement par l'appelant de l'API

Rejeté : permettrait à un Core d'émettre des identités sous un realm qui n'est pas le sien, en violation directe de GENESIS-008 §3 invariant 1.

---

## Test de conformité

Une modification est conforme si :

- elle ne permet jamais à un Core d'émettre une identité sous un realm différent du sien ;
- elle ne modifie jamais rétroactivement une identité sans produire une entrée d'audit explicite ;
- elle ne déduit jamais le realm d'une information géographique (adresse IP, locale, pays déclaré) — le realm est une configuration explicite du Core, jamais une inférence.

---

## Formule canonique

> Une identité porte toujours son Core d'origine dans son identifiant. Le realm se déclare, il ne se devine jamais.
