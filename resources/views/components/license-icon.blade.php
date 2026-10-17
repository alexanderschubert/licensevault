@props(['license', 'size' => 'size-10'])
@if($license->hasIcon())
    <img src="{{ route('licenses.icon', $license) }}?v={{ $license->updated_at->timestamp }}" alt="" loading="lazy" onerror="this.style.display='none'" {{ $attributes->merge(['class' => "$size shrink-0 rounded-xl bg-white object-contain"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$size grid shrink-0 place-items-center rounded-xl text-sm font-bold text-white"]) }} style="background: {{ $license->avatarColor() }}" aria-hidden="true">{{ $license->initials() }}</span>
@endif
