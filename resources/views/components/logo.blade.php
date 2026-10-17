@props(['size' => 'size-9', 'glyph' => 'size-5', 'radius' => 'rounded-xl'])
<span {{ $attributes->merge(['class' => "grid $size shrink-0 place-items-center $radius bg-gradient-to-br from-ac to-fuchsia-400 text-white"]) }}><x-icon name="logo" :class="$glyph" /></span>
