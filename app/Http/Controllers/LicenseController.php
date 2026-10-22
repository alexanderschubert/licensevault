<?php

namespace App\Http\Controllers;

use App\Enums\BillingInterval;
use App\Enums\LicenseType;
use App\Enums\Platform;
use App\Http\Requests\LicenseRequest;
use App\Models\Category;
use App\Models\Device;
use App\Models\License;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LicenseController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:'.implode(',', array_column(LicenseType::cases(), 'value'))],
            'interval' => ['nullable', 'in:'.implode(',', array_column(BillingInterval::cases(), 'value'))],
            'category' => ['nullable', 'integer'],
            'tag' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,soon,expired'],
            'platform' => ['nullable', 'in:'.implode(',', array_column(Platform::cases(), 'value'))],
            'device' => ['nullable', 'integer'],
            'view' => ['nullable', 'in:list,tiles'],
        ]);

        // Ansicht: Kacheln sind der Standard; die Wahl wird ein Jahr lang im Browser gemerkt.
        $stored = $request->cookie('licenses_view');
        $view = in_array($stored, ['list', 'tiles'], true) ? $stored : 'tiles';
        if (! empty($f['view'])) {
            $view = $f['view'];
            Cookie::queue('licenses_view', $view, 60 * 24 * 365);
        }
        unset($f['view']);

        $licenses = License::query()->with(['category', 'tags', 'devices', 'keys'])
            ->search($f['q'] ?? null)
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('license_type', $v))
            ->when($f['interval'] ?? null, fn ($q, $v) => $q->where('billing_interval', $v))
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($f['tag'] ?? null, fn ($q, $v) => $q->whereHas('tags', fn ($t) => $t->whereKey($v)))
            ->when($f['platform'] ?? null, fn ($q, $v) => $q->whereJsonContains('platforms', $v))
            ->when($f['device'] ?? null, fn ($q, $v) => $q->whereHas('devices', fn ($d) => $d->whereKey($v)))
            ->when(($f['status'] ?? null) === 'active', fn ($q) => $q->active())
            ->when(($f['status'] ?? null) === 'soon', fn ($q) => $q->expiringSoon())
            ->when(($f['status'] ?? null) === 'expired', fn ($q) => $q->where('expires_at', '<', today()))
            ->orderByRaw('expires_at is null, expires_at asc')->orderBy('name')
            ->paginate($view === 'tiles' ? 12 : 15)->withQueryString();

        return view('licenses.index', [
            'licenses' => $licenses,
            'filters' => $f,
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'devices' => Device::orderBy('name')->get(),
            'view' => $view,
            'total' => License::count(),
        ]);
    }

    public function create()
    {
        return view('licenses.form', $this->formData(new License(['currency' => 'EUR', 'license_type' => LicenseType::Subscription, 'billing_interval' => BillingInterval::Yearly])));
    }

    public function store(LicenseRequest $request): RedirectResponse
    {
        $license = new License;
        $this->fill($license, $request);

        return redirect()->route('licenses.show', $license)->with('status', 'Lizenz wurde angelegt.');
    }

    public function show(License $license)
    {
        return view('licenses.show', ['license' => $license->load(['category', 'tags', 'documents', 'devices', 'keys'])]);
    }

    public function edit(License $license)
    {
        return view('licenses.form', $this->formData($license->load('tags', 'category', 'devices', 'keys')));
    }

    public function update(LicenseRequest $request, License $license): RedirectResponse
    {
        $this->fill($license, $request);

        return redirect()->route('licenses.show', $license)->with('status', 'Änderungen gespeichert.');
    }

    public function destroy(License $license): RedirectResponse
    {
        $license->delete();

        return redirect()->route('licenses.index')->with('status', 'Lizenz wurde gelöscht.');
    }

    /** Gibt einen entschlüsselten Schlüssel nur auf ausdrückliche Anfrage aus. */
    public function reveal(License $license, int $key): JsonResponse
    {
        $row = $license->keys()->whereKey($key)->firstOrFail();

        return response()->json(['secret' => $row->secret])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** Wiederherstellungscodes nur auf ausdrückliche Anfrage. */
    public function revealRecovery(License $license): JsonResponse
    {
        abort_unless($license->hasRecoveryCodes(), 404);

        return response()->json(['secret' => $license->recovery_codes])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** Icon ist kein Geheimnis, wird aber nur nach Login ausgeliefert. */
    public function icon(License $license): Response
    {
        abort_unless($license->hasIcon() && Storage::disk('local')->exists($license->icon_path), 404);

        return response(Storage::disk('local')->get($license->icon_path), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($license->icon_path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Konto-Passwort nur auf ausdrückliche Anfrage. */
    public function revealAccount(License $license): JsonResponse
    {
        abort_unless($license->hasAccountPassword(), 404);

        return response()->json(['secret' => $license->account_password])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    private function fill(License $license, LicenseRequest $request): void
    {
        $data = $request->safe()->except(['category', 'tags', 'keys', 'account_password', 'clear_account_password', 'recovery_codes', 'clear_recovery_codes', 'platforms', 'devices', 'icon', 'clear_icon']);
        $license->fill($data);

        if ($request->boolean('clear_account_password')) {
            $license->account_password = null;
        } elseif (filled($request->input('account_password'))) {
            $license->account_password = $request->input('account_password');
        }

        if ($request->boolean('clear_recovery_codes')) {
            $license->recovery_codes = null;
        } elseif (filled($request->input('recovery_codes'))) {
            $license->recovery_codes = $request->input('recovery_codes');
        }

        $platforms = array_values(array_unique((array) $request->input('platforms', [])));
        $license->platforms = $platforms ?: null;

        if ($request->boolean('clear_icon') || $request->hasFile('icon')) {
            if ($license->icon_path) {
                Storage::disk('local')->delete($license->icon_path);
            }
            $license->icon_path = null;
        }
        if ($file = $request->file('icon')) {
            $path = 'uploads/icons/'.Str::random(40).'.'.$file->guessExtension();
            Storage::disk('local')->put($path, $file->get());
            $license->icon_path = $path;
        }

        $name = trim((string) $request->input('category'));
        $license->category_id = $name !== '' ? Category::firstOrCreate(['name' => $name])->id : null;
        $license->save();
        $this->syncKeys($license, (array) $request->input('keys', []));

        $license->tags()->sync(collect($request->tagNames())->map(fn ($n) => Tag::firstOrCreate(['name' => $n])->id));
        $license->devices()->sync(collect($request->deviceNames())->map(fn ($n) => Device::firstOrCreate(['name' => $n])->id));
    }

    /** Zeilen aus dem Formular: vorhandene (id) aktualisieren/entfernen, neue mit Inhalt anlegen; leeres Feld = unverändert. */
    private function syncKeys(License $license, array $rows): void
    {
        $existing = $license->keys()->get()->keyBy('id');
        $position = (int) $existing->max('position') + 1;

        foreach ($rows as $row) {
            $label = filled($row['label'] ?? null) ? trim($row['label']) : null;
            $key = $existing->get((int) ($row['id'] ?? 0));

            if ($key) {
                if (! empty($row['remove'])) {
                    $key->delete();

                    continue;
                }
                $key->label = $label;
                if (filled($row['secret'] ?? null)) {
                    $key->secret = $row['secret'];
                }
                $key->save();
            } elseif (filled($row['secret'] ?? null) && empty($row['remove'])) {
                $license->keys()->create(['label' => $label, 'secret' => $row['secret'], 'position' => $position++]);
            }
        }
    }

    private function formData(License $license): array
    {
        return [
            'license' => $license,
            'types' => LicenseType::cases(),
            'intervals' => BillingInterval::cases(),
            'categoryNames' => Category::orderBy('name')->pluck('name'),
            'tagNames' => Tag::orderBy('name')->pluck('name'),
            'platformOptions' => Platform::cases(),
            'deviceNames' => Device::orderBy('name')->pluck('name'),
        ];
    }
}
