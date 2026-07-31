#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Point d'entrée actif du contrôle documentaire pendant la transition.

Le contrôleur historique Genesis II reste conservé sans modification dans
`outils/verifier-integrite-genesis.py`. Ce point d'entrée réutilise tous ses
contrôles et remplace uniquement C5 afin que les consignes opérationnelles de
`CLAUDE.md` puissent évoluer sans réécrire un constat historique.
"""

from __future__ import annotations

import importlib.util
import sys
from pathlib import Path
from types import ModuleType
from typing import Any

CONTROLEUR_HISTORIQUE = Path(__file__).with_name("verifier-integrite-genesis.py")

CHEMINS_OPERATIONNELS_HORS_EMPREINTE = {
    "CLAUDE.md": (
        "consignes opérationnelles évolutives ; l'ancienne empreinte reste "
        "un constat historique dans Genesis II"
    ),
}


def charger_controleur_historique() -> ModuleType:
    specification = importlib.util.spec_from_file_location(
        "gamad_verifier_integrite_genesis",
        CONTROLEUR_HISTORIQUE,
    )
    if specification is None or specification.loader is None:
        raise RuntimeError(
            f"Impossible de charger le contrôleur historique : {CONTROLEUR_HISTORIQUE}"
        )

    module = importlib.util.module_from_spec(specification)
    specification.loader.exec_module(module)
    return module


def controle_c5_operationnel(
    historique: ModuleType,
    racine: Path,
    rapport: Any,
) -> None:
    """Vérifie les empreintes, sauf les chemins opérationnels explicitement listés."""
    if historique.empreinte_git(racine, historique.INDEX_ADOPTIONS) is None:
        rapport.avertissement(
            "C5 — contrôle non exécuté : `git hash-object` indisponible"
        )
        print("  [SAUTÉ] C5 — Empreintes Git déclarées (Git indisponible)")
        return

    declarations: dict[str, dict[int, dict[str, list[str]]]] = {}
    for fichier in historique.fichiers_du_corpus(racine):
        relatif = fichier.relative_to(racine).as_posix()
        rang = historique.rang_declarant(relatif)
        for ligne in historique.lire(fichier).splitlines():
            correspondance = historique.RE_LIGNE_EMPREINTE.match(ligne.strip())
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

    for chemin, declaree, declarant in historique.declarations_des_feuilles_statut(
        racine
    ):
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
    operationnels_decouples: set[str] = set()

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

        if chemin in CHEMINS_OPERATIONNELS_HORS_EMPREINTE:
            operationnels_decouples.add(chemin)
            continue
        if chemin in historique.CHEMINS_HORS_DEPOT:
            continue
        if not (racine / chemin).exists():
            continue

        declaree, declarants = next(iter(liantes.items()))
        verifiees += 1
        reelle = historique.empreinte_git(racine, chemin)
        if reelle != declaree:
            constats.append(
                f"{', '.join(sorted(set(declarants)))} déclare `{declaree}` "
                f"pour `{chemin}` ; empreinte réelle `{reelle}`"
            )

    rapport.controle(
        "C5", f"Empreintes Git déclarées ({verifiees} vérifiées)", constats
    )

    for chemin in sorted(operationnels_decouples):
        print(
            f"          · `{chemin}` découplé de l'empreinte historique — "
            f"{CHEMINS_OPERATIONNELS_HORS_EMPREINTE[chemin]}"
        )

    inutilises = sorted(
        set(CHEMINS_OPERATIONNELS_HORS_EMPREINTE) - operationnels_decouples
    )
    for chemin in inutilises:
        rapport.avertissement(
            f"C5 — chemin opérationnel déclaré mais non rencontré : `{chemin}`"
        )

    if depassees:
        print(
            f"          · {depassees} déclaration(s) antérieure(s) dépassée(s) "
            f"par une adoption plus récente — constat historique, non contrôlé"
        )


def main() -> int:
    historique = charger_controleur_historique()
    historique.controle_c5 = lambda racine, rapport: controle_c5_operationnel(
        historique,
        racine,
        rapport,
    )
    return int(historique.main())


if __name__ == "__main__":
    sys.exit(main())
