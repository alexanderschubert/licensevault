@props(['route', 'icon', 'soon' => false, 'match' => null, 'count' => 0])
@php $on = request()->routeIs($match ?? $route); @endphp
<a href="{{ route($route) }}" @if($on) aria-current="page" @endif
   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $on ? 'bg-acs text-ac font-semibold' : 'text-ink2 hover:bg-panel2' }}">
    <x-icon :name="$icon" /> <span>{{ $slot }}</span>
    @if($count)<span class="ml-auto rounded-full bg-was px-2 py-0.5 text-[11px] font-semibold text-wa">{{ $count }}</span>@endif
    @if($soon)<span class="ml-auto rounded-full bg-panel2 px-2 py-0.5 text-[11px] text-ink3">bald</span>@endif
</a>
