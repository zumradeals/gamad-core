@php
    $footer = config('gamad_public.footer', []);
    $columns = $footer['columns'] ?? [];
@endphp
<footer class="gamad-footer">
    <div class="conteneur gamad-footer-grid">
        <div class="gamad-footer-brand">
            <a class="marque" href="{{ route('reseau.accueil') }}">GAMAD <small>Réseau</small></a>
            <p>{{ $footer['description'] ?? 'Le réseau social d’action pour les personnes et leurs communautés.' }}</p>
            @foreach (($footer['social_links'] ?? []) as $social)
                @if (!empty($social['label']) && !empty($social['url']) && filter_var($social['url'], FILTER_VALIDATE_URL) && in_array(parse_url($social['url'], PHP_URL_SCHEME), ['https'], true))
                    <a href="{{ $social['url'] }}" rel="noopener noreferrer" target="_blank">{{ $social['label'] }}</a>
                @endif
            @endforeach
        </div>
        @foreach ($columns as $column)
            <nav aria-label="{{ $column['title'] ?? 'Liens' }}">
                <h2>{{ $column['title'] ?? 'Liens' }}</h2>
                @foreach (($column['links'] ?? []) as $link)
                    @php
                        $href = isset($link['route']) && Route::has($link['route']) ? route($link['route']) : ($link['url'] ?? null);
                        $allowed = is_string($href) && (str_starts_with($href, '/') && !str_starts_with($href, '//') || str_starts_with($href, 'https://'));
                    @endphp
                    @if ($allowed)
                        <a href="{{ $href }}">{{ $link['label'] ?? 'Lien' }}</a>
                    @endif
                @endforeach
            </nav>
        @endforeach
    </div>
    <div class="conteneur gamad-footer-bottom">
        <span>© {{ date('Y') }} GAMAD. {{ $footer['bottom_text'] ?? 'Tous droits réservés.' }}</span>
        <span>Le réseau social d’action</span>
    </div>
</footer>