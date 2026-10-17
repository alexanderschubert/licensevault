@props(['model', 'key'])
<div class="card p-6">
    <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-ink3"><x-icon name="doc" class="size-4" /> Dokumente ({{ $model->documents->count() }})</div>
    @forelse($model->documents as $d)
        <div class="flex items-center gap-3 border-t border-line py-2.5 first:border-0">
            <div class="min-w-0 flex-1"><a href="{{ route('documents.download', $d) }}" class="block truncate font-medium hover:text-ac">{{ $d->title }}</a>
                <span class="text-xs text-ink3">{{ $d->original_name }} · {{ $d->sizeLabel() }} · {{ $d->created_at->format('d.m.Y') }}</span></div>
            <form method="POST" action="{{ route('documents.destroy', $d) }}" onsubmit="return confirm('Dokument „{{ e(addslashes($d->title)) }}“ löschen?')">@csrf @method('DELETE')
                <button class="text-ink3 hover:text-er" aria-label="Löschen"><x-icon name="trash" /></button></form>
        </div>
    @empty
        <p class="mb-3 text-sm text-ink2">Noch keine Dokumente (z. B. Rechnung, Beleg, Screenshot).</p>
    @endforelse
    <div class="mt-4 border-t border-line pt-4"><x-upload-form :attach="$key.':'.$model->id" /></div>
</div>
