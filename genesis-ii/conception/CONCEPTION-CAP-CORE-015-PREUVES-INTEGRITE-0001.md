# CONCEPTION-CAP-CORE-015-PREUVES-INTEGRITE-0001
## Projet de conception de la capacité souveraine `CAP-CORE-015` — Preuves d'intégrité

> **PROJET D'ACTE — soumis à l'autorité de proposition. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0043`) n'a pas été signé.** Ce document conçoit ; il n'installe aucune clé, ne signe rien et ne franchit aucune frontière d'accès réservé.

## Nature et rattachement

Le présent acte est l'**étape de conception** de la séquence de l'Article 63 : invariants, données, contrats, menaces, contrôles, migrations et preuves **avant** tout choix technologique. Il raffine la fiche de `CAP-CORE-015` (Article 50 du registre des capacités), qui lui attribue le contrat `CTR-10` — « émission, vérification, révocation et attestation d'intégrité ».

Rédigé par SIRR (Claude, `AGENT-IA-002`) sous instruction (`ADOPTION-0024`, Art. 3 : conçoit et vérifie, ne décide ni ne signe).

## Article 1 — Pourquoi cette capacité arrive maintenant

Six capacités sont codées et prouvées. Toutes reposent, sans exception, sur une même affirmation : **l'empreinte déclarée dans un acte identifie exactement le texte adopté**. `INV-1` porte cette affirmation depuis `ADOPTION-0029`, et chaque garde du dépôt la présuppose.

Cette affirmation n'a jamais été fondée. Elle a été **pratiquée**. `CAP-CORE-015` est la capacité qui la fonde, ou qui en montre les limites.

## Article 2 — Trois constats mesurés, préalables à toute conception

Les chiffres ci-dessous sont relevés sur le corpus au 28 juillet 2026, `main` à `2c855e9`. Ils ne sont pas des estimations.

**2.1 — Un seul algorithme, et il est affaibli.** Les 87 objets canoniques porteurs d'une empreinte déclarée la portent tous en SHA-1, et en SHA-1 seulement : `git hash-object` calcule `sha1("blob " · taille · "\0" · contenu)`. Or la résistance aux collisions de SHA-1 est publiquement rompue depuis 2017, et une attaque à préfixe choisi est démontrée depuis 2020.

La portée exacte doit être dite, sans dramatisation : ce qui est rompu est la résistance aux **collisions** — fabriquer deux contenus de même empreinte —, non la résistance à la **préimage** — fabriquer un contenu correspondant à une empreinte déjà publiée. Un adversaire ne peut donc pas forger aujourd'hui un faux texte correspondant à une empreinte déjà inscrite dans un acte signé. Il pourrait, en revanche, faire adopter un texte préparé en même temps que son jumeau malveillant, et substituer ensuite l'un à l'autre sans que l'empreinte change. Cette menace suppose un agent capable de soumettre le texte à l'adoption — c'est-à-dire, dans l'état actuel du Core, **l'agent qui rédige les projets**. Elle n'est pas théorique ; elle est structurelle.

**2.2 — Les actes eux-mêmes ne portent aucune preuve.** Quarante-trois actes d'adoption sont sur disque. **Aucun** ne fait l'objet d'une empreinte déclarée par un autre acte.

Le contrôle `C5` vérifie les empreintes que les actes **déclarent**, c'est-à-dire les objets vers lesquels ils pointent. Il ne vérifie jamais l'acte qui déclare. Le corpus sait donc prouver tout ce dont il parle, et rien de ce qui parle. La source primaire — celle dont tout le reste tire son autorité — est le seul objet sans preuve déclarée.

Ce que cela signifie exactement, et pas davantage : les actes sont versionnés dans Git, `main` ne se réécrit pas (`CLAUDE.md`, règle 4), et l'historique publié constitue une protection réelle. Mais cette protection est **extérieure au système de preuve du corpus** : elle repose sur une discipline d'exploitation et sur l'hébergeur, non sur une déclaration que le corpus pourrait vérifier lui-même. Un acte altéré passerait `C5` sans un mot, ses déclarations altérées étant vérifiées contre les fichiers altérés.

**2.3 — La couverture est partielle et personne ne la mesure.** Le corpus compte 148 fichiers `.md` sous `genesis-ii/`, dont 87 portent une empreinte déclarée. Les 61 restants — les 43 actes, les documents compagnons de statut, les documents d'accueil — n'en portent aucune. Cette proportion n'est aujourd'hui exposée nulle part : rien, dans le Core, ne dit quelle part de lui-même est prouvée.

## Article 3 — Périmètre, en trois objets ordonnés

1. **La politique de preuve** — quels algorithmes sont admis, lequel fait foi, comment on en ajoute un sans réécrire le passé.
2. **Le contrat `CTR-10`** — émettre, vérifier, attester, et restituer la politique en vigueur.
3. **L'inventaire des preuves racines** — pour chaque objet canonique : une preuve est-elle déclarée, par quel algorithme, concorde-t-elle. C'est l'une des quatre preuves `G0` attendues par l'Article 50.

Ce que ce périmètre exclut est traité à l'Article 21, et l'exclusion y est motivée.

---

# TITRE I — INVARIANTS

## Article 4 — Numérotation

La règle de l'Article 2 de `CONCEPTION-CAP-CORE-006` s'applique : séquence unique à l'échelle du Core, jamais réemployée. Le dernier invariant attribué est `INV-30` (`ADOPTION-0040`). La présente conception introduit `INV-31` à `INV-35`.

## Article 5 — `INV-31` — Empreinte nommée

**Une empreinte sans algorithme nommé n'est pas une preuve.**

Une chaîne hexadécimale de quarante caractères est un nombre. Elle ne devient une preuve qu'accompagnée de la fonction qui la produit et de la convention d'encodage de l'objet — pour Git, l'en-tête `blob · taille · NUL` précédant le contenu, sans lequel le calcul ne se reproduit pas.

Le corpus déclare aujourd'hui ses empreintes nues, l'algorithme étant sous-entendu par l'usage de `git hash-object`. L'usage n'est pas une déclaration. Toute empreinte émise ou restituée par `CTR-10` nomme son algorithme ; les déclarations antérieures sont **interprétées** comme `git-sha1` — interprétation inscrite ici pour être contestable, plutôt que laissée tacite.

## Article 6 — `INV-32` — Double conservation, par algorithmes indépendants

**Un objet canonique dont l'intégrité repose sur un seul algorithme n'est protégé que par la solidité de celui-ci.**

L'Article 50 exige la « double conservation » parmi ses contrôles requis. Elle est ici définie : tout objet canonique doit pouvoir être vérifié par **au moins deux algorithmes indépendants**, dont l'un au moins n'est pas affaibli.

Cette exigence est un **objectif mesuré, non un acquis**. Aucune déclaration du corpus ne porte aujourd'hui de seconde empreinte, et il est impossible d'en ajouter aux actes déjà signés sans les réécrire — ce que la règle d'ajout seul interdit. Le service ne peut donc pas rendre la double conservation vraie ; il peut la **calculer** et **chiffrer l'écart**. C'est ce qu'il fera, et c'est tout ce qu'il prétendra faire.

## Article 7 — `INV-33` — Migration par ajout, jamais par réécriture

**Ajouter un algorithme est un ajout. Aucune empreinte déclarée n'est jamais réécrite ni retirée, fût-elle produite par un algorithme révoqué.**

Une empreinte périmée demeure vraie à sa date : elle atteste ce qui a été constaté au moment où on l'a constaté. La retirer effacerait une constatation, non une erreur. La migration d'algorithme, que l'Article 50 exige de prévoir, s'opère donc en déclarant un algorithme supplémentaire pour les objets à venir, jamais en corrigeant les objets passés.

Corollaire : le corpus portera durablement des objets à preuve unique et des objets à preuve double. Cette hétérogénéité est le prix de l'ajout seul, et elle est assumée.

## Article 8 — `INV-34` — Une attestation non signée le déclare

**Le service atteste ; il ne certifie pas.**

Une attestation rendue par `CTR-10` constate qu'à l'instant du calcul, le contenu d'un fichier produisait telle empreinte, concordante ou non avec telle déclaration. Elle n'est **pas signée** : elle ne prouve donc ni son origine, ni son moment, et quiconque exécute le service peut en produire une autre.

Chaque attestation porte cette limite **dans son corps**, non dans une note de bas de page. Une attestation qui tairait qu'elle n'est pas signée serait plus dangereuse qu'aucune attestation, car elle serait citée comme une preuve d'origine.

## Article 9 — `INV-35` — Vérification par recalcul

**L'empreinte réelle est recalculée depuis l'objet, jamais lue depuis l'index.**

`INV-1` posait l'exigence pour les normes ; elle est ici généralisée à tout objet canonique. Un index qui se vérifierait lui-même ne vérifierait rien : il confirmerait sa propre copie. La comparaison n'a de valeur que si l'un de ses deux termes provient du fichier, à l'instant de la question.

---

# TITRE II — DONNÉES

## Article 10 — Principe

L'index demeure **dérivé et reconstructible** (`INV-5`). Aucune donnée de preuve n'est autoritative : la source de vérité reste le fichier, et la déclaration reste l'acte signé.

## Article 11 — Algorithme de preuve

```
algorithme (
    code        text PRIMARY KEY,   -- 'git-sha1', 'sha256'
    libelle     text NOT NULL,
    statut      text NOT NULL,      -- 'ADMIS' | 'AFFAIBLI' | 'RÉVOQUÉ'
    fait_foi    integer NOT NULL,   -- 1 pour l'algorithme des déclarations du corpus
    motif       text                -- pourquoi ce statut, en clair
)
```

`statut` porte la **révocation** que l'Article 50 attend du contrat, sans clé ni signature : révoquer un algorithme est une décision inscrite, pas une opération cryptographique. Le vocabulaire est distinct de celui des normes et de celui des capacités (`INV-10`).

Deux algorithmes sont proposés à l'adoption :

| Code | Statut proposé | `fait_foi` | Motif |
|---|---|---|---|
| `git-sha1` | `AFFAIBLI` | oui | Résistance aux collisions rompue (2017, 2020) ; demeure l'algorithme de **toutes** les déclarations du corpus, donc celui qui fait foi tant qu'aucun acte n'en déclare un autre |
| `sha256` | `ADMIS` | non | Non affaibli ; calculable sur tout objet ; aucune déclaration du corpus ne le porte encore |

Qu'un algorithme `AFFAIBLI` fasse foi est un état inconfortable et **exact**. Le déclarer `RÉVOQUÉ` rendrait invalides les 87 déclarations en vigueur et le corpus entier avec elles ; le déclarer `ADMIS` mentirait. L'inconfort est le constat juste.

## Article 12 — Empreinte constatée

```
empreinte (
    id                 …,
    objet              text NOT NULL,   -- chemin canonique dans le dépôt
    algorithme_code    text NOT NULL REFERENCES algorithme(code),
    valeur             text NOT NULL,
    origine            text NOT NULL,   -- 'DÉCLARÉE' | 'CALCULÉE'
    adoption_reference text             -- l'acte déclarant, si origine = DÉCLARÉE
)
```

`origine` sépare ce que le corpus **affirme** de ce que le service **constate**. Les confondre reviendrait à laisser un calcul se faire passer pour une déclaration signée — soit exactement la menace `M-34`.

## Article 13 — Ce qui n'est pas stocké

Aucune clé, aucun secret, aucune signature, aucun jeton d'horodatage. La frontière de `ADOPTION-0025`, Art. 3.a n'est ni franchie, ni effleurée. Un dépôt qui contiendrait une clé de signature ne prouverait plus rien : il offrirait le moyen de fabriquer la preuve.

---

# TITRE III — CONTRATS

## Article 14 — Les quatre opérations de `CTR-10`

```
politique()
  → { algorithmes: [{ code, libelle, statut, fait_foi, motif }] }
    • restitue l'état d'admission et de révocation des algorithmes.

emettre(chemin)
  → { objet, empreintes: [{ algorithme, valeur }], moment, signee: false }
    • calcule l'empreinte de l'objet par CHAQUE algorithme non révoqué.

verifier(objet?)
  → [ { objet, declaree: {algorithme, valeur, acte} | null,
        calculee: [{algorithme, valeur}], concorde: bool|null,
        couverture: int, verdict } ]
    • 'concorde' vaut null quand rien n'est déclaré : rien n'a été comparé.
    • 'couverture' est le nombre d'algorithmes portés par la DÉCLARATION.

attester(objet)
  → { objet, verdict, preuve: {…}, moment, signee: false, portee: "…" }
    • attestation explicable, non signée, portant sa propre limite (INV-34).
```

## Article 15 — Lecture et attestation seulement

Comme `CTR-04` et `CTR-09`, ce contrat n'expose **aucune** écriture applicative du corpus (`INV-4`). Il ne modifie aucun fichier, n'ajoute aucune déclaration, ne corrige aucune empreinte. Les seules écritures du corpus passent par des actes d'adoption signés.

## Article 16 — Explicabilité

Toute réponse rattache son résultat à sa preuve : quel objet, quel algorithme, quelle déclaration, quel acte. Un verdict sans preuve rattachée est un défaut.

---

# TITRE IV — L'INVENTAIRE DES PREUVES RACINES

## Article 17 — Nature et nécessité

L'Article 50 attend, parmi ses preuves `G0`, un « inventaire des preuves racines ». Il n'existe pas. Sa nécessité est établie par les trois constats de l'Article 2 : rien, aujourd'hui, ne dit quelle part du corpus est prouvée, par quel algorithme, ni ce qui ne l'est pas du tout.

## Article 18 — Forme

`CTR-10` restitue, pour l'ensemble des objets canoniques du corpus :

- le nombre d'objets porteurs d'une empreinte déclarée, et le nombre qui n'en portent aucune ;
- la répartition par algorithme, et le nombre d'objets satisfaisant la double conservation de `INV-32` ;
- la liste nommée des objets **sans preuve déclarée**, les actes d'adoption en tête ;
- le nombre de discordances constatées entre déclaration et recalcul.

L'inventaire est **dérivé**, jamais autoritatif (`INV-5`). Il ne comble aucun écart : il le chiffre et le nomme. Le combler est un acte de l'autorité, non une opération du service.

---

# TITRE V — MENACES

## Article 19 — Menaces retenues

| Réf. | Menace | Ce qui la contient |
|---|---|---|
| `M-32` | Collision SHA-1 : deux textes de même empreinte, l'un adopté, l'autre substitué | `INV-32` — second algorithme indépendant ; écart mesuré tant qu'il n'est pas comblé |
| `M-33` | Acte d'adoption altéré après signature, aucune déclaration ne le couvrant | `INV-35` et l'inventaire de l'Article 18, qui nomme les actes comme objets sans preuve |
| `M-34` | Empreinte calculée présentée comme empreinte déclarée | `INV-31`, `origine` séparant `CALCULÉE` de `DÉCLARÉE` |
| `M-35` | Attestation non signée citée comme preuve d'origine | `INV-34` — la limite est portée dans le corps de l'attestation |
| `M-36` | Algorithme obsolète maintenu par simple inertie | Article 11 — `statut` déclaré, motif en clair, révocation possible par acte |
| `M-37` | Correction d'une empreinte périmée effaçant une constatation ancienne | `INV-33` — ajout seul, jamais réécriture |

## Article 20 — Menace non contenue, et déclarée telle

`M-32` n'est **pas contenue** par le présent périmètre. La double conservation est définie et mesurée ; elle n'est pas atteinte, et ne peut l'être sans que l'autorité déclare des empreintes `sha256` dans des actes à venir.

Le service réduit la menace d'un cran — il rend l'écart visible et chiffré, là où il était invisible. Il ne l'annule pas. Prétendre le contraire serait le genre exact de fausse assurance que cette capacité existe pour empêcher.

---

# TITRE VI — CONTRÔLES ET PREUVES

## Article 21 — Contrôles

- Recalcul systématique depuis le fichier, jamais depuis l'index (`INV-35`).
- Séparation stricte `DÉCLARÉE` / `CALCULÉE` (`INV-31`, `M-34`).
- Chaque attestation porte sa portée et son absence de signature (`INV-34`).
- L'inventaire chiffre la couverture au lieu de la présumer (`INV-32`).

## Article 22 — Preuve `P3` visée, et sa falsification

`CAP-CORE-015` vise `P3 — TESTÉ`, par une garde de comportement propre, conformément à `ADOPTION-0035`, Art. 2.2. Une capacité n'hérite pas de la preuve d'une autre : les gardes de `CTR-04` et `CTR-09` éprouvent des services voisins, non celui-ci.

Cas d'essai : `verifier()` sur un objet déclaré du corpus restitue la concordance ; l'inventaire dénombre les actes d'adoption comme objets sans preuve déclarée ; `attester()` porte `signee: false`.

> **Contre-épreuve obligatoire** (`ADOPTION-0032`, Art. 3) : sur **copie hors dépôt**, le contenu d'un objet déclaré est altéré d'un octet. Il est constaté que la garde échoue. Un test qui ne peut pas échouer ne prouve rien. L'acte d'adoption déclarera les deux exécutions.

## Article 23 — Les gardes demeurent séparées

Le contrôle Python (`outils/verifier-integrite.py`) n'est ni absorbé, ni réécrit dans le cadre applicatif (`ADOPTION-0027`, Art. 4). La duplication du recalcul entre le contrôle `C5` et `CTR-10` est **maintenue à dessein** : deux implémentations indépendantes qui concordent valent mieux qu'une seule mutualisée. C'est particulièrement vrai ici, où l'objet vérifié est la vérification elle-même.

---

# TITRE VII — CE QUE CETTE CONCEPTION NE FAIT PAS

## Article 24 — Frontières infranchissables

**Aucune signature.** Signer suppose une clé privée. Les clés relèvent exclusivement de l'autorité (`ADOPTION-0025`, Art. 3.a) et `CAP-CORE-016` n'est pas conçue. Le Core ne signera rien tant que la gouvernance des clés n'aura pas été adoptée.

**Aucun horodatage de confiance.** Un horodatage vaut par l'autorité qui l'émet. Le Core ne dispose d'aucune horloge de confiance ; il rend le moment de son propre calcul, en le déclarant tel — un fait local, non une attestation temporelle.

**Aucune autorité de signature, aucune chaîne de confiance, aucune révocation de clé.** Ces quatre objets — signature, horodatage, chaîne, révocation de clé — sont ceux que l'Article 50 nomme parmi ses données minimales et que la présente conception laisse **explicitement vides**. Les laisser vides et le dire vaut mieux que les remplir de valeurs sans autorité.

## Article 25 — Ce qui reste hors périmètre

- L'ajout effectif d'empreintes `sha256` aux déclarations du corpus : acte de l'autorité.
- La déclaration des empreintes des actes d'adoption eux-mêmes, qui comblerait `M-33` : acte de l'autorité, dont la forme reste à arbitrer (un acte ne peut déclarer sa propre empreinte).
- La conservation externe des preuves, l'export et la restauration : relèvent de `CAP-CORE-019`.

---

# TITRE VIII — RÉSERVE D'AUDIT

## Article 26 — Rappel, et ce que cette capacité y ajoute de particulier

La conception est rédigée par l'agent, sous une fonction AUDIT non indépendante (`ADOPTION-0025`, Art. 3). Le concepteur ne s'audite pas.

La réserve pèse ici plus lourd qu'ailleurs, et il faut le dire. La menace `M-32` suppose un agent capable de soumettre à l'adoption un texte préparé avec son jumeau. Dans l'état actuel du Core, cet agent, c'est **le rédacteur de la présente conception**. Le document qui décrit la menace est écrit par celui qui serait en position de l'exécuter.

Cela ne rend pas la conception fausse. Cela rend l'AUDIT indépendant plus nécessaire ici que pour toute capacité précédente, et la double conservation moins théorique qu'elle n'en a l'air.

---

# TITRE IX — DÉCISIONS RÉSERVÉES À L'AUTORITÉ

## Article 27 — Points soumis à l'autorité

1. **Algorithmes admis et leur statut** — `git-sha1` déclaré `AFFAIBLI` mais faisant foi ; `sha256` déclaré `ADMIS`. La combinaison « affaibli et faisant foi » est un constat, non un confort.
2. **Périmètre obligatoire de la preuve** — quels objets doivent porter une empreinte déclarée. La présente conception propose : tout texte adopté, **et les actes d'adoption eux-mêmes**, ce que le corpus ne fait pas aujourd'hui.
3. **Forme de la déclaration future** — proposition : `algorithme:valeur`, l'empreinte nue demeurant lue comme `git-sha1` (`INV-31`).

## Article 28 — Décisions demeurant ouvertes

Autorités de signature, horodatage de confiance, rotation et succession des clés : ouvertes, et le demeurent tant que `CAP-CORE-016` n'est pas conçue. L'Article 50 les attend parmi les preuves `G0` ; elles ne seront pas fournies par le présent incrément.

## Article 29 — Non-effet

La présente conception ne rend `CAP-CORE-015` ni admise, ni active, n'installe aucune clé, ne signe rien, ne modifie le corps d'aucun texte adopté, n'accepte aucun risque nouveau et ne constate pas `G0`.
