# CONSTAT D'EXÉCUTION — ADOPTION-0064

> **Ce document n'est pas un acte d'adoption.** Il ne constate, n'adopte et ne signe rien. Il déclare l'empreinte du code que l'exécution de `ADOPTION-0064` a produit, sans rouvrir l'acte signé qui ne pouvait la porter : un acte ne contient pas le commit dont il fait partie.

## Ce que le présent constat déclare

L'Article 1 de `ADOPTION-0064` adopte un incrément de code au titre de `CAP-CORE-020`, sans pouvoir en nommer le commit.

- **Incrément :** dérivation d'une capacité inscrite hors du tableau de l'Article 31 par le service de `CTR-14`, et attendus d'admission dérivés au lieu d'être écrits en dur. **Commit :** `6e1696f07aa34381e4cf0909878f71cc1b0da248`. **Capacité :** `CAP-CORE-020`. **Garde :** `core/registre-annuaire/tests/annuaire_p3.php`.

Le commit est **relevé du dépôt** et non écrit de mémoire (`ADOPTION-0054`).

## Ce que le présent constat ne fait pas

Il ne rouvre pas `ADOPTION-0064`, n'en modifie aucun article, n'admet aucune capacité, ne rend `CAP-CORE-021` ni implémentée ni active, n'accepte aucun risque et ne constate pas `G0`.

Il ne prononce aucune caducité : celle-ci procède de la modification d'un module (`INV-68`), et le constat la relève sans la décider.

**Deux admissions deviennent caduques** du fait de cet incrément, et le service les nomme :

| Capacité | Module modifié | Commit admis devenu caduc |
|---|---|---|
| `CAP-CORE-009` | `core/registre-contrats/tests/` | `8eafa66b726de0ae94375552fb6c951309f64536` |
| `CAP-CORE-020` | `core/registre-annuaire/` | `242921d6a4b3bff8675835eaade8adc69ad73355` |

C'est le mécanisme de l'Article 231 du Registre initial des capacités souveraines qui fonctionne, non une faute. La caducité est un constat porté au tableau de bord ; elle n'interrompt ni le travail ni l'intégration continue, et la réinscription relève de l'autorité au moment qu'elle choisira.
