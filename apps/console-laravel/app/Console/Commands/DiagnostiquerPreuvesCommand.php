<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistrePreuves\Magasin as PreuvesMagasin;
use Gamad\RegistrePreuves\RegistrePreuves;
use Illuminate\Console\Command;

/** Diagnostic en lecture seule du registre des preuves (CAP-CORE-015). */
final class DiagnostiquerPreuvesCommand extends Command
{
    protected $signature = 'core:preuves:diagnostiquer';

    protected $description = 'Rapporte l’état du registre des preuves d’intégrité, sans réparation implicite.';

    public function handle(): int
    {
        try {
            $registre = new RegistrePreuves(PreuvesMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Diagnostic interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $diagnostic = $registre->diagnostiquerRegistre();
        $this->info("Preuves : {$diagnostic['preuves']}");
        $this->info("Actives : {$diagnostic['actives']}");
        $this->info("Compromises : {$diagnostic['compromises']}");
        $this->info("Préparées bloquées (jamais émises) : {$diagnostic['preparees_bloquees']}");
        $this->info('Extension sodium disponible : ' . ($diagnostic['sodium_disponible'] ? 'oui' : 'non'));

        if (!$diagnostic['sodium_disponible']) {
            $this->error('sodium indisponible : aucune signature Ed25519 ne peut être émise ni vérifiée.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
