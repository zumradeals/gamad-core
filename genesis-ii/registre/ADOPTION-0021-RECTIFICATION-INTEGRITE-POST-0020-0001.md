# REGISTRE D'ADOPTION — ADOPTION-0021

## Rectification d'intégrité postérieure à `ADOPTION-0020`

## Nature de la présente adoption

Le présent acte ne crée aucune norme nouvelle. Il rectifie trois défauts d'intégrité du dépôt constatés après la publication de `ADOPTION-0020` le 27 juillet 2026, et adopte les quatre documents rédigés pour les corriger.

Un corpus documentaire ne vaut que s'il dit la vérité sur lui-même. Les trois défauts constatés au Titre I ci-dessous ne portent atteinte au fond d'aucun texte adopté : ils portent sur ce que le dépôt donne à lire de son propre état. Aucun n'est réparable en modifiant un texte adopté, et aucun ne l'a été.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les quatre documents listés à l'Article 7 ci-dessous, et avoir tranché le sort des deux branches visées à l'Article 5.

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire au sens de `REGISTRE-INITIAL-AUTORITES-MANDATS-0001`
- **Date de la décision :** 27 juillet 2026
- **Entrée en vigueur :** immédiate, à la publication sur `main`

---

# TITRE I — CONSTATS

## Article 1 — Objet

Le présent titre énonce trois faits vérifiables constatés sur le dépôt `zumradeals/gamad-core` après la publication de `ADOPTION-0020`. Ils sont énoncés comme faits, non comme fautes : deux résultent d'une omission matérielle, le troisième d'une opération technique dont l'effet n'avait pas été mesuré.

## Article 2 — Premier constat : l'index central omettait `ADOPTION-0020`

`REGISTRE-DES-ADOPTIONS-0001` se donne pour objet, à son Article 1, de consolider les références essentielles des registres d'adoption. Son Article 3 dispose que toute nouvelle adoption exige une ligne supplémentaire au tableau de l'Article 4.

Cette ligne n'a pas été ajoutée lors de la publication de `ADOPTION-0020`. L'index consolidé s'arrêtait donc à `ADOPTION-0019` alors que le dépôt contenait vingt registres d'adoption. Un lecteur s'en tenant à l'index ignorait l'adoption des cinquante-sept documents du 27 juillet 2026.

**Rectification :** l'ajout des deux lignes manquantes au tableau de l'Article 4 de l'index, conformément à l'Article 3 du même index — ajout de lignes exclusivement, sans modification d'aucune ligne existante.

## Article 3 — Deuxième constat : les cinquante-sept documents étaient sans statut canonique

Chacun des textes adoptés par `ADOPTION-0001` à `ADOPTION-0019` est accompagné d'un fichier de statut canonique (`…-STATUT.md`) qui indique son état institutionnel courant, le corps du texte conservant l'en-tête `PROJET NORMATIF — EN COURS DE DÉLIBÉRATION` écrit avant son adoption.

Aucun fichier de statut n'a été produit pour les cinquante-sept documents adoptés par `ADOPTION-0020`. Ces documents se décrivaient donc eux-mêmes comme en cours de délibération alors qu'ils étaient adoptés et en vigueur.

**Rectification :** l'adoption de `STATUT-CONSOLIDE-0020-0001`, statut canonique consolidé en un fichier unique, qui énonce le statut des cinquante-sept documents sans toucher au corps d'aucun d'eux.

## Article 4 — Troisième constat : deux branches annoncent un contenu qu'elles ne portent plus

`ADOPTION-0020` (Décision connexe) et la correction du 27 juillet 2026 à `REGISTRE-INITIAL-USAGES-IA-0001` (Article 186 et suivants) énoncent l'un et l'autre que les deux branches de `AGENT-IA-001` (ChatGPT) « demeurent poussées sur `origin` à titre de trace historique non canonique ».

La vérification du dépôt distant établit que cet énoncé est devenu faux. Les deux branches :

- `agent/genesis-ii-registres-et-modeles-securite-0001`
- `agent/genesis-ii-registres-et-modeles-ingenierie-0001`

pointent l'une et l'autre sur le commit `8ab8e0df9f79c64cee2aeb367ab8114b342e8504`, c'est-à-dire sur la tête de `main` elle-même. Elles ne portent plus les commits `e9f3f7a9f0e5296d0b93176e1efe456f93348690` (sécurité, 26 juillet 2026 à 22h47 UTC) et `99c7ada849b374bed8df8f715e3e5eafd646c5cb` (ingénierie, 26 juillet 2026 à 22h28 UTC), ni les deux fichiers `genesis-ii/securite/REGISTRES-ET-MODELES-SECURITE-0001.md` et `genesis-ii/ingenierie/REGISTRES-ET-MODELES-INGENIERIE-0001.md` qu'ils contenaient.

Ces deux branches sont donc devenues trompeuses : elles portent le nom d'une trace qu'elles ne conservent pas, et un lecteur les ouvrant y trouverait le contenu de `main`. Leur conservation en l'état vaut moins que rien, car elle donne l'apparence d'une conservation.

Les deux commits demeuraient, au 27 juillet 2026, accessibles par l'interface de la forge sans être atteignables depuis aucune référence du dépôt. Un objet non atteignable est un objet en sursis : il subsiste jusqu'au prochain ramasse-miettes de la forge, sans garantie de durée.

**Rectification :** l'Article 5 ci-dessous.

---

# TITRE II — RECTIFICATIONS

## Article 5 — Sort des deux branches et de la trace de `AGENT-IA-001`

L'autorité tranche comme suit.

**5.1 — Conservation de la trace.** Il est procédé, préalablement à toute suppression, à rendre les deux commits historiques de nouveau atteignables en les rattachant à deux références permanentes sous le préfixe `archive/` :

| Référence | Commit visé | Contribution |
|---|---|---|
| `archive/genesis-ii-chatgpt-securite-0001` | `e9f3f7a9f0e5296d0b93176e1efe456f93348690` | `genesis-ii/securite/REGISTRES-ET-MODELES-SECURITE-0001.md` |
| `archive/genesis-ii-chatgpt-ingenierie-0001` | `99c7ada849b374bed8df8f715e3e5eafd646c5cb` | `genesis-ii/ingenierie/REGISTRES-ET-MODELES-INGENIERIE-0001.md` |

Ces références n'adoptent rien. Elles conservent une trace historique non canonique, conformément à ce que `ADOPTION-0020` avait entendu conserver et à ce que la correction du 27 juillet 2026 à `REGISTRE-INITIAL-USAGES-IA-0001` déclare vouloir conserver.

La forme retenue est celle de branches d'archive (`refs/heads/archive/…`) et non d'étiquettes (`refs/tags/…`). Ce choix suit la convention déjà établie dans le dépôt par `archive/genesis-i-2026-07-24`, elle-même une branche. Il n'affecte ni l'atteignabilité des commits, ni leur préservation du ramasse-miettes, ni le caractère non canonique de la trace. Une conversion ultérieure en étiquettes, si elle est jugée préférable, ne changerait rien à ce qui est conservé.

**5.2 — Suppression des deux branches.** Les deux branches nommées à l'Article 4 sont supprimées de `origin`. Elles ne portent plus que des doublons de `main` et leur nom induit en erreur sur ce qu'elles conservent. Cette suppression est irréversible ; elle est exécutée sur instruction expresse de l'autorité et ne peut l'être autrement.

**5.3 — Périmètre de la suppression.** La suppression porte sur ces deux branches et sur elles seules. Les dix-huit autres branches `agent/…`, la branche `archive/genesis-i-2026-07-24`, la branche `cursor` et `main` demeurent intactes. L'historique de `main` n'est pas réécrit.

**5.4 — Si la conservation échoue.** Si les commits `e9f3f7a` et `99c7ada` ne peuvent être rattachés à une étiquette — ramasse-miettes de la forge déjà passé, ou refus de la forge de servir un objet non atteignable — la trace est **réputée non conservée**. Ce fait est alors acté ici même, sans être maquillé : les contributions correspondantes de `AGENT-IA-001` demeurent inscrites au registre des usages IA par leur description, leur date, leur horodatage et leur empreinte de commit, mais leur contenu n'est plus recouvrable. L'échec de conservation ne fait pas obstacle à la suppression décidée au 5.2, la branche trompeuse ne conservant rien qui puisse être perdu.

**5.5 — Constat de récupérabilité.** Le 27 juillet 2026, les deux commits ont été récupérés depuis `origin` par leur empreinte complète et leur contenu vérifié :

| Commit | Fichier retrouvé | Volume |
|---|---|---|
| `e9f3f7a9f0e5296d0b93176e1efe456f93348690` | `genesis-ii/securite/REGISTRES-ET-MODELES-SECURITE-0001.md` | 1 190 lignes |
| `99c7ada849b374bed8df8f715e3e5eafd646c5cb` | `genesis-ii/ingenierie/REGISTRES-ET-MODELES-INGENIERIE-0001.md` | 1 615 lignes |

La trace est donc intégralement recouvrable. L'hypothèse de l'alinéa 5.4 n'est pas réalisée et la conservation prévue au 5.1 peut être exécutée.

**5.6 — Constat d'exécution.**

| Élément | Constat au 27 juillet 2026 |
|---|---|
| Branche d'archive `archive/genesis-ii-chatgpt-securite-0001` | **Créée** sur `origin`, sur le commit `e9f3f7a9f0e5296d0b93176e1efe456f93348690` ; contenu vérifié |
| Branche d'archive `archive/genesis-ii-chatgpt-ingenierie-0001` | **Créée** sur `origin`, sur le commit `99c7ada849b374bed8df8f715e3e5eafd646c5cb` ; contenu vérifié |
| Branche `agent/genesis-ii-registres-et-modeles-securite-0001` | **Non supprimée** — voir alinéa 5.7 |
| Branche `agent/genesis-ii-registres-et-modeles-ingenierie-0001` | **Non supprimée** — voir alinéa 5.7 |

La conservation prévue au 5.1 est donc **acquise** : les deux contributions de `AGENT-IA-001` sont de nouveau atteignables depuis une référence permanente de `origin` et ne dépendent plus du sursis décrit à l'Article 4.

**5.7 — Suppression non exécutée et sa raison.** La suppression décidée au 5.2 n'a pas pu être exécutée par l'agent chargé de la publication. L'environnement d'exécution de cet agent interpose un mandataire qui refuse toute suppression de référence sur `origin`, aussi bien par `git push --delete` (rejet `HTTP 403` au moment de la remise) que par appel direct à l'interface de programmation de la forge (`Write access to this GitHub API path is not permitted through this proxy`). Ce refus est une restriction de l'outillage, non une décision institutionnelle.

La décision du 5.2 demeure entière et n'est pas rapportée. Son exécution incombe à l'autorité de proposition, qui dispose des accès nécessaires :

```
git push origin --delete agent/genesis-ii-registres-et-modeles-securite-0001
git push origin --delete agent/genesis-ii-registres-et-modeles-ingenierie-0001
```

Tant que cette exécution n'a pas eu lieu, les deux branches trompeuses subsistent sur `origin` et le troisième constat de l'Article 4 demeure partiellement ouvert : la trace est conservée, mais l'apparence trompeuse n'est pas encore levée. Le présent alinéa sera complété par le constat de la suppression lorsqu'elle sera intervenue.

**5.8 — Effet sur les textes adoptés.** Ni `ADOPTION-0020` ni `REGISTRE-INITIAL-USAGES-IA-0001` ne sont modifiés. Leur énoncé sur la conservation des deux branches devient historiquement daté ; le présent article prévaut pour établir l'état réel du dépôt distant à compter de son adoption.

## Article 6 — Message du commit de fusion et hygiène de l'historique

Le commit de fusion `8ab8e0df9f79c64cee2aeb367ab8114b342e8504`, par lequel `ADOPTION-0020` a été publiée sur `main`, porte un message dans lequel ont été laissés les commentaires d'aide de l'éditeur de Git (« *Please enter a commit message to explain why this merge is necessary* », « *Lines starting with `#` will be ignored* »). Ces lignes ne sont pas un message : ce sont les instructions adressées au rédacteur du message.

Ce défaut est **irréparable sans réécriture de l'historique de `main`**, laquelle est refusée : l'historique publié d'un corpus normatif ne se retouche pas pour des motifs de présentation. Le défaut est donc constaté, laissé en place et acté ici.

Pour l'avenir :

- tout commit de fusion publiant une adoption porte un message explicite énonçant l'acte publié ;
- le message est fourni par l'option `-m` plutôt que laissé à l'éditeur interactif ;
- le contrôleur adopté à l'Article 7 signale, à titre indicatif et non bloquant, tout message d'historique portant ces commentaires.

Le caractère non bloquant de ce signalement est délibéré : un contrôle qui échouerait sur un défaut irréparable rendrait tout contrôle ultérieur impossible ou contraindrait à réécrire l'historique. Le fait est signalé à chaque exécution, sans jamais être effacé ni bloquer.

## Article 7 — Documents adoptés par le présent acte

| Chemin | Objet | Empreinte Git |
|---|---|---|
| `outils/verifier-integrite.py` | Contrôleur d'intégrité documentaire du corpus | `aafe8414d351008a8f183a7e48deca6077c4dc13` |
| `.github/workflows/integrite-documentaire.yml` | Exécution automatique du contrôleur sur `main` et sur les propositions de fusion | `2b828d8c4feaded5c68804cd20b541a694c184e9` |
| `genesis-ii/registre/STATUT-CONSOLIDE-0020-0001.md` | Statut canonique consolidé des cinquante-sept documents d'`ADOPTION-0020` | `6bb6637d41bf5bbbba690e4c28cbbf7d3d5ef939` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Index central, complété de deux lignes au tableau de son Article 4 | `5d5ce96680b8c0de21cf1a5fd66d58c3a5d1ac1d` |

**Sur l'empreinte de l'index.** L'empreinte ci-dessus remplace, pour ce seul fichier, celle déclarée à la Section B du Titre I de `ADOPTION-0020`. Cette dernière demeure exacte comme constat de l'état du fichier au 27 juillet 2026 ; elle est dépassée par le présent acte, seul l'ajout de lignes permis par l'Article 3 de l'index étant intervenu entre les deux. Aucune ligne existante du tableau n'a été modifiée.

**Sur l'empreinte du présent registre.** Le présent registre ne déclare pas sa propre empreinte, un document ne pouvant contenir l'empreinte de son propre contenu. Elle est celle de l'objet Git publié sur `main` avec le présent acte.

## Article 8 — Nature et limites du contrôleur adopté

`outils/verifier-integrite.py` est un outil de constat. Il lit le dépôt, ne le modifie pas, et n'a aucune autorité normative : il ne décide rien, n'adopte rien et ne prononce rien.

Ce qu'il vérifie est limité et énoncé dans son propre en-tête : la concordance entre l'index central et les registres d'adoption, la continuité de leur numérotation, l'existence des chemins cités par le corpus, la concordance des empreintes Git déclarées avec les fichiers publiés, et le décompte annoncé par `ADOPTION-0020`.

Ce qu'il ne vérifie pas doit être dit avec la même netteté. Il ne lit pas le fond des textes. Il ne constate ni la véracité, ni la cohérence juridique, ni la complétude normative d'aucun document. Un dépôt qu'il déclare intègre est un dépôt qui dit la vérité sur sa propre structure — rien de plus. Il ne constate pas la Porte `G0` et aucune de ses sorties ne peut être invoquée à cet effet.

Sa liste d'exemptions déclarées (`CHEMINS_HORS_DEPOT`) recense les chemins cités par le corpus et volontairement absents du dépôt. Chaque exemption doit demeurer justifiée par un texte adopté ; une exemption dont le motif disparaît doit être retirée de la liste et non maintenue par commodité.

---

# TITRE III — DISPOSITIONS FINALES

## Article 9 — Ce que le présent acte ne fait pas, et gel du corpus

**9.1 — Non-effets.** La présente adoption :

- ne crée aucune norme nouvelle et n'amende le fond d'aucun texte adopté ;
- ne réadopte pas les cinquante-sept documents d'`ADOPTION-0020`, dont l'adoption date du 27 juillet 2026 et demeure inchangée ;
- ne crée, ne prolonge et ne régularise aucun accès technique, compte, secret, clé ou permission ;
- ne nomme aucun responsable, validateur, registraire, auditeur ou autorité permanente ; les fonctions `AUT-SEC`, `AUT-EXP`, `AUDIT` et `AUT-ING` demeurent vacantes ;
- n'accepte aucun risque, ne valide aucun contrôle, n'admet aucun produit ;
- n'autorise aucun déploiement, aucune mise en production, aucun codage canonique ;
- ne rend aucune capacité opérationnelle ;
- **ne constate pas la Porte constitutionnelle `G0`.** Conformément à la Loi 75 de `CORE-LAWS-0001` et à l'Article 26 de `GOVERNANCE-0001`, seul un acte distinct et exprès peut la prononcer. `ACTE-DE-CONSTAT-G0-0001` demeure un squelette vide et non exécuté.

**9.2 — Gel du corpus documentaire.** Le présent acte clôt le chantier documentaire ouvert le 26 juillet 2026. À compter de son adoption, le corpus Genesis II est gelé : aucun nouveau texte normatif n'est rédigé et aucun texte existant n'est amendé, hors correction matérielle documentée ou rectification d'un défaut d'intégrité de même nature que ceux du Titre I.

Ce gel n'est pas un aboutissement mais une limite reconnue. Ce qui demeure ouvert après le présent acte relève des faits et non des textes : l'inventaire réel des comptes et accès, qui demeure l'affaire exclusive de l'autorité de proposition ; la désignation des fonctions vacantes ; l'évaluation formelle des agents artificiels ; la qualification des quatre produits historiques ; et le constat de la Porte `G0`. Aucun document supplémentaire ne les fera advenir. Le corpus attend désormais des faits.

**9.3 — Bloc Autorité.**

| Élément | Valeur |
|---|---|
| Autorité d'adoption | Koné Djakaridja, dit Zakaria le Soufi |
| Qualité | Dirigeant actuel de GAMAD |
| Date d'adoption | 27 juillet 2026 |
| Entrée en vigueur | Immédiate, à la publication sur `main` |
| Mention | LU ET ADOPTÉ |

---

## Publication

Le présent acte et les quatre documents qu'il identifie à l'Article 7 sont publiés ensemble sur `main`, conformément à l'Article 66 de `GOVERNANCE-0001`. La publication est exécutée par un agent artificiel sous instruction expresse de l'autorité de proposition ; conformément au même Article 66, cette exécution ne fait pas de l'agent l'autorité d'adoption.
