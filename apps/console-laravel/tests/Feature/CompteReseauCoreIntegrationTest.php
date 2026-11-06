<?php

use App\Support\Unification\ResoudreCompteReseauCore;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\SchemaInscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('resout une vraie session Core avec magasins distincts puis refuse la revocation', function () {
    $identitesPath = tempnam(sys_get_temp_dir(), 'gamad-id-');
    $accesPath = tempnam(sys_get_temp_dir(), 'gamad-ac-');

    try {
        config()->set('database.connections.gamad_identity.url', null);
        config()->set('database.connections.gamad_identity.database', $identitesPath);
        config()->set('database.connections.gamad_access.url', null);
        config()->set('database.connections.gamad_access.database', $accesPath);

        $identites = new PDO('sqlite:'.$identitesPath);
        $identites->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        SchemaInscription::migrer($identites);
        $identites->prepare(
            'INSERT INTO identite_inscrite
             (reference,type,libelle,regime,provisoire,canal,producteur,
              politique_inscription,source_inscription,preuve_reference,
              classification,date_creation)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            'PER-RESEAU-001', 'personne', 'Compte de qualification',
            'INSCRIT_AU_REGISTRE', 0, 'TEST', 'CORE',
            'TEST', 'TEST', 'PREUVE-TEST', 'INTERNE', date('Y-m-d'),
        ]);
        $identites->prepare(
            'INSERT INTO evenement_cycle_identite
             (identite_reference,evenement_type,etat_avant,etat_apres,source,
              preuve_reference,politique_inscription,acteur_reference,date_effet)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            'PER-RESEAU-001', 'ACTIVATION', null, 'ACTIVE', 'TEST',
            'PREUVE-TEST', 'TEST', 'CORE', date('Y-m-d', strtotime('-1 day')),
        ]);

        $user = \App\Models\User::factory()->create();
        DB::table('reseau_comptes_identites')->insert([
            'user_id' => $user->id, 'identite_reference' => 'PER-RESEAU-001',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $acces = new PDO('sqlite:'.$accesPath);
        $acces->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        AccesMagasin::creer($acces);
        $ctr = new Ctr16($acces);
        $ctr->inscrireAuthentificateur('PER-RESEAU-001', 'qualification-core-reseau-2026');
        $session = $ctr->etablirSession('PER-RESEAU-001', 'qualification-core-reseau-2026');
        expect($session)->not->toBeNull();

        $request = Request::create('/reseau/mon-compte', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put('gamad_session', $session['session']);

        $compte = ResoudreCompteReseauCore::resoudre($request);
        expect($compte)->not->toBeNull();
        expect($compte->userId)->toBe((string) $user->id);

        $identites->prepare(
            'INSERT INTO evenement_cycle_identite
             (identite_reference,evenement_type,etat_avant,etat_apres,source,
              preuve_reference,politique_inscription,acteur_reference,date_effet)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            'PER-RESEAU-001', 'REVOCATION', 'ACTIVE', 'REVOQUEE', 'TEST',
            'PREUVE-TEST', 'TEST', 'CORE', date('Y-m-d'),
        ]);
        expect(ResoudreCompteReseauCore::resoudre($request))->toBeNull();

        $request->session()->forget('gamad_session');
        expect(ResoudreCompteReseauCore::resoudre($request))->toBeNull();
    } finally {
        @unlink($identitesPath);
        @unlink($accesPath);
    }
});
