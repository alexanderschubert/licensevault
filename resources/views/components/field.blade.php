@props(['name', 'label', 'type' => 'text', 'value' => null])
<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" {{ $attributes->merge(['class' => 'field']) }}>
    @error($name)<p class="mt-1.5 text-sm text-er" role="alert">{{ $message }}</p>@enderror
</div>
