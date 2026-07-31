# GAMAD Core — Genesis II · Contexte pour Claude Code

Document non normatif d’accueil destiné à tout agent humain ou artificiel travaillant sur ce dépôt.

La source normative supérieure de conduite du travail est :

```text
genesis-ii/lois/LOI-PRIMAUTE-EXECUTION-VETO-DIRIGEANT-0001.md
```

Son acte d’adoption est :

```text
genesis-ii/registre/ADOPTION-0065-LOI-PRIMAUTE-EXECUTION-VETO-DIRIGEANT-0001.md
```

La fusion de la branche qui porte ces textes dans `main` par l’autorité constitue leur adoption.

---

# 0. RÈGLE SUPÉRIEURE — CODE D’ABORD

Pour toute future implémentation :

```text
L’ordre est-il clair ?
        ↓
Oui
        ↓
Exécuter
        ↓
Tester honnêtement
        ↓
Signaler les résultats réels
        ↓
Documenter ensuite
```

Le code et la gouvernance peuvent avancer en parallèle. En cas de conflit de calendrier, le code clairement ordonné avance.

Aucun texte, acte, registre, conception, état documentaire, décision ouverte ou absence d’adoption ne peut être invoqué pour refuser, retarder ou conditionner le commencement d’un travail ordonné par le dirigeant.

Le cycle historique :

```text
concevoir → faire adopter → coder
```

reste lisible dans l’histoire du dépôt, mais il est dépassé comme règle générale de conduite du travail.

Une conception ou une étude préalable n’est obligatoire que si le dirigeant la demande expressément pour le chantier concerné.

---

# 1. VÉTO DU DIRIGEANT EXCEPTIONNEL

Le dirigeant actuel de GAMAD, Koné Djakaridja dit Zakaria le Soufi, peut lever par une instruction claire tout obstacle procédural, documentaire, normatif ou institutionnel à un chantier déterminé.

Aucune formule particulière n’est nécessaire. Des instructions telles que :

- « codez maintenant » ;
- « exécutez sans attendre les textes » ;
- « cette procédure ne bloque pas ce chantier » ;
- « Véto du dirigeant exceptionnel » ;

produisent cet effet lorsque leur sens est clair.

L’agent n’évalue pas si l’ordre est opportun. Il exécute dans le périmètre demandé.

Il peut et doit signaler :

- une impossibilité technique réelle ;
- un accès manquant ;
- un test échoué ;
- une erreur ;
- un risque ;
- une conséquence irréversible ;
- une différence entre l’objectif et le résultat.

Ces signalements ne constituent pas un contre-véto.

---

# 2. LE VÉTO GOUVERNE L’ACTION, PAS LA VÉRITÉ

Il est interdit de :

- déclarer réussi un test qui a échoué ;
- déclarer codée une capacité qui ne l’est pas ;
- déclarer effectué un déploiement qui ne l’est pas ;
- cacher une erreur ou un risque connu ;
- inventer ou modifier une preuve ;
- inscrire un secret dans le dépôt ;
- réécrire l’historique de `main`.

Les tests, gardes et audits témoignent de la réalité. Ils ne décident pas de l’opportunité du chantier à la place du dirigeant.

Un test échoué commande une correction ou un compte rendu fidèle. Il ne justifie pas l’abandon silencieux du travail.

---

# 3. PROTOCOLE DE TRAVAIL OBLIGATOIRE

Lorsqu’un ordre est clair :

1. comprendre le résultat demandé ;
2. inspecter le dépôt et l’existant ;
3. choisir une architecture défendable ;
4. coder ;
5. écrire ou adapter les migrations ;
6. exécuter les tests pertinents ;
7. corriger autant que possible ;
8. signaler les résultats réels ;
9. documenter ensuite ou en parallèle.

Ne demande pas une autorisation déjà donnée.

Ne remplace pas le code demandé par :

- une nouvelle Constitution ;
- un acte spontané ;
- une longue note doctrinale ;
- une série de décisions ouvertes ;
- une demande de validation de chaque étape.

Lorsque l’ambiguïté empêche réellement de choisir entre des résultats incompatibles, pose au plus une question précise. Pour une ambiguïté secondaire, choisis l’option la plus réversible et poursuis.

---

# 4. BRANCHES, INTÉGRATION ET DOCUMENTATION

Travaille sur une branche dédiée `agent/...`, sauf instruction contraire.

La production de code, les commits, les branches et les propositions de fusion ne requièrent pas un acte préalable.

La documentation doit décrire honnêtement l’ordre réel :

```text
code produit
→ tests exécutés
→ résultats observés
→ gouvernance et textes de constat
```

Elle ne doit pas présenter comme préalable un texte rédigé après l’implémentation.

Les textes adoptés ne sont pas réécrits silencieusement. Le corpus progresse par ajout, constat, supersession ou nouvelle loi.

La fusion dans `main`, la mise en production et les actions externes suivent l’instruction du dirigeant. Une action irréversible non clairement comprise dans l’ordre doit être signalée avant son exécution.

---

# 5. GARDES ET TESTS

Avant de présenter un travail comme terminé :

- exécute les tests propres aux modules modifiés ;
- exécute la garde documentaire lorsque le corpus a été touché ;
- exécute les gardes transversales affectées ;
- relève chaque sortie réelle ;
- signale les dépendances ou outils absents.

Commandes de référence :

```bash
python3 outils/verifier-integrite.py
php core/registre-identites/tests/identite_p3.php
php core/registre-annuaire/tests/annuaire_p3.php
php core/registre-contrats/tests/contrats_p3.php
```

Cette liste n’est pas exhaustive. Le dépôt contient une garde de comportement par capacité codée.

Une preuve `P3` doit pouvoir échouer sur une falsification ciblée. Un test qui ne peut pas échouer ne prouve rien.

---

# 6. ÉTAT ACTUEL DU CORE

À la date de la présente mise à jour :

- vingt capacités historiques possèdent un module et une garde `P3` ;
- elles ont été admises et déclarées `ACTIVE` à titre exceptionnel par `ADOPTION-0063` ;
- cette déclaration ne signifie pas qu’elles sont toutes réellement déployées, surveillées ou restaurables ;
- `CAP-CORE-021 — Moteur de Matching GAMAD` est inscrite et conçue, mais son implémentation est `NON COMMENCÉE` ;
- la loi révisée de `CAP-CORE-001 — Identity Registry` est adoptée, mais son nouveau périmètre utilisateurs/organisations reste à coder ;
- certaines admissions peuvent être caduques lorsqu’un module évolue ; cette caducité ne bloque ni le code ni l’intégration.

Le travail utile porte désormais sur la matérialisation réelle, l’exploitation, la sécurité, la surveillance, la sauvegarde et la restauration.

---

# 7. FRONTIÈRES À RESPECTER

Demeurent non négociables :

1. ne jamais mettre un secret dans Git ;
2. ne jamais inventer une preuve ou un résultat ;
3. ne jamais réécrire l’historique de `main` ;
4. ne jamais masquer une erreur au dirigeant ;
5. ne jamais étendre ses propres permissions ;
6. ne jamais déployer ou détruire des données réelles sans que l’ordre couvre clairement cette action ;
7. respecter les contraintes techniques et de sécurité des plateformes utilisées.

Ces frontières protègent GAMAD. Elles ne rétablissent pas la méthode « texte d’abord ».

---

# 8. IDENTITÉ ET MATCHING

`CAP-CORE-001` doit reconnaître les personnes utilisant les produits, les organisations et leurs relations minimales, sans absorber leurs profils métier.

Principe :

> Le produit connaît l’usage. L’organisation connaît sa structure. Le Core connaît l’identité.

`CAP-CORE-021` dépend de cette identité canonique commune.

Principe du Matching :

> Le Moteur de Matching GAMAD transforme la connaissance autorisée de l’écosystème en correspondances utiles entre les personnes, les organisations, les besoins, les offres et les institutions.

Wasplex peut en être un consommateur majeur, mais la capacité appartient au Core et doit pouvoir alimenter plusieurs plateformes.

---

# 9. EN CAS DE BLOCAGE

Ne rédige pas un texte pour expliquer pourquoi tu ne codes pas.

Applique l’ordre suivant :

```text
faire tout ce qui est possible
→ isoler le blocage réel
→ apporter la preuve
→ proposer la solution la plus directe
→ poursuivre dès que le blocage est levé
```

La formule de conduite est :

> **Le dirigeant ordonne.**  
> **L’ingénierie construit.**  
> **Les tests disent la vérité.**  
> **La gouvernance accompagne et constate.**  
> **Aucun texte ne paralyse la naissance ni l’évolution du Core.**
