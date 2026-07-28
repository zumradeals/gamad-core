# CONCEPTION-CAP-CORE-005-AUTHENTIFICATION-0001
## Projet de conception de la capacité souveraine `CAP-CORE-005` — Authentification et assurance communes

> **PROJET D'ACTE — soumis à l'autorité. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0039`) n'a pas été signé.**

## Nature et rattachement

Étape de conception de la séquence de l'Article 63, rédigée sous `MISSION-AI-GENESIS-II-CODE-0001`.

**Cette capacité rompt avec les quatre précédentes.** `CAP-CORE-007`, `006`, `003` et `001` sont des services de **lecture** : ils dérivent des faits que le corpus déclare et les restituent. `CAP-CORE-005` exige d'**écrire** — un authentificateur, une session — c'est-à-dire de produire un état que le corpus ne déclare pas et ne déclarera jamais.

Le Core n'a jamais rien écrit. `INV-4` l'interdit depuis `ADOPTION-0026`. La présente conception doit donc trancher une question doctrinale avant toute technique : **qu'est-ce qu'une écriture légitime ?**

---

# TITRE I — LA QUESTION DOCTRINALE : TROIS ESPACES D'ÉCRITURE

## Article 1 — Ce que `INV-4` interdit réellement

`INV-4` énonce que l'adoption est distincte de la publication, et que **les seules écritures du corpus passent par des actes signés**. Sa portée est le **corpus** — les fichiers versionnés qui font foi.

Il n'a jamais interdit toute écriture : l'index dérivé est écrit à chaque ingestion depuis `ADOPTION-0029`, sans que personne y voie une violation. Nul ne l'a relevé parce que cette écriture ne produit aucun fait : elle recopie des faits que le corpus porte déjà.

La distinction pertinente n'est donc pas *écrire ou ne pas écrire*, mais **où l'on écrit, et si ce qu'on y écrit prétend faire foi**.

## Article 2 — `INV-22` — Trois espaces, trois régimes d'écriture

Le Core comporte désormais trois espaces distincts, qu'aucune opération ne doit confondre :

| Espace | Contenu | Qui écrit | Perte tolérable ? |
|---|---|---|---|
| **Le corpus** — fichiers versionnés | les faits institutionnels | **actes signés seulement** (`INV-4`) | non : c'est la vérité même |
| **L'index dérivé** — base reconstructible | copie ordonnée du corpus | l'ingestion (`INV-5`) | oui : reconstructible à volonté |
| **Le magasin d'exploitation** — nouveau | authentificateurs, sessions | l'application | oui : sa perte ne détruit aucune vérité |

**Aucun fait du corpus ne réside dans le magasin d'exploitation, et rien de ce qu'il contient ne fait foi.** Il ne porte que des moyens techniques d'accès — révocables, remplaçables, sans valeur probante propre.

Cette séparation n'est pas une commodité : c'est ce qui permet d'écrire sans que `INV-4` soit entamé.

## Article 3 — La séparation est imposée par la technique autant que par la doctrine

`Schema::create()` détruit et reconstruit toutes les tables de l'index à chaque ingestion. Un identifiant qui y résiderait serait effacé à la première réindexation.

La doctrine et la technique commandent donc la même chose : **le magasin d'exploitation est un espace séparé, que l'ingestion ne touche jamais.**

---

# TITRE II — INVARIANTS

## Article 4 — `INV-23` — Un compte n'est pas une identité

La fiche de l'Article 40 nomme ce risque en premier : *« compte confondu avec identité »*.

`CAP-CORE-001` établit **qui existe**. `CAP-CORE-005` établit **par quel moyen quelqu'un prouve qu'il est cette entité**. Un authentificateur est *rattaché* à une identité ; il n'est pas elle.

Conséquences tenues par le modèle : une identité peut n'avoir aucun authentificateur ; elle peut en avoir plusieurs ; la révocation de tous ses authentificateurs **ne supprime pas l'identité** et n'altère aucun fait du corpus.

## Article 5 — `INV-24` — Jamais de secret, ni en clair ni au corpus

La fiche l'énonce : *« jamais les secrets en clair »*. La conception l'étend :

- le magasin ne conserve **aucun secret**, mais une **empreinte non réversible** ;
- **aucun secret ne figure jamais au corpus**, en clair ou sous quelque forme que ce soit (`ADOPTION-0025`, Art. 3.a) ;
- **aucun secret ne transite par l'agent.** Le premier identifiant est établi par l'autorité elle-même, au moyen d'une commande qui lit le secret en saisie protégée et n'en conserve que l'empreinte.

Ce dernier point n'est pas une précaution d'usage : c'est la limite 4 de `ADOPTION-0037`, que nulle instruction ne lève.

## Article 6 — `INV-25` — Une session expire et se révoque

Reprise des Articles 89 et 90 de `SECURITY-GOVERNANCE-0001`. Toute session porte une création, une durée, un niveau d'assurance et une capacité de révocation. **Aucune session ne devient permanente par commodité.**

Une session survivant à la révocation de l'authentificateur qui l'a ouverte est un défaut, non une facilité.

## Article 7 — `INV-26` — L'assurance se déclare, ne se présume pas

Le niveau d'assurance d'une session est celui des facteurs réellement présentés, jamais celui qu'on souhaiterait. Un facteur unique donne une assurance faible et le dit. Élever l'assurance exige de présenter davantage, non de le déclarer.

---

# TITRE III — DONNÉES DU MAGASIN D'EXPLOITATION

## Article 8 — Tables

```sql
-- Moyen par lequel une entité prouve son identité. JAMAIS le secret :
-- seulement son empreinte non réversible.
CREATE TABLE authentificateur (
    reference        text PRIMARY KEY,
    entite_reference text NOT NULL,      -- vers CAP-CORE-001, sans clé étrangère :
                                         -- l'index dérivé est reconstruit, pas ce magasin
    type             text NOT NULL,      -- 'mot_de_passe' | … (liste ouverte, facteurs à décider)
    empreinte        text NOT NULL,      -- hachage à sens unique (INV-24)
    niveau_assurance text NOT NULL,
    etat             text NOT NULL,      -- 'ACTIF' | 'RÉVOQUÉ' | 'SUSPENDU'
    cree_le          timestamp NOT NULL,
    revoque_le       timestamp
);

-- Session ouverte. Expire, se révoque, ne devient jamais permanente (INV-25).
CREATE TABLE session_ouverte (
    reference             text PRIMARY KEY,
    authentificateur_ref  text NOT NULL REFERENCES authentificateur(reference),
    entite_reference      text NOT NULL,
    niveau_assurance      text NOT NULL,
    ouverte_le            timestamp NOT NULL,
    expire_le             timestamp NOT NULL,   -- jamais NULL (INV-25)
    revoquee_le           timestamp
);
```

Aucune colonne de secret. Aucune colonne de profil, de dossier ou de jugement — l'exclusion de `INV-19` vaut ici aussi.

---

# TITRE IV — CONTRAT `CTR-05`

## Article 9 — Opérations

```
etablir_session(entite, secret)      → { session, assurance, expire_le } | échec
verifier_session(session, a_la_date) → { valide, entite, assurance, motif }
revoquer(session | authentificateur) → { revoque_le }
attester(entite)                     → { authentificateurs[], assurance_max, sessions_actives }
```

`etablir_session` est **la première écriture applicative du Core**. Elle n'écrit que dans le magasin d'exploitation, jamais dans le corpus ni dans l'index dérivé.

## Article 10 — Ce que l'authentification ne confère pas

Ouvrir une session établit **qui l'on est**, non **ce que l'on peut faire**. Les droits relèvent de `CAP-CORE-004`, `À ÉTABLIR`.

Tant que `CAP-CORE-004` n'existe pas, une session ouverte ne confère qu'un accès en **lecture** à la console — c'est-à-dire exactement ce que la console offre déjà publiquement aujourd'hui. La capacité **ferme une porte** ; elle n'en ouvre aucune.

---

# TITRE V — MENACES

## Article 11 — Menaces retenues

| Réf. | Menace (fiche, Art. 40) | Couverture |
|---|---|---|
| `M-20` | Compte confondu avec identité | `INV-23` |
| `M-21` | Session persistante après révocation | `INV-25` ; `verifier_session` contrôle l'authentificateur, pas seulement la session |
| `M-22` | Récupération faible | **non couverte** — voir Article 12 |
| `M-23` | Compromission de facteur | révocation ; rotation |
| `M-24` | Dépendance fournisseur | aucun fournisseur externe n'est introduit |
| `M-25` | Secret exposé | `INV-24` — empreinte seule, jamais au corpus, jamais par l'agent |

## Article 12 — La menace que cette conception ne couvre pas

`M-22` — **la récupération.** Si l'autorité perd son secret, aucun mécanisme prévu ici ne le lui rend. Il n'existe qu'un titulaire ; il n'y a personne pour l'attester.

La fiche exige une *« récupération institutionnelle »* ; elle suppose une institution. Tant qu'une seule personne détient toutes les fonctions, la perte du secret n'a d'autre remède que la reconstruction du magasin d'exploitation — laquelle est possible sans dommage, **précisément parce que ce magasin ne contient aucune vérité** (`INV-22`).

C'est la première fois qu'une conception du Core tire un avantage opérationnel de sa propre pauvreté institutionnelle. Il ne faut pas s'en réjouir : c'est le signe que la vacance des fonctions (Article 170 du Registre des autorités) demeure entière.

---

# TITRE VI — PREUVE

## Article 13 — Cas `P3` et contre-épreuve

Garde propre à la capacité. **Aucun secret réel n'y figure** : l'essai crée un authentificateur éphémère avec un secret de test, dans un magasin temporaire détruit à la fin.

**Cas d'essai.** Un secret exact ouvre une session ; un secret erroné la refuse ; une session expirée est invalide ; la révocation de l'authentificateur invalide la session qu'il avait ouverte (`M-21`).

**Contre-épreuve.** La vérification de session est neutralisée sur copie ; il est constaté que le test échoue. Une garde d'authentification qui ne peut pas échouer ne prouve rien — et ici, l'enjeu n'est pas la preuve `P3` mais la porte elle-même.

---

# TITRE VII — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 14 — Frontières

- Aucun droit, aucune permission : `CAP-CORE-004`.
- Aucun facteur multiple : les Articles 78 à 80 de `SECURITY-GOVERNANCE-0001` les exigent pour les comptes privilégiés ; un seul facteur est livré, et l'écart est déclaré.
- Aucune récupération (`M-22`).
- Aucun fournisseur d'identité externe.
- **Aucun compte créé par l'agent.**

---

# TITRE VIII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 15 — Points à trancher

1. L'adoption ou la correction de la présente conception.
2. **L'établissement du premier identifiant.** L'agent livre la commande ; l'autorité l'exécute et saisit son secret. **Aucun secret ne transite par l'agent** (limite 4 de `ADOPTION-0037`).
3. **Les niveaux d'assurance** et les facteurs admis, que la fiche laisse ouverts.
4. **L'écart de facteur unique** au regard des Articles 78 et 79 : l'accepter à titre transitoire, ou exiger un second facteur avant tout usage.
5. Le sort de la console publique : la fermer dès l'ouverture du premier compte, ou la laisser lisible.

## Article 16 — Non-effet

Le présent acte ne code rien par lui-même, ne crée aucun compte, ne détient aucun secret, ne rend `CAP-CORE-005` ni implémentée ni active, ne modifie le corps d'aucun texte adopté et ne constate pas `G0`.

---

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
> Registre d'adoption pressenti : `ADOPTION-0039`
