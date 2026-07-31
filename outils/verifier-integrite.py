#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Contrôle d'intégrité documentaire du corpus Genesis II de GAMAD Core.

Ce programme ne lit que le dépôt. Il ne modifie aucun fichier, ne crée aucune
référence Git et ne prend aucune décision institutionnelle : il constate des
faits vérifiables et les rapporte.

Il applique six contrôles bloquants et un contrôle indicatif :

  C1  Tout registre d'adoption présent dans `genesis-ii/registre/` est inscrit
      au tableau de l'Article 4 de l'index central des adoptions.
  C2  Toute ligne du tableau de l'Article 4 renvoie à un registre d'adoption
      réellement présent dans le dépôt.
  C3  La numérotation des registres d'adoption est continue depuis `0001`,
      sans trou ni doublon. Un constat d'exécution compagnon
      (`ADOPTION-NNNN-…-EXECUTION.md`) n'est pas un acte distinct et n'est pas
      compté ici : il déclare, sans rouvrir l'acte signé NNNN, l'empreinte des
      fichiers que son exécution a modifiés.
  C4  Tout chemin de fichier du dépôt cité entre accents graves dans le corpus
      existe, hors exemptions déclarées ci-dessous.
  C5  Toute empreinte Git déclarée en regard d'un chemin, dans un tableau du
      corpus, correspond à l'empreinte réelle du fichier publié. Lorsqu'un
      chemin est déclaré par plusieurs registres d'adoption, seule la
      déclaration du registre le plus récent lie le fichier : les déclarations
      antérieures sont des constats historiques, qu'un amendement adopté peut
      légitimement avoir dépassés.
  C6  Le décompte annoncé par `ADOPTION-0020` correspond au nombre de fichiers
      uniques que son Titre I identifie.
  A1  (indicatif) Les messages de commit de la branche courante ne contiennent
      pas les commentaires d'aide laissés par l'éditeur de Git.

Code de sortie : 0 si aucune erreur bloquante, 1 sinon.

Usage :
    python3 outils/verifier-integrite.py [--racine CHEMIN]
"""

from __future__ import annotations

import argparse
import re
import subprocess
import sys
from pathlib import Path

# --------------------------------------------------------------------------
# Constantes du corpus
# --------------------------------------------------------------------------

REPERTOIRE_ADOPTIONS = "genesis-ii/registre"
INDEX_ADOPTIONS = "genesis-ii/registres/sources/REGISTRE-DES-ADOPTIONS-0001.md"
ADOPTION_0020 = "genesis-ii/registre/ADOPTION-0020-CHANTIER-PHASES-1-A-4-0001.md"
DECOMPTE_ATTENDU_0020 = 57

# Chemins cités par le corpus mais volontairement absents du dépôt publié.
# Chaque exemption doit rester justifiée par un texte adopté ; toute exemption
# non justifiée doit être retirée d'ici plutôt que tolérée en silence.
CHEMINS_HORS_DEPOT = {
    "genesis-ii/securite/REGISTRES-ET-MODELES-SECURITE-0001.md":
        "Contribution non retenue de AGENT-IA-001 ; non fusionnée sur main "
        "(REGISTRE-INITIAL-USAGES-IA-0001, correction du 27 juillet 2026 ; "
        "ADOPTION-0020, Décision connexe).",
    "genesis-ii/ingenierie/REGISTRES-ET-MODELES-INGENIERIE-0001.md":
        "Contribution non retenue de AGENT-IA-001 ; non fusionnée sur main "
        "(REGISTRE-INITIAL-USAGES-IA-0001, correction du 27 juillet 2026 ; "
        "ADOPTION-0020, Décision connexe).",
}

# --------------------------------------------------------------------------
# Expressions régulières
# --------------------------------------------------------------------------

# Nom de fichier d'un registre d'adoption : ADOPTION-NNNN-…-md
RE_FICHIER_ADOPTION = re.compile(r"^ADOPTION-(\d{4})-.+\.md$")

# Nom de fichier d'un constat d'exécution compagnon : ADOPTION-NNNN-…-EXECUTION.md
# Un tel fichier ne constate ni n'adopte rien par lui-même : il déclare les
# empreintes des conséquences d'exécution d'un acte signé qui ne doit pas être
# rouvert pour les porter. Il n'est pas compté par C1/C3 comme un acte
# d'adoption distinct — un seul acte porte chaque numéro.
RE_FICHIER_EXECUTION = re.compile(r"^ADOPTION-(\d{4})-.+-EXECUTION\.md$")

# Ligne du tableau de l'Article 4 : | `ADOPTION-NNNN` | … |
RE_LIGNE_INDEX = re.compile(r"^\|\s*`ADOPTION-(\d{4})`\s*\|")

# Chemin du dépôt cité entre accents graves.
RE_CHEMIN_CITE = re.compile(
    r"`((?:genesis-ii|outils|archives|\.github)/[^`\s]+?\.(?:md|py|yml|yaml|json|txt))`"
)

# Ligne de tableau associant un chemin à une empreinte Git de 40 caractères.
RE_LIGNE_EMPREINTE = re.compile(
    r"^\|\s*`([^`]+?\.(?:md|py|yml))`[^|]*\|(?:[^|]*\|)*?\s*`([0-9a-f]{40})`\s*\|\s*$"
)

# Empreinte déclarée par une feuille de statut (`X-STATUT.md`). Le chemin n'y
# est pas cité : il est implicite, c'est le texte compagnon du même répertoire.
# Cette forme est celle des textes fondateurs (ère ADOPTION-0001 à 0019), que
# les constats d'exécution en tableau n'ont jamais couverts.
RE_EMPREINTE_STATUT = re.compile(
    r"^-\s*\*\*Empreinte Git du contenu adopté\s*:\*\*\s*`([0-9a-f]{40})`"
)

# Nom d'une feuille de statut.
RE_FICHIER_STATUT = re.compile(r"^(?P<base>.+)-STATUT\.md$")

# Décompte annoncé par ADOPTION-0020, en toutes lettres puis en chiffres.
RE_DECOMPTE_0020 = re.compile(r"adopte\s+\*\*[^*]*?\((\d+)\)\s+fichiers\s+uniques\*\*")

# Commentaires d'aide de l'éditeur de Git, jamais destinés à être publiés.
RE_COMMENTAIRE_EDITEUR = re.compile(
    r"#\s*(?:Please enter a commit message"
    r"|Lines starting with"
    r"|It looks like you may be committing a merge)"
)


# --------------------------------------------------------------------------
# Rapport
# --------------------------------------------------------------------------


class Rapport:
    """Collecte les constats et détermine le code de sortie."""

    def __init__(self) -> None:
        self.erreurs: list[str] = []
        self.avertissements: list[str] = []

    def erreur(self, message: str) -> None:
        self.erreurs.append(message)

    def avertissement(self, message: str) -> None:
        self.avertissements.append(message)

    def controle(self, code: str, libelle: str, constats: list[str]) -> None:
        """Affiche le résultat d'un contrôle et enregistre ses erreurs."""
        if constats:
            print(f"  [ÉCHEC] {code} — {libelle}")
            for constat in constats:
                print(f"          · {constat}")
                self.erreur(f"{code} — {constat}")
        else:
            print(f"  [OK]    {code} — {libelle}")


# --------------------------------------------------------------------------
# Utilitaires
# --------------------------------------------------------------------------


def lire(chemin: Path) -> str:
    return chemin.read_text(encoding="utf-8")


def empreinte_git(racine: Path, chemin_relatif: str) -> str | None:
    """Empreinte Git réelle du fichier, ou None si Git est indisponible."""
    try:
        resultat = subprocess.run(
            ["git", "hash-object", chemin_relatif],
            cwd=racine,
            capture_output=True,
            text=True,
            check=True,
        )
    except (OSError, subprocess.CalledProcessError):
        return None
    return resultat.stdout.strip()


def fichiers_du_corpus(racine: Path) -> list[Path]:
    return sorted(p for p in (racine / "genesis-ii").rglob("*.md") if p.is_file())


# --------------------------------------------------------------------------
# Contrôles
# --------------------------------------------------------------------------


def adoptions_sur_disque(racine: Path) -> dict[str, list[str]]:
    """Numéro d'adoption -> fichiers portant ce numéro dans `genesis-ii/registre/`.

    Les constats d'exécution compagnons (suffixe `-EXECUTION.md`) ne sont pas
    des actes d'adoption distincts et ne sont pas comptés ici.
    """
    trouvees: dict[str, list[str]] = {}
    for fichier in sorted((racine / REPERTOIRE_ADOPTIONS).glob("ADOPTION-*.md")):
        if RE_FICHIER_EXECUTION.match(fichier.name):
            continue
        correspondance = RE_FICHIER_ADOPTION.match(fichier.name)
        if correspondance:
            trouvees.setdefault(correspondance.group(1), []).append(fichier.name)
    return trouvees


def adoptions_dans_index(texte_index: str) -> list[str]:
    """Numéros d'adoption inscrits au tableau de l'Article 4, dans l'ordre."""
    return [
        correspondance.group(1)
        for ligne in texte_index.splitlines()
        if (correspondance := RE_LIGNE_INDEX.match(ligne.strip()))
    ]


def controle_c1_c2_c3(racine: Path, rapport: Rapport) -> None:
    """Cohérence entre l'index central et les registres d'adoption."""
    chemin_index = racine / INDEX_ADOPTIONS
    if not chemin_index.exists():
        rapport.controle(
            "C1-C3", "Index central des adoptions",
            [f"index central introuvable : {INDEX_ADOPTIONS}"],
        )
        return

    sur_disque = adoptions_sur_disque(racine)
    dans_index = adoptions_dans_index(lire(chemin_index))

    manquants = sorted(set(sur_disque) - set(dans_index))
    rapport.controle(
        "C1",
        f"Index complet ({len(sur_disque)} registres d'adoption sur disque)",
        [
            f"`ADOPTION-{numero}` ({', '.join(sur_disque[numero])}) n'est pas "
            f"inscrit au tableau de l'Article 4 de l'index central"
            for numero in manquants
        ],
    )

    orphelins = sorted(set(dans_index) - set(sur_disque))
    rapport.controle(
        "C2",
        f"Index sans référence orpheline ({len(dans_index)} lignes)",
        [
            f"l'index inscrit `ADOPTION-{numero}` mais aucun fichier "
            f"correspondant n'existe dans {REPERTOIRE_ADOPTIONS}/"
            for numero in orphelins
        ],
    )

    constats_c3: list[str] = []
    for numero in sorted({n for n in dans_index if dans_index.count(n) > 1}):
        constats_c3.append(f"`ADOPTION-{numero}` est inscrit plusieurs fois à l'index")
    for numero, fichiers in sorted(sur_disque.items()):
        if len(fichiers) > 1:
            constats_c3.append(
                f"`ADOPTION-{numero}` correspond à plusieurs fichiers : "
                f"{', '.join(fichiers)}"
            )
    numeros = sorted(int(n) for n in sur_disque)
    if numeros:
        attendus = set(range(1, max(numeros) + 1))
        for absent in sorted(attendus - set(numeros)):
            constats_c3.append(
                f"la numérotation saute `ADOPTION-{absent:04d}` "
                f"(aucun fichier dans {REPERTOIRE_ADOPTIONS}/)"
            )
    rapport.controle(
        "C3",
        f"Numérotation continue des adoptions (0001 à {max(numeros):04d})"
        if numeros else "Numérotation continue des adoptions",
        constats_c3,
    )


def controle_c4(racine: Path, rapport: Rapport) -> None:
    """Tout chemin cité par le corpus existe, hors exemptions déclarées."""
    constats: list[str] = []
    exemptions_rencontrees: set[str] = set()

    for fichier in fichiers_du_corpus(racine):
        relatif = fichier.relative_to(racine).as_posix()
        cites = sorted(set(RE_CHEMIN_CITE.findall(lire(fichier))))
        for chemin in cites:
            if (racine / chemin).exists():
                continue
            if chemin in CHEMINS_HORS_DEPOT:
                exemptions_rencontrees.add(chemin)
                continue
            constats.append(f"{relatif} cite `{chemin}`, absent du dépôt")

    rapport.controle("C4", "Chemins cités présents dans le dépôt", constats)
    for chemin in sorted(exemptions_rencontrees):
        print(f"          · exemption déclarée : `{chemin}` — {CHEMINS_HORS_DEPOT[chemin]}")

    inutilisees = sorted(set(CHEMINS_HORS_DEPOT) - exemptions_rencontrees)
    for chemin in inutilisees:
        rapport.avertissement(
            f"C4 — exemption déclarée mais jamais rencontrée : `{chemin}` "
            f"(à retirer de la liste)"
        )


def rang_declarant(chemin_relatif: str) -> int:
    """Rang du texte déclarant : numéro d'adoption, ou 0 hors registre d'adoption.

    Une empreinte déclarée par `ADOPTION-0021` prime celle déclarée pour le
    même fichier par `ADOPTION-0020`, l'adoption la plus récente étant celle
    qui lie le contenu publié. Un constat d'exécution compagnon
    (`ADOPTION-NNNN-…-EXECUTION.md`) porte le même rang que l'acte NNNN qu'il
    accompagne : il existe précisément pour déclarer, sans rouvrir un acte
    signé, l'empreinte des conséquences d'exécution de cet acte.
    """
    if not chemin_relatif.startswith(f"{REPERTOIRE_ADOPTIONS}/"):
        return 0
    nom = Path(chemin_relatif).name
    correspondance = RE_FICHIER_EXECUTION.match(nom) or RE_FICHIER_ADOPTION.match(nom)
    return int(correspondance.group(1)) if correspondance else 0


def texte_compagnon(feuille: Path) -> Path | None:
    """Texte adopté que décrit une feuille de statut.

    La feuille `X-STATUT.md` déclare l'empreinte d'un texte qu'elle ne nomme
    pas : c'est le fichier du même répertoire dont le nom commence par le même
    radical `X`. Le radical ne suffit pas toujours à désigner un fichier unique
    (`SOURCES-0001-STATUT.md` face à `SOURCES-0001-hierarchie-….md`) ; on exige
    donc une correspondance unique, faute de quoi le lien n'est pas établi et
    la déclaration reste non contrôlée plutôt que rapportée au mauvais texte.
    """
    correspondance = RE_FICHIER_STATUT.match(feuille.name)
    if not correspondance:
        return None
    base = correspondance.group("base")
    candidats = [
        p for p in feuille.parent.glob(f"{base}*.md")
        if p.is_file() and not p.name.endswith("-STATUT.md")
    ]
    return candidats[0] if len(candidats) == 1 else None


def declarations_des_feuilles_statut(racine: Path) -> list[tuple[str, str, str]]:
    """Empreintes déclarées par les feuilles de statut.

    Retourne des triplets (chemin déclaré, empreinte déclarée, déclarant).
    Ces déclarations reçoivent le rang 0 dans `C5`, si bien que toute
    déclaration ultérieure portée par un acte d'adoption les dépasse
    automatiquement — une feuille de statut est la déclaration d'origine, non
    la déclaration liante lorsqu'un acte postérieur a fait évoluer le texte.
    """
    trouvees: list[tuple[str, str, str]] = []
    for feuille in sorted((racine / "genesis-ii").rglob("*-STATUT.md")):
        compagnon = texte_compagnon(feuille)
        if compagnon is None:
            continue
        for ligne in lire(feuille).splitlines():
            correspondance = RE_EMPREINTE_STATUT.match(ligne.strip())
            if correspondance:
                trouvees.append((
                    compagnon.relative_to(racine).as_posix(),
                    correspondance.group(1),
                    feuille.relative_to(racine).as_posix(),
                ))
                break
    return trouvees


def controle_c5(racine: Path, rapport: Rapport) -> None:
    """Les empreintes Git déclarées correspondent aux fichiers publiés."""
    if empreinte_git(racine, INDEX_ADOPTIONS) is None:
        rapport.avertissement(
            "C5 — contrôle non exécuté : `git hash-object` indisponible"
        )
        print("  [SAUTÉ] C5 — Empreintes Git déclarées (Git indisponible)")
        return

    # chemin déclaré -> {rang du déclarant -> {empreinte déclarée -> déclarants}}
    declarations: dict[str, dict[int, dict[str, list[str]]]] = {}
    for fichier in fichiers_du_corpus(racine):
        relatif = fichier.relative_to(racine).as_posix()
        rang = rang_declarant(relatif)
        for ligne in lire(fichier).splitlines():
            correspondance = RE_LIGNE_EMPREINTE.match(ligne.strip())
            if not correspondance:
                continue
            chemin, declaree = correspondance.group(1), correspondance.group(2)
            (
                declarations
                .setdefault(chemin, {})
                .setdefault(rang, {})
                .setdefault(declaree, [])
                .append(relatif)
            )

    # Feuilles de statut : déclaration d'origine des textes fondateurs, au
    # rang 0. Elles étaient jusqu'ici hors de toute vérification.
    for chemin, declaree, declarant in declarations_des_feuilles_statut(racine):
        (
            declarations
            .setdefault(chemin, {})
            .setdefault(0, {})
            .setdefault(declaree, [])
            .append(declarant)
        )

    constats: list[str] = []
    verifiees = 0
    depassees = 0

    for chemin, par_rang in sorted(declarations.items()):
        rang_courant = max(par_rang)
        depassees += sum(
            len(declarants)
            for rang, empreintes in par_rang.items()
            if rang != rang_courant
            for declarants in empreintes.values()
        )
        liantes = par_rang[rang_courant]

        if len(liantes) > 1:
            detail = " ; ".join(
                f"`{empreinte}` ({', '.join(sorted(set(declarants)))})"
                for empreinte, declarants in sorted(liantes.items())
            )
            constats.append(
                f"`{chemin}` reçoit des empreintes contradictoires de même "
                f"rang : {detail}"
            )
            continue

        if chemin in CHEMINS_HORS_DEPOT:
            continue
        if not (racine / chemin).exists():
            continue  # signalé par C4

        declaree, declarants = next(iter(liantes.items()))
        verifiees += 1
        reelle = empreinte_git(racine, chemin)
        if reelle != declaree:
            constats.append(
                f"{', '.join(sorted(set(declarants)))} déclare `{declaree}` "
                f"pour `{chemin}` ; empreinte réelle `{reelle}`"
            )

    rapport.controle(
        "C5", f"Empreintes Git déclarées ({verifiees} vérifiées)", constats
    )
    if depassees:
        print(
            f"          · {depassees} déclaration(s) antérieure(s) dépassée(s) "
            f"par une adoption plus récente — constat historique, non contrôlé"
        )


def controle_c6(racine: Path, rapport: Rapport) -> None:
    """Le décompte annoncé par ADOPTION-0020 correspond à son Titre I."""
    chemin = racine / ADOPTION_0020
    if not chemin.exists():
        rapport.controle("C6", "Décompte d'ADOPTION-0020",
                         [f"fichier introuvable : {ADOPTION_0020}"])
        return

    texte = lire(chemin)
    chemins_uniques = {
        RE_LIGNE_EMPREINTE.match(ligne.strip()).group(1)
        for ligne in texte.splitlines()
        if RE_LIGNE_EMPREINTE.match(ligne.strip())
    }

    constats: list[str] = []
    correspondance = RE_DECOMPTE_0020.search(texte)
    if not correspondance:
        constats.append(
            "le décompte annoncé à l'Article 1 du Titre II est illisible "
            "(formulation modifiée ?)"
        )
    else:
        annonce = int(correspondance.group(1))
        if annonce != DECOMPTE_ATTENDU_0020:
            constats.append(
                f"ADOPTION-0020 annonce {annonce} fichiers ; "
                f"le contrôleur attend {DECOMPTE_ATTENDU_0020}"
            )
        if len(chemins_uniques) != annonce:
            constats.append(
                f"ADOPTION-0020 annonce {annonce} fichiers uniques ; "
                f"son Titre I en identifie {len(chemins_uniques)}"
            )

    rapport.controle(
        "C6",
        f"Décompte d'ADOPTION-0020 ({len(chemins_uniques)} fichiers uniques identifiés)",
        constats,
    )


def controle_a1(racine: Path, rapport: Rapport) -> None:
    """Constat indicatif sur la propreté des messages de commit."""
    try:
        resultat = subprocess.run(
            ["git", "log", "--format=%H%x1f%B%x1e", "-n", "200"],
            cwd=racine,
            capture_output=True,
            text=True,
            check=True,
        )
    except (OSError, subprocess.CalledProcessError):
        print("  [SAUTÉ] A1 — Messages de commit (historique indisponible)")
        return

    pollues: list[str] = []
    for entree in resultat.stdout.split("\x1e"):
        if "\x1f" not in entree:
            continue
        empreinte, message = entree.split("\x1f", 1)
        if RE_COMMENTAIRE_EDITEUR.search(message):
            titre = message.strip().splitlines()[0][:60]
            pollues.append(f"{empreinte.strip()[:7]} — {titre}…")

    if pollues:
        print("  [NOTE]  A1 — Messages de commit portant les commentaires de l'éditeur")
        for entree in pollues:
            print(f"          · {entree}")
        print("          (constat non bloquant : l'historique publié n'est pas réécrit)")
        for entree in pollues:
            rapport.avertissement(f"A1 — message de commit pollué : {entree}")
    else:
        print("  [OK]    A1 — Messages de commit exempts de commentaires d'éditeur")


# --------------------------------------------------------------------------
# Programme principal
# --------------------------------------------------------------------------


def main() -> int:
    analyseur = argparse.ArgumentParser(
        description="Contrôle d'intégrité documentaire du corpus Genesis II."
    )
    analyseur.add_argument(
        "--racine",
        default=str(Path(__file__).resolve().parent.parent),
        help="racine du dépôt (par défaut : le dépôt contenant ce fichier)",
    )
    arguments = analyseur.parse_args()
    racine = Path(arguments.racine).resolve()

    print("CONTRÔLE D'INTÉGRITÉ DOCUMENTAIRE — GAMAD CORE / GENESIS II")
    print(f"Racine : {racine}")
    print()

    if not (racine / "genesis-ii").is_dir():
        print(f"ERREUR : aucun répertoire `genesis-ii/` sous {racine}", file=sys.stderr)
        return 1

    rapport = Rapport()
    controle_c1_c2_c3(racine, rapport)
    controle_c4(racine, rapport)
    controle_c5(racine, rapport)
    controle_c6(racine, rapport)
    controle_a1(racine, rapport)

    print()
    if rapport.avertissements:
        print(f"Avertissements : {len(rapport.avertissements)} (non bloquants)")
    if rapport.erreurs:
        print(f"ERREURS BLOQUANTES : {len(rapport.erreurs)}")
        for erreur in rapport.erreurs:
            print(f"  · {erreur}")
        print()
        print("Intégrité documentaire : NON VÉRIFIÉE.")
        return 1

    print("Intégrité documentaire : VÉRIFIÉE.")
    print()
    print("Ce constat porte sur la cohérence documentaire du dépôt et sur elle seule.")
    print("Il ne constate pas la Porte G0 et ne rend aucune capacité opérationnelle.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
