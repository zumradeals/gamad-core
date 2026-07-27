# CONCEPTION-CAP-CORE-007-REGISTRE-DES-NORMES-0001
## Projet d'acte de conception de la capacité souveraine `CAP-CORE-007` — Registre des normes

> **PROJET D'ACTE — soumis à l'autorité de proposition. Non exécuté, non adopté, non signé tant qu'un registre d'adoption distinct (pressenti `ADOPTION-0026`) n'a pas été signé par l'autorité compétente.** Le présent document conçoit ; il ne code rien, ne rend rien opérationnel et ne fige aucun choix technologique.

## Nature et rattachement

Le présent acte est le **premier acte de conception du codage canonique** ouvert par le constat de `G0` (`ADOPTION-0025`, signé le 27 juillet 2026). Il porte sur `CAP-CORE-007 — Registre des normes`, dont la fiche figure à l'Article 42 de `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md`.

Il est rédigé par SIRR (Claude, `AGENT-IA-002`), sous instruction, conformément à sa mission bornée par `ADOPTION-0024`, Article 3 : **il conçoit et vérifie ; il ne constate, ne décide ni ne signe.** L'ouverture d'une branche de code canonique et l'adoption du présent acte relèvent de la fonction d'ingénierie `FCT-CORE-009`, exercée à titre transitoire par l'autorité de proposition (`ADOPTION-0022`).

## Article 1 — Pourquoi `CAP-CORE-007` en premier

Trois raisons, toutes tirées des textes adoptés :

1. **C'est une racine, et la plus avancée.** `CAP-CORE-007` est de criticité `RACINE` et appartient à l'ensemble de référence de l'Article 61 (sources, normes, autorité, identité, intégrité, cartographie). Sa preuve `G0` attendue (Article 42) est *« un contrôle automatique ou reproductible de cohérence entre texte, statut, adoption et index »* — or ce contrôle existe déjà : `outils/verifier-integrite.py`. Le premier module ne part pas de zéro ; il **promeut ce contrôle en premier service canonique du Core**.
2. **Il ne dépend d'aucune réserve ouverte.** Contrairement à `CAP-CORE-001` (Identity Registry), qui dépend de l'authentification puis des secrets (`CAP-CORE-016`) — donc de l'inventaire des accès que `ADOPTION-0025`, Article 3.a, garde exclusivement entre les mains de l'autorité — le Registre des normes se bâtit entièrement à partir d'objets déjà présents dans le dépôt. Aucun secret, aucune identité, aucun compte externe n'est requis.
3. **Il rend tout le reste digne de confiance.** Un Registre des normes qui garantit qu'un texte adopté n'a pas été altéré, que son statut est exact et que l'index ne ment pas, est le socle sur lequel les capacités suivantes pourront s'appuyer sans avoir à re-vérifier les fondations.

## Article 2 — Ordre de conception imposé par l'Article 63

Conformément à l'Article 63 du registre des capacités — *« toute conception devra commencer par les invariants, données, contrats, menaces, contrôles, migrations et preuves avant de figer des choix technologiques »* — le présent acte est structuré dans cet ordre exact. Le choix technologique (langage, cadre, base de données) fait l'objet du Titre VII et n'est **pas** tranché ici : il relèvera d'une décision distincte, postérieure à l'adoption de la présente conception.

## Article 3 — Ce que cet acte ne fait pas

Il ne rend `CAP-CORE-007` ni implémentée, ni admise, ni active (états `NON COMMENCÉE` / `INACTIVE` inchangés au sens des Articles 14-15 du registre des capacités). Il n'admet aucun produit, ne nomme aucun opérateur permanent, n'accepte aucun risque nouveau, ne fige aucune technologie et ne modifie le corps d'aucun texte adopté.

---

# TITRE I — INVARIANTS

Les invariants sont la part de la conception qui **survit au changement de langage, de cadre et de base**. Ils sont énoncés en premier parce qu'ils sont ce que le code devra préserver, quel que soit le stack retenu au Titre VII.

## Article 4 — `INV-1` — Empreinte exacte

Toute version canonique d'une norme est identifiée par l'empreinte Git exacte de son contenu (`git hash-object` sur le fichier). Le lien entre `(référence, version)` et le contenu est **immuable** : modifier le contenu produit une empreinte différente, donc une autre version. Cet invariant applique la « Règle d'intégrité » finale du registre des capacités et le contrôle exigé à l'Article 42.

## Article 5 — `INV-2` — Statut séparé du corps

Le statut d'une norme (`EN VIGUEUR`, `AMENDÉ`, `REMPLACÉ`, `ABROGÉ`…) n'est **jamais** porté par modification du corps du texte adopté. Il est tenu dans un enregistrement distinct. C'est la méthode déjà éprouvée dans le corpus : fichiers `…-STATUT.md` et Titres additifs, jamais de réécriture d'un article adopté. Le Registre des normes formalise cette séparation ; il ne l'invente pas.

## Article 6 — `INV-3` — Historique non réécrit

Aucun changement d'état ne supprime un état antérieur (Articles 30 et 60 du registre des capacités). Le Registre est **en ajout seul** : il doit permettre de reconstruire l'état d'une norme — version en vigueur, statut, autorité — applicable à **une date donnée** (Article 10 du registre des capacités). C'est la condition du contrat `CTR-04`.

## Article 7 — `INV-4` — Adoption distincte de la publication

Une norme n'a force canonique que par un acte d'adoption exprès et signé, inscrit au Registre des adoptions. Un commit, une fusion ou une publication ne valent pas adoption (Article 20 du registre des capacités ; risque « adoption tacite » de l'Article 42). Le Registre des normes **refuse par construction** de tenir pour en vigueur une norme dépourvue d'acte d'adoption signé.

## Article 8 — `INV-5` — Index dérivé, jamais autoritatif

L'index central (`genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md`) est une **vue dérivée** des actes d'adoption individuels. En cas de divergence, l'acte individuel prévaut (déjà posé à l'Article 2 de cet index). Le Registre des normes doit donc pouvoir **reconstruire l'index à partir des actes primaires** et signaler toute divergence — ce que fait déjà le contrôle `C1`/`C2` de `verifier-integrite.py`.

## Article 9 — `INV-6` — Supersession traçable

Une norme remplacée ne doit plus être appliquée (risque « norme remplacée encore appliquée » de l'Article 42). Le Registre relie explicitement une norme à celle qui l'amende, la remplace ou l'abroge, de sorte que la résolution temporelle du `CTR-04` retourne toujours la version réellement en vigueur à la date demandée.

---

# TITRE II — MODÈLE DE DONNÉES

Le modèle est énoncé de façon **neutre technologiquement** : ce sont des entités et des relations, non des tables d'un moteur particulier. Il reprend les « données minimales » de l'Article 42.

## Article 10 — Entités

- **`norme`** : référence stable, titre, rang (au sens de `SOURCES-0001`), domaine principal.
- **`version_norme`** : rattachée à une `norme` ; porte le numéro de version, l'empreinte Git du contenu (`INV-1`), le chemin du fichier canonique, la date de rédaction.
- **`statut`** : rattaché à une `version_norme` ; valeur, date d'effet, référence de l'acte d'adoption qui l'établit. Table **en ajout seul** (`INV-3`) : un nouveau statut n'écrase pas le précédent.
- **`adoption`** : référence de l'acte (`ADOPTION-NNNN`), autorité constatante, date, empreinte déclarée du contenu adopté, présence d'une signature. Une `version_norme` n'est réputée en vigueur que si une `adoption` signée l'établit (`INV-4`).
- **`relation_evolution`** : relie une norme source à une norme cible, avec un type (`amende`, `remplace`, `abroge`) et l'acte qui l'établit (`INV-6`).

## Article 11 — Contraintes structurantes

- unicité de `(référence, version)` — pas de version ambiguë (risque de l'Article 42) ;
- une `version_norme` référence exactement une empreinte Git ; l'empreinte désambiguïse deux contenus de même numéro de version ;
- toute ligne de `statut` référence un acte d'`adoption` existant — pas de statut orphelin ;
- l'index dérivé (`INV-5`) est reconstructible par agrégation des `adoption` et `statut`, jamais saisi à la main comme source primaire.

## Article 12 — Données exclues

Le Registre des normes ne conserve **pas** le raisonnement privé d'un rédacteur, ni de données personnelles, ni de secret. Il conserve des références, des empreintes, des statuts et des dates — rien qui doive être protégé au-delà de l'intégrité et de la disponibilité.

---

# TITRE III — CONTRAT `CTR-04`

## Article 13 — Nature du contrat

`CTR-04` (Article 42) est, à ce stade, un contrat **de lecture et d'attestation** seulement. Il n'expose aucune écriture : les seules écritures du Registre passent par la publication d'actes d'adoption signés, jamais par une API d'administration. Cette restriction applique l'Article 68 du registre des capacités (« interdiction des raccourcis » : un service d'administration ne confère pas la capacité).

## Article 14 — Opérations exposées

- **`resoudre_norme(référence, [version], [date])`** → version applicable, empreinte, statut en vigueur, acte d'adoption, indicateur `en_vigueur`. Sans `date`, la date courante ; avec une `date` passée, la résolution temporelle de `INV-3`/`INV-6`.
- **`verifier_integrite(référence, version)`** → empreinte déclarée, empreinte réelle recalculée, indicateur de concordance. C'est l'opération que `verifier-integrite.py` réalise déjà pour l'ensemble du corpus.
- **`resoudre_index()`** → l'index dérivé reconstruit à partir des actes primaires, avec la liste des divergences éventuelles (`INV-5`).

## Article 15 — Explicabilité

Toute réponse du `CTR-04` doit être **rattachable à sa preuve** : quelle version, quelle empreinte, quel acte d'adoption. Une réponse sans preuve rattachée est un défaut, non un résultat.

---

# TITRE IV — MENACES ET CONTRÔLES

## Article 16 — Table menace → contrôle

| Menace (Article 42) | Invariant protecteur | Contrôle |
|---|---|---|
| Texte modifié après adoption | `INV-1` | Comparaison empreinte déclarée / empreinte réelle (`C5` de `verifier-integrite.py`) |
| Adoption tacite | `INV-4` | Refus de tenir en vigueur une norme sans acte d'adoption signé |
| Index divergent | `INV-5` | Reconstruction de l'index et signalement de divergence (`C1`/`C2`) |
| Norme remplacée encore appliquée | `INV-6` | Résolution temporelle via `relation_evolution` |
| Version ambiguë | `INV-1`, Art. 11 | Unicité `(référence, version)` + désambiguïsation par empreinte |
| Surévaluation (commit pris pour adoption) | `INV-4` | La `présence de signature` est un champ requis de l'entité `adoption` |
| Historique réécrit | `INV-3` | Tables `statut` et `adoption` en ajout seul ; historique Git non réécrit |

## Article 17 — Le contrôle existant, promu

`outils/verifier-integrite.py` est aujourd'hui un **script de constat** exécuté manuellement et en intégration continue (`.github/workflows/integrite-documentaire.yml`). La conception le désigne comme **premier contrôle de niveau `P2`** de `CAP-CORE-007` : un contrôle exécuté, dont le résultat (`0` / non-`0`) est conservé et opposable. Le passage à un service `CTR-04` interrogeable est l'incrément à concevoir ; le contrôle de cohérence, lui, est déjà là.

---

# TITRE V — MIGRATION

## Article 18 — Source de migration

La matière du Registre des normes est le corpus déjà publié sur `main` : les actes `ADOPTION-*`, les fichiers `…-STATUT.md`, l'index `REGISTRE-DES-ADOPTIONS-0001` et le registre des capacités. Chacun est une source de niveau `P1` identifiable, horodatée et empreinte.

## Article 19 — Principe cardinal — le fichier reste la source de vérité

Le Registre des normes est initialement un **index dérivé, en lecture, au-dessus des fichiers versionnés par Git** — non une nouvelle base de vérité qui les remplacerait. Cela préserve `INV-5`, évite que le magasin devienne une autorité non auditée (Article 68), et garantit qu'en cas de doute, on revient toujours au fichier signé et à son empreinte Git. Une éventuelle bascule ultérieure vers un magasin primaire serait une décision distincte, avec sa propre analyse.

## Article 20 — Interdiction héritée

Conformément à l'Article 34 du registre des capacités, aucune branche ni aucun dépôt de Genesis I n'est fusionné dans `main`. Les traces historiques (dont les branches d'archive `archive/genesis-ii-chatgpt-*`) demeurent des sources d'audit, jamais des entrées canoniques par fusion.

---

# TITRE VI — PREUVES

## Article 21 — Niveaux atteints et visés

- **`P1 — DOCUMENTÉ`** : atteint — le corpus, l'index et les empreintes existent.
- **`P2 — CONTRÔLÉ`** : partiellement atteint — `verifier-integrite.py` exécute et conserve un résultat de cohérence. À formaliser comme contrôle de service.
- **`P3 — TESTÉ`** : à produire — un test reproductible de **reconstruction temporelle** : « à une date passée donnée, le Registre restitue-t-il la version et le statut réellement en vigueur ? » C'est le test propre de `INV-3`/`INV-6` et la preuve `P3` manquante de `CAP-CORE-007`.
- **`P4 — OPÉRATIONNEL PROUVÉ`** : hors périmètre du présent acte ; suppose une exploitation surveillée, postérieure.

## Article 22 — Preuve `G0` de l'Article 42 — satisfaction partielle

La preuve attendue à l'Article 42 (« contrôle automatique ou reproductible de cohérence entre texte, statut, adoption et index ») est **déjà satisfaite au niveau documentaire** par `verifier-integrite.py`. La conception vise à l'élever au niveau d'un service interrogeable (`CTR-04`) et à lui adjoindre la preuve `P3` de reconstruction temporelle.

---

# TITRE VII — DÉCISION TECHNOLOGIQUE DIFFÉRÉE

## Article 23 — Report assumé

Conformément à l'Article 63, aucun langage, cadre ou base n'est figé par le présent acte. Le choix fera l'objet d'une **décision distincte**, adoptée après la présente conception et inscrite au registre des dépendances d'ingénierie (`genesis-ii/registres/ingenierie/REGISTRE-DES-DEPENDANCES-0001.md`).

## Article 24 — Exigences qui contraindront ce choix

Quel que soit le stack, il devra offrir :

- **intégrité relationnelle forte** : unicité `(référence, version)`, clés étrangères, tables en ajout seul — une base relationnelle transactionnelle (dont PostgreSQL, proposé par l'autorité) satisfait pleinement ce besoin ;
- **adressage par contenu** : les empreintes Git fournissent déjà cet adressage ; le magasin **référence** les objets Git, il ne les remplace pas ;
- **export, révocation, restauration, remplacement et souveraineté** (Article 85 du registre des capacités) — aucun fournisseur ne doit devenir irremplaçable ;
- **reproductibilité** : la reconstruction de l'index et des états passés doit être déterministe.

## Article 25 — Note sur la proposition PHP / Laravel / PostgreSQL

La proposition de l'autorité (PHP, Laravel, PostgreSQL) est **compatible** avec ces exigences et **réversible** (Article 12 : état `EN RÉVISION` prévu ; Article 85 : remplacement préservé). PostgreSQL convient au modèle du Titre II ; un cadre applicatif mature convient au `CTR-04`. Le présent acte ne la retient ni ne l'écarte : il constate sa compatibilité et renvoie sa **décision** à l'acte technologique distinct de l'Article 23.

---

# TITRE VIII — GOUVERNANCE ET RÉSERVES

## Article 26 — Autorités (fiche de gouvernance, Article 22 du registre des capacités)

- **Autorité institutionnelle :** Koné Djakaridja, dit Zakaria le Soufi (`AUT-GAMAD-001`).
- **Autorité d'ingénierie :** `FCT-CORE-009`, exercée à titre transitoire par l'autorité de proposition (`ADOPTION-0022`).
- **Autorité des données du Core :** `FCT-CORE-023` — **vacante**.
- **Autorité d'audit :** `FCT-CORE-021`, exercée par la même personne — **non indépendante**.
- **Responsable de capacité / opérateur :** non désignés.

## Article 27 — Réserve d'audit expressément rappelée

L'AUDIT de `CAP-CORE-007` est exercé par le même titulaire que la conception, et une part de cette conception est rédigée par `AGENT-IA-002`. Conformément à `ADOPTION-0024`, Article 3, cette vérification par l'agent est une **assistance technique subordonnée à l'autorité AUDIT humaine**, non l'audit indépendant lui-même. La réserve de `ADOPTION-0025`, Article 3.b, s'applique intégralement : le concepteur ne s'audite pas. Un contrôle indépendant reste dû dès qu'une seconde personne de confiance sera disponible.

---

# TITRE IX — ÉCARTS ET DÉCISIONS HUMAINES REQUISES

## Article 28 — Décisions réservées à l'autorité

Avant tout code, l'autorité doit trancher :

1. l'adoption ou la correction de la présente conception (acte pressenti `ADOPTION-0026`) ;
2. le choix technologique de l'Article 23, à inscrire au registre des dépendances ;
3. le vocabulaire de statut canonique définitif (les valeurs employées ici — `EN VIGUEUR`, `AMENDÉ`, `REMPLACÉ`, `ABROGÉ` — sont proposées, non figées) ;
4. la désignation, ou la vacance explicite, du responsable de capacité et de l'autorité des données `FCT-CORE-023`.

## Article 29 — Écart de preuve subsistant

La preuve `P3` (reconstruction temporelle) n'existe pas encore ; elle est à produire dans le premier incrément de code. Jusque-là, `CAP-CORE-007` demeure au niveau `P2` partiel.

## Article 30 — Non-effet

Le présent acte ne rend rien opérationnel et ne prononce rien. Il conçoit le premier noyau ; il attend la lecture, la correction éventuelle et la signature de l'autorité.

---

## Autorité d'adoption

- **Nom :** _[réservé à l'autorité de proposition]_
- **Qualité :** _[à compléter]_
- **Date :** _[à compléter à l'adoption]_
- **Registre d'adoption pressenti :** `ADOPTION-0026`
- **Signature :** _[réservée à l'autorité]_

Jusqu'à adoption expresse et inscription au Registre des adoptions, le présent texte demeure :

> **PROJET NORMATIF — EN COURS DE DÉLIBÉRATION**
