# REGISTRE D'ADOPTION — ADOPTION-0033
## Rectification d'intégrité : empreinte inexistante déclarée par `ADOPTION-0018`, et extension du contrôle `C5` aux feuilles de statut

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-rectification-integrite-0033`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte est un **acte de rectification d'intégrité**, second du genre après `ADOPTION-0021`. Il procède d'un audit de cohérence conduit sur l'ensemble de `genesis-ii/` à la demande de l'autorité, avant la poursuite des travaux de `CAP-CORE-006`, lesquels sont suspendus le temps de cette correction.

Il ne réécrit le corps d'aucun texte adopté. Toute correction se fait par déclaration nouvelle, l'ancienne demeurant exacte à sa date ou, lorsqu'elle ne l'a jamais été, étant expressément constatée comme telle.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les quatre volets de rectification décrits ci-après.

---

# TITRE I — LE DÉFAUT CONSTATÉ

## Article 1 — Une empreinte qui ne désigne rien

`REGISTRE-INITIAL-DECISIONS-0001-STATUT.md`, feuille de statut adoptée par `ADOPTION-0018` le 26 juillet 2026, déclare :

```
- **Empreinte Git du contenu adopté :** `191bcd402a5719b415dcf89f1a9152993a0a3557`
```

Cet objet **n'existe pas et n'a jamais existé dans le dépôt**. `git cat-file` ne le connaît pas. Le fichier `genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md` ne compte qu'un seul commit depuis sa création (`55cbe4f`, 26 juillet 2026) et son contenu a toujours porté l'empreinte `0d34a205f9ffbe5f2d54152635840bd74c89c2df`.

Un acte d'adoption identifiait donc son contenu adopté par une référence ne pointant sur rien.

## Article 2 — Ce qui n'est pas en cause

Le **contenu** du texte n'est pas en cause. Le fichier n'a jamais été modifié depuis son unique commit. Il n'y a eu ni altération, ni substitution, ni perte. Le défaut porte sur la déclaration, non sur le texte déclaré.

L'autorité constate en conséquence que le contenu adopté par `ADOPTION-0018` est celui, inchangé, dont l'empreinte réelle est `0d34a205f9ffbe5f2d54152635840bd74c89c2df`, et que l'empreinte `191bcd40…` procède d'une erreur de transcription à la rédaction de la feuille de statut.

## Article 3 — Pourquoi le défaut est demeuré invisible trois jours

Le corpus a porté deux mécanismes d'intégrité successifs :

| Mécanisme | Textes couverts | Lu par `C5` avant le présent acte |
|---|---|---|
| Feuilles `X-STATUT.md` (ère `ADOPTION-0001` à `0019`) | 18 textes, dont les Lois et les gouvernances | **Non** |
| `Constat d'exécution` en tableau (depuis `ADOPTION-0020`) | 65 fichiers | Oui |

`outils/verifier-integrite.py` ne comportait aucune référence aux feuilles de statut. **Soixante-neuf fichiers sur cent trente-quatre demeuraient hors de toute vérification d'empreinte**, dont `CORE-LAWS-0001`, `GOVERNANCE-0001`, `SOURCES-0001`, `ACTE-0001` et les trente-deux actes d'adoption eux-mêmes.

Le défaut de l'Article 1 n'était donc pas dissimulé : il était simplement hors de portée du seul instrument capable de le voir.

---

# TITRE II — LES QUATRE VOLETS DE RECTIFICATION

## Article 4 — Volet 1 : rectification de la déclaration d'`ADOPTION-0018`

Le constat d'exécution du présent acte déclare, pour `genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md`, l'empreinte réelle et vérifiée `0d34a205f9ffbe5f2d54152635840bd74c89c2df`.

Cette déclaration, portée par un acte de rang 33, dépasse celle de la feuille de statut. `ADOPTION-0018` et sa feuille de statut **ne sont pas réécrits** : ils demeurent au dépôt tels qu'adoptés, et le présent Article constate que leur déclaration d'empreinte était erronée dès l'origine, sans effet sur le contenu adopté.

## Article 5 — Volet 2 : extension du contrôle `C5` aux feuilles de statut

`outils/verifier-integrite.py` est étendu pour lire les feuilles de statut, y relever l'empreinte déclarée et la rattacher au texte compagnon du même répertoire.

Cette extension **n'introduit aucun cas particulier** : les déclarations de feuilles de statut reçoivent le rang `0`, si bien que toute déclaration ultérieure portée par un acte d'adoption les dépasse automatiquement, par le mécanisme de rang que `C5` appliquait déjà. Le lien entre une feuille et son texte n'est établi que lorsqu'il est **univoque** ; à défaut, la déclaration demeure non contrôlée plutôt que rapportée à un texte incertain.

**Effet mesuré :** le contrôle passe de **65 à 82 fichiers vérifiés**. Les quatorze textes fondateurs entrent sous garde et sont constatés **intacts** : `CORE-LAWS-0001`, `GOVERNANCE-0001`, `-0002`, `-0003`, `SOURCES-0001`, `LEXICON-0001`, `CORE-CHARTER-0001`, `CORE-ATLAS-0001`, `PRODUCT-CONSTITUTION-0001`, `AI-`, `DATA-`, `ENGINEERING-` et `SECURITY-GOVERNANCE-0001`, `MODELES-INITIAUX-CYCLE-DECISION-0001`.

Le contrôle demeure **indépendant de l'application** (`ADOPTION-0027`, Art. 4) : il reste un programme Python distinct, ne partageant ni code ni exécution avec le service `CTR-04`.

## Article 6 — Volet 3 : les supersessions de feuilles de statut

Trois feuilles de statut déclarent une empreinte que leur texte ne porte plus, une évolution ultérieure ayant été régulièrement adoptée. Ces écarts sont **légitimes** et sont désormais absorbés par le mécanisme de rang, sans intervention.

| Texte | Feuille déclare | Empreinte réelle | Déclarée par |
|---|---|---|---|
| `REGISTRE-INITIAL-AUTORITES-MANDATS-0001.md` | `060de10c…` | `80643391…` | `ADOPTION-0022` |
| `REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | `103f6366…` | `88932a3f…` | `ADOPTION-0032` |
| `REGISTRE-INITIAL-PRODUITS-0001.md` | `1aa3305c…` | `61eb2762…` | `ADOPTION-0023` |

Aucune de ces trois feuilles n'est modifiée. Le corpus ne se contredit plus en silence : la supersession est désormais **constatée par l'instrument** à chaque exécution, ce qui vaut mieux qu'une mention manuscrite susceptible de se périmer à son tour.

## Article 7 — Volet 4 : déclaration d'une empreinte omise

`CONCEPTION-LIVRAISON-LARAVEL-CTR-04-0001.md`, adopté par `ADOPTION-0030`, y était identifié par le commit de sa branche mais **son empreinte de fichier n'a jamais été déclarée** — seul document de conception du corpus dans ce cas, et de ce fait seul à demeurer hors de `C5`. Le constat d'exécution du présent acte la déclare.

---

# TITRE III — CE QUE L'AUDIT A ÉCARTÉ

## Article 8 — Constats non retenus

L'instrument d'audit a produit deux cent quatre-vingt-trois constats bruts, dont **deux cent soixante-neuf ont été écartés** après vérification comme artefacts de l'instrument lui-même :

- deux cent trente et un « renvois internes brisés » : les renvois du type « la séquence de l'Article 63 » visent un article d'un **autre** texte, ce que l'heuristique ne savait pas distinguer ;
- trois « articles en double » : il s'agit des `Article 4 bis`, `3 ter`, `6 bis`, technique légistique d'insertion sans renumérotation — le corpus appliquait déjà correctement la discipline d'ajout, et c'est une bonne pratique qui fut lue comme un défaut ;
- vingt-deux « actes sans date » : libellé `Date d'adoption` au lieu de `Date`. Le contrôle correct établit **zéro écart** de date entre les trente-deux actes et l'index ;
- treize « feuilles de statut sans texte » : convention de nommage, non absence de texte.

Ce Titre est inscrit parce qu'un audit qui ne déclare pas ce qu'il a écarté ne peut pas être contrôlé.

## Article 9 — Constats mineurs demeurant ouverts

Ne sont pas corrigés par le présent acte, et demeurent signalés :

- `REGISTRE-INITIAL-SOURCES-0001`, Article 7, énonce « dix-neuf textes adoptés » ; le corpus en compte davantage. Sa mise à jour relève de `CAP-CORE-006` et de la qualification des sources, qui appartient à l'autorité.
- Douze actes sur trente-deux portent une section « Autorité d'adoption » ; quatre actes ne portent pas de date lisible par machine. Hétérogénéité de forme, sans conséquence sur l'intégrité.
- Les feuilles `X-STATUT.md` ne partagent pas leur radical exact avec le texte qu'elles décrivent. Le présent acte s'en accommode par une résolution exigeant l'unicité.

---

# TITRE IV — EFFETS ET RÉSERVES

## Article 10 — Effets

- Le contenu adopté par `ADOPTION-0018` est identifié, sans ambiguïté et sans réécriture, par l'empreinte `0d34a205f9ffbe5f2d54152635840bd74c89c2df`.
- Le contrôle `C5` couvre désormais 82 fichiers au lieu de 65. Les textes fondateurs sont sous garde et constatés intacts.
- Aucune capacité n'est rendue implémentée, admise ou active. `G0` n'est pas constatée.

## Article 11 — Ce que cet acte enseigne

Le défaut de l'Article 1 a traversé sept jours et trente-deux actes sans être vu, non parce qu'il était caché, mais parce que l'instrument chargé de le voir ne regardait pas là. Une garde ne vaut que par son périmètre, et un périmètre non déclaré est un angle mort.

Il a été relevé par un audit conduit à la demande expresse de l'autorité, non par une garde. C'est le troisième constat consécutif — après `ADOPTION-0031` — établissant que la fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3.b) laisse passer ce qu'aucun test ne cherche. La réserve demeure entière.

## Article 12 — Non-effet

Le présent acte ne modifie le corps d'aucun texte adopté, n'admet aucun produit, n'accepte aucun risque nouveau, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3.a) et ne constate pas `G0`.

---

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `outils/verifier-integrite.py` | Extension de `C5` aux feuilles de statut (Volet 2) | `dfc06afb708ae4bf2ba7e0ef32450d7224823008` |
| `genesis-ii/registres/decisions/REGISTRE-INITIAL-DECISIONS-0001.md` | Empreinte réelle déclarée, rectifiant `ADOPTION-0018` (Volet 1) | `0d34a205f9ffbe5f2d54152635840bd74c89c2df` |
| `genesis-ii/conception/CONCEPTION-LIVRAISON-LARAVEL-CTR-04-0001.md` | Empreinte omise par `ADOPTION-0030`, désormais déclarée (Volet 4) | `dd1083f8ada2422f32d9e98fbbdd7ffd32628b30` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ajout de la ligne `ADOPTION-0033` | `4a53e0da86c4699054d7e81668b209c2b809d363` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date — à la seule exception de celle rectifiée à l'Article 1, qui ne l'a jamais été et dont le présent acte constate l'inexactitude d'origine.

## Vérification des deux gardes

- **Garde 1** (`outils/verifier-integrite.py`, étendue) : `VÉRIFIÉE`, code de sortie `0`, 82 fichiers vérifiés.
- **Garde 2** (`core/registre-normes/tests/temporel_p3.php`) : `ÉTABLIE`, code de sortie `0`, inchangée par le présent acte.

Conformément à l'exigence de l'Article 3 de `ADOPTION-0032`, la garde étendue a été éprouvée par sa capacité à échouer : exécutée avant rectification, elle a **signalé les deux écarts** et rendu le code `1` ; c'est cette exécution en échec qui donne sa valeur à l'exécution en succès.

## Publication

La fusion `--no-ff` dans `main` **est** l'acte d'adoption ; elle appartient exclusivement à l'autorité et n'est pas exécutée par l'agent.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 28 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`
- **Mention :** LU ET ADOPTÉ
