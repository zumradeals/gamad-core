<dialog id="edition-profil" class="espace-dialogue" aria-labelledby="edition-profil-titre"><header><h2 id="edition-profil-titre">Modifier mon profil</h2><button type="button" class="button" data-close>Fermer</button></header><p>Ces informations alimentent votre fiche selon la visibilité choisie.</p>        <form class="espace-form" data-async method="post" action="{{ route('compte.profil.modifier') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_espace" value="1">
            
            <p>{{ $compte['nom'] }}</p>
            @if (session('profil_enregistre'))
                <p role="status">Présentation enregistrée.</p>
            @endif
            <div class="field">
                <label for="bio">Ma présentation</label>
                <textarea class="input" id="bio" name="bio" maxlength="{{ $bioMax }}" rows="5">{{ old('bio', $compte['bio']) }}</textarea>
            </div>
            <div class="field">
                <label for="visibilite">Visibilité du profil</label>
                <select class="input" id="visibilite" name="visibilite">
                    @foreach (['prive' => 'Privé', 'members' => 'Membres avec e-mail vérifié', 'public' => 'Public'] as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(old('visibilite', $fiche['visibilite'] ?? 'prive') === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="apportees">Ce que je peux apporter (une compétence par ligne)</label>
                <textarea class="input" id="apportees" name="apportees" rows="4">{{ old('apportees', $fiche['competences_apportees'] ?? '') }}</textarea>
            </div>
            <div class="field">
                <label for="recherchees">Ce que je veux apprendre (une compétence par ligne)</label>
                <textarea class="input" id="recherchees" name="recherchees" rows="4">{{ old('recherchees', $fiche['competences_recherchees'] ?? '') }}</textarea>
            </div>
            <div class="field">
                <label for="titre">Titre de profil</label>
                <input class="input" id="titre" name="titre" maxlength="100" value="{{ old('titre', $fiche['titre'] ?? '') }}">
            </div>
            <div class="field">
                <label for="introduction">Introduction</label>
                <textarea class="input" id="introduction" name="introduction" maxlength="1200" rows="4">{{ old('introduction', $fiche['introduction'] ?? '') }}</textarea>
            </div>
            <div class="field">
                <label for="territoire">Localité</label>
                <select class="input" id="territoire" name="territoire">
                    <option value="">Non renseignée</option>
                    @foreach ($territoires as $code => $libelle)
                        <option value="{{ $code }}" @selected(old('territoire', $fiche['territoire'] ?? null) === $code)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <fieldset>
                <legend>Domaines d’action (trois maximum)</legend>
                <input type="hidden" name="domaines_present" value="1">
                @foreach ($domaines as $code => $libelle)
                    <label><input type="checkbox" name="domaines[]" value="{{ $code }}" @checked(in_array($code, (array) old('domaines', old('domaines_present') ? [] : ($fiche['domaines'] ?? [])), true))> {{ $libelle }}</label>
                @endforeach
            </fieldset>
            @foreach ($errors->all() as $erreur)
                <p class="form-error" role="alert">{{ $erreur }}</p>
            @endforeach
            <div class="espace-erreurs" role="alert" tabindex="-1"></div><button class="button button--primary" type="submit">Enregistrer les modifications</button>




        </form></dialog>
<dialog id="edition-experiences" class="espace-dialogue" aria-labelledby="edition-experiences-titre">
    <header><h2 id="edition-experiences-titre">Mes expériences</h2><button class="button" type="button" data-close>Fermer</button></header>
    @if ($fiche === [])<p>Enregistrez d’abord votre profil pour ajouter une expérience.</p>@else
        @foreach ($fiche['experiences'] as $experience)
            <details><summary>{{ $experience['titre'] }}</summary>
                @include('acces.experience-profil', ['experience' => $experience, 'dansEspace' => true])
                <form method="post" action="{{ route('compte.profil.experiences.retirer', $experience['reference']) }}" data-async data-confirm="Retirer cette expérience ?">
                    @csrf @method('DELETE')<input type="hidden" name="_espace" value="1"><div class="espace-erreurs" role="alert" tabindex="-1"></div><button class="button" type="submit">Retirer l’expérience</button>
                </form>
            </details>
        @endforeach
        @if (count($fiche['experiences']) < 10)<details open><summary>Ajouter une expérience</summary>@include('acces.experience-profil', ['experience' => null, 'dansEspace' => true])</details>@endif
    @endif
</dialog>
<dialog id="edition-publication" class="espace-dialogue" aria-labelledby="edition-publication-titre">
    <header><h2 id="edition-publication-titre">Partager une activité</h2><button class="button" type="button" data-close>Fermer</button></header>
    <form class="espace-form" method="post" action="{{ route('publications.store') }}" data-async>
        @csrf <input type="hidden" name="_espace" value="1">
        <div class="field"><label for="publication-type">Type de publication</label><select class="input" id="publication-type" name="type">@foreach ($typesPublication as $code => $libelle)<option value="{{ $code }}">{{ $libelle }}</option>@endforeach</select></div>
        <div class="field"><label for="publication-visibilite">Qui peut voir cette publication ?</label><select class="input" id="publication-visibilite" name="visibilite"><option value="members">Membres vérifiés</option><option value="public">Public</option></select><p class="espace-note">Cette audience est indépendante de la visibilité de votre profil.</p></div>
        <div class="field"><label for="publication-contenu">Votre texte (500 caractères maximum)</label><textarea class="input" id="publication-contenu" name="contenu" rows="6" required maxlength="500"></textarea></div>
        <p>La modification et le retrait des publications ne sont pas encore disponibles ici.</p>
        <div class="espace-erreurs" role="alert" tabindex="-1"></div><button class="button button--primary" type="submit">Publier</button>
    </form>
</dialog>
