<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reseau\BlocagesGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BlocagesGamadController
{
    public function index(Request $request, BlocagesGamad $blocages): Response
    {
        $liste = $blocages->lister((string) $request->attributes->get('gamad_entite'));
        abort_if($liste === null, 403);

        return response()->view('reseau.blocages', ['blocages' => $liste])->header('Cache-Control', 'private, no-store');
    }

    public function modifier(Request $request, BlocagesGamad $blocages, string $reference): RedirectResponse
    {
        abort_unless($blocages->modifier(
            (string) $request->attributes->get('gamad_entite'),
            $reference,
            $request->isMethod('POST'),
        ), 404);

        return redirect()->route('blocages.index');
    }
}
