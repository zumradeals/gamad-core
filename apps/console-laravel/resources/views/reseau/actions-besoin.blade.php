<section aria-labelledby="aide-besoin" class="section">
    <h2 id="aide-besoin">Entraide</h2>
    @php
        $reference = $publication['reference'];
        $etat = $publication['etat_besoin'];
        $ouvert = in_array($etat, ['open', 'in_progress'], true);
        $etats = ['open' => 'Ouvert', 'in_progress' => 'En cours', 'resolved' => 'Résolu', 'closed' => 'Clos'];
        $etatsAide = ['pending' => 'En attente', 'accepted' => 'Acceptée', 'declined' => 'Refusée', 'withdrawn' => 'Retirée'];
    @endphp
    <p>État du besoin · {{ $etats[$etat] }}</p>
    @if ($peut_commenter)
        @if ($ouvert && !$gere_besoin && ($propositions === [] || $propositions[0]['etat'] === 'withdrawn'))
            <form method="post" action="{{ route('besoins.propositions.store', $reference) }}" class="formulaire-reseau">
                @csrf
                <label for="message-aide">Comment pouvez-vous aider ?</label>
                <textarea id="message-aide" name="message" maxlength="280" rows="3">{{ old('message') }}</textarea>
                <button class="bouton accent">Proposer mon aide</button>
            </form>
        @endif
        @foreach ($propositions as $aide)
            <article>
                <h3>{{ $gere_besoin ? $aide['proposant'] : 'Votre proposition' }} · {{ $etatsAide[$aide['etat']] }}</h3>
                <p class="texte-reseau">{{ $aide['message'] }}</p>
                @if ($aide['etat'] === 'pending')
                    <form method="post" action="{{ route('besoins.propositions.traiter', [$reference, $aide['reference']]) }}">
                        @csrf
                        @method('PATCH')
                        @if ($gere_besoin && $ouvert)
                            <button class="bouton accent" name="action" value="accepter">Accepter l’aide</button>
                            <button class="bouton" name="action" value="refuser">Refuser</button>
                        @elseif (!$gere_besoin)
                            <button class="bouton" name="action" value="retirer">Retirer ma proposition</button>
                        @endif
                    </form>
                @elseif ($aide['etat'] === 'accepted')
                    <a href="{{ route('besoins.propositions.messages.index', [$reference, $aide['reference']]) }}">Ouvrir l’échange privé</a>
                @endif
            </article>
        @endforeach
        @if ($gere_besoin && $ouvert)
            <form method="post" action="{{ route('besoins.etat', $reference) }}" class="section">
                @csrf
                @method('PATCH')
                <button class="bouton" name="action" value="resoudre">Marquer comme résolu</button>
                <button class="bouton" name="action" value="fermer">Clore sans résolution</button>
            </form>
        @endif
    @elseif ($ouvert)
        <a href="{{ route('reseau.connexion') }}">Se connecter pour proposer de l’aide</a>
    @endif
</section>
