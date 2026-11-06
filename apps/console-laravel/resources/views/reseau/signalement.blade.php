<details>
    <summary>Signaler ce contenu</summary>
    <form method="post" action="{{ route('publications.signaler', [$publication['reference'], $cibleCommentaire ?? null]) }}">
        @csrf
        <label>Motif
            <select name="motif" required>
                @foreach ($motifs as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>
        </label>
        <button class="bouton" type="submit">Envoyer le signalement</button>
    </form>
</details>
