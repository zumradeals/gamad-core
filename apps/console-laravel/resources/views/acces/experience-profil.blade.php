@php($id = $experience['reference'] ?? 'nouvelle')
<form @if ($dansEspace ?? false) data-async @endif class="auth-form" method="post" action="{{ isset($experience) ? route('compte.profil.experiences.modifier', $id) : route('compte.profil.experiences.ajouter') }}">
    @if ($dansEspace ?? false)<input type="hidden" name="_espace" value="1"><div class="espace-erreurs" role="alert" tabindex="-1"></div>@endif
    @csrf
    @isset($experience) @method('PATCH') @endisset
    <input type="hidden" name="experience_formulaire" value="{{ $id }}">
    <label for="experience-titre-{{ $id }}">Titre</label>
    <input class="input" id="experience-titre-{{ $id }}" name="titre" maxlength="100" required value="{{ old('experience_formulaire') === $id ? old('titre') : ($experience['titre'] ?? '') }}">
    <label for="experience-type-{{ $id }}">Type</label>
    <select class="input" id="experience-type-{{ $id }}" name="type" required>
        @foreach ($typesExperience as $code => $libelle)
            <option value="{{ $code }}" @selected((old('experience_formulaire') === $id ? old('type') : ($experience['type'] ?? null)) === $code)>{{ $libelle }}</option>
        @endforeach
    </select>
    <label for="experience-periode-{{ $id }}">Période</label>
    <input class="input" id="experience-periode-{{ $id }}" name="periode" maxlength="80" value="{{ old('experience_formulaire') === $id ? old('periode') : ($experience['periode'] ?? '') }}">
    <label for="experience-description-{{ $id }}">Description</label>
    <textarea class="input" id="experience-description-{{ $id }}" name="description" maxlength="500" rows="3">{{ old('experience_formulaire') === $id ? old('description') : ($experience['description'] ?? '') }}</textarea>
    <button class="button" type="submit">{{ isset($experience) ? 'Enregistrer l’expérience' : 'Ajouter l’expérience' }}</button>
</form>
