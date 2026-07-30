<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreNormes\Db;
use Illuminate\Console\Command;

/**
 * Produit l'autorisation éphémère qui amorce une passkey.
 *
 * La commande reste volontairement hors HTTP : posséder un mot de passe ne
 * suffit pas pour attacher un nouveau facteur fort à une autorité.
 */
final class PreparerPasskeyCommand extends Command
{
    protected $signature = 'identite:preparer-passkey
                            {entite : référence de l’entité}
                            {--duree=600 : validité en secondes, de 60 à 1800}';

    protected $description = 'Prépare une autorisation unique et temporaire d’enrôlement WebAuthn.';

    public function handle(): int
    {
        $entite = (string) $this->argument('entite');
        try {
            $identite = (new Ctr01(Db::connect()))->resoudreIdentite($entite);
        } catch (\Throwable $e) {
            $this->error('Identity Registry indisponible : '.$e->getMessage());

            return self::FAILURE;
        }
        if ($identite === null) {
            $this->error("Entité inconnue : {$entite}. Une passkey ne crée jamais une identité.");

            return self::FAILURE;
        }

        try {
            $autorisation = (new Ctr16(Magasin::connecter()))->preparerEnrolementPasskey(
                $entite,
                (int) $this->option('duree'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Autorisation d’enrôlement créée.');
        $this->line("Entité : {$entite}");
        $this->line("Expire : {$autorisation['expire_le']}");
        $this->newLine();
        $this->warn('Jeton à usage unique — ne pas le transmettre par messagerie ou l’inscrire dans un fichier :');
        $this->line($autorisation['jeton']);
        $this->newLine();
        $this->line('Ouvrir ensuite : '.rtrim((string) config('app.url'), '/').'/passkeys/enrolement');

        return self::SUCCESS;
    }
}
