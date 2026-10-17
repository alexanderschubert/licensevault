@php $tabs = ['settings.password' => 'Passwort', 'settings.notifications' => 'Benachrichtigungen']; @endphp
<nav class="mb-6 inline-flex overflow-hidden rounded-xl border border-line bg-panel text-sm" aria-label="Einstellungen">
    @foreach($tabs as $route => $label)
        <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif
           class="{{ $loop->first ? '' : 'border-l border-line' }} px-4 py-2.5 font-medium {{ request()->routeIs($route) ? 'bg-acs text-ac' : 'text-ink2 hover:bg-panel2' }}">{{ $label }}</a>
    @endforeach
</nav>
