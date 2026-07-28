# CONCEPTION-IMPLEMENTATION-CTR-04-0001
## Projet de conception d'implémentation du contrat `CTR-04` — service du Registre des normes (`CAP-CORE-007`)

> **PROJET D'ACTE — soumis à l'autorité de proposition. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0028`) n'a pas été signé.** Ce document conçoit le premier incrément de code ; il ne l'écrit pas encore et n'installe rien.

## Nature et rattachement

Le présent acte est l'**étape 3, phase de conception concrète**, de la séquence de l'Article 63. Il raffine, au niveau de l'implémentation, la conception adoptée `CONCEPTION-CAP-CORE-007-REGISTRE-DES-NORMES-0001` (`ADOPTION-0026`) sur la pile adoptée par `ADOPTION-0027` (Git, PostgreSQL, PHP/Laravel, contrôle Python séparé). Il traduit les six invariants `INV-1` à `INV-6` en schéma SQL, opérations et tests concrets.

Rédigé par SIRR (Claude, `AGENT-IA-002`) sous instruction (`ADOPTION-0024`, Art. 3 : conçoit et vérifie, ne décide ni ne signe). La branche de code canonique est ouverte au titre de `FCT-CORE-009` par l'autorité ; l'agent y écrit sous instruction.

## Article 1 — Périmètre du premier incrément

`CTR-04` est, à ce stade, un contrat **de lecture et d'attestation seulement** (Titre III de la conception adoptée). Le premier incrément livre :

1. le schéma relationnel de l'index dérivé (Titre II ci-dessous) ;
2. l'ingestion qui **dérive** cet index des fichiers canoniques versionnés, sans jamais les modifier (`INV-5`, Article 19 de la conception) ;
3. les trois opérations de lecture `resoudre_norme`, `verifier_integrite`, `resoudre_index` ;
4. le test `P3` de reconstruction temporelle, preuve aujourd'hui manquante de `CAP-CORE-007`.

Il ne livre **aucune** écriture applicative : les seules écritures du corpus passent par des actes d'adoption signés (`INV-4`, Article 68 du registre des capacités).

---

# TITRE I — ARBORESCENCE DU CODE CANONIQUE

## Article 2 — Racine et premier module

Le code canonique du Core est placé sous une racine distincte du corpus documentaire :

```
core/                                  ← racine du code canonique du Core
  registre-normes/                     ← premier module (service CAP-CORE-007 / CTR-04)
    README.md                          ← portée, invariants servis, limites
    database/
      migrations/                      ← DDL PostgreSQL (Titre II)
    src/
      Ingestion/                       ← dérivation depuis les fichiers (Titre III)
      Ctr04/                           ← les trois opérations de lecture (Titre IV)
    tests/
      TemporelP3Test                   ← preuve P3 (Titre V)
```

Le contrôle d'intégrité `outils/verifier-integrite.py` **reste où il est** et n'entre pas sous `core/` : il demeure séparé du module qu'il contrôle (Article 4 de `ADOPTION-0027`).

## Article 3 — Séparation de l'application et du contrôle

`core/registre-normes/` est l'**application** (service `CTR-04`). `outils/verifier-integrite.py` est le **contrôle**. Ils lisent les mêmes fichiers canoniques mais ne partagent ni code, ni exécution : un défaut de l'un n'atteint pas l'autre. L'application peut, à terme, invoquer le contrôle comme une porte (gate) sans l'absorber.

---

# TITRE II — SCHÉMA RELATIONNEL DE L'INDEX DÉRIVÉ

## Article 4 — Principe

Le schéma matérialise le modèle de données du Titre II de la conception adoptée. Il est **dérivé** : les fichiers Git restent la source de vérité (`INV-5`), la base est un index reconstructible. Le schéma est énoncé en DDL PostgreSQL de référence ; les noms exacts pourront être ajustés à l'écriture sans changer la structure.

## Article 5 — Définition des tables

```sql
-- Une norme : identité stable, indépendante de ses versions.
CREATE TABLE norme (
    reference   text PRIMARY KEY,        -- ex. 'CORE-LAWS-0001'
    titre       text NOT NULL,
    rang        text NOT NULL,           -- au sens de SOURCES-0001
    domaine     text NOT NULL
);

-- Une version d'une norme, liée à un contenu par son empreinte Git (INV-1).
CREATE TABLE version_norme (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    norme_reference   text NOT NULL REFERENCES norme(reference),
    version           text NOT NULL,     -- ex. '0.1'
    empreinte_git     char(40) NOT NULL, -- git hash-object du contenu
    chemin            text NOT NULL,     -- chemin canonique du fichier
    date_redaction    date,
    UNIQUE (norme_reference, version),   -- pas de version ambiguë
    UNIQUE (empreinte_git)               -- un contenu = une identité
);

-- Un acte d'adoption. La présence d'une signature est un fait, pas une présomption (INV-4).
CREATE TABLE adoption (
    reference          text PRIMARY KEY, -- ex. 'ADOPTION-0026'
    autorite           text NOT NULL,
    date_adoption      date NOT NULL,
    signature_presente boolean NOT NULL
);

-- Le statut d'une version, en AJOUT SEUL (INV-3). Chaque statut est fondé sur un acte.
CREATE TABLE statut (
    id                 bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    version_norme_id   bigint NOT NULL REFERENCES version_norme(id),
    valeur             text NOT NULL,    -- 'EN VIGUEUR' | 'AMENDE' | 'REMPLACE' | 'ABROGE'
    date_effet         date NOT NULL,
    adoption_reference text NOT NULL REFERENCES adoption(reference)  -- pas de statut sans acte
);

-- La supersession entre normes (INV-6).
CREATE TABLE relation_evolution (
    id                 bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    norme_source       text NOT NULL REFERENCES norme(reference),
    norme_cible        text NOT NULL REFERENCES norme(reference),
    type               text NOT NULL CHECK (type IN ('amende','remplace','abroge')),
    adoption_reference text NOT NULL REFERENCES adoption(reference)
);
```

## Article 6 — Comment le schéma porte les invariants

| Invariant | Traduction dans le schéma |
|---|---|
| `INV-1` empreinte exacte | `version_norme.empreinte_git` obligatoire et unique |
| `INV-3` historique non réécrit | table `statut` en ajout seul ; aucun `UPDATE`/`DELETE` accordé au rôle applicatif |
| `INV-4` adoption distincte | `statut.adoption_reference` obligatoire ; `signature_presente` conservée |
| `INV-6` supersession traçable | table `relation_evolution` avec type contraint |
| version ambiguë (menace) | `UNIQUE (norme_reference, version)` |

## Article 7 — Ajout seul garanti par les privilèges

`INV-3` n'est pas qu'une convention : le rôle PostgreSQL de l'application ne reçoit que `SELECT` et `INSERT` sur `statut`, `adoption` et `relation_evolution` — **jamais** `UPDATE` ni `DELETE`. Une correction se fait par une nouvelle ligne datée, non par l'effacement de l'ancienne.

---

# TITRE III — INGESTION DÉRIVÉE

## Article 8 — Source et sens unique

L'ingestion lit les fichiers canoniques déjà publiés sur `main` — les actes d'adoption du répertoire `genesis-ii/registre/`, les fichiers de statut, l'index `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` — et remplit l'index. Le sens est **unique** : des fichiers vers la base, jamais l'inverse. L'ingestion n'écrit dans aucun fichier `.md` (`INV-5`, Article 19 de la conception ; Article 34 du registre des capacités : aucune fusion Genesis I).

## Article 9 — Recalcul, jamais recopie de confiance

Pour chaque version de norme, l'ingestion **recalcule** l'empreinte (`git hash-object` sur le fichier) plutôt que de recopier l'empreinte déclarée dans un acte. L'empreinte déclarée sert alors de valeur attendue, comparée à l'empreinte réelle — ce que fait déjà `verifier-integrite.py` (contrôle `C5`). Une divergence est un fait signalé, jamais silencieusement absorbé.

## Article 10 — Idempotence et reconstruction

L'ingestion est **idempotente** : rejouée sur le même état de `main`, elle produit le même index. L'index est donc entièrement reconstructible à partir des fichiers, ce qui est la garantie concrète de `INV-5`.

---

# TITRE IV — LES TROIS OPÉRATIONS `CTR-04`

## Article 11 — Signatures

```
resoudre_norme(reference, version?, date?)
  → { reference, version, empreinte_git, statut, adoption_reference, en_vigueur }
    • sans 'date' : l'état courant ;
    • avec une 'date' passée : la version et le statut en vigueur à cette date (INV-3, INV-6).

verifier_integrite(reference, version)
  → { empreinte_declaree, empreinte_reelle, concorde }
    • recalcule l'empreinte réelle et la compare à celle déclarée par l'acte.

resoudre_index()
  → { lignes, divergences[] }
    • reconstruit l'index dérivé et liste les écarts éventuels avec l'index publié (INV-5).
```

## Article 12 — Exposition

Au premier incrément, les trois opérations sont exposées comme **commandes de lecture** (interface en ligne de commande de type `artisan`), exécutables localement et en intégration continue. Une exposition HTTP en lecture seule pourra suivre ; **aucune** route d'écriture n'est créée (`INV-4`, Article 68). Le choix d'exposer HTTP dès le premier incrément ou plus tard est une décision réservée (Titre VIII).

## Article 13 — Explicabilité

Toute réponse rattache son résultat à sa preuve : quelle version, quelle empreinte, quel acte d'adoption (Article 15 de la conception adoptée). Une réponse sans preuve rattachée est un défaut.

---

# TITRE V — PREUVE `P3` — RECONSTRUCTION TEMPORELLE

## Article 14 — L'objet du test

`CAP-CORE-007` atteint aujourd'hui `P2` (contrôle exécuté) mais pas `P3` (comportement éprouvé par essai reproductible). Le test `P3` du premier incrément vérifie la reconstruction temporelle : *« à une date passée donnée, le service restitue-t-il la version et le statut réellement en vigueur à cette date ? »*

## Article 15 — Cas d'essai fondé sur des faits déjà vrais

Le corpus fournit un cas vérifiable sans rien inventer : l'état de conception de `CAP-CORE-007` lui-même.

- **Avant `ADOPTION-0026`** (adopté le 27 juillet 2026) : `CAP-CORE-007` était `EN CONCEPTION`.
- **Après `ADOPTION-0026`** : `CONÇUE`.

Le test amorce l'index avec ces deux faits datés et vérifie que `resoudre_norme('CAP-CORE-007', date=<veille de l'adoption>)` retourne `EN CONCEPTION`, et qu'à la date d'adoption ou après il retourne `CONÇUE`. La réussite de ce test est la preuve `P3` attendue par la fiche de `CAP-CORE-007`.

## Article 16 — Intégration continue

Le test `P3` s'exécute en intégration continue, aux côtés — et non à la place — du contrôle `verifier-integrite.py`. Le contrôle vérifie la cohérence documentaire ; le test `P3` vérifie le comportement du service. Les deux gardes sont distinctes.

---

# TITRE VI — CE QUE LE PREMIER INCRÉMENT NE FAIT PAS

## Article 17 — Frontière opérationnelle

Le premier incrément **s'exécute localement et en intégration continue**, contre une base PostgreSQL locale ou éphémère. Il ne comporte :

- aucun déploiement, aucune base hébergée, aucun serveur ;
- aucune écriture applicative dans le corpus ;
- aucun secret, aucune clé, aucun compte externe ;
- aucune route d'écriture, aucune administration.

## Article 18 — Frontière des accès réservés

Tout hébergement réel — base gérée, VPS, service en ligne — touche l'inventaire des accès et secrets que `ADOPTION-0025`, Article 3.a, garde exclusivement entre les mains de l'autorité. Cette frontière est **infranchissable par l'agent** : SIRR livre du code testable localement ; le déploiement relève de l'autorité, avec les accès qu'elle seule détient.

---

# TITRE VII — RÉSERVE D'AUDIT

## Article 19 — Rappel

Le premier incrément est conçu et vérifié par le même agent, sous une fonction AUDIT (`FCT-CORE-021`) non indépendante (`ADOPTION-0025`, Art. 3.b). La lecture critique de l'autorité et le test `P3` reproductible sont, à ce stade, les deux contre-pouvoirs réels. Un contrôle indépendant demeure dû dès qu'une seconde personne de confiance sera disponible.

---

# TITRE VIII — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 20 — Points à trancher

1. l'adoption ou la correction de la présente conception d'implémentation (acte pressenti `ADOPTION-0028`) ;
2. l'exposition HTTP en lecture dès le premier incrément, ou commande en ligne uniquement d'abord ;
3. les versions majeures précises de PostgreSQL, PHP et Laravel, au moment du premier build (rappel de `ADOPTION-0027`, Art. 8.2) ;
4. le vocabulaire de statut canonique définitif (`EN VIGUEUR`, `AMENDE`, `REMPLACE`, `ABROGE` sont proposés).

## Article 21 — Non-effet

Le présent acte ne code rien, n'installe rien, ne rend `CAP-CORE-007` ni implémentée ni active, n'accepte aucun risque nouveau et ne modifie le corps d'aucun texte adopté.

---

## Autorité d'adoption

- **Nom :** _[réservé à l'autorité de proposition]_
- **Qualité :** _[à compléter]_
- **Date :** _[à compléter à l'adoption]_
- **Registre d'adoption pressenti :** `ADOPTION-0028`
- **Signature :** _[réservée à l'autorité]_

Jusqu'à adoption expresse et inscription au Registre des adoptions, le présent texte demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
