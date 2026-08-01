#!/usr/bin/env python3
"""Serveur FTP minimal, destiné aux seules épreuves de la copie hors machine.

Ce n'est PAS un serveur d'exploitation : il n'implémente que ce dont `curl` a
besoin pour déposer, lister, relire et supprimer un fichier, en mode passif,
sur une boucle locale.

Sa raison d'être : éprouver `offsite.sh` et `offsite-drill.sh` contre un
véritable dialogue FTP plutôt que contre une simulation de commande. Il ne
prouve pas la compatibilité avec un serveur d'hébergeur donné — seule une
première exécution réelle le prouvera.

Usage : serveur_ftp_double.py <racine> <port> [utilisateur] [motdepasse]
"""

from __future__ import annotations

import os
import socket
import sys
import threading

HOTE = "127.0.0.1"


class Session(threading.Thread):
    def __init__(self, connexion: socket.socket, racine: str, utilisateur: str, secret: str):
        super().__init__(daemon=True)
        self.connexion = connexion
        self.racine = os.path.abspath(racine)
        self.utilisateur = utilisateur
        self.secret = secret
        self.courant = "/"
        self.authentifie = False
        self.donnees: socket.socket | None = None

    # -- utilitaires ---------------------------------------------------
    def repondre(self, texte: str) -> None:
        self.connexion.sendall((texte + "\r\n").encode("utf-8"))

    def chemin_reel(self, chemin: str) -> str:
        if not chemin.startswith("/"):
            chemin = os.path.join(self.courant, chemin)
        reel = os.path.abspath(os.path.join(self.racine, chemin.lstrip("/")))
        # Aucune sortie de la racine, même dans un double d'épreuve.
        if not (reel == self.racine or reel.startswith(self.racine + os.sep)):
            raise PermissionError(chemin)
        return reel

    def ouvrir_passif(self) -> int:
        ecoute = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        ecoute.bind((HOTE, 0))
        ecoute.listen(1)
        port = ecoute.getsockname()[1]
        self._ecoute = ecoute
        return port

    def accepter_donnees(self) -> socket.socket:
        canal, _ = self._ecoute.accept()
        self._ecoute.close()
        return canal

    # -- boucle --------------------------------------------------------
    def run(self) -> None:
        try:
            self.repondre("220 Double FTP d'épreuve GAMAD Core")
            tampon = b""
            while True:
                morceau = self.connexion.recv(4096)
                if not morceau:
                    break
                tampon += morceau
                while b"\r\n" in tampon:
                    ligne, tampon = tampon.split(b"\r\n", 1)
                    if not self.traiter(ligne.decode("utf-8", "replace")):
                        return
        except Exception:
            pass
        finally:
            try:
                self.connexion.close()
            except Exception:
                pass

    def traiter(self, ligne: str) -> bool:
        if not ligne:
            return True
        partie = ligne.split(" ", 1)
        commande = partie[0].upper()
        argument = partie[1] if len(partie) > 1 else ""

        if commande == "USER":
            self.repondre("331 Mot de passe requis")
        elif commande == "PASS":
            if argument == self.secret:
                self.authentifie = True
                self.repondre("230 Authentifié")
            else:
                self.repondre("530 Authentification refusée")
        elif not self.authentifie:
            self.repondre("530 Authentification requise")
        elif commande == "SYST":
            self.repondre("215 UNIX Type: L8")
        elif commande in {"FEAT", "OPTS"}:
            self.repondre("211 Aucune extension")
        elif commande == "AUTH":
            # Le double ne parle pas TLS : le mode opportuniste doit savoir
            # continuer en clair après ce refus.
            self.repondre("500 TLS non disponible")
        elif commande == "PWD":
            self.repondre(f'257 "{self.courant}" est le répertoire courant')
        elif commande == "TYPE":
            self.repondre("200 Type accepté")
        elif commande == "EPSV":
            self.repondre("500 EPSV non implémenté")
        elif commande == "PASV":
            port = self.ouvrir_passif()
            h = HOTE.split(".")
            self.repondre(
                f"227 Entering Passive Mode ({h[0]},{h[1]},{h[2]},{h[3]},{port // 256},{port % 256})"
            )
        elif commande == "CWD":
            try:
                reel = self.chemin_reel(argument)
            except PermissionError:
                self.repondre("550 Chemin refusé")
                return True
            if os.path.isdir(reel):
                self.courant = "/" + os.path.relpath(reel, self.racine).replace(os.sep, "/")
                self.courant = "/" if self.courant == "/." else self.courant
                self.repondre("250 Répertoire changé")
            else:
                self.repondre("550 Répertoire inconnu")
        elif commande == "MKD":
            try:
                os.makedirs(self.chemin_reel(argument), exist_ok=True)
                self.repondre(f'257 "{argument}" créé')
            except Exception:
                self.repondre("550 Création impossible")
        elif commande == "SIZE":
            try:
                self.repondre(f"213 {os.path.getsize(self.chemin_reel(argument))}")
            except Exception:
                self.repondre("550 Taille inconnue")
        elif commande == "DELE":
            try:
                os.remove(self.chemin_reel(argument))
                self.repondre("250 Supprimé")
            except Exception:
                self.repondre("550 Suppression impossible")
        elif commande in {"STOR", "RETR", "LIST", "NLST"}:
            self.transferer(commande, argument)
        elif commande == "QUIT":
            self.repondre("221 Au revoir")
            return False
        else:
            self.repondre("502 Commande non implémentée")
        return True

    def transferer(self, commande: str, argument: str) -> None:
        try:
            cible = self.chemin_reel(argument) if argument else self.chemin_reel(self.courant)
        except PermissionError:
            self.repondre("550 Chemin refusé")
            return

        self.repondre("150 Ouverture du canal de données")
        canal = self.accepter_donnees()
        try:
            if commande == "STOR":
                with open(cible, "wb") as fichier:
                    while True:
                        bloc = canal.recv(65536)
                        if not bloc:
                            break
                        fichier.write(bloc)
            elif commande == "RETR":
                with open(cible, "rb") as fichier:
                    while True:
                        bloc = fichier.read(65536)
                        if not bloc:
                            break
                        canal.sendall(bloc)
            else:
                dossier = cible if os.path.isdir(cible) else os.path.dirname(cible)
                noms = sorted(os.listdir(dossier))
                if commande == "NLST":
                    corps = "".join(f"{nom}\r\n" for nom in noms)
                else:
                    corps = ""
                    for nom in noms:
                        taille = os.path.getsize(os.path.join(dossier, nom))
                        corps += f"-rw-r--r-- 1 gamad gamad {taille} Jan  1 00:00 {nom}\r\n"
                canal.sendall(corps.encode("utf-8"))
        except Exception:
            canal.close()
            self.repondre("451 Transfert interrompu")
            return
        canal.close()
        self.repondre("226 Transfert terminé")


def main() -> int:
    if len(sys.argv) < 3:
        print(__doc__, file=sys.stderr)
        return 2
    racine = sys.argv[1]
    port = int(sys.argv[2])
    utilisateur = sys.argv[3] if len(sys.argv) > 3 else "gamad"
    secret = sys.argv[4] if len(sys.argv) > 4 else "epreuve"
    os.makedirs(racine, exist_ok=True)

    ecoute = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    ecoute.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    ecoute.bind((HOTE, port))
    ecoute.listen(8)
    print(f"double FTP prêt sur {HOTE}:{port}", flush=True)

    while True:
        connexion, _ = ecoute.accept()
        Session(connexion, racine, utilisateur, secret).start()


if __name__ == "__main__":
    raise SystemExit(main())
