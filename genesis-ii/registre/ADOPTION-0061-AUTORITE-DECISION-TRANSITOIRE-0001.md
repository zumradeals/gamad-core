# REGISTRE D'ADOPTION — ADOPTION-0061
## Arbitrage de `DECISION-0007` et `DECISION-0045` — l'autorité de décision, unique et transitoire

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-autorite-transitoire`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Acte **d'arbitrage**. Il tranche deux décisions inscrites et les clôt.

Aucun code n'est livré, aucune garde n'est ajoutée ni modifiée. Aucune fonction n'est créée, aucun mandat modifié.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — L'ARBITRAGE

## Article 1 — Objet

Est adopté le **Titre XVIII** — Articles 191 à 198 — ajouté au Registre initial des décisions, qui tranche et clôt `DECISION-0007` et `DECISION-0045`.

## Article 2 — La décision

Koné Djakaridja, dit Zakaria le Soufi, exerce **seul et à titre transitoire** l'autorité de décision pour toutes les classes de décisions des Articles 41 à 55 et pour tous les niveaux de risque, et il en répond seul.

Il prononce en particulier l'admission d'une implémentation souveraine au sens du Titre XV, et en répond.

## Article 3 — Une nomination expresse, comme l'Article 137 l'exige

L'Article 137 demande que les autorités compétentes par classe et par risque soient confirmées « par actes et mandats, **sans nomination implicite** ». Le présent acte est cet acte.

La nomination est expresse et ne s'étend qu'à ce qu'elle nomme : elle désigne **qui décide**, et rien d'autre.

## Article 4 — Cet acte confirme, il n'innove pas

`ADOPTION-0022` avait déjà attribué à ce titulaire, à titre transitoire, les quatre fonctions du Core demeurées vacantes — dont `FCT-CORE-021`, l'autorité d'audit et de contrôle.

Le présent acte étend aux **classes de décisions** ce que `ADOPTION-0022` avait arrêté pour les **fonctions**, dans les mêmes termes : seul, et transitoire. Il ne crée ni fonction, ni pouvoir, ni titulaire nouveau.

## Article 5 — Le caractère transitoire porte une condition déjà adoptée

« Transitoire » n'est pas ici une formule d'atténuation. La condition existe, elle est adoptée, et le présent acte n'en invente aucune autre :

> « dès qu'une seconde personne de confiance sera disponible » — engagement de l'Article 1 d'`ADOPTION-0022`, repris par `REGISTRE-INITIAL-EXCEPTIONS-SECURITE-0001`, sans terme fixe.

`DECISION-0032` — une condition tient-elle lieu d'échéance, ou un terme est-il fixé — demeure ouverte, et le présent acte ne la préjuge pas.

## Article 6 — Ce que cette décision oblige, et qu'elle n'atténue pas

`INV-70` oblige une admission prononcée sans audit distinct de la production à le mentionner dans son inscription. Par l'effet du présent acte, cette obligation devient **la règle et non l'exception** : tant que l'autorité de décision et `FCT-CORE-021` sont le même titulaire, aucune admission ne peut être prononcée sans porter cette mention, et une inscription qui l'omettrait serait irrégulière.

Concentrer la décision **ne répare pas** la non-indépendance de l'audit. `RISK-SEC-0001` n'est ni levé, ni diminué, ni requalifié. Le présent acte en fixe la portée et oblige chaque admission à la dire — c'est tout ce qu'un acte de nomination peut faire, et il ne prétend pas faire davantage.

---

# TITRE II — PREUVE

## Article 7 — État dérivé après clôtures

Décomptes dérivés par `Ctr05`, selon la règle d'`ADOPTION-0054` :

| | Avant | Après |
|---|---|---|
| Inscrites | 49 | **49** |
| Ouvertes | 46 | **44** |
| Closes | 3 | **5** |
| Clôtures désignant un acte absent | 0 | **0** |

Deux clôtures, deux décisions closes : `DECISION-0007` et `DECISION-0045`. Les lignes d'ouverture demeurent lisibles à l'Article 155 et à l'Article 180 (`INV-47`).

## Article 8 — Vérification des gardes

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
| `core/registre-decisions/tests/decisions_p3.php` | `CAP-CORE-008` | `0` — 5 closes, aucune sans acte |
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

## Article 9 — Contre-épreuve de falsification

Aucune contre-épreuve n'est déclarée. `ADOPTION-0032`, Art. 3 l'exige de toute garde livrée au titre d'une preuve `P3` ; le présent acte n'en livre ni n'en modifie aucune.

---

# TITRE III — LIMITES

## Article 10 — Ce que cet acte ne tranche pas

Il nomme qui décide. Demeurent ouvertes, faute d'avoir été énoncées :

| Décision | Objet |
|---|---|
| `DECISION-0009` | pouvoirs d'acceptation de risque, durées maximales, compensations, réexamen |
| `DECISION-0010` | pouvoirs, durées et revues des décisions d'urgence |
| `DECISION-0011` | habilitations, délais, effets suspensifs et **autorités de recours** |
| `DECISION-0018` | responsables, opérateurs, auditeurs et pouvoirs de suspension ; `FCT-CORE-003` demeure `VACANTE` |
| `DECISION-0046` · `DECISION-0047` · `DECISION-0048` | le **contenu** de l'admission, non qui la prononce |

`DECISION-0011` mérite d'être relevée : une autorité unique rend le recours structurellement difficile, l'autorité de recours étant celle dont la décision est contestée. Le présent acte ne le résout pas et ne le dissimule pas.

## Article 11 — Non-effet

Le présent acte n'admet aucune implémentation et n'en présente aucune à l'admission. Il ne change l'état d'aucune des quatre dimensions, pour aucune des vingt capacités.

Il ne livre aucun code, n'ajoute ni ne modifie aucune garde, ne crée aucune fonction, ne modifie aucun mandat ni le Registre initial des autorités et mandats, n'accepte aucun risque nouveau, ne lève ni ne diminue `RISK-SEC-0001`, ne rend l'audit ni indépendant ni suffisant, ne fixe aucun terme, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Réserve d'audit maintenue

La décision de l'Article 2 est celle de l'autorité. La rédaction est celle de l'agent, sous une fonction AUDIT non indépendante.

Cette réserve porte ici sur son propre objet une seconde fois : **l'acte par lequel une autorité se confirme seule est rédigé par l'agent qu'elle instruit, et la fonction qui devrait le contrôler est tenue par elle.** Le concepteur ne s'audite pas, et l'autorité ne se contrôle pas davantage.

La précaution retenue n'est pas déclarative. L'Article 6 tire de la décision sa conséquence défavorable plutôt que de l'omettre : la concentration de la décision rend **obligatoire pour toute admission** la mention d'audit non indépendant que `INV-70` prévoyait comme cas particulier. Un acte de nomination qui aurait tu cette conséquence aurait été plus court, et faux.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md` | Titre XVIII — Articles 191 à 198 (ajout seul) | `32aec253b45173ea9a193a7513e67ae243ccfcf5` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0061` | `de0d24cce991b9ca69a1d9f43fd8e38252ce19c7` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs — `ADOPTION-0060` compris —, lesquelles demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
