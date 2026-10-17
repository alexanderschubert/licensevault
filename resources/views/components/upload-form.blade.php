@props(['attach' => null, 'compact' => false])
<form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
    @csrf
    @if($attach)<input type="hidden" name="attach" value="{{ $attach }}">@endif
    <div class="min-w-[220px] flex-1">
        <label class="mb-1.5 block text-sm font-medium" for="file{{ $attach }}">Datei (PDF, Bild inkl. HEIC, TXT · max. 10 MB)</label>
        <input id="file{{ $attach }}" name="file" type="file" required accept=".pdf,.png,.jpg,.jpeg,.webp,.heic,.heif,.txt" class="field">
    </div>
    <div class="min-w-[180px] flex-1">
        <label class="mb-1.5 block text-sm font-medium" for="title{{ $attach }}">Titel <span class="font-normal text-ink3">(optional)</span></label>
        <input id="title{{ $attach }}" name="title" maxlength="150" class="field" placeholder="z. B. Rechnung 2026">
    </div>
    {{ $slot }}
    <button class="btn">Hochladen</button>
    @error('file')<p class="w-full text-sm text-er" role="alert">{{ $message }}</p>@enderror
    @error('attach')<p class="w-full text-sm text-er" role="alert">{{ $message }}</p>@enderror
</form>
