# REGISTRE D'ADOPTION — ADOPTION-0063
## Admission exceptionnelle des vingt implémentations souveraines et entrée en exploitation

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-admission-exceptionnelle`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Acte de **décision**, prononcé à titre **exceptionnel**.

Il admet les vingt implémentations souveraines, porte leur exploitation à `ACTIVE` et les déclare prêtes pour la production. Il adopte l'incrément de code qui permet au service de vérifier ces inscriptions.

C'est la première fois que le corpus prononce une admission. `ADOPTION-0062` avait livré le dossier ; il ne concluait pas, et ne le pouvait pas (`INV-72`). La conclusion est ici, et elle est celle de l'autorité seule.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — LA DÉCISION

## Article 1 — Objet

Sont adoptés :

- **Le Titre XXXVIII** — Articles 228 à 234 — ajouté au Registre initial des capacités souveraines, qui prononce l'admission des vingt implémentations, inscrit les vingt admissions à la forme de l'Article 174 du Registre initial des décisions, et porte l'exploitation à `ACTIVE`.
- **Incrément :** vérification des inscriptions d'admission par le service de `CTR-14` — `INV-67` et `INV-68` éprouvés sur le corpus. **Commit :** `242921d6a4b3bff8675835eaade8adc69ad73355`. **Capacité :** `CAP-CORE-020`. **Garde :** `core/registre-annuaire/tests/annuaire_p3.php`.

## Article 2 — Ce qui est décidé

Les **vingt implémentations souveraines sont admises**. Leur état d'implémentation passe de `PARTIELLEMENT MATÉRIALISÉE` à **`ADMISE`**, leur état d'exploitation de `INACTIVE` à **`ACTIVE`**.

La conception demeure `CONÇUE` et la preuve `P3 — TESTÉ` : **rien n'a été conçu ni éprouvé de nouveau**. Seules changent les deux dimensions qu'une décision, et elle seule, fait changer.

## Article 3 — Le caractère exceptionnel, et ce qu'il couvre

Le mot n'est pas de style. Il couvre **trois écarts que l'autorité constate et passe outre**, en connaissance :

| Écart | Ce que le corpus exigeait | Ce que l'autorité décide |
|---|---|---|
| `INV-69` | nul ne se présente à l'admission depuis un état partiel ; les vingt étaient `PARTIELLEMENT MATÉRIALISÉE` | admettre depuis l'état partiel |
| Article 15 du Registre des capacités | `ACTIVE` suppose autorisation, opérateur, contrats, contrôles, surveillance, sauvegarde, restauration et preuves proportionnés | passer outre : **l'opérateur n'est pas nommé, la restauration n'est pas testée, la surveillance n'est pas établie** |
| Article 13 de `CONCEPTION-CONTROLE-ADMISSION-0001` | un dossier auquel manque une pièce est incomplet ; celui de `CAP-CORE-007` l'est | admettre nonobstant |

Ces trois écarts sont **inscrits, non levés**. Le service continue de les restituer après l'admission comme avant : rien n'est réputé acquis du fait d'avoir été admis.

## Article 4 — Ce qui a rendu cet acte possible sans en rendre un autre nécessaire

L'autorité a prononcé le **29 juillet 2026 un décret de travail** : le code prime, aucun texte ne conditionne ni ne retarde la production de code, et le cycle d'adoption est suspendu jusqu'à décision contraire. Le décret est inscrit en tête de `CLAUDE.md`, document d'accueil sans valeur normative, afin que tout agent le lise avant toute autre règle.

Le présent acte est rendu **en vertu de ce décret et non contre lui** : l'autorité a expressément demandé que les vingt capacités soient portées à l'admission et à l'exploitation, par un acte unique et exceptionnel, plutôt que par vingt actes successifs.

Il ne faut pas lire ici l'abrogation du corpus. Le décret **suspend une procédure**, il n'annule aucun invariant : `INV-67` à `INV-72` sont vérifiés par la garde, et l'Article 3 ci-dessus nomme les écarts au lieu de les taire.

## Article 5 — Ce que l'admission engage

Que les vingt capacités soient `ADMISE` et `ACTIVE` signifie que **l'autorité en répond**. Les vingt inscriptions nomment le même responsable — l'autorité elle-même — parce que le corpus n'en établit aucun autre (`INV-39`), et parce qu'`ADOPTION-0061` a déclaré l'autorité de décision unique et transitoire.

Cela ne signifie pas que les vingt capacités sont surveillées, sauvegardées ou restaurables. L'Article 3 dit exactement le contraire.

---

# TITRE II — PREUVE

## Article 6 — État dérivé après l'admission

Décomptes dérivés par `Ctr14`, relus du corpus selon la règle d'`ADOPTION-0054` :

| | Avant | Après |
|---|---|---|
| Admissions inscrites à la forme de l'Article 174 | 0 | **20** |
| Capacités `ADMISE` | 0 | **20** |
| Capacités en exploitation `ACTIVE` | 0 | **20** |
| Admissions caduques | — | **0** |
| Admissions sans objet | — | **0** |
| Dossiers complets | 19 | **19** |
| Dossiers incomplets | 1 | **1** — `CAP-CORE-007` |
| Inscriptions portant la mention d'audit non indépendant | — | **20** |
| Divergences carte/réalité | 0 | **0** |

Les vingt commits admis sont **relevés du dépôt** module par module, jamais écrits de mémoire.

## Article 7 — Ce que le service vérifie désormais, et qu'il ne vérifiait pas

L'incrément adopté à l'Article 1 ajoute au service trois contrôles que le corpus n'avait pas :

1. **`INV-67` — l'admission tacite est une divergence.** Une implémentation déclarée `ADMISE` que nulle inscription ne porte est nommée `ADMISSION NON INSCRITE`. Une exploitation `ACTIVE` sans admission est nommée `EXPLOITATION SANS ADMISSION`. C'est le contrôle qui rend l'Article 2 vérifiable au lieu d'être cru.
2. **`INV-68` — le commit admis est confronté à l'histoire du module**, non à sa seule tête. Une admission nommant un commit que le module n'a jamais porté est `SANS OBJET` : elle n'a rien admis.
3. **La caducité est distinguée du faux.** Un module qui évolue rend son admission caduque — c'est le mécanisme qui fonctionne, non une faute.

## Article 8 — La caducité ne bloque pas le travail, et c'est délibéré

La garde **relève** les admissions caduques et **n'échoue pas** sur elles.

Le choix est motivé. Une garde qui exigerait qu'aucune admission ne soit caduque rendrait rouge toute branche modifiant un module admis, avant même que la ligne soit écrite : le corpus interdirait le code au nom d'un invariant qui ne demande rien de tel. `INV-68` exige qu'une admission **nomme un commit**, non que le code s'arrête.

Réinscrire relève de l'autorité, au moment qu'elle choisit. Ce que la garde refuse, c'est le silence : l'état de chaque admission est nommé au tableau de bord, caduque compris.

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

## Article 10 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Cinq falsifications, sur **copies hors dépôt**, avec témoin non altéré. Chacune vise un contrôle propre à l'admission.

| Corpus | Altération | Sortie |
|---|---|---|
| Corpus du dépôt, intact | aucune | `0` |
| Copie hors dépôt — témoin | aucune | `0` |
| Copie hors dépôt | une inscription d'admission retirée | `1` |
| Copie hors dépôt | un commit admis remplacé par un commit étranger au module | `1` |
| Copie hors dépôt | la mention d'audit non indépendant retirée d'une inscription | `1` |
| Copie hors dépôt | une inscription rattachée à une famille que son module ne sert pas | `1` |
| Copie hors dépôt | une implémentation ramenée à `PARTIELLEMENT MATÉRIALISÉE`, exploitation `ACTIVE` maintenue | `1` |

Chaque falsification a fait tomber la vérification qu'elle visait, et non une autre :

| Altération | Vérification tombée en premier | Autres vérifications tombées |
|---|---|---|
| inscription retirée | « aucune capacité ne déclare un état que la réalité observée contredit » — divergence `ADMISSION NON INSCRITE` sur `CAP-CORE-013` | le décompte des vingt inscriptions ; l'état nommé de chaque admission ; `INV-67` |
| commit étranger | « `INV-68` — chaque commit admis appartient à l'histoire de son module » | — |
| mention retirée | « `INV-70` — les vingt inscriptions portent la mention d'audit non indépendant » | — |
| famille fausse | « chaque admission nomme la famille que son module sert réellement » | — |
| exploitation sans admission | « aucune capacité ne déclare un état que la réalité observée contredit » — divergence `EXPLOITATION SANS ADMISSION` | le décompte des vingt `ADMISE` ; `INV-67` |

Les deux altérations qui touchent `INV-67` sont relevées **d'abord comme divergences carte/réalité**, avant même le décompte des inscriptions. C'est l'ordre correct : une admission tacite est un écart entre ce que le corpus déclare et ce qu'il porte, et c'est à ce titre que le service la voit.

La dernière est celle qui compte le plus : elle produit exactement l'état qu'un corpus complaisant afficherait — **une capacité en exploitation que rien n'a admise** — et la garde le refuse.

Le témoin établit que les échecs procèdent des altérations et non des copies. Le dépôt est demeuré intact pendant les épreuves.

---

# TITRE III — LIMITES

## Article 11 — Ce que cet acte ne tranche pas

Demeurent **ouvertes** : `DECISION-0046` — la proportionnalité exigée d'une capacité `RACINE` ; `DECISION-0047` — le sens de la complétude au regard de l'objet d'une famille ; `DECISION-0048` — la condition de réexamen sous audit non indépendant.

L'admission est prononcée **sans qu'elles soient tranchées**, ce que l'Article 3 assume. Une décision d'espèce ne vaut pas doctrine : le présent acte n'établit aucune règle générale d'admission et ne préjuge d'aucune admission future.

## Article 12 — Non-effet

Le présent acte **ne constate pas `G0`**, ne lève et ne requalifie aucune réserve de `G0`, ne rend l'audit ni indépendant ni suffisant, ne nomme aucun opérateur, n'établit aucune surveillance, ne teste aucune restauration, n'accepte aucun risque nouveau, ne modifie l'Atlas ni aucune de ses tables, ne crée ni ne supprime aucune famille de contrat, ne modifie le corps d'aucun article antérieur et ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3).

Il n'opère **aucun déploiement**. Prêt pour la production n'est pas en production.

## Réserve d'audit maintenue

L'admission est prononcée sous une fonction `AUDIT` non indépendante, par une autorité qui est aussi le titulaire de `FCT-CORE-021`, sur un ouvrage conçu et codé par l'agent qui a écrit le contrôle d'admission lui-même.

C'est le cumul le plus complet que le corpus ait porté, et il est inscrit dans chacune des vingt admissions plutôt que dans un préambule qu'on oublie (`INV-70`). `RISK-SEC-0001` demeure entier, sans terme fixe.

La précaution retenue est vérifiable et non déclarative : les vingt inscriptions sont **éprouvées par la garde** — forme, famille, commit réel, mention d'audit — et cinq falsifications établissent que cette vérification n'est pas complaisante. L'autorité décide ; le service vérifie que ce qu'elle a écrit dit bien ce qu'elle a voulu.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXXVIII — Articles 228 à 234 (ajout seul) | `64cf94c3fb2ed9d5704ddcab0bca868242c81449` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0063` | `320caa7e801ae962b0ae95b129dea439b8313073` |

Le code adopté est identifié par son **commit** : `242921d6a4b3bff8675835eaade8adc69ad73355`.

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs — `ADOPTION-0062` compris —, lesquelles demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
