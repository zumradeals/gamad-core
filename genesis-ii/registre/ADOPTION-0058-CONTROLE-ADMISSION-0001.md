# REGISTRE D'ADOPTION — ADOPTION-0058
## Le contrôle d'admission — définir ce que l'Article 27 exige depuis le 26 juillet 2026

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-controle-admission`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte adopte une **conception de contrôle**, et elle seule. Aucun code n'est livré, aucune garde n'est ajoutée ni modifiée.

Il n'est pas un acte de lot : il n'énumère aucun incrément et n'en adopte aucun.

Il ne modifie le corps d'aucun texte adopté. Il ajoute un Titre au Registre initial des décisions et une ligne à l'index des adoptions.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — L'OBJET ET SON MOTIF

## Article 1 — Objet

Sont adoptés :

1. la conception portée par `genesis-ii/conception/CONCEPTION-CONTROLE-ADMISSION-0001.md` ;
2. le **Titre XV** — Articles 167 à 175 — ajouté au Registre initial des décisions, qui en porte les dispositions normatives.

## Article 2 — Motif : une exigence sans contenu, et un empêchement qui a cessé

L'Article 27 du Registre initial des capacités souveraines exige de chaque fiche de capacité un **contrôle d'admission**. L'expression figure **une seule fois dans tout le corpus** : à cet article. Les dix autres contrôles de la même liste trouvent ailleurs de quoi être compris ; celui-ci était requis et muet.

L'Article 14 énumère les états `IMPLÉMENTÉE NON ADMISE` et `ADMISE`, puis motive leur inaccessibilité par un fait daté — « car `G0` n'est pas constatée ». `ADOPTION-0025` a constaté `G0` le 27 juillet 2026.

L'empêchement est donc tombé, et la définition n'a pas été écrite à sa place. Depuis deux jours, l'admission d'une implémentation souveraine est **possible et indéfinie**. C'est ce vide que le présent acte comble, et rien d'autre.

## Article 3 — Trois faits établis par l'instruction

Décomptes dérivés par `Ctr14::comparerReel()`, selon la règle d'`ADOPTION-0054`.

| Fait | Portée |
|---|---|
| Les **vingt** capacités portent implémentation `PARTIELLEMENT MATÉRIALISÉE` et exploitation `INACTIVE` | aucune n'a jamais porté ni `IMPLÉMENTÉE NON ADMISE` ni `ADMISE` |
| `IMPLÉMENTÉE NON ADMISE` n'apparaît **qu'à l'énumération de l'Article 14** | l'état existe comme vocabulaire, jamais comme fait |
| Concordance déclaré / observé : **`CONCORDE` pour les vingt** | le corpus dit vrai sur son propre état, y compris sur ce qu'il n'a pas atteint |

Le troisième fait mérite d'être lu deux fois : ce n'est pas un défaut qui a empêché l'admission, c'est son absence de définition.

## Article 4 — Ce que le présent acte ne rapproche pas

Définir le contrôle d'admission **ne rapproche aucune capacité de l'admission**.

Les vingt gardes prouvent chacune qu'un service honore son propre contrat tel qu'il l'a écrit ; aucune ne prouve que ce contrat couvre l'objet de la famille qu'il sert. L'état `PARTIELLEMENT MATÉRIALISÉE` déclare exactement cet écart, et il le déclare avec exactitude pour les vingt.

L'acte rend cette distance **mesurable**. Il ne la réduit pas.

---

# TITRE II — CE QUI EST ARRÊTÉ

## Article 5 — Invariants introduits

- **`INV-67` — l'admission est un troisième terme**, distinct de l'adoption qui fixe un contenu et de la publication qui le rend accessible (`INV-4`). Une adoption n'emporte aucune admission ; à défaut, chaque fusion admettrait tacitement ce qu'elle porte et nul n'aurait jugé.
- **`INV-68` — une admission nomme un commit et ne lui survit pas.** Le commit suivant qui touche le module admis n'hérite de rien. C'est ce qui distingue une admission d'une réputation.
- **`INV-69` — nul ne se présente à l'admission depuis un état partiel.** La complétude se mesure à l'objet de la **famille de contrat servie**, non à ce que le service a choisi d'offrir. Une exclusion de mission déclarée appartient au périmètre ; une opération promise par l'objet de la famille et non offerte est un manque.
- **`INV-70` — une admission déclare la qualité de l'audit sous lequel elle est prononcée.** Elle demeure possible sous audit non indépendant — le corpus a déjà tranché ce cas en levant un écart de `G0` « par décision documentée, non par résolution technique complète » — à charge d'inscrire qu'il en fut ainsi.
- **`INV-71` — l'admission n'active rien.** `ADMISE` est un état d'implémentation ; `ACTIVE` est un état d'exploitation, subordonné par l'Article 15 à huit conditions distinctes.
- **`INV-72` — le service assemble le dossier et ne conclut pas.** Il ne prononce, ne propose et ne qualifie aucune admission.

## Article 6 — L'énumération de l'Article 14 n'est pas augmentée

`INV-70` **n'ajoute aucune valeur** à l'énumération des états d'implémentation. Une admission prononcée sous audit non indépendant porte l'état `ADMISE` comme toute autre ; c'est son **inscription** qui porte la mention.

Créer un état de rang inférieur aurait installé dans le vocabulaire une admission de seconde classe, que l'usage aurait fini par tenir pour normale. La mention, elle, se lit dans chaque inscription, une par une.

## Article 7 — Le rattachement retenu, et le voisinage qu'il faut nommer

La famille compétente retenue est **`CTR-14` — Capacité souveraine**, dont l'objet déclaré est de « résoudre mission, **statut**, opérateur, dépendances et **sortie** ». L'admission d'une implémentation est le statut d'une capacité ; son retrait est sa sortie.

**`CTR-08` — Statut produit ou realm** porte pourtant le mot : « résoudre **admission**, conformité et cycle de vie ». Elle ne convient pas — elle admet des produits et des realms, non des implémentations de capacités souveraines.

Deux familles portent le même mot pour deux objets. L'acte le signale plutôt que de le taire : c'est le genre de voisinage qui a produit les deux usurpations de famille rectifiées par `ADOPTION-0045`. Le rattachement demeure soumis à l'autorité (Article 11, point 1).

## Article 8 — Le dossier se dérive, l'admission ne se dérive pas

Neuf pièces du dossier d'admission se dérivent du corpus et du dépôt sans jugement (Article 13 de la conception). Quatre questions n'en relèvent d'aucun service : la complétude au sens d'`INV-69`, la proportionnalité à la criticité, l'identité du responsable, et l'opportunité.

**Un dossier complet ne vaut pas admission.** Il rend l'admission examinable, ce qu'elle n'est pas aujourd'hui.

---

# TITRE III — PREUVE

## Article 9 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-organisations/tests/organisations_p3.php` | `CAP-CORE-002` | `0` |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| `core/registre-sources/tests/sources_p3.php` | `CAP-CORE-006` | `0` |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |
| `core/registre-decisions/tests/decisions_p3.php` | `CAP-CORE-008` | `0` |
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` |
| `core/registre-lexique/tests/lexique_p3.php` | `CAP-CORE-010` | `0` |
| `core/registre-produits/tests/produits_p3.php` | `CAP-CORE-011` | `0` |
| `core/registre-realms/tests/realms_p3.php` | `CAP-CORE-012` | `0` |
| `core/registre-audit/tests/audit_p3.php` | `CAP-CORE-013` | `0` |
| `core/registre-evenements/tests/evenements_p3.php` | `CAP-CORE-014` | `0` |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-secrets/tests/secrets_p3.php` | `CAP-CORE-016` | `0` |
| `core/registre-risques/tests/risques_p3.php` | `CAP-CORE-017` | `0` |
| `core/registre-incidents/tests/incidents_p3.php` | `CAP-CORE-018` | `0` |
| `core/registre-continuite/tests/continuite_p3.php` | `CAP-CORE-019` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` |

Ces vingt et une sorties ont été relevées **une par une**, selon la règle portée par `ADOPTION-0054`, Art. 7.

## Article 10 — Contre-épreuve de falsification

Aucune contre-épreuve n'est déclarée. `ADOPTION-0032`, Art. 3 l'exige de toute garde livrée au titre d'une preuve `P3` ; le présent acte n'en livre aucune. Elle sera produite avec le premier code qui dérivera le dossier d'admission.

---

# TITRE IV — LIMITES

## Article 11 — Points soumis à l'autorité

1. **Le rattachement à `CTR-14` plutôt qu'à `CTR-08`** (Article 7), et le sort du mot « admission » présent dans les deux objets.
2. **Qui prononce l'admission**, parmi les neuf qualités de gouvernance que distingue l'Article 22 du Registre des capacités — et laquelle en répond ensuite.
3. **La proportionnalité exigée** d'une capacité `RACINE` par rapport à une capacité `CRITIQUE` (Article 18).
4. **Le sens exact de la complétude** au regard de l'objet d'une famille (`INV-69`) : appréciation d'espèce, ou grille arrêtée une fois pour toutes ?
5. **La condition de réexamen** d'une admission prononcée sous audit non indépendant : condition, terme fixe, ou aucun.
6. **L'ordre** entre définir l'admission, admettre effectivement, et rendre l'audit indépendant — que l'Article 83 réserve à l'autorité.

## Article 12 — Non-effet

Le présent acte **n'admet aucune implémentation** et n'en présente aucune à l'admission.

Il ne change l'état d'aucune des quatre dimensions, pour aucune des vingt capacités : les vingt demeurent conception `CONÇUE`, implémentation `PARTIELLEMENT MATÉRIALISÉE`, exploitation `INACTIVE`, preuve `P3 — TESTÉ`.

Il ne livre aucun code, n'ajoute ni ne modifie aucune garde, n'augmente aucune énumération d'état, ne nomme aucun responsable, n'accepte aucun risque nouveau, ne lève et ne requalifie aucune réserve de `G0`, ne rend l'audit ni indépendant ni suffisant, n'opère aucun déploiement, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Réserve d'audit maintenue

L'acte et la conception qu'il adopte sont rédigés par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

La réserve porte ici sur l'objet même du texte : **les vingt implémentations qu'un contrôle d'admission aurait à juger ont toutes été écrites par l'agent qui conçoit ce contrôle.** C'est la troisième fois qu'une conception a pour objet le défaut dont son auteur bénéficie — après `CAP-CORE-017` et `CAP-CORE-013`.

Deux précautions en découlent, portées à la doctrine et non à l'intention. `INV-72` interdit au futur service de conclure. `INV-70` oblige toute admission à déclarer sous quel audit elle est prononcée — ce qui rendra visible, dans l'inscription même, que l'agent a conçu le contrôle qui l'admet.

Elles ne suppriment pas la réserve. Aucune conception de l'agent ne le peut.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/conception/CONCEPTION-CONTROLE-ADMISSION-0001.md` | création | `5c6a03a489f1d332f51095d5acfca57ffd490961` |
| `genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md` | Titre XV — Articles 167 à 175 (ajout seul) | `90d2487b4186ebe06f54b0d3450610823cbbe82f` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0058` | `233da06f21293190560033221d3086912005bba5` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, lesquelles demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
