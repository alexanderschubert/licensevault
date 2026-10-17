@if (session('status'))
    <div class="pill pill-ok mb-4 !px-3.5 !py-2 text-sm" role="status"><x-icon name="check" class="size-4" />{{ session('status') }}</div>
@endif
