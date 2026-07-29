# REGISTRE D'ADOPTION — ADOPTION-0054
## Rectification d'un décompte faux d'`ADOPTION-0053`, et dérivation des décomptes par criticité

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-rectification-decomptes`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte rectifie un chiffre faux porté par `ADOPTION-0053` et donne au corpus le moyen de dériver les décomptes de cette espèce.

Il est un **acte propre**, conformément à l'Article 164 du Registre initial des décisions : une faute mérite d'être lue pour elle-même, et non classée au milieu de travaux qui réussissent.

Il ne réécrit le corps d'aucun texte adopté. `ADOPTION-0053` demeure au dépôt tel qu'adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — LA FAUTE

## Article 1 — Le chiffre faux

`ADOPTION-0053`, Article 13, affirme :

> « Dix capacités sur vingt sont désormais codées et éprouvées, dont **sept des huit** `RACINE`. »

**Les deux propositions sont fausses**, et la seconde l'est deux fois.

| | Affirmé | Vérifié |
|---|---|---|
| Capacités codées et éprouvées | dix | **treize** |
| Capacités de criticité `RACINE` | huit | **dix** |
| `RACINE` codées et éprouvées | sept | **huit** |

Le premier chiffre décrivait l'état **avant** le lot que l'acte lui-même adoptait : `ADOPTION-0053` portait trois capacités de plus, et son propre décompte ne les comptait pas.

Les dix `RACINE` sont `CAP-CORE-001`, `003`, `004`, `005`, `006`, `007`, `008`, `015`, `016` et `019`. Les huit premières sont codées ; `CAP-CORE-016` — Gouvernance des secrets et clés et `CAP-CORE-019` — Sauvegarde et restauration souveraines ne le sont pas.

## Article 2 — La cause

**Le chiffre a été écrit de mémoire, non dérivé.** Aucun service ne le produisait, aucune garde ne pouvait le contredire, et il a traversé la rédaction de l'acte, la demande de fusion et l'adoption sans rencontrer d'obstacle.

Ce n'est pas une erreur de calcul : c'est une affirmation formulée là où une lecture s'imposait. Le corpus disposait de la donnée — le Registre initial des capacités porte la criticité de chacune des vingt — et nul n'est allé la lire.

## Article 3 — Ce que la faute a de particulier

`ADOPTION-0050` rapportait un défaut trouvé **par un mécanisme** du corpus. Celui-ci n'a été trouvé par aucun : il a fallu qu'une question de l'autorité conduise l'agent à recompter.

C'est la troisième faute de l'agent portée au corpus en sept actes, et la première qu'aucun mécanisme n'a vue. Elle mesure exactement ce que vaut la réserve d'`ADOPTION-0025`, Art. 3.b : là où aucun contrôle écrit d'avance n'existe, la vigilance de l'agent ne supplée rien.

## Article 4 — Ce que cet acte ne fait pas à `ADOPTION-0053`

L'Article 13 de `ADOPTION-0053` **n'est pas réécrit**. Il demeure au dépôt tel qu'adopté, avec son chiffre faux, et son empreinte demeure exacte à sa date.

Le présent acte constate la valeur vraie. Le corpus porte ainsi les deux faits : ce qui a été affirmé, et ce qui est. C'est la règle d'ajout seul, et elle vaut aussi — surtout — pour les fautes.

---

# TITRE II — LA RECTIFICATION

## Article 5 — Valeur constatée

À la date du présent acte, et dérivé du corpus par le service `CTR-14` :

| Criticité | Total | Codées et éprouvées | Restantes |
|---|---|---|---|
| `RACINE` | 10 | **8** | `CAP-CORE-016`, `CAP-CORE-019` |
| `CRITIQUE` | 10 | **5** | `CAP-CORE-010`, `013`, `014`, `017`, `018` |
| **Total** | **20** | **13** | **7** |

Treize capacités sont codées et éprouvées, par **treize gardes de comportement** distinctes — une par capacité, conformément à `ADOPTION-0035`, Art. 2.2. Aucune criticité `MAJEURE` n'est employée par le Registre.

Ces décomptes sont ceux que le service restitue, et l'Article 6 les rend vérifiables à tout moment.

## Article 6 — Les décomptes deviennent dérivables, et gardés

L'opération `Ctr14::parCriticite()` est ajoutée : elle restitue, pour chaque criticité, le total, les capacités codées et les restantes, dérivés du Registre initial des capacités et de la réalité observée sur le disque.

La garde de `CAP-CORE-020` est **étendue** en conséquence : les décomptes par criticité sont éprouvés, et leur somme doit couvrir les vingt capacités sans reste. Aucune garde n'est ajoutée (`ADOPTION-0035`, Art. 2.2).

Un chiffre cité dans un acte peut désormais être **relu du corpus** au lieu d'être reconstitué.

## Article 7 — Deux règles portées au document d'accueil

`CLAUDE.md`, non normatif, reçoit deux règles tirées de la présente faute et d'une seconde, de méthode :

1. **Tout décompte cité dans un acte est relu du corpus**, par le service qui le dérive, jamais reconstitué de mémoire.
2. **Les sorties de gardes se relèvent une par une.** La boucle qu'employait l'agent — `echo "$(basename $g) = $?"` — rapporte le code de retour de `basename`, donc `0` quoi qu'il advienne. Ce défaut a été signalé à la réserve d'audit d'`ADOPTION-0053` ; il est ici porté au document que lit tout agent.

---

# TITRE III — PREUVE

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
| `core/registre-decisions/tests/decisions_p3.php` | `CAP-CORE-008` | `0` |
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` |
| `core/registre-produits/tests/produits_p3.php` | `CAP-CORE-011` | `0` |
| `core/registre-realms/tests/realms_p3.php` | `CAP-CORE-012` | `0` |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` — étendue |

Ces quatorze sorties ont été relevées **une par une**, selon la règle que le présent acte porte à l'Article 7.

## Article 9 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

| Corpus | Altération | Résultat | Sortie |
|---|---|---|---|
| Corpus du dépôt, intact | aucune | `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt — témoin | aucune | `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt | module d'une capacité `CRITIQUE` retiré | `P3` **NON ÉTABLIE** — le décompte tombe de cinq à quatre | `1` |

La falsification établit que le décompte est **réellement dérivé** de la réalité observée, et non inscrit en dur dans la garde : retirer un module change le nombre, et la garde le voit.

Le témoin établit que l'échec procède de l'altération et non de la copie. Le dépôt est demeuré intact.

---

# TITRE IV — EFFETS ET LIMITES

## Article 10 — Effets

Aucun invariant, aucune menace, aucun contrat nouveau. **Aucun état de capacité n'est modifié** : une extension de garde n'est pas un changement d'état (`ADOPTION-0050`, Art. 9).

`ADOPTION-0053` demeure adopté et en vigueur. Seul son décompte de l'Article 13 est rectifié par le présent constat ; le reste de cet acte n'est pas affecté.

## Article 11 — Ce que cet acte ne fait pas

Il ne rend aucune capacité admise ni active, n'adopte aucun incrément de code de capacité, n'opère aucun déploiement, n'accepte aucun risque nouveau, ne nomme aucun responsable, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

La faute rectifiée ici est la première des trois portées au corpus que **nul mécanisme n'a détectée**. Le remède retenu n'est donc pas une intention de vigilance : c'est une opération qui dérive le chiffre et une garde qui l'éprouve. Ce qui n'est pas gardé n'est pas tenu.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| Incrément de code — `Ctr14::parCriticite()`, extension de la garde de `CAP-CORE-020`, règles portées à `CLAUDE.md` | commit | `d59f92673b4366f6615833267f727421235a97f2` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0054` | `63a53eb69fa87e1bb84855254c739a26090facbf` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
