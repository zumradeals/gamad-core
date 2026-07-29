# REGISTRE D'ADOPTION — ADOPTION-0049
## Rattachement de la famille `CTR-07` à `CAP-CORE-014` — d'une mention en prose à une déclaration dérivable

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-rattachement-ctr-07`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Le présent acte tranche le point soumis par l'Article 11 de `ADOPTION-0048`. Il porte au champ qui les recense une attribution que le Registre énonçait déjà en prose, et il livre la forme par laquelle un service la dérive.

Il adopte le **Titre XXX** du Registre initial des capacités souveraines (ajout seul, Articles 185 à 190) et un **incrément de code** de faible portée. Il ne réécrit le corps d'aucun texte adopté ; l'Article 48 demeure au dépôt tel qu'adopté.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — LE FAIT ET SA CORRECTION

## Article 1 — Ce qui manquait

L'Article 48 énonce, à sa ligne « État actuel », que les « principes et famille `CTR-07` » sont adoptés pour `CAP-CORE-014` — Journal d'événements communs. C'est une attribution, faite par un texte adopté.

Elle n'a jamais figuré à la ligne « Contrats attendus » : la fiche de `CAP-CORE-014` ne porte pas cette ligne, et cette capacité est **la seule des vingt** dans ce cas.

`CTR-07` apparaissait donc **sans capacité titulaire** à tout service qui dérive le Registre, alors qu'une capacité la revendiquait en toutes lettres. Le défaut n'était pas dans l'attribution, qui existait ; il était dans sa forme, qui ne se dérivait pas.

## Article 2 — Comment le défaut a été vu

Le catalogue des contrats livré par `ADOPTION-0048` distingue deux espèces de vacance : celle dont aucune capacité ne garde le domaine, **structurelle** et prévue par l'écart global de données de l'Article 70, et celle dont le domaine gardien **est** tenu, qui procède d'une déclaration manquante.

Le service a rangé `CTR-07` dans la seconde et n'y a pas touché. Déduire une attribution d'une phrase de prose eût été le comblement que `INV-43` interdit ; l'attribution appartient à l'autorité (`INV-42`). L'écart a été **nommé et soumis**, non corrigé d'office.

Ce point mérite d'être relevé pour ce qu'il vaut : le mécanisme a trouvé, dans le corpus, un défaut que ni la conception ni l'acte qui l'a précédé n'avaient vu. Il ne l'a pas réparé, et c'est ce qui était attendu de lui.

## Article 3 — La forme adoptée

Le Titre XXX inscrit la déclaration suivante, que le service dérive sans interprétation :

> **Rattachement :** `CAP-CORE-014` — famille attribuée `CTR-07`. **Source :** `ADOPTION-0049`, constatant l'Article 48.

Cette forme est **distincte de la réattribution** arrêtée par `ADOPTION-0045`. Une réattribution retire une famille et en donne une autre ; un rattachement ne retire rien. Les confondre obligerait à déclarer une famille retirée fictive, c'est-à-dire à écrire une fausseté pour satisfaire une forme.

`CTR-07` est gardée par `DOM-06` ; `CAP-CORE-014` garde `DOM-06` et `DOM-09`. **`INV-40` est satisfait.** La famille n'est revendiquée par aucune autre capacité : elle n'est pas partagée.

## Article 4 — Ce que le service ne fait toujours pas

L'étape de dérivation ajoutée à `CTR-14` lit une **forme exigée**, jamais la prose d'une fiche. Le service ne cherche pas les attributions dans les phrases ; il en serait techniquement capable, et c'est précisément ce qu'il ne doit pas faire.

Le vide déclaré demeure l'état sûr : ce n'est pas le service qui a comblé, c'est l'autorité qui a déclaré.

---

# TITRE II — PREUVE

## Article 5 — Gardes étendues, aucune ajoutée

Aucune garde n'est créée : deux gardes existantes sont **étendues**, conformément à `ADOPTION-0035`, Art. 2.2 — une capacité, une garde.

- `CAP-CORE-020` (`annuaire_p3.php`) : un rattachement déclaré est dérivé, la fiche demeurant intacte.
- `CAP-CORE-009` (`contrats_p3.php`) : `CTR-07` est rattachée à `CAP-CORE-014` ; **toute vacance restante est structurelle**, et les trois qui demeurent — `CTR-09`, `CTR-12`, `CTR-13` — sont celles que le corpus prévoit.

La seconde extension est la plus utile : elle ne vérifie pas seulement que `CTR-07` est réparée, elle exige qu'**aucune vacance d'une autre espèce ne reparaisse**. Un rattachement omis à l'avenir ferait échouer la preuve.

## Article 6 — Vérification des gardes

| Garde | Capacité | Sortie |
|---|---|---|
| `outils/verifier-integrite.py` — documentaire | — | `0` |
| `core/registre-identites/tests/identite_p3.php` | `CAP-CORE-001` | `0` |
| `core/registre-autorites/tests/mandat_p3.php` | `CAP-CORE-003` | `0` |
| `core/registre-autorisation/tests/autorisation_p3.php` | `CAP-CORE-004` | `0` |
| `core/registre-acces/tests/authentification_p3.php` | `CAP-CORE-005` | `0` |
| `core/registre-sources/tests/sources_p3.php` | `CAP-CORE-006` | `0` |
| `core/registre-normes/tests/temporel_p3.php` | `CAP-CORE-007` | `0` |
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` — étendue |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` — étendue |

## Article 7 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

La falsification porte sur la déclaration adoptée par le présent acte : sur une copie du corpus **hors dépôt**, la ligne de rattachement du Titre XXX est effacée. La mention en prose de l'Article 48 y demeure intacte — c'est ce qui donne à l'épreuve son sens : elle vérifie que le service dérive la **forme** et non la phrase.

| Corpus | Altération | Résultat des deux gardes étendues | Sortie |
|---|---|---|---|
| Corpus du dépôt, intact | aucune | `P3` **ÉTABLIE** pour les deux | `0` · `0` |
| Copie hors dépôt — témoin | aucune | `P3` **ÉTABLIE** pour les deux | `0` · `0` |
| Copie hors dépôt | ligne de rattachement effacée | `CAP-CORE-009` **NON ÉTABLIE**, 3 écarts ; `CAP-CORE-020` **NON ÉTABLIE**, 1 écart | `1` · `1` |

Sous falsification, `CTR-07` reparaît au relevé des vacances non structurelles — l'écart que `ADOPTION-0048` avait nommé. Le témoin établit que l'échec procède de l'altération et non de la copie. Le dépôt est demeuré intact pendant l'épreuve.

---

# TITRE III — EFFETS ET LIMITES

## Article 8 — Aucun état n'est modifié

`CAP-CORE-014` demeure conception `À ÉTABLIR`, implémentation `NON COMMENCÉE`, exploitation `INACTIVE`, preuve `P1`. Aucune conception n'est adoptée pour elle, aucun code n'est livré pour elle.

`CAP-CORE-009` et `CAP-CORE-020` demeurent conception `CONÇUE`, implémentation `PARTIELLEMENT MATÉRIALISÉE`, exploitation `INACTIVE`, preuve `P3 — TESTÉ`. Une extension de garde n'est pas un changement d'état.

Aucun invariant, aucune menace, aucun contrat nouveau n'est introduit.

## Article 9 — Ce que cet acte ne fait pas

Il n'établit ni les **types d'événement**, ni le **mécanisme**, ni la **conservation** que l'Article 48 déclare non établis, ni aucune des décisions ouvertes de cette fiche.

Il ne traite pas les trois vacances demeurées — `CTR-09`, `CTR-12`, `CTR-13` — dont les domaines gardiens `DOM-07`, `DOM-11` et `DOM-12` ne sont tenus par aucune des vingt capacités. Les combler supposerait une capacité nouvelle, décision réservée à l'autorité par l'Article 83.

Il ne rend aucune capacité admise ni active, n'opère aucun déploiement, n'admet aucun produit, n'accepte aucun risque nouveau, ne nomme aucun responsable, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXX — Articles 185 à 190 (ajout seul) | `87e9c8c3f0638d0d44c6f2fb9a9b56d5f6a442bc` |
| Incrément de code — dérivation des rattachements et extension des deux gardes | commit | `67b21d57f157feaa315dfc7a592ea9ed8c2ded56` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0049` | `cedac3130da01ca2099fb50699188958b89f9c88` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs, qui demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
