# REGISTRE D'ADOPTION — ADOPTION-0056
## Rectification : deux capacités étaient dépourvues de famille de contrat

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-lot-final`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Acte de **rectification**, distinct de tout acte de lot. Conformément à l'Article 164 du Registre initial des décisions, la rectification d'un défaut a son acte propre : elle n'est jamais énumérée parmi les incréments d'un lot, fût-il adopté par la même fusion.

Il rectifie une affirmation fausse, par **ajout seul**. Il ne réécrit le corps d'aucun texte adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — LE DÉFAUT

## Article 1 — L'affirmation rectifiée

L'Article 133 de `CORE-ATLAS-0001` conclut :

> « Avec le présent Titre, **les vingt capacités portent toutes au moins une famille de contrat.** »

L'Article 208 du Registre initial des capacités souveraines porte la même affirmation. L'Article 9 d'`ADOPTION-0055` l'a reprise et l'a portée dans `main` le 29 juillet 2026.

Elle était **fausse à sa date**.

## Article 2 — Ce que le corpus portait réellement

Dérivé par `Ctr14::attributions()`, service de `CAP-CORE-020`, et non reconstitué de mémoire :

| Relevé | Valeur |
|---|---|
| Familles de contrat définies par l'Atlas | **18** |
| Capacités portant au moins une famille | **18** |
| Capacités n'en portant aucune | **2** |

Les deux capacités dépourvues étaient :

| Capacité | Objet | Domaine | Criticité |
|---|---|---|---|
| `CAP-CORE-010` | Lexique canonique | `DOM-01` | `CRITIQUE` |
| `CAP-CORE-016` | Gouvernance des secrets et clés | `DOM-08` | `RACINE` |

## Article 3 — La cause

L'Article 133 raisonnait par récurrence sur les Titres antérieurs de l'Atlas : `CTR-17` avait pourvu `CAP-CORE-002`, `CTR-18` pourvoyait `CAP-CORE-019`, donc la série était close.

La récurrence était juste ; **sa prémisse ne l'était pas.** Elle supposait que ces deux capacités étaient les seules dépourvues, sans le vérifier. Deux autres l'étaient, depuis l'origine, parce que leurs fiches — Articles 45 et 51 — énoncent leurs contrats attendus en prose et sans référence, comme celles de `CAP-CORE-002` et `CAP-CORE-019`.

## Article 4 — La seconde occurrence du même défaut

`ADOPTION-0054`, signé le 29 juillet 2026, a rectifié `ADOPTION-0053` qui avait affirmé « sept des huit `RACINE` » là où le corpus en portait dix. Son Article 7 a arrêté que tout décompte cité dans un acte doit être **relu du corpus** par le service qui le dérive.

`ADOPTION-0055` a été signé le même jour, et a reproduit le défaut sous une autre forme.

**Ce que la règle d'`ADOPTION-0054` ne couvrait pas explicitement :** un décompte n'est pas seulement un nombre écrit en chiffres. « Toutes », « la dernière », « la seule », « désormais complet » sont des décomptes qui ne disent pas leur nombre — et qui échappent pour cette raison à la vigilance qu'un chiffre appelle.

---

# TITRE II — CE QUI EST ADOPTÉ

## Article 5 — Les deux Titres de rectification

| Texte | Titre ajouté | Objet |
|---|---|---|
| `CORE-ATLAS-0001` | Titre XVII — Articles 136 et 137 | Le décompte de l'Article 133 était faux |
| `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001` | Titre XXXV — Articles 213 et 214 | Deux capacités étaient dépourvues de famille |

Ajout seul dans les deux cas. Aucun article, aucun tableau, aucune ligne préexistante n'est réécrit ni supprimé. Les Articles 133 et 208 demeurent exacts quant à `CTR-18` et faux quant à leur décompte final ; les Titres ajoutés prévalent sur ce seul point.

## Article 6 — La règle étendue

**Une totalité est un décompte qui ne dit pas son nombre.**

« Toutes », « la dernière », « la seule », « complet » se dérivent du corpus exactement comme un chiffre, par le service qui les établit, et jamais de mémoire. Cette extension complète l'Article 7 d'`ADOPTION-0054` ; elle ne le remplace pas.

---

# TITRE III — PREUVE

## Article 7 — Le fait rectifié est dérivable, et il l'a été

```
php -r 'require "core/registre-annuaire/src/Ctr14.php";
  $a = new Gamad\RegistreAnnuaire\Ctr14(__DIR__);
  $tit = [];
  foreach ($a->attributions() as $f => $caps) foreach ($caps as $c) $tit[$c][] = $f;
  $toutes = []; foreach ($a->comparerReel() as $l) $toutes[] = $l["capacite"];
  print_r(array_values(array_diff($toutes, array_keys($tit))));'
```

Exécutée sur `main` au commit `02a8184`, cette commande restitue `CAP-CORE-010` et `CAP-CORE-016`. C'est le relevé qui fonde le présent acte.

## Article 8 — Ce que la garde de `CAP-CORE-008` ne voyait pas

La garde d'`ADOPTION-0051` vérifie qu'un acte de lot énumère des incréments dont la capacité existe et dont la garde existe et s'exécute en intégration continue (`INV-51`). Elle a laissé passer `ADOPTION-0055` **sans faute de sa part** : l'énumération de l'Article 5 était entièrement exacte.

Le défaut n'était pas dans l'énumération, mais dans le **récit** qui l'entoure — l'Article 9. Aucune garde n'éprouve les affirmations chiffrées de la prose d'un acte, et le présent acte n'en crée pas.

C'est une **limite connue et déclarée**, non un manquement corrigé : la vérification du récit d'un acte demeure un travail de lecture, qui appartient à l'autorité et à l'AUDIT.

## Article 9 — Vérification documentaire

| Garde | Sortie |
|---|---|
| `outils/verifier-integrite.py` — documentaire | `0` |

Le présent acte ne livre aucun code et n'introduit aucune garde de comportement. Les vingt gardes de comportement du dépôt sont relevées à l'Article 12 d'`ADOPTION-0057`.

---

# TITRE IV — EFFETS ET LIMITES

## Article 10 — Ce que cet acte ne fait pas

Il **ne retire rien** au rattachement de `CTR-18` à `CAP-CORE-019`, régulier et maintenu, ni à aucun autre rattachement.

Il **ne modifie l'état d'aucune capacité**, n'en promeut ni n'en rétrograde aucune, ne crée aucune famille de contrat et n'en attribue aucune.

Il **n'annule ni ne rouvre `ADOPTION-0055`**, dont les trois incréments demeurent adoptés et éprouvés.

Il ne rend aucune capacité admise ni active, n'opère aucun déploiement, n'accepte aucun risque, ne nomme aucun responsable, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Article 11 — Point soumis à l'autorité

La **cadence** : `ADOPTION-0053`, `ADOPTION-0054` et `ADOPTION-0055` ont été préparés et signés le même jour, et le défaut rectifié par le second a reparu dans le troisième. Le rythme d'une séance appartient à l'autorité (`ADOPTION-0052`) ; l'agent constate seulement que deux occurrences du même défaut ont traversé une même journée.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

L'agent rectifie ici une affirmation qu'il a lui-même rédigée dans `ADOPTION-0055`. Cette rectification ne vaut pas contrôle : elle vaut signalement. Seul l'AUDIT humain, et l'autorité qui signe, arrêtent qu'un défaut est traité.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/atlas/CORE-ATLAS-0001-atlas-initial-gamad-core.md` | Titre XVII — Articles 136 et 137 (ajout seul) | `be4ad1861c6c981e8e837b8db2bbab58cfe5fa5e` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXXV — Articles 213 et 214 (ajout seul) | `225a963d1a89beecd1449986c239e06f255d992c` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0056` | `1453fd41d4d23b6b5068cb771779f59db03d6058` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
