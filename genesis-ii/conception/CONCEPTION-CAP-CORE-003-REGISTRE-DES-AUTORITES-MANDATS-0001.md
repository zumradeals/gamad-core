# CONCEPTION-CAP-CORE-003-REGISTRE-DES-AUTORITES-MANDATS-0001
## Projet de conception de la capacité souveraine `CAP-CORE-003` — Registre des autorités et mandats

> **PROJET D'ACTE — soumis à l'autorité de proposition. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0035`) n'a pas été signé.** Ce document conçoit ; il ne code rien et n'installe rien.

## Nature et rattachement

Le présent acte est l'**étape de conception** de la séquence de l'Article 63 : invariants, données, contrats, menaces, contrôles et preuves **avant** toute technologie.

Il se distingue des conceptions précédentes sur un point : la doctrine de `CAP-CORE-003` est **déjà entièrement écrite**. `REGISTRE-INITIAL-AUTORITES-MANDATS-0001` (adopté par `ADOPTION-0017`, complété par `ADOPTION-0022`) compte cent soixante-six articles qui définissent les fonctions, les mandats, les délégations, leurs états, leurs preuves et leurs schémas obligatoires. La présente conception **n'invente rien** : elle traduit cette doctrine en service, et se borne à cela.

Le contrat est déjà numéroté par le registre des capacités (Article 38) : `CTR-02`.

## Article 1 — Périmètre

Le service livrera, en **lecture et attestation seulement** :

1. l'index dérivé des fonctions, titulaires, mandats et délégations, avec leurs états tels que définis aux Articles 11 à 13 du registre adopté ;
2. `resoudre_mandat` — qui détient quelle fonction, à quelle date ;
3. `verifier_acte` — l'autorité signataire d'un acte d'adoption détenait-elle un mandat valide **à la date de cet acte** ;
4. `resoudre_vacance` — quelles fonctions demeurent vacantes.

Aucune écriture applicative (`INV-4`). Nommer, suspendre ou révoquer demeure un acte signé, jamais une opération de service.

---

# TITRE I — INVARIANTS

## Article 2 — `INV-12` — Aucune autorité implicite

Nulle autorité ne se déduit de l'ancienneté, de la contribution, de la possession d'un compte ou de l'exercice de fait. Reprise directe de l'Article 7 du registre adopté et de l'Article 20 (interdiction de surévaluation).

Le service refuse par construction de dériver un mandat d'autre chose que d'un acte : une fonction sans acte de nomination est `VACANTE`, quelles que soient les apparences.

## Article 3 — `INV-13` — Non-rétroactivité

Une nomination produit effet à compter de sa date, jamais avant (Article 8 du registre adopté). Un acte accompli avant l'activation d'un mandat n'est pas couvert rétroactivement par ce mandat.

## Article 4 — `INV-14` — Le mandat se vérifie à la date de l'acte

**C'est l'invariant central de cette capacité.** La question pertinente n'est jamais « X détient-il la fonction F ? » mais « X détenait-il la fonction F **le jour où il a signé** ? ».

Un mandat expiré, suspendu ou révoqué ne couvre aucun acte postérieur à son extinction ; un mandat futur n'en couvre aucun antérieur à son début. Cette vérification temporelle est à `CAP-CORE-003` ce que la reconstruction temporelle est à `CAP-CORE-007`.

Elle ferme la chaîne que le Core laisse aujourd'hui ouverte :

```
norme → acte d'adoption → autorité signataire → mandat valide à cette date
```

Le service `CTR-04` résout aujourd'hui les trois premiers maillons. Le quatrième n'est vérifié par personne.

## Article 5 — `INV-15` — Terminaison constitutive

Toute chaîne de mandats se termine dans un **acte constitutif** — un acte qui fonde l'autorité au lieu de la dériver d'une autorité antérieure. Cette terminaison n'est pas un défaut à corriger : c'est la condition d'existence de tout ordre normatif.

Le service ne doit ni boucler, ni déclarer non autorisés les actes fondateurs. Il doit les qualifier **`CONSTITUTIF`**, état distinct de `VÉRIFIÉ` et de `NON COUVERT`, et nommer l'acte où la chaîne s'arrête.

Le cas est concret et documenté : `MANDAT-GENESIS-II-0001` débute le 24 juillet 2026, date d'adoption de `GOVERNANCE-0002` (Article 47 du registre adopté). Or `GOVERNANCE-0002` est adopté par `ADOPTION-0004`, du même jour, signé par le titulaire de ce mandat. `ADOPTION-0001` à `0005` sont toutes du 24 juillet 2026.

À la granularité du jour, la chaîne paraît close. Logiquement, elle ne l'est pas : les cinq premiers actes sont contemporains de la fondation de l'autorité qui les signe. Le service déclarera `CONSTITUTIF` plutôt que de laisser croire à une vérification qui n'a pas eu lieu.

## Article 6 — `INV-16` — Interdiction de surévaluation

Reprise de l'Article 20 du registre adopté. Une série d'adoptions réussies ne prouve ni la permanence d'une fonction, ni un contrôle `P3`, ni une continuité `P4`. Le service restitue le niveau de preuve inscrit, jamais un niveau inféré de l'usage.

---

# TITRE II — DONNÉES

## Article 7 — Principe

Index **dérivé** du registre adopté et des actes (`INV-5`), reconstructible, jamais autoritatif. Les vocabulaires d'état sont repris **littéralement** des Articles 11 à 13 : aucune valeur nouvelle n'est introduite.

## Article 8 — Tables

```sql
-- Fonction instituée. États : Article 11 du registre adopté (10 valeurs).
CREATE TABLE fonction (
    reference   text PRIMARY KEY,        -- 'FCT-CORE-001', 'FCT-CAP-007'
    libelle     text NOT NULL,
    domaine     text,
    source      text NOT NULL            -- texte ou acte instituant la fonction
);

-- Titulaire : personne, organe ou agent. L'identité fine relève de
-- CAP-CORE-001 ; on ne conserve ici qu'une référence stable (INV-7).
CREATE TABLE titulaire (
    reference text PRIMARY KEY,          -- 'AUT-GAMAD-001'
    libelle   text NOT NULL,
    nature    text NOT NULL              -- 'personne' | 'organe' | 'agent'
);

-- Mandat, en AJOUT SEUL (INV-3). États : Article 12 (12 valeurs).
CREATE TABLE mandat (
    reference          text PRIMARY KEY, -- 'MANDAT-GENESIS-II-0001'
    fonction_reference text NOT NULL REFERENCES fonction(reference),
    titulaire_reference text NOT NULL REFERENCES titulaire(reference),
    debut              date NOT NULL,
    fin                date,             -- NULL = sans terme déclaré
    niveau_preuve      text NOT NULL,    -- 'P0'…'P4', Article 15
    adoption_reference text NOT NULL REFERENCES adoption(reference)
);

-- Historique d'état du mandat, en ajout seul. Aucun UPDATE, aucun DELETE.
CREATE TABLE etat_mandat (
    id                 bigint …,
    mandat_reference   text NOT NULL REFERENCES mandat(reference),
    valeur             text NOT NULL,    -- vocabulaire de l'Article 12
    date_effet         date NOT NULL,
    adoption_reference text NOT NULL REFERENCES adoption(reference)
);

-- Délégation. États : Article 13 (7 valeurs).
CREATE TABLE delegation (
    reference          text PRIMARY KEY,
    mandat_source      text NOT NULL REFERENCES mandat(reference),
    delegataire        text NOT NULL REFERENCES titulaire(reference),
    portee             text NOT NULL,
    debut              date NOT NULL,
    fin                date,
    adoption_reference text NOT NULL REFERENCES adoption(reference)
);
```

## Article 9 — Ce que ces tables ne contiennent pas

Ni mot de passe, ni clé, ni jeton, ni permission technique. Le rapprochement des **accès** avec les mandats est décrit au Titre VIII du registre adopté et relève de `CAP-CORE-016` ; l'inventaire des accès demeure du ressort exclusif de l'autorité (`ADOPTION-0025`, Art. 3.a). Le présent service ne le franchit pas.

---

# TITRE III — CONTRAT `CTR-02`

## Article 10 — Les trois opérations

```
resoudre_mandat(fonction?, titulaire?, date?)
  → { fonction, titulaire, mandat, etat, debut, fin, niveau_preuve, adoption_reference }
    • sans date : l'état courant ; avec une date : l'état à cette date (INV-14).

verifier_acte(adoption_reference)
  → { acte, date, signataire_declare, mandat_couvrant, verdict }
    • verdict ∈ { VÉRIFIÉ, CONSTITUTIF, NON COUVERT, INDETERMINE }
    • VÉRIFIÉ     : un mandat actif à la date de l'acte couvre la fonction exercée ;
    • CONSTITUTIF : l'acte fonde l'autorité qu'il exerce ; la chaîne s'y arrête (INV-15) ;
    • NON COUVERT : aucun mandat actif à cette date — anomalie à signaler, non à masquer ;
    • INDETERMINE : le corpus ne permet pas de conclure.

resoudre_vacance(date?)
  → [ { fonction, etat, depuis, derniere_source } ]
    • les fonctions VACANTES sont un fait institutionnel majeur, exposé et non masqué.
```

## Article 11 — Lecture et attestation seulement

Aucune écriture (`INV-4`). Le service ne nomme pas, ne révoque pas, ne suspend pas : il restitue ce que les actes ont décidé.

## Article 12 — Explicabilité

Toute réponse rattache son résultat à sa preuve : quel mandat, quel acte, quelle date, quel niveau de preuve (Article 19 du registre adopté — preuve d'exercice).

---

# TITRE IV — MENACES

## Article 13 — Menaces retenues

Reprises de la fiche `CAP-CORE-003` (Article 38 du registre des capacités).

| Réf. | Menace | Couverture |
|---|---|---|
| `M-7` | Usurpation — exercer une fonction sans mandat | `INV-12` ; `verifier_acte` rend `NON COUVERT` |
| `M-8` | Mandat expiré demeuré actif | `INV-14` ; l'état se résout à la date, non au présent |
| `M-9` | Sous-délégation abusive | table `delegation` rattachée à un mandat source ; portée déclarée |
| `M-10` | Concentration de pouvoirs | `resoudre_vacance` et le cumul rendus visibles (Articles 52, 113) |
| `M-11` | Absence de succession | `resoudre_vacance` expose les fonctions sans titulaire |
| `M-12` | Chaîne de mandats circulaire | `INV-15` ; terminaison constitutive déclarée, jamais silencieuse |

## Article 14 — La menace que le service ne peut pas couvrir

`M-13` — **l'autorité qui se nomme elle-même.** Aucun contrôle technique ne peut empêcher un titulaire unique d'étendre ses propres pouvoirs par acte régulier. Le registre adopté le reconnaît : l'Article 163 constate que `FCT-CORE-021` (audit) est attribuée au même titulaire que les trois autres fonctions, privant l'audit de son indépendance structurelle.

Le service **rendra ce fait visible** — cumul, vacances, concentration — mais ne le corrigera pas. Seule la nomination d'une seconde personne de confiance le peut. C'est une limite institutionnelle, non technique, et la présente conception refuse de laisser croire le contraire.

---

# TITRE V — CONTRÔLES ET PREUVES

## Article 15 — Preuve `P3` visée et sa contre-épreuve

Conformément à l'Article 3 de `ADOPTION-0032`, l'essai `P3` sera accompagné d'une contre-épreuve de falsification déclarée.

**Cas d'essai proposé.** `verifier_acte('ADOPTION-0026')` — l'acte du 27 juillet 2026 — rend `VÉRIFIÉ`, `MANDAT-GENESIS-II-0001` étant `ACTIF — TRANSITOIRE` depuis le 24 juillet. **Falsification :** déplacer, sur copie hors dépôt, le début du mandat au 28 juillet ; il est alors vérifié que le verdict bascule en `NON COUVERT` et que le test échoue.

Un essai qui ne pourrait pas échouer ne prouverait rien.

## Article 16 — Contrôle de couverture des trente-quatre actes

Le service pourra rendre, pour l'ensemble des actes du corpus, la répartition des verdicts. Ce décompte est un **résultat d'audit institutionnel**, non un indicateur technique : il dira combien d'actes reposent sur un mandat vérifié, combien sur la fondation, combien sur rien.

L'agent ne préjuge pas de ce décompte.

## Article 17 — Question de garde, non tranchée

Cet essai `P3` serait une garde supplémentaire. La décision réservée à l'Article 11 de `ADOPTION-0034` — nombre et répartition des gardes du dépôt — demeure ouverte et **conditionne la livraison de la preuve** de la présente capacité. Elle doit être tranchée avant, non après.

---

# TITRE VI — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 18 — Frontières

- Aucune écriture applicative, aucune nomination, aucune révocation par le service.
- Aucun rapprochement des accès techniques : `CAP-CORE-016`, frontière réservée.
- Aucune identité fine : `CAP-CORE-001`, dont `CAP-CORE-003` ne conserve qu'une référence stable.
- Aucune authentification, aucune session : `CAP-CORE-005`.
- Aucun jugement sur la légitimité d'un mandat régulièrement inscrit. Le service constate ; il n'apprécie pas.

## Article 19 — Rappel sur ce que cette capacité n'apporte pas

`CAP-CORE-003` ne permettra à personne de se connecter. Elle rend le pouvoir **traçable**, non exerçable. Se connecter exige `CAP-CORE-001`, `005` et `004`, qui demeurent `À ÉTABLIR`.

---

# TITRE VII — RÉSERVE D'AUDIT

## Article 20 — Rappel

La présente conception est rédigée par l'agent qui la codera et la vérifiera, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b ; Article 163 du registre adopté).

Cette réserve pèse ici plus qu'ailleurs : la capacité conçue a précisément pour objet de rendre visible la concentration des pouvoirs, et elle est conçue par l'agent d'un titulaire unique. L'agent ne peut pas s'en abstraire ; il l'inscrit.

---

# TITRE VIII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 21 — Points à trancher

1. **L'adoption ou la correction de la présente conception** (acte pressenti `ADOPTION-0035`).
2. **Le nombre de gardes** (Article 17) — préalable à toute preuve `P3` de cette capacité.
3. **Le verdict applicable aux actes fondateurs.** La présente conception propose `CONSTITUTIF` pour `ADOPTION-0001` à `0005`. L'autorité peut préférer une autre qualification, ou fixer un acte constitutif unique.
4. **La confirmation du mandat transitoire**, que l'Article 142 du registre adopté requiert expressément et qui demeure due.
5. **Les nominations prioritaires** (Article 143) : tant qu'aucune seconde personne n'est nommée, `M-13` demeure sans contre-pouvoir.

## Article 22 — Non-effet

Le présent acte ne code rien, n'installe rien, ne rend `CAP-CORE-003` ni implémentée ni active, ne nomme ni ne révoque personne, n'accepte aucun risque nouveau, ne modifie le corps d'aucun texte adopté et ne constate pas `G0`.

---

## Autorité d'adoption

- **Nom :** _[réservé à l'autorité de proposition]_
- **Qualité :** _[à compléter]_
- **Date :** _[à compléter à l'adoption]_
- **Registre d'adoption pressenti :** `ADOPTION-0035`
- **Signature :** _[réservée à l'autorité]_

Jusqu'à adoption expresse et inscription au Registre des adoptions, le présent texte demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
