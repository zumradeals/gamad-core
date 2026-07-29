# REGISTRE D'ADOPTION — ADOPTION-0057
## Troisième acte de lot — les quatre dernières capacités souveraines

> **PROJET D'ACTE — préparé sur la branche `agent/genesis-ii-lot-final`.** Il n'entre en vigueur qu'à la fusion `--no-ff` dans `main`, laquelle **est** l'acte d'adoption et appartient exclusivement à l'autorité (`ADOPTION-0024`, Art. 3).

## Nature

Troisième **acte de lot** au sens du Titre XIV du Registre initial des décisions. Il adopte quatre incréments de code, énumérés à l'Article 6, et les textes qui les fondent.

Il porte `CAP-CORE-010`, `CAP-CORE-013`, `CAP-CORE-014` et `CAP-CORE-016` de la conception à la preuve `P3`. Il ne réécrit le corps d'aucun texte adopté.

Il est **distinct d'`ADOPTION-0056`**, acte de rectification préparé sur la même branche et adopté par la même fusion. Conformément à l'Article 164 du Registre des décisions, la rectification n'est énumérée parmi aucun incrément du présent lot et ne partage aucun commit avec eux.

## Décision

Koné Djakaridja, dit Zakaria le Soufi, dirigeant actuel de GAMAD, déclare avoir lu et adopté les dispositions ci-après.

---

# TITRE I — LE CONSTAT

## Article 1 — Ce qui rassemble ces quatre capacités n'est pas leur objet

Les deux lots précédents portaient des capacités d'un même domaine, dont les sources se répondaient. Ces quatre-ci n'ont **ni domaine ni famille en commun** — `DOM-01`, `DOM-09`, `DOM-06`/`DOM-09`, `DOM-08`.

Ce qui les rassemble est une **position** : elles sont ce qui restait. Un document de conception unique se justifie par la simultanéité de la séance, non par une communauté de matière, et la conception conjointe ne crée entre elles aucune dépendance.

| Capacité | Source adoptée | Ce qu'elle porte réellement |
|---|---|---|
| `CAP-CORE-010` | `LEXICON-0001` + `REGISTRE-LEXICAL-INITIAL-0001` | 341 entrées, une version vérifiable, **une observation non tranchée** |
| `CAP-CORE-013` | `DOSSIER-AUDIT-G0-0001` | Cinq réserves levées, dont **deux sous restriction écrite** |
| `CAP-CORE-014` | `CTR-07`, rattachée par `ADOPTION-0049` | **Une famille de contrat, et rien d'autre** |
| `CAP-CORE-016` | `REGISTRE-CRYPTOGRAPHIQUE-INITIAL-0001` + `REGISTRE-INITIAL-ACCES-PRIVILEGIES-0001` | Un schéma, une exclusion de mission, **une interdiction absolue** |

## Article 2 — La version de référence du Lexique est vérifiable, et elle est intacte

L'Article 6 du Registre lexical déclare que le Lexique en vigueur est la version `0.1` de `LEXICON-0001`, d'empreinte `b3e83f2ccd6d117ed111b3d4eec6304d61e4a648`.

Cette déclaration est **exacte à ce jour** : l'empreinte recalculée du fichier lui est identique. C'est la première déclaration du corpus qu'un service confronte à sa source, et rien ne la recalculait.

## Article 3 — Un terme qui nomme une fonction constitutionnelle est absent du Lexique, et nul ne l'a tranché

L'Article 8 du Registre lexical signale que « Registraire constitutionnel » ne figure pas dans les 341 entrées. Il le signale **pour examen**, refusant expressément de l'ignorer comme de l'ajouter d'office.

Ce terme n'est pas incident : il nomme `FCT-CORE-003`, fonction constitutionnelle déclarée `VACANTE`, qui porte son propre Article 59 au Registre initial des autorités et mandats et reparaît à quatre autres de ses articles.

L'observation date du 27 juillet 2026. Elle n'est pas tranchée. Elle a été **reportée** au `REGISTRE-QUALITE-CORRECTIONS-0001`, qui la mentionne « pour visibilité croisée » tout en déclarant qu'elle ne relève pas de son objet.

**Un report n'est pas un arbitrage.** Le terme est désormais visible à deux endroits et tranché à aucun.

## Article 4 — Deux réserves de `G0` sont levées par décision, non par résolution

Le Titre V de `DOSSIER-AUDIT-G0-0001` lève les cinq écarts de ses Articles 6 à 10. Deux levées portent leur propre restriction, écrite :

- Article 15 — l'écart sur les accès et secrets est « **levé par décision documentée, non par résolution technique complète** » ;
- Article 18 — les quatre produits non qualifiés sont levés « **au sens d'une décision de statut, non d'une certification de conformité** ».

Le corpus a pris soin d'écrire ce que ses levées ne valent pas. Aucun service ne le restituait, et un lecteur du seul Article 20 — « les cinq écarts sont tous levés » — en tirerait l'inverse.

## Article 5 — Trois espèces d'absence, et non deux

`INV-59` séparait l'absence **déclarée et motivée** de l'absence **par exclusion de mission**. Le journal d'événements en révèle une troisième : `CTR-07` est adoptée et rattachée, mais le corpus ne porte **aucun registre, aucun modèle, aucun type, et aucune déclaration motivée de cette absence**.

La troisième espèce est la plus dangereuse, parce qu'elle ne se distingue d'un oubli par aucun signe : là où l'absence est écrite, elle est vérifiable ; là où rien n'est écrit, rien ne peut l'être.

---

# TITRE II — LES INCRÉMENTS DU LOT

## Article 6 — Énumération (`INV-51`, Article 163 du Registre des décisions)

Ce que le présent acte n'énumère pas, il ne l'adopte pas.

- **Incrément :** famille `CTR-19`, service du lexique, vérification de la version de référence et relevé des observations non tranchées. **Commit :** `f575009288422d71de0a500e1d26e77b34d60232`. **Capacité :** `CAP-CORE-010`. **Garde :** `core/registre-lexique/tests/lexique_p3.php`.
- **Incrément :** volet audit de `CTR-10`, restitution des levées avec leurs restrictions et relevé des formes de trace. **Commit :** `2ef40f204e1b2f505387fe467d16e3d5d99ceefa`. **Capacité :** `CAP-CORE-013`. **Garde :** `core/registre-audit/tests/audit_p3.php`.
- **Incrément :** service du journal d'événements et distinction des trois espèces d'absence. **Commit :** `a1d6a36b27b16882cb747d11daec7b59e65fe501`. **Capacité :** `CAP-CORE-014`. **Garde :** `core/registre-evenements/tests/evenements_p3.php`.
- **Incrément :** famille `CTR-20`, service des secrets, refus de franchir l'exclusion de mission et attestation de l'interdiction absolue. **Commit :** `a9f6ef7c3510bf50f6af5e6443abe64b2c5a7ba4`. **Capacité :** `CAP-CORE-016`. **Garde :** `core/registre-secrets/tests/secrets_p3.php`.

Un cinquième commit porte les **textes communs** du lot — conception conjointe, Titres XVIII et XIX de l'Atlas, Titre XXXVI du Registre des capacités, câblage des quatre gardes en intégration continue et ajustement des deux gardes dont le décompte change. Il n'est pas un incrément et n'est pas énuméré comme tel.

## Article 7 — Les cinq garanties sont entières, et par incrément

| Garantie | `CAP-CORE-010` | `CAP-CORE-013` | `CAP-CORE-014` | `CAP-CORE-016` |
|---|---|---|---|---|
| Garde de comportement propre | `lexique_p3.php` | `audit_p3.php` | `evenements_p3.php` | `secrets_p3.php` |
| Contre-épreuve de falsification | Article 14 | Article 14 | Article 14 | Article 14 |
| Titre de constat d'état | Article 219 | Article 219 | Article 219 | Article 219 |
| Ajout seul | aucun texte réécrit | — | — | — |
| Empreinte exacte | Article 16 | Article 16 | Article 16 | Article 16 |

## Article 8 — Ce que le lot contient et ne contient pas

Le présent lot **ne contient aucune rectification** au sens de l'Article 164 du Registre des décisions. La rectification du décompte des familles est portée par `ADOPTION-0056`, acte propre, sur des commits propres.

Deux modifications touchent des gardes existantes — le décompte des familles de contrat passe de dix-huit à vingt, et les décomptes par criticité de neuf à dix `RACINE` et de sept à dix `CRITIQUE`. Ce sont des **conséquences directes** des incréments du lot, non des rectifications.

Une troisième modification touche la garde de `CAP-CORE-020` : l'assertion « le décompte des capacités codées est ni nul ni total » est remplacée par « il est dérivé des modules observés ». La borne haute était un signal tant qu'une capacité restait à coder ; les vingt étant codées, la conserver ferait échouer la preuve **sur un fait vrai**. Ce qui demeure éprouvable — que le décompte soit dérivé et non présumé — l'est désormais explicitement.

---

# TITRE III — CE QUI EST ADOPTÉ

## Article 9 — La conception conjointe et les cinq invariants

`CONCEPTION-LOT-FINAL-LEXIQUE-AUDIT-EVENEMENTS-SECRETS-0001` est adoptée.

| Invariant | Énoncé |
|---|---|
| `INV-62` | Une réserve levée par décision n'est pas une réserve résolue |
| `INV-63` | Une observation reportée n'est pas une observation tranchée |
| `INV-64` | Une version de référence se vérifie, elle ne se présume pas |
| `INV-65` | Une famille de contrat adoptée n'est pas un registre établi |
| `INV-66` | Une interdiction absolue borne le service, non seulement sa portée |

Menaces retenues : `M-70` à `M-75`.

## Article 10 — Les familles `CTR-19` et `CTR-20`, et la fin d'une série

Le Titre XVIII de `CORE-ATLAS-0001` crée `CTR-19` — Résolution de terme, gardée par `DOM-01`, rattachée à `CAP-CORE-010`. Le Titre XIX crée `CTR-20` — Gouvernance de secret, gardée par `DOM-08`, rattachée à `CAP-CORE-016`.

`INV-40` est satisfait dans les deux cas sans réattribution. `CTR-19` ne recouvre pas `CTR-15` — l'une résout une **source**, l'autre un **terme** ; `CTR-20` ne recouvre pas `CTR-16` — l'une établit une **session**, l'autre gouverne un **secret**.

**Les vingt capacités portent désormais toutes au moins une famille de contrat** — dérivé par `Ctr14::attributions()`, et non reconstitué de mémoire (`ADOPTION-0056`, Art. 6).

## Article 11 — Ce que `INV-66` ajoute à `INV-61`

`ADOPTION-0055` avait établi qu'un service ne franchit pas une **exclusion de mission**. `CAP-CORE-016` en révèle la limite : le Registre cryptographique porte deux dispositions de nature différente.

L'Article 4 — exclusion de mission — borne ce que le service a le droit de **connaître**. Elle tomberait si l'autorité renseignait l'inventaire elle-même.

L'Article 3 — **interdiction absolue** — borne ce que le service a le droit de **produire** : aucune valeur secrète, jamais, dans aucun fichier du dépôt. Elle **survit à la levée de la première** : le jour où l'autorité renseignera l'inventaire, l'Article 3 s'appliquera encore.

Le service atteste que l'interdiction est tenue dans les sources qu'il lit, et le fait sans jamais reproduire ce qu'il cherche : le relevé porte le **nom** du motif et le **nombre** d'occurrences, jamais la correspondance. Un détecteur qui citerait ce qu'il trouve violerait l'interdiction qu'il atteste.

## Article 12 — Ce que le service d'audit ne fait pas, et pourquoi

Il **ne prononce, ne requalifie et ne juge aucune levée**, et ne dit pas si une restriction est suffisante. Il restitue ce que le corpus écrit, restrictions comprises.

Il **nomme la non-indépendance de la fonction `AUDIT`** — tenue par l'autorité de proposition seule depuis `ADOPTION-0022` — sans l'atténuer. Une capacité qui a mission d'établir « qui a fait quoi, sous quelle autorité » ne peut pas taire que l'autorité de la fonction d'audit et l'autorité auditée sont la même personne.

Il constate enfin que le corpus enregistre sa propre trace d'adoption sous **trois formes jamais unifiées**, et que **cinq actes ne se reconstituent par aucune** — dont l'exécution du constat de `G0`. L'Article 49 range « impossibilité de reconstruire une action » parmi les risques de cette capacité. Le service constate ; il ne réécrit aucun acte pour uniformiser (`INV-43`).

---

# TITRE IV — PREUVE

## Article 13 — Vérification des gardes

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
| `core/registre-contrats/tests/contrats_p3.php` | `CAP-CORE-009` | `0` — vingt familles |
| `core/registre-lexique/tests/lexique_p3.php` | `CAP-CORE-010` | `0` — **nouvelle** |
| `core/registre-produits/tests/produits_p3.php` | `CAP-CORE-011` | `0` |
| `core/registre-realms/tests/realms_p3.php` | `CAP-CORE-012` | `0` |
| `core/registre-audit/tests/audit_p3.php` | `CAP-CORE-013` | `0` — **nouvelle** |
| `core/registre-evenements/tests/evenements_p3.php` | `CAP-CORE-014` | `0` — **nouvelle** |
| `core/registre-preuves/tests/preuves_p3.php` | `CAP-CORE-015` | `0` |
| `core/registre-secrets/tests/secrets_p3.php` | `CAP-CORE-016` | `0` — **nouvelle** |
| `core/registre-risques/tests/risques_p3.php` | `CAP-CORE-017` | `0` |
| `core/registre-incidents/tests/incidents_p3.php` | `CAP-CORE-018` | `0` |
| `core/registre-continuite/tests/continuite_p3.php` | `CAP-CORE-019` | `0` |
| `core/registre-annuaire/tests/annuaire_p3.php` | `CAP-CORE-020` | `0` — décomptes étendus |

Ces vingt et une sorties ont été relevées **une par une**, selon la règle portée par `ADOPTION-0054`, Art. 7.

## Article 14 — Contre-épreuve de falsification (`ADOPTION-0032`, Art. 3)

Une falsification par incrément, sur **copies hors dépôt**, avec témoin non altéré.

| Corpus | Altération | Garde | Sortie |
|---|---|---|---|
| Corpus du dépôt, intact | aucune | les quatre | `0` |
| Copie hors dépôt — témoin | aucune | les quatre | `0` · `0` · `0` · `0` |
| Copie hors dépôt | empreinte de référence du Lexique modifiée | `CAP-CORE-010` | `1` |
| Copie hors dépôt | restriction « non par résolution technique complète » effacée | `CAP-CORE-013` | `1` |
| Copie hors dépôt | registre d'événements fabriqué — graphie sans accents | `CAP-CORE-014` | `1` |
| Copie hors dépôt | registre d'événements fabriqué — graphie accentuée | `CAP-CORE-014` | `1` |
| Copie hors dépôt | Article 3 — interdiction absolue — effacé | `CAP-CORE-016` | `1` |

Chaque falsification vise l'invariant propre de sa capacité. Celle de `CAP-CORE-016` **retire une interdiction ; elle n'en simule pas la violation** — aucune valeur secrète, fût-elle fictive, n'est écrite dans aucun fichier, du dépôt ou de la copie.

Le témoin établit que les échecs procèdent des altérations et non des copies. Le dépôt est demeuré intact pendant les épreuves.

## Article 15 — Ce que la contre-épreuve a trouvé, et qui fonde la règle

La falsification de `CAP-CORE-014` **n'a pas échoué du premier coup**.

Une première version du service reconnaissait un registre d'événements à son intitulé accentué — « ÉVÉNEMENTS ». Le registre fabriqué portait « EVENEMENTS » sans accents : le service ne l'a pas vu et a maintenu son constat d'absence. La garde est passée là où elle devait échouer.

Le défaut était réel et non théorique : un registre adopté sous cette graphie aurait été manqué, et le service aurait déclaré une absence là où le corpus portait un texte. Il a été corrigé dans l'incrément lui-même — la reconnaissance est désormais insensible aux accents — et les deux graphies font échouer la garde.

**Ce fait est consigné parce qu'il établit ce que l'Article 3 d'`ADOPTION-0032` vaut :** une contre-épreuve qui ne trouve jamais rien n'aurait pas de raison d'être exigée. Celle-ci a trouvé, avant toute adoption, un défaut que la garde seule ne signalait pas.

---

# TITRE V — EFFETS ET LIMITES

## Article 16 — Effets sur l'état des quatre capacités

Les quatre passent à conception **`CONÇUE`**, implémentation **`PARTIELLEMENT MATÉRIALISÉE`** et preuve **`P3 — TESTÉ`**. L'exploitation demeure **`INACTIVE`**.

Le Titre XXXVI du Registre initial des capacités (Articles 215 à 221, ajout seul) porte ce constat.

Décomptes **dérivés du corpus** par `Ctr14::parCriticite()`, selon la règle d'`ADOPTION-0054` :

| Criticité | Total | Codées et éprouvées | Restantes |
|---|---|---|---|
| `RACINE` | 10 | **10** | aucune |
| `CRITIQUE` | 10 | **10** | aucune |
| **Total** | **20** | **20** | **aucune** |

**Les vingt capacités souveraines sont codées et éprouvées.**

## Article 17 — Ce que ce décompte ne signifie pas

Qu'une capacité atteigne `P3 — TESTÉ` signifie qu'elle porte une garde éprouvant son propre contrat. **Cela ne signifie ni qu'elle est exploitée, ni qu'elle est admise, ni que les écarts qu'elle constate sont comblés.**

La plupart de ces vingt services ont précisément pour objet de **nommer ce qui manque**. Un corpus dont toutes les capacités sont éprouvées et dont tous les écarts demeurent ouverts est exactement l'état que le présent acte constate.

## Article 18 — Ce que cet acte ne fait pas

Il **ne tranche pas** l'observation lexicale de l'Article 8, ne crée, ne modifie, ne déprécie aucune entrée de `LEXICON-0001` et ne met à jour aucune empreinte déclarée.

Il **ne rouvre, ne requalifie et ne juge aucune réserve de `G0`**.

Il **n'établit aucun type d'événement**, aucune convention de version, aucune garantie de livraison, aucune politique de conservation.

Il **n'inventorie, ne crée, ne fait tourner et ne révoque aucun secret, aucune clé, aucun certificat, aucun coffre**, et n'écrit aucune valeur secrète où que ce soit.

Il ne comble ni l'**écart global de sécurité** de l'Article 72, ni l'**écart global de continuité** de l'Article 74. `RISK-SEC-0001` — l'audit non indépendant — demeure accepté sans terme fixe.

Il ne rend aucune capacité admise ni active, n'opère aucun déploiement, n'accepte aucun risque nouveau, ne nomme aucun responsable, ne franchit pas la frontière des accès réservés (`ADOPTION-0025`, Art. 3) et **ne constate pas `G0`**.

## Article 19 — Points soumis à l'autorité

1. **L'arbitrage du terme « Registraire constitutionnel »** — entrée au Lexique, ou constat motivé qu'il désigne une fonction et non un concept lexical. Ouvert depuis le 27 juillet 2026.
2. Les **règles de numérotation lexicale, le statut des synonymes et la gouvernance des termes locaux** (Article 45).
3. Les **événements auditables, délais, accès et l'indépendance de la fonction d'audit** (Article 49), et le sort des **cinq actes dont la trace d'adoption ne se reconstitue pas**.
4. Les **types d'événement, garanties de livraison, ordre et conservation** du journal commun (Article 48), et s'il y a lieu de déclarer l'absence de ce registre plutôt que de la laisser muette.
5. Les **solutions de coffre, détenteurs, seuils, fréquence de rotation et clés racines** (Article 51).
6. Le sort du **mot de passe GamaDrive consigné en clair**, accepté comme risque transitoire par le registre autonome des accès et secrets — **qu'aucun code ne corrigera**.
7. **Ce qui suit `P3`.** Les vingt capacités étant éprouvées, la question ouverte n'est plus le codage mais l'**exploitation** : aucune capacité n'est active, et l'activation touche le déploiement, donc la frontière des accès réservés.

## Réserve d'audit maintenue

L'acte est rédigé par l'agent, sous une fonction AUDIT non indépendante. Le concepteur ne s'audite pas.

Cette réserve atteint ici son point le plus aigu : **l'agent a conçu et codé le service d'audit sous l'autorité d'audit que ce service constate comme non indépendante.** C'est la seconde fois qu'une capacité d'un lot a pour objet le défaut dont son concepteur bénéficie — la première étant `CAP-CORE-017` au lot précédent.

Deux précautions en découlent, portées au code et non à l'intention : le service **ne prononce aucune levée** et **nomme la non-indépendance** sans l'atténuer. L'une et l'autre sont éprouvées par la garde et par sa falsification.

Elles ne suppriment pas la réserve. Elles l'empêchent de se dissimuler. Que les vingt capacités soient éprouvées ne rend pas l'AUDIT indépendant, et aucun acte de l'agent ne le peut.

## Constat d'exécution

| Chemin | Mise à jour | Empreinte Git après mise à jour |
|---|---|---|
| `genesis-ii/atlas/CORE-ATLAS-0001-atlas-initial-gamad-core.md` | Titres XVIII et XIX — Articles 138 à 143 (ajout seul) | `35faac78bd8353bce22f1fa9fb3d67ac9c0e3b78` |
| `genesis-ii/conception/CONCEPTION-LOT-FINAL-LEXIQUE-AUDIT-EVENEMENTS-SECRETS-0001.md` | création | `0329ce5b27d2b3494077f0c31d352b1bde5c7adf` |
| `genesis-ii/registres/capacites/REGISTRE-INITIAL-CAPACITES-SOUVERAINES-0001.md` | Titre XXXVI — Articles 215 à 221 (ajout seul) | `74dcb1a83437ab93b18b4d058e65d312574a8e29` |
| Incrément 1 — `CAP-CORE-010` | commit | `f575009288422d71de0a500e1d26e77b34d60232` |
| Incrément 2 — `CAP-CORE-013` | commit | `2ef40f204e1b2f505387fe467d16e3d5d99ceefa` |
| Incrément 3 — `CAP-CORE-014` | commit | `a1d6a36b27b16882cb747d11daec7b59e65fe501` |
| Incrément 4 — `CAP-CORE-016` | commit | `a9f6ef7c3510bf50f6af5e6443abe64b2c5a7ba4` |
| `genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md` | Article 4 — ligne `ADOPTION-0057` | `0a0578fa97a5320f8d6959d374202b78d0b09625` |

Ces empreintes remplacent, pour ces fichiers et pour eux seuls, celles déclarées par les actes antérieurs — y compris `ADOPTION-0056` pour l'Atlas, le Registre des capacités et l'index —, lesquelles demeurent exactes à leur date. **Aucune ligne ni article préexistant n'a été réécrit ou supprimé.**

## Autorité d'adoption

- **Nom :** Koné Djakaridja, dit Zakaria le Soufi
- **Qualité :** dirigeant actuel de GAMAD, autorité institutionnelle transitoire
- **Date :** 29 juillet 2026 · **Mention :** LU ET ADOPTÉ
