# REGISTRE D'ADOPTION — ADOPTION-0032
## Conception de `CAP-CORE-006` — Registre des sources, et arbitrages de cohérence du Core

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-conception-cap-core-006`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte adopte la conception de `CAP-CORE-006 — Registre des sources`, deuxième capacité racine du domaine `DOM-01` après `CAP-CORE-007`. Il tranche en outre **cinq points de cohérence** qui excédaient le pouvoir de l'agent et que celui-ci a portés à l'autorité plutôt que de les résoudre par défaut.

Il ne livre aucun code. Conformément à l'Article 63, il conçoit avant de coder.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté `CONCEPTION-CAP-CORE-006-REGISTRE-DES-SOURCES-0001`, et arrête les cinq arbitrages énoncés à l'Article 2 ci-dessous.

## Version adoptée

| Objet | Branche de préparation | Empreinte Git |
|---|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-006-REGISTRE-DES-SOURCES-0001.md` | `agent/genesis-ii-conception-cap-core-006` | `e8ca07b4465adcab52bcdfc0b954c3a7c38f8359` |

- **Version :** `0.1`
- **Date d'adoption :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`

---

## Article 1 — Objet de la conception adoptée

La conception adoptée fixe, pour `CAP-CORE-006`, les invariants, les données, le contrat, les menaces, les contrôles et les preuves visées — dans cet ordre, et **avant** tout choix technologique (Article 63).

Son périmètre comprend trois objets, dans un ordre arrêté par l'autorité qui place la substance de la capacité en dernier : la commande de réindexation (dette contractée par `ADOPTION-0031`), la séparation des vocabulaires de statut (défaut constaté à l'Article 107 et observable en production), puis le rang et l'identité fondés sur les sources reconnues.

Cet ordre n'est pas celui de la facilité. Il procède du constat qu'une capacité bâtie sur deux défauts connus les rendrait structurels.

## Article 2 — Les cinq arbitrages

L'agent a soumis cinq points sur lesquels il s'est déclaré sans pouvoir. L'autorité les arrête comme suit.

**2.1 — Numéro de contrat de `CAP-CORE-006` : `CTR-09`.**
Le registre adopté décrit le contrat de cette capacité sans lui attribuer de numéro, comme pour `CAP-CORE-002` et `CAP-CORE-005`. `CTR-01` à `CTR-08` sont pris. L'autorité attribue `CTR-09` et arrête, dans le même mouvement, la **règle d'attribution** : les numéros de contrat sont donnés dans l'ordre chronologique d'adoption de la conception qui les définit, jamais par correspondance avec le numéro de la capacité servie, et ne sont jamais réemployés. Un numéro attribué sans règle n'aurait fait que différer la collision au premier chantier parallèle.

**2.2 — Numérotation des invariants : séquence unique à l'échelle du Core.**
Les invariants forment une suite unique, `INV-1`, `INV-2`, …, et non des suites par capacité. Chaque invariant reste attribué à la capacité qui l'a introduit, mais son numéro est unique et n'est jamais réemployé, fût-ce après abandon.

Le motif est la citation. Une numérotation par capacité obligerait à écrire « `INV-3` de `CAP-CORE-007` », et l'usage abrège toujours ; la citation abrégée deviendrait fausse. La séquence unique rend toute citation exacte pour toujours. Elle montera haut — inconvénient sans gravité là où l'ambiguïté serait fatale. Cet arbitrage entérine un usage déjà établi : `INV-1`, `INV-4` et `INV-5` sont cités sans mention de portée dans le code adopté par `ADOPTION-0029` et `ADOPTION-0030`.

**2.3 — Évolution de deux colonnes de `norme` : succession de schéma, non réécriture.**
`norme.rang` et `norme.reference` évoluent (conception adoptée, Article 11). L'autorité l'accorde sous la forme d'une **succession** : le Titre II de `ADOPTION-0028` n'est pas réécrit et **demeure exact à sa date** ; le présent acte déclare l'état de schéma qui lui succède, dans la seule mesure des deux colonnes. C'est le mécanisme que le corpus applique déjà à ses empreintes.

L'index étant dérivé et reconstruit à chaque ingestion (`INV-5`), la succession ne migre ni ne perd aucune donnée. La colonne `chemin` demeurant inchangée, **aucune résolution possible avant la succession ne devient impossible après**.

**2.4 — Adoption de la conception : accordée, après révision.**
Le projet soumis portait encore une désignation provisoire (`CTR-06bis`) en attente de l'arbitrage 2.1. L'autorité n'adopte pas un texte portant une béquille : la conception a été révisée pour incorporer les arbitrages 2.1 à 2.3 et 2.5, et c'est le texte révisé, d'empreinte `e8ca07b4465adcab52bcdfc0b954c3a7c38f8359`, qui est adopté.

**2.5 — Sources `NON ÉTABLI` : maintenues, mais exposées.**
`GENESIS-003` et `GENESIS-005` demeurent `NON ÉTABLI` (Article 6 du registre des sources). Leur qualification appartient à une autorité compétente (`SOURCES-0001`, Art. 21) et n'est ni déléguée à l'agent, ni déduite par un programme. Elles seront néanmoins **inscrites au registre et restituées comme `NON ÉTABLI`**, et non omises : une source non qualifiée qui disparaît de l'index peut reparaître plus tard avec un statut présumé, tandis qu'une source déclarée non qualifiée demeure visible et interrogeable. L'ignorance déclarée est l'état sûr.

## Article 3 — Exigence nouvelle : la contre-épreuve de falsification

La conception adoptée pose à son Article 19 une exigence qui n'existait pas dans le Core :

> Tout test livré au titre d'un niveau de preuve `P3` sera accompagné d'une **contre-épreuve de falsification** — une altération délibérée du corpus, sur copie hors dépôt, dont il est constaté qu'elle fait échouer le test. L'acte d'adoption déclarera les deux exécutions : celle qui passe et celle qui échoue.

Cette exigence est la leçon de `ADOPTION-0031` convertie en règle. Le défaut rectifié par cet acte — une preuve qui relisait ses propres constantes — aurait été impossible sous cette règle. L'autorité l'adopte pour l'ensemble des capacités à venir, et non pour la seule `CAP-CORE-006`.

## Article 4 — Limite de restitution constatée

L'adoption du présent acte fait passer `CAP-CORE-006` à l'état de conception `CONÇUE`. Le service `CTR-04`, dans son état adopté par `ADOPTION-0031`, **ne restituera pas cet état**.

La cause est identifiée : la correspondance entre une capacité et son document de conception est inscrite en dur dans `Ingestion.php` (lignes 208-209), qui ne connaît que `CAP-CORE-007`. Les états de capacité sont bien dérivés du registre depuis `ADOPTION-0031` — la circularité est levée — mais leur rattachement demeure codé.

Ce défaut relève de `INV-7` (identité canonique) et sera traité par l'incrément de code de `CAP-CORE-006`. Il est constaté ici, au Titre XVIII, Article 111 du registre des capacités, afin qu'il ne se découvre pas plus tard comme un silence. L'autorité adopte en connaissance de cette limite.

## Article 5 — Effets

- `CAP-CORE-006` passe en conception `CONÇUE` ; implémentation `NON COMMENCÉE`, exploitation `INACTIVE`, preuve `P1` inchangées. Ce fait est constaté au Titre XVIII de `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001`, sans réécriture d'aucun article antérieur.
- `CTR-09` est attribué et la règle d'attribution des numéros de contrat est établie.
- La séquence unique des invariants est établie ; `INV-7` à `INV-11` sont introduits.
- L'exigence de contre-épreuve (Article 3) s'applique désormais à toute preuve `P3` du Core.

Cette adoption ne livre aucun code, ne rend `CAP-CORE-006` ni implémentée ni active, n'admet aucun produit, n'accepte aucun risque nouveau, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3.a) et ne constate pas `G0`.

## Réserve d'audit maintenue

La conception est rédigée par l'agent qui la codera et la vérifiera, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b). `ADOPTION-0031` a mesuré le coût réel de cette réserve. L'exigence de contre-épreuve de l'Article 3 en est la réponse technique ; elle ne remplace pas un auditeur indépendant, qui demeure dû.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/conception/CONCEPTION-CAP-CORE-006-REGISTRE-DES-SOURCES-0001.md` | Texte adopté (création) | `e8ca07b4465adcab52bcdfc0b954c3a7c38f8359` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XVIII (Articles 109-112) — `CAP-CORE-006` en conception `CONÇUE` | `88932a3f7b314c6cdf4ec6a9cd5fb84efbf5629f` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ajout de la ligne `ADOPTION-0032` | `858a3b2eb130a633a148e8b2caa2c6f9e56d0302` |

Ces empreintes remplacent, pour les deux registres et pour eux seuls, celles déclarées par `ADOPTION-0031`, qui demeurent exactes à leur date. Aucune ligne ou article préexistant n'a été réécrit.

## Vérification des deux gardes

- **Garde 1** (`outils/verifier-integrite.py`) : `VÉRIFIÉE`, code de sortie `0`.
- **Garde 2** (`core/registre-normes/tests/temporel_p3.php`) : `ÉTABLIE`, code de sortie `0`.

Aucune contre-épreuve de falsification n'est déclarée au présent acte : il n'adopte aucun code et ne prétend à aucun niveau de preuve nouveau. L'exigence de l'Article 3 s'appliquera au premier acte adoptant du code au titre de `CAP-CORE-006`.

## Publication

La fusion `--no-ff` dans `main` **est** l'acte d'adoption ; elle appartient exclusivement à l'autorité et n'est pas exécutée par l'agent.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`
- **Mention :** LU ET ADOPTÉ
