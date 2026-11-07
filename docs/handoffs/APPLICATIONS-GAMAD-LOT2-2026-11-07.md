# Lot 2 — frontière HTTP multidomaine

## Reprise et décision
La PR #126 est conservée, sur la base #124 (`617e478`). Son amorce contenait
une erreur de syntaxe bootstrap, une frontière web après session, aucune qualification
et le refus de tous les parcours sauf `/`. Ces défauts sont corrigés sans remplacer
la branche ni introduire de registre concurrent.

## Contrat livré
- Activation explicite `GAMAD_MULTIDOMAIN_ENABLED=true` ; désactivée par défaut pour
  préserver les installations existantes. Ce mode désactivé ne fournit pas d'isolation
  multidomaine et ne doit pas être utilisé pour exposer plusieurs applications.
- `GAMAD_INSTITUTIONAL_HOST` : domaine institutionnel réservé, validé strictement.
  Pas de valeur réelle inventée. Racine institutionnelle vers `/console`.
- `TRUSTED_PROXIES` : IP ou CIDR explicites, séparés par virgules. Pas de joker,
  ni plage /0. Même configuration compilable pour validation et interprétation.
- Host strict, port numérique 1–65535 accepté. X-Forwarded-Host unique uniquement
  depuis un proxy approuvé. Toute transmission depuis un proxy inconnu est refusée.
  Forwarded, X-Forwarded-Port et X-Forwarded-Prefix non supportés et refusés ; le proxy
  doit transmettre le port dans Host / X-Forwarded-Host si nécessaire.
- Résolution exacte CAP-CORE-011 à chaque requête ; application active et adaptateur
  installé exigés. Aucun cache applicatif persistant, aucune sélection par paramètre.
- Routes web publiques et personnelles annotées explicitement `gamad_application`.
  Routes sans annotation inaccessibles sur domaine applicatif, avant session.
  Le seul adaptateur livré reste Réseau ; aucune déclaration opérationnelle créée.
- API inchangée : authentification et autorisations existantes restent exigées sur
  les hôtes reconnus ; les endpoints API centraux ne deviennent pas publics.
- Cookies host-only obligatoires (SESSION_DOMAIN vide), pas de SSO. CSRF et HTTPS
  conservés. Réponses réussies du mode multidomaine privées/no-store.
- Les moyens de connexion Core restent communs, avec redirections publiques sur
  leur domaine. Les routes administratives Réseau restent institutionnelles au lot 2.

## Qualification réelle
Parcours HTTP produits incluant multidomaine : passe. Vérifie Host falsifié, proxy
fiable/non fiable, domaine inconnu/actif/suspendu/retiré, hôte institutionnel réservé,
absence de cookies sur route centrale refusée, connexion publique, redirections,
cookies host-only, CSRF 419, HTTPS API 426 et API authentifiée.
Cache des routes ET configuration compilés puis chargés dans un nouveau noyau :
accueil, frontière et URL derrière proxy passent.
Non-régressions HTTP comptes, fédération et renommage 005 : passent localement.
Ces scénarios rejoignent le job produits déjà présent dans la CI.

## Limites et suite
Aucune production, DNS, certificat ou migration opérationnelle modifiés.
PRD-GAMAD-005 conserve type, identité, cycle et délégations ; décision historique
DEC-2026-09-08 consultée, aucune conversion automatique.
L'infrastructure doit rejeter les doubles en-têtes Host avant PHP (PHP ne préserve
pas nécessairement les duplications reçues sur le réseau). Le port des URLs publiques
est celui du Host transmis. Les domaines IDN ne sont pas supportés par le lot 1.
Toute nouvelle application doit livrer son adaptateur et ses routes annotées ; les
routes non déclarées sont refusées par défaut.
Lot 3 : permissions effectives lecture/écriture des fonctions administratives
applicatives, maintien du mandat institutionnel, tests révocation/refus. Ne pas
confondre l'isolation d'hôte avec les habilitations individuelles.
