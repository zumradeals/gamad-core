@if ($ligne['retire_le'] !== null)
    <p>Commentaire retiré.</p>
@else
    <strong>{{ $ligne['auteur'] }}</strong>
    <p class="texte-reseau">{{ $ligne['contenu'] }}</p>
    @if ($peut_commenter)
        @include('reseau.signalement', ['cibleCommentaire' => $ligne['reference']])
    @endif
@endif
