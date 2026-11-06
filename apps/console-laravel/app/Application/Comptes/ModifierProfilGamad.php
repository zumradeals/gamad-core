<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use App\Support\Normalizer;
use Gamad\RegistreIdentites\Magasin;
use Illuminate\Validation\ValidationException;

final class ModifierProfilGamad
{
    public const BIO_MAX = 280;

    public function __construct(private LireCompteGamad $lecture)
    {
    }

    public function executer(string $identite, ?string $bio, ?array $fiche = null): bool
    {
        $registre = Magasin::ouvrir();
        if ($this->lecture->executer($identite, $registre) === null) {
            return false;
        }

        $presentation = Normalizer::multiline($bio ?? '');
        $apportees = $fiche === null ? '' : $this->competences($fiche['apportees'] ?? '');
        $recherchees = $fiche === null ? '' : $this->competences($fiche['recherchees'] ?? '');

        $registre->beginTransaction();
        try {
            $registre->prepare(
                'INSERT INTO profil_compte_gamad (identite_reference, bio) VALUES (?, ?)
                 ON CONFLICT (identite_reference) DO UPDATE SET bio = excluded.bio'
            )->execute([$identite, $presentation === '' ? null : $presentation]);
            if ($fiche !== null) {
                $registre->prepare(
                    'INSERT INTO fiche_annuaire_gamad
                        (identite_reference, reference_publique, visibilite, competences_apportees, competences_recherchees)
                     VALUES (?, ?, ?, ?, ?)
                     ON CONFLICT (identite_reference) DO UPDATE SET visibilite = excluded.visibilite,
                         competences_apportees = excluded.competences_apportees,
                         competences_recherchees = excluded.competences_recherchees'
                )->execute([$identite, bin2hex(random_bytes(12)), $fiche['visibilite'], $apportees, $recherchees]);
            }
            $registre->commit();
        } catch (\Throwable $exception) {
            $registre->rollBack();
            throw $exception;
        }

        return true;
    }

    private function competences(string $texte): string
    {
        $competences = [];
        foreach (preg_split('/\R/u', $texte) ?: [] as $ligne) {
            $libelle = Normalizer::display($ligne);
            if ($libelle !== '') {
                $competences[Normalizer::key($libelle)] ??= $libelle;
            }
        }

        if (count($competences) > 10 || array_filter($competences, static fn (string $libelle): bool => mb_strlen($libelle) > 60)) {
            throw ValidationException::withMessages(['competences' => 'Indiquez jusqu’à dix compétences de 60 caractères par liste.']);
        }

        return implode("\n", $competences);
    }
}
