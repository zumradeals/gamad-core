# REGISTRE D'ADOPTION — ADOPTION-0053
## Premier acte de lot — le domaine `DOM-04` : organisations, produits et realms

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-lot-dom-04`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte est le **premier acte de lot** au sens du Titre XIV du Registre initial des décisions (`ADOPTION-0052`). Il adopte trois incréments de code, énumérés à l'Article 4, et les textes qui les fondent.

Il porte les trois capacités du domaine `DOM-04` — `CAP-CORE-002`, `CAP-CORE-011` et `CAP-CORE-012` — de la conception à la preuve `P3`. Il ne réécrit le corps d'aucun texte adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — LE CONSTAT

## Article 1 — Deux des trois sources canoniques de `DOM-04` étaient absentes

L'Article 35 de `CORE-ATLAS-0001` nomme trois sources canoniques pour ce domaine : le **Registre des organisations**, le **Registre des produits**, le **Registre des realms**.

Seul le second existait, adopté par `ADOPTION-0016`. Les fiches des capacités le constataient elles-mêmes — Article 37 : « registre initial et sources de vérité organisationnelles non constitués » ; Article 47 : « aucun inventaire initial ni contrat de fédération établi ».

## Article 2 — `CAP-CORE-002` était la seule capacité sans famille de contrat

L'Article 37 énonce ses contrats attendus — « résolution d'organisation, statut, représentation et événements de cycle de vie » — **en prose et sans référence**. Aucune des seize familles de l'Article 69 ne les portait : `CTR-08` garde `DOM-04`, mais son intitulé — « Statut produit ou realm » — la restreint aux produits et aux realms.

C'est la troisième omission de l'Article 69, après les deux relevées par le Titre XIV de l'Atlas, et elle est d'une autre espèce : non pas une famille empruntée hors de son domaine, mais une capacité **sans aucune famille**.

## Article 3 — Ce que le portefeuille dit de lui-même

Les quatre produits historiques portent tous une admission `DOSSIER À CONSTITUER`, une conformité `NON ÉVALUÉ` et aucun propriétaire institutionnel. Leurs quatre états courants, constatés par `ADOPTION-0023`, sont **absents des onze états du vocabulaire de l'Article 22**. Ces états procèdent d'un Titre adopté : ils sont réguliers **et** hors vocabulaire à la fois.

---

# TITRE II — LES INCRÉMENTS DU LOT

## Article 4 — Énumération (`INV-51`, Article 163 du Registre des décisions)

Ce que le présent acte n'énumère pas, il ne l'adopte pas.

- **Incrément :** famille `CTR-17` — Référence d'organisation, Registre initial des organisations et service du contrat. **Commit :** `2a15722b552e7dd58e8d662c8c8799fa0f8469f1`. **Capacité :** `CAP-CORE-002`. **Garde :** `core/registre-organisations/tests/organisations_p3.php`.
- **Incrément :** portefeuille des produits, états dérivés du dernier Titre et relevé des prétentions sans dossier. **Commit :** `70a527d0457361a0e6fde366c699ce6006319a5a`. **Capacité :** `CAP-CORE-011`. **Garde :** `core/registre-produits/tests/produits_p3.php`.
- **Incrément :** registre des realms, constat d'absence et refus de suppléance de source. **Commit :** `dc03828ba68d815a39b882cca591ccf2834800a8`. **Capacité :** `CAP-CORE-012`. **Garde :** `core/registre-realms/tests/realms_p3.php`.

Un quatrième commit — `a88e1c0e59b660137a4655d37e50d4789caea9c2` — porte les textes communs au lot : la conception conjointe et le Titre XXXIII du Registre initial des capacités. Il n'est pas un incrément et n'est pas énuméré comme tel : il ne sert aucune capacité seul.

## Article 5 — Les cinq garanties sont entières, et par incrément

| Garantie | Incrément 1 | Incrément 2 | Incrément 3 |
|---|---|---|---|
| Garde de comportement propre | `organisations_p3.php` | `produits_p3.php` | `realms_p3.php` |
| Contre-épreuve de falsification | Article 12 | Article 12 | Article 12 |
| Titre de constat d'état | Article 204 | Article 204 | Article 204 |
| Ajout seul | aucun texte réécrit | — | — |
| Empreinte exacte | Article 13 | Article 13 | Article 13 |

Aucun incrément n'hérite de la preuve d'un autre. `CAP-CORE-011` et `CAP-CORE-012` partagent la famille `CTR-08` ; elles ne partagent pas leur garde, et l'Article 8 dit pourquoi.

## Article 6 — Le lot ne contient aucune rectification

Conformément à l'Article 164 du Registre des décisions, le présent lot ne mêle à ses incréments aucune rectification d'un défaut que l'un d'eux aurait introduit.

Une seule modification touche des gardes existantes : celles de `CAP-CORE-009` et `CAP-CORE-020` comptaient seize familles de contrat et en comptent dix-sept. Ce n'est pas une rectification d'un défaut, c'est la **conséquence directe** de la création de `CTR-17` par l'incrément 1.

---

# TITRE III — CE QUI EST ADOPTÉ

## Article 7 — La conception conjointe et les cinq invariants

`CONCEPTION-DOM-04-ORGANISATIONS-PRODUITS-REALMS-0001` est adoptée. Un seul document pour trois capacités qui partagent un domaine et une source d'écart ; les trois demeurent distinctes, avec trois modules, trois gardes et trois constats d'état. **La conception est conjointe, la preuve ne l'est pas.**

| Invariant | Énoncé |
|---|---|
| `INV-52` | Admission et conformité ne se présument jamais |
| `INV-53` | L'état courant procède du dernier Titre, et n'est jamais traduit |
| `INV-54` | Un realm non inscrit n'est pas reconnu ; aucune confiance n'est implicite |
| `INV-55` | L'absence d'une source canonique est constatée, jamais suppléée |
| `INV-56` | Être nommée par un texte ne vaut pas reconnaissance |

Menaces retenues : `M-58` à `M-63`.

## Article 8 — Pourquoi deux gardes pour une même famille

`CTR-08` sert `CAP-CORE-011` et `CAP-CORE-012`, et l'Atlas l'énonce dans son intitulé même : le partage est régulier (Article 125 de l'Atlas). Chaque capacité a néanmoins **sa garde propre**.

Le motif n'est pas formel. **Les produits sont inventoriés, les realms ne le sont pas.** Une garde commune aurait établi la première moitié de ce fait et masqué la seconde — et c'est exactement ce que `ADOPTION-0035`, Art. 2.2 interdit.

## Article 9 — Le Registre initial des organisations

`REGISTRE-INITIAL-ORGANISATIONS-0001` est adopté : première des deux sources canoniques manquantes. Il arrête une forme d'inscription dérivable, trois types, quatre statuts, et **inscrit une seule organisation** — GAMAD, souveraine, fondée par `ACTE-0001`.

Ce nombre est un constat, non une lacune dissimulée : aucun autre texte adopté ne reconnaît d'organisation. Les organisations propriétaires des familles de produits partenaires ne sont nommées par aucun texte ; les inscrire exigerait de leur donner un nom que le corpus n'a pas écrit.

## Article 10 — Ce que le service des produits établit sans le recopier

La réserve d'`ADOPTION-0025`, Art. 3.c — **aucun produit certifié** — est désormais **dérivée du corpus** : les quatre produits portent une admission non acquise et une conformité non évaluée, et le service le constate à chaque interrogation.

Recopiée, cette réserve cesserait d'être vraie le jour où le corpus changerait sans que personne s'en aperçoive. Dérivée, elle ne peut plus mentir.

`INV-52` en tire la conséquence : GamaDrive est **reconnu produit officiel** depuis `ADOPTION-0023` et son dossier d'admission **demeure à constituer**. Reconnaître n'est pas admettre.

---

# TITRE IV — PREUVE

## Article 11 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-organisations/tests/organisations_p3.php` | `CAP-CORE-002` | `0` — **nouvelle** |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| `core/registre-sources/tests/sources_p3.php` | `CAP-CORE-006` | `0` |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |
| `core/registre-decisions/tests/decisions_p3.php` | `CAP-CORE-008` | `0` |
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` — dix-sept familles |
| `core/registre-produits/tests/produits_p3.php` | `CAP-CORE-011` | `0` — **nouvelle** |
| `core/registre-realms/tests/realms_p3.php` | `CAP-CORE-012` | `0` — **nouvelle** |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` — dix-sept familles |

Les trois gardes nouvelles sont inscrites à l'intégration continue par l'incrément qui les livre.

## Article 12 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Une falsification par incrément, sur **copies hors dépôt**, avec témoin non altéré.

| Corpus | Altération | Garde | Sortie |
|---|---|---|---|
| Corpus du dépôt, intact | aucune | les trois | `0` |
| Copie hors dépôt — témoin | aucune | les trois | `0` · `0` · `0` |
| Copie hors dépôt | `PRD-GAMAD-002` déclaré `CONFORME` sans dossier d'admission | `CAP-CORE-011` | `1` |
| Copie hors dépôt | un realm fabriqué inscrit à un Registre des realms créé pour l'occasion | `CAP-CORE-012` | `1` |
| Copie hors dépôt | Wasplex inscrit comme organisation, avec un statut hors vocabulaire | `CAP-CORE-002` | `1` |

Chaque falsification vise l'invariant propre de sa capacité : la prétention sans dossier pour `INV-52`, la reconnaissance fabriquée pour `INV-54`, l'inscription d'une entité nommée pour `INV-56`.

Le témoin établit que les échecs procèdent des altérations et non des copies. Le dépôt est demeuré intact pendant les trois épreuves.

---

# TITRE V — EFFETS ET LIMITES

## Article 13 — Effets sur l'état des trois capacités

Les trois passent de conception `À ÉTABLIR` à **`CONÇUE`**, d'implémentation `NON COMMENCÉE` à **`PARTIELLEMENT MATÉRIALISÉE`**, et de preuve `P1` à **`P3 — TESTÉ`**. L'exploitation demeure **`INACTIVE`** pour les trois.

Le Titre XXXIII du Registre initial des capacités (Articles 201 à 206, ajout seul) porte ce constat, ainsi que le rattachement de `CTR-17` à `CAP-CORE-002`.

**Dix capacités sur vingt sont désormais codées et éprouvées, dont sept des huit `RACINE`.**

## Article 14 — Ce que cet acte ne fait pas

Il **n'admet, ne qualifie et ne certifie aucun produit** — `ADOPTION-0025`, Art. 3.c demeure entière. Il **ne reconnaît aucun realm**, n'établit aucune fédération et n'accorde aucune confiance. Il **ne constitue pas le Registre des realms** : cette absence est constatée, non comblée. Il ne nomme aucun propriétaire institutionnel, aucun représentant, aucune autorité d'admission, et n'arbitre pas l'articulation avec les réalités juridiques externes.

Il ne rend aucune capacité admise ni active, n'opère aucun déploiement, n'accepte aucun risque nouveau, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Article 15 — Points soumis à l'autorité

1. **Le Registre des realms** : constitué, ou faisant l'objet de la « décision motivée d'absence » que l'Article 47 admet ?
2. **Les propriétaires institutionnels** des quatre produits, non désignés depuis `ADOPTION-0016`.
3. **Les états de produits hors vocabulaire** : le vocabulaire de l'Article 22 est-il étendu, ou les états rapprochés ?
4. **La typologie des organisations**, l'autorité d'admission et l'articulation avec les réalités juridiques externes.
5. **Les dossiers d'admission** des quatre produits, à constituer depuis `ADOPTION-0016`.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

Une précaution particulière s'imposait : **deux des trois capacités portent principalement sur des absences**, et une absence est ce qu'il est le plus tentant de combler. Les trois services ne lisent donc aucune prose — ils lisent des tableaux, des formes déclaratives et des chemins de fichiers.

L'agent signale en outre une faute de méthode commise pendant la préparation du présent lot : la boucle de vérification qu'il employait pour relever les sorties des gardes capturait le code de retour d'une commande intermédiaire, non celui de la garde. Les fusions antérieures demeurent saines — l'intégration continue les a vérifiées indépendamment à chaque fois —, mais les relevés que l'agent a présentés par cette voie ne valaient rien. La méthode est corrigée ; le fait est porté ici parce qu'un contrôle qui rapporte toujours `0` est pire qu'un contrôle absent.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/atlas/CORE-ATLAS-0001-atlas-initial-gamad-core.md` | Titre XV — Articles 130 à 132 (ajout seul) | `e46243e0dc558c2fff339187964f4df2cc22ef66` |
| `genesis-ii/registres/organisations/REGISTRE-INITIAL-ORGANISATIONS-0001.md` | création | `357752d7084a71b58b47bec6f0c58f6132fe0a24` |
| `genesis-ii/conception/CONCEPTION-DOM-04-ORGANISATIONS-PRODUITS-REALMS-0001.md` | création | `92bff64aa6878a9bdec558f863222cd08fb2b3bf` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXXIII — Articles 201 à 206 (ajout seul) | `171c9acf7a951aeff5ebf66919686b230fc1d2cd` |
| Incrément 1 — `CAP-CORE-002` | commit | `2a15722b552e7dd58e8d662c8c8799fa0f8469f1` |
| Incrément 2 — `CAP-CORE-011` | commit | `70a527d0457361a0e6fde366c699ce6006319a5a` |
| Incrément 3 — `CAP-CORE-012` | commit | `dc03828ba68d815a39b882cca591ccf2834800a8` |
| Textes communs du lot | commit | `a88e1c0e59b660137a4655d37e50d4789caea9c2` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0053` | `f47b89778aea2dcd85a95716cce39b82733b12ff` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
