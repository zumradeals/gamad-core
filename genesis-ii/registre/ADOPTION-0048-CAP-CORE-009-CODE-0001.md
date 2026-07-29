# REGISTRE D'ADOPTION — ADOPTION-0048
## Premier incrément de code de `CAP-CORE-009` — catalogue des contrats communs (`CTR-06`), preuve `P3` établie

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-code-cap-core-009`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte adopte le **premier incrément de code** de la capacité souveraine `CAP-CORE-009` — Registre des contrats, dont la conception a été adoptée par `ADOPTION-0047`.

Il livre le service du contrat `CTR-06` — Catalogue de contrats, son point d'entrée de consultation et la garde de comportement qui lui est propre. Il ne modifie le corps d'aucun texte adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — CE QUI EST LIVRÉ

## Article 1 — Le service `CTR-06`

`core/registre-contrats/src/Ctr06.php` — opérations de **lecture et d'attestation seulement**, sans écriture applicative du corpus (`INV-4`), sans base et sans état conservé : le catalogue est reconstruit à chaque interrogation.

Les opérations arrêtées par l'Article 9 de la conception sont livrées en entier : `catalogue()`, `resoudreContrat()`, `producteurs()`, `consommateurs()`, `sansProducteur()` et `ecarts()`. Deux opérations s'y ajoutent, que les faits ont rendues nécessaires : `dependances()`, qui relève dans le code les liens entre contrats, et `sansTitulaireMalgreGardien()`, dont l'Article 5 rend compte.

## Article 2 — Le catalogue emprunte le relevé des familles, il ne le refait pas

Le service consomme `CTR-14` (`CAP-CORE-020`) pour le relevé des seize familles de l'Atlas. À cette fin, l'opération `Ctr14::familles()` passe de privée à publique ; aucune autre ligne du service de l'annuaire n'est touchée.

Le motif est de fond. L'Atlas est la source ; l'annuaire en porte déjà l'analyseur, éprouvé et gardé. **Un second analyseur du même tableau donnerait au corpus deux vérités sur ses propres contrats**, qui divergeraient au premier ajout de famille. La dépendance ainsi créée n'est pas dissimulée : le service la relève lui-même et la restitue comme non déclarée, `INV-44` s'appliquant à son auteur avant les autres.

## Article 3 — Le point d'entrée de consultation

`core/registre-contrats/public/index.php` — vue en **lecture seule**, sans base de données, qui relève les fichiers du dépôt à chaque affichage. Elle restitue les seize familles avec leur domaine gardien, leurs titulaires déclarés, leur producteur et leurs consommateurs observés, les dépendances relevées dans le code et les champs que le corpus n'établit pas.

Comme pour `ADOPTION-0046`, le motif est qu'une capacité dont l'état n'est lisible qu'en exécutant sa propre preuve n'est pas consultable par l'autorité. L'affichage de cette page ne constitue ni une publication, ni une admission, ni une mise en service.

---

# TITRE II — CE QUE LE CATALOGUE CONSTATE

## Article 4 — Les trois constats de la conception, vérifiés par le code

Les faits ci-après sont **relevés par le service**, non estimés.

| Constat de `ADOPTION-0047` | Relevé du service |
|---|---|
| Seize familles définies, huit servies | seize familles ; **neuf** servies, le présent module servant la neuvième |
| `CTR-09` sans capacité titulaire | confirmé, et reconnu **structurel** : aucune capacité ne garde `DOM-07` |
| Une dépendance entre contrats qu'aucun texte ne déclare | `CTR-04 → CTR-15` relevée dans le code, restituée **non déclarée** |

## Article 5 — Un quatrième fait, que la conception n'avait pas vu

Le service distingue deux espèces de vacance, et cette distinction fait apparaître un fait que la conception ignorait.

`CTR-09`, `CTR-12` et `CTR-13` sont gardées par des domaines qu'aucune des vingt capacités ne tient : leur vacance est **structurelle**, prévue par l'écart global de données de l'Article 70.

`CTR-07` — Événement commun ne relève pas de cette espèce. Son domaine gardien `DOM-06` est tenu par `CAP-CORE-009` et `CAP-CORE-014`, et l'Article 48 du Registre initial des capacités énonce que la famille `CTR-07` est adoptée pour `CAP-CORE-014` — **en prose, dans la ligne « État actuel », et non dans le champ qui porte les contrats attendus**. `CAP-CORE-014` est la seule des vingt capacités dans ce cas.

Le service **nomme** cet écart et n'y touche pas (`INV-38`, `INV-42`). Porter `CTR-07` au champ qui l'attribue est un acte de l'autorité ; le déduire d'une phrase serait exactement le comblement que `INV-43` interdit. Le point est soumis à l'Article 11 du présent acte.

## Article 6 — Ce que le service refuse d'inventer

Version, politique de compatibilité, stratégie d'erreur et procédure de sortie sont restituées **`NON ÉTABLI`** pour toute famille (`INV-45`). Le registre initial des contrats, seul document qui les établirait, n'est pas adopté ; le service le constate au lieu de le suppléer.

Aucune convention par défaut, aucune numérotation implicite, aucune règle de compatibilité déduite de l'usage. Une version inventée serait une promesse de compatibilité que personne n'a faite.

---

# TITRE III — PREUVE

## Article 7 — La neuvième garde de comportement

`core/registre-contrats/tests/contrats_p3.php` éprouve le contrat propre de la capacité : `INV-42` la dérivation intégrale des seize familles ; `INV-43` la déclaration du vide, producteur comme titulaire, et la distinction des deux espèces de vacance ; `INV-44` l'observation dans le code des dépendances, dont celle du service lui-même ; `INV-45` les quatre champs non établis ; et le rendu du point d'entrée.

Conformément à `ADOPTION-0035`, Art. 2.2, la capacité n'hérite de la preuve d'aucune autre. La garde est inscrite à l'intégration continue (`.github/workflows/gardes-comportement.yml`). La garde documentaire Python demeure unique, séparée et inchangée (`ADOPTION-0027`, Art. 4).

## Article 8 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| `core/registre-sources/tests/sources_p3.php` | `CAP-CORE-006` | `0` |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` — **nouvelle** |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` |

## Article 9 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Deux falsifications distinctes ont été opérées sur des **copies hors dépôt**, désignées par `CORPUS_PATH`. Un témoin non altéré établit que l'échec procède de l'altération et non de la copie.

| Corpus | Altération | Résultat | Sortie |
|---|---|---|---|
| Corpus du dépôt, intact | aucune | Preuve `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt — témoin | aucune | Preuve `P3` **ÉTABLIE** | `0` |
| Copie hors dépôt — falsification 1 | famille `CTR-13` retirée du tableau de l'Atlas | Preuve `P3` **NON ÉTABLIE** — 2 écarts | `1` |
| Copie hors dépôt — falsification 2 | import de `Ctr15` effacé du service `CTR-04` | Preuve `P3` **NON ÉTABLIE** — 3 écarts | `1` |

La seconde falsification importe autant que la première : elle établit que la dépendance `CTR-04 → CTR-15` est **réellement lue dans le code** et non inscrite en dur dans le test. Un test qui ne peut pas échouer ne prouve rien.

Le dépôt est demeuré intact pendant les deux épreuves.

---

# TITRE IV — EFFETS ET LIMITES

## Article 10 — Effets sur l'état de `CAP-CORE-009`

| Dimension | Avant | Après |
|---|---|---|
| Conception | `CONÇUE` (`ADOPTION-0047`) | `CONÇUE` |
| Implémentation | `NON COMMENCÉE` | **`PARTIELLEMENT MATÉRIALISÉE`** |
| Exploitation | `INACTIVE` | `INACTIVE` — inchangée |
| Preuve | `P1` | **`P3 — TESTÉ`** |

Aucun invariant, aucune menace, aucun contrat nouveau n'est introduit : `INV-42` à `INV-45` et `M-46` à `M-51` procèdent de `ADOPTION-0047`. Aucune ligne du corpus n'est réécrite.

L'existence d'un service et d'une page ne rend pas la capacité active. L'exploitation demeure `INACTIVE` tant qu'aucun acte de mise en service n'est adopté, et aucun déploiement n'est opéré.

## Article 11 — Points soumis à l'autorité

1. **`CTR-07` — Événement commun.** L'Article 48 rattache cette famille à `CAP-CORE-014` en prose seulement. L'autorité entend-elle porter ce rattachement au champ qui porte les contrats attendus, par un Titre ajouté au Registre initial des capacités ? L'agent ne le fait pas de lui-même.
2. **Le registre initial des contrats** demeure non adopté ; les quatre champs de `INV-45` demeurent `NON ÉTABLI` jusqu'à son adoption.
3. Les décisions ouvertes de l'Article 44 — formats, protocoles, règles de compatibilité, autorité d'approbation — demeurent entières.

## Article 12 — Ce que cet acte ne fait pas

Il ne crée aucun contrat, n'en approuve, n'en déprécie et n'en retire aucun. Il n'attribue aucune famille à aucune capacité. Il ne traite pas le **blocage des accès directs aux bases**, que l'Article 44 range parmi les contrôles requis : ce contrôle suppose une exploitation, `INACTIVE` pour toutes les capacités, et il demeure **nommé et non traité**.

Il ne rend aucune capacité admise ni active, n'opère aucun déploiement, n'admet aucun produit, n'accepte aucun risque nouveau, ne nomme aucun responsable, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas : la vérification portée au Titre III est une assistance subordonnée à l'AUDIT humain.

L'Article 5 en donne la mesure : le fait qu'il rapporte n'avait pas été vu par la conception, écrite par le même agent trois jours plus tôt.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| Incrément de code — service `CTR-06`, point d'entrée, garde `P3`, ouverture de `Ctr14::familles()` et inscription en intégration continue | commit | `2e2fd295c41118ccc5ce9c24cd8b113d751e96a4` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0048` | `7f14cf788f56816a297956d21dbb5f71278cf6fc` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
