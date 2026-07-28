# GAMAD Core — Genesis II · Contexte pour Claude Code

Document non normatif d'accueil. Il oriente tout agent Claude Code travaillant
sur ce dépôt. Il ne modifie aucun texte adopté et n'a aucune valeur d'autorité.

---

## 1. Ce qu'est ce dépôt

`gamad-core` est le **corpus documentaire canonique** de GAMAD (Genesis II) et,
depuis le constat de la Porte `G0`, le **début de son code canonique**. Le corpus
définit les sources, lois, chartes, gouvernances, capacités souveraines, autorités
et registres du Core. Chaque texte est adopté par un acte signé, identifié par son
empreinte Git exacte.

La langue du projet est le français.

---

## 2. État actuel (à la passation)

- **Porte `G0` : CONSTATÉE** par `ADOPTION-0025`, signée par l'autorité le
  27 juillet 2026. Le codage canonique est autorisé, dans le périmètre constaté.
- **29 actes d'adoption** (`ADOPTION-0001` à `ADOPTION-0029`), numérotation
  continue, tous inscrits à l'index central.
- **Premier module de code** : `core/registre-normes/` — service `CTR-04` de la
  capacité `CAP-CORE-007` (Registre des normes). État : conception `CONÇUE`,
  implémentation `PARTIELLEMENT MATÉRIALISÉE`, exploitation `INACTIVE`,
  preuve `P3 — TESTÉ`.
- **Réserves ouvertes maintenues** (`ADOPTION-0025`, Art. 3) : inventaire des
  accès et secrets détenu exclusivement par l'autorité ; AUDIT non indépendant ;
  aucun produit certifié ; Wasplex et IKOMA hors GAMAD.

---

## 3. Règles NON NÉGOCIABLES

1. **L'agent n'est pas une autorité.** `ADOPTION-0024`, Article 3 : Claude
   conçoit, vérifie et code **sous instruction** ; il **ne constate, n'adopte
   et ne signe jamais**. Seul Koné Djakaridja, dit Zakaria le Soufi, adopte et
   signe. Le concepteur ne s'audite pas : ta vérification est une assistance
   subordonnée à l'AUDIT humain.
2. **Deux gardes doivent être vertes avant toute fusion dans `main`** (voir §4).
3. **On ne réécrit jamais le corps d'un texte adopté.** Toute modification d'un
   registre adopté se fait par **ajout** (un nouveau Titre en fin de fichier),
   jamais en éditant un article ou un tableau existant. L'acte qui adopte
   l'ajout déclare la **nouvelle empreinte Git** du fichier dans son
   « Constat d'exécution » ; cette déclaration la plus récente prévaut.
4. **L'historique de `main` ne se réécrit pas.** Fusions en `--no-ff`, messages
   explicites (jamais les commentaires `#` de l'éditeur).
5. **Frontière des accès réservés (infranchissable par l'agent).** Secrets,
   clés, `DATABASE_URL`, comptes VPS, hébergement : domaine **exclusif** de
   l'autorité (`ADOPTION-0025`, Art. 3.a). Ne manipule aucun secret, ne les
   mets jamais dans le dépôt. Le déploiement est l'acte de l'autorité ; tu
   guides, tu ne déploies pas à sa place.

---

## 4. Les deux gardes (à lancer avant toute fusion dans `main`)

```bash
# Garde 1 — intégrité documentaire (Python, indépendante de l'application)
python3 outils/verifier-integrite.py        # doit sortir 0

# Garde 2 — comportement du service : preuve P3 de reconstruction temporelle
php core/registre-normes/tests/temporel_p3.php   # doit sortir 0
```

Les gardes sont **séparées à dessein** (`ADOPTION-0027`, Art. 4) : le contrôle
Python vérifie la cohérence des fichiers ; les tests PHP vérifient le
comportement du code. **Ne réécris jamais le contrôle Python dans le cadre
applicatif** — un contrôle couplé à ce qu'il vérifie perd sa valeur.

**Doctrine arrêtée par `ADOPTION-0035`, Art. 2.2 :** le dépôt porte **une garde
documentaire unique** et **une garde de comportement par capacité codée**. Une
capacité ne peut atteindre `P3 — TESTÉ` que par une garde éprouvant son propre
contrat — une capacité n'hérite pas de la preuve d'une autre. Le nombre de
gardes croît donc avec les capacités ; la garde documentaire, elle, reste unique
et indépendante.

**Toute garde livrée au titre d'une preuve `P3` doit être accompagnée d'une
contre-épreuve de falsification** (`ADOPTION-0032`, Art. 3) : altérer
délibérément une copie du corpus hors dépôt et constater que le test échoue.
L'acte déclare les deux exécutions. Un test qui ne peut pas échouer ne prouve
rien.

---

## 5. Le cycle de travail (discipline post-`G0`, Article 63)

Pour toute évolution : **concevoir → faire adopter → coder**, invariants avant
technologie. Concrètement :

1. Travaille sur une **branche dédiée** (`agent/…`), jamais directement sur `main`.
2. Rédige un **projet** (conception, décision, ou code) — statut
   `PROJET NORMATIF`, non signé.
3. Prépare le **registre d'adoption** correspondant (`ADOPTION-00NN`) :
   déclare l'empreinte Git du contenu adopté, ajoute **une ligne** à l'index
   `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` (Article 4,
   ajout seul), numérotation continue.
4. Lance les **deux gardes** → 0.
5. **Demande l'autorisation de l'autorité.** La fusion `--no-ff` dans `main`
   **est** l'acte d'adoption : elle appartient à l'autorité, pas à toi.
6. Après fusion, relance les deux gardes sur `main`.

Le code est identifié par son **commit** (empreinte de l'incrément entier),
déclaré dans l'acte qui l'adopte.

---

## 6. Carte du dépôt

- `genesis-ii/` — le corpus canonique (textes adoptés, registres, actes).
  - `registre/ADOPTION-*.md` — les actes d'adoption (source primaire).
  - `registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` — l'index central (dérivé).
  - `registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` — les
    20 capacités souveraines et leur ordre de dépendance (Article 61).
  - `conception/` — les projets et actes de conception du code.
  - `audit/` — dossier d'audit `G0` et acte de constat.
- `outils/verifier-integrite.py` — **garde 1** (contrôle documentaire).
- `core/registre-normes/` — **premier module de code** (service `CTR-04`).
- `.github/workflows/` — les deux gardes en intégration continue.

---

## 7. Le premier module : `core/registre-normes/`

Service `CTR-04` en **lecture et attestation seulement** (aucune écriture du
corpus). Les fichiers Git restent la source de vérité ; la base n'est qu'un
**index dérivé, reconstructible**. Voir `core/registre-normes/README.md`.

Invariants portés (à ne jamais violer) :
`INV-1` empreinte exacte · `INV-3` historique en ajout seul (aucun `UPDATE`/
`DELETE` sur `statut`, `adoption`, `relation_evolution`) · `INV-4` adoption
distincte de la publication · `INV-5` index dérivé, jamais autoritatif ·
`INV-6` supersession traçable.

Exécution locale (aucun secret) :
```bash
php core/registre-normes/tests/temporel_p3.php               # preuve P3
php -S 127.0.0.1:8080 -t core/registre-normes/public          # tableau de bord
```

---

## 8. Ce qui reste à faire (pistes, à valider par l'autorité)

- **Déploiement VPS / Railway** — acte de l'autorité. Premier regard en SQLite
  (aucun secret) ; persistance PostgreSQL ensuite (`DATABASE_URL` = secret à
  consigner au registre d'accès de l'autorité, jamais dans le dépôt).
- **Couche de livraison Laravel** — `ADOPTION-0027` a retenu Laravel ; le premier
  incrément a livré le cœur en PHP portable (`ADOPTION-0029`, Art. 1). Ajouter la
  couche Laravel autour du cœur existant, sans le réécrire, est un incrément
  naturel — à faire adopter.
- **Capacités racines suivantes** (Article 61 du registre des capacités) :
  `CAP-CORE-006` sources, `CAP-CORE-003` autorités, `CAP-CORE-001` identité,
  `CAP-CORE-015` preuves d'intégrité. L'ordre exact est une décision de
  l'autorité (Article 83).

---

## 9. En cas de doute

Si une action est difficilement réversible, tournée vers l'extérieur, ou touche
un secret / un accès / un déploiement : **arrête-toi et demande à l'autorité**.
Le corpus vaut par sa vérité sur lui-même ; ne publie jamais un état que les deux
gardes ne confirment pas.
