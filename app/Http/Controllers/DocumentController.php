<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\HomekitDevice;
use App\Models\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private function disk()
    {
        return Storage::disk('local');
    }

    public function index(Request $request)
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $like = filled($f['q'] ?? null) ? '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']).'%' : null;
        $op = Document::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return view('documents.index', [
            'documents' => Document::query()->with('attachable')
                ->when($like, fn ($q) => $q->where(fn ($w) => $w->where('title', $op, $like)->orWhere('original_name', $op, $like)))
                ->latest()->paginate(20)->withQueryString(),
            'filters' => $f,
            'licenses' => License::orderBy('name')->get(['id', 'name']),
            'devices' => HomekitDevice::orderBy('name')->get(['id', 'name']),
            'total' => Document::count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', config('licensevault.document_mimes')), 'max:'.config('licensevault.document_max_kb')],
            'title' => ['nullable', 'string', 'max:150'],
            'attach' => ['nullable', 'regex:/^(license|homekit):\d+$/'],
        ], [
            'file.required' => 'Bitte eine Datei auswählen.',
            'file.mimes' => 'Erlaubt sind PDF, PNG, JPG, WebP, HEIC und TXT.',
            'file.max' => 'Die Datei ist zu groß (max. '.(config('licensevault.document_max_kb') / 1024).' MB).',
            'attach.regex' => 'Ungültige Zuordnung.',
        ]);

        $parent = null;
        if (filled($data['attach'] ?? null)) {
            [$type, $id] = explode(':', $data['attach']);
            $parent = ($type === 'license' ? License::class : HomekitDevice::class)::findOrFail($id);
        }

        $file = $request->file('file');
        // Dateiname und Typ werden serverseitig bestimmt, nie vom Client übernommen.
        $original = Str::limit(preg_replace('/[^\p{L}\p{N}._ \-()]/u', '_', basename($file->getClientOriginalName())), 150, '');
        $ext = $file->guessExtension() ?: 'bin';
        $path = 'uploads/documents/'.Str::random(40).'.'.$ext;
        $this->disk()->put($path, $file->get());

        $doc = new Document([
            'title' => filled($data['title'] ?? null) ? $data['title'] : pathinfo($original, PATHINFO_FILENAME),
            'original_name' => $original,
            'path' => $path,
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
        ]);
        if ($parent) {
            $doc->attachable()->associate($parent);
        }
        $doc->save();

        return back()->with('status', 'Dokument wurde hochgeladen.');
    }

    public function download(Document $document): StreamedResponse
    {
        abort_unless($this->disk()->exists($document->path), 404);

        return $this->disk()->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function destroy(Document $document): RedirectResponse
    {
        $document->delete();

        return back()->with('status', 'Dokument wurde gelöscht.');
    }
}
