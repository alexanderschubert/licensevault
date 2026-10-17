<?php

namespace App\Http\Controllers;

use App\Enums\DeviceProtocol;
use App\Enums\HomekitStatus;
use App\Http\Requests\HomekitDeviceRequest;
use App\Models\HomekitDevice;
use App\Support\Matter;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class HomekitController extends Controller
{
    private function disk()
    {
        return Storage::disk('local');
    }

    private function filtered(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:'.implode(',', array_column(HomekitStatus::cases(), 'value'))],
            'protocol' => ['nullable', 'in:'.implode(',', array_column(DeviceProtocol::cases(), 'value'))],
        ]);

        $query = HomekitDevice::query()->search($f['q'] ?? null)
            ->when($f['room'] ?? null, fn ($q, $v) => $q->where('room', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            // „HomeKit“ zeigt HomeKit-Geräte und „HomeKit + Matter“, aber keine reinen Matter-Geräte;
            // „Matter“ zeigt Matter-Geräte und „HomeKit + Matter“.
            ->when($f['protocol'] ?? null, fn ($q, $v) => $q->whereIn('protocol', $v === 'both' ? ['both'] : [$v, 'both']))
            ->orderBy('room')->orderBy('name');

        return [$query, $f];
    }

    public function index(Request $request)
    {
        [$query, $f] = $this->filtered($request);

        return view('homekit.index', [
            'devices' => $query->get(),
            'filters' => $f,
            'rooms' => HomekitDevice::whereNotNull('room')->distinct()->orderBy('room')->pluck('room'),
            'total' => HomekitDevice::count(),
        ]);
    }

    public function print(Request $request)
    {
        [$query, $f] = $this->filtered($request);

        return view('homekit.print', ['devices' => $query->get()->filter->hasQr(), 'filters' => $f]);
    }

    /** PDF aller (gefilterten) Geräte mit QR-Code – für iPhone-App/Teilen/Drucken. */
    public function printPdf(Request $request): Response
    {
        [$query, $f] = $this->filtered($request);

        return $this->pdf($query->get()->filter->hasQr()->values()->all(), 'licensevault-qr-codes.pdf');
    }

    public function devicePdf(HomekitDevice $device): Response
    {
        abort_unless($device->hasQr(), 404);

        return $this->pdf([$device], 'qr-'.Str::slug($device->name ?: 'geraet').'.pdf');
    }

    /** @param list<HomekitDevice> $devices */
    private function pdf(array $devices, string $filename): Response
    {
        $items = array_map(fn (HomekitDevice $d) => ['device' => $d, 'image' => $this->qrDataUri($d)], $devices);
        $html = view('homekit.pdf', ['items' => $items, 'single' => count($devices) === 1])->render();

        $dir = storage_path('app/private/tmp/dompdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $options = new Options;
        $options->set(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'chroot' => [storage_path('app/private/tmp'), resource_path()],
            'tempDir' => $dir, 'fontDir' => $dir, 'fontCache' => $dir, 'defaultFont' => 'Helvetica', 'defaultPaperSize' => 'a4']);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** QR als data:-URI (SVG aus dem Payload, sonst hochgeladenes PNG/JPEG/WebP); null bei nicht darstellbaren Formaten. */
    private function qrDataUri(HomekitDevice $device): ?string
    {
        if ($device->hasQrImage() && $this->disk()->exists($device->imagePath())) {
            $mime = $this->disk()->mimeType($device->imagePath());
            if (in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) && extension_loaded('gd')) {
                return 'data:'.$mime.';base64,'.base64_encode($this->disk()->get($device->imagePath()));
            }
        }
        if ($device->hasQrPayload()) {
            return 'data:image/svg+xml;base64,'.base64_encode($this->qrSvg($device->qr_payload, 360));
        }

        return null;
    }

    private function qrSvg(string $payload, int $size): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd)))->writeString($payload);
    }

    public function create()
    {
        return view('homekit.form', $this->formData(new HomekitDevice(['status' => HomekitStatus::Active, 'protocol' => DeviceProtocol::HomeKit])));
    }

    public function store(HomekitDeviceRequest $request): RedirectResponse
    {
        $device = new HomekitDevice;
        $this->fill($device, $request);

        return redirect()->route('homekit.show', $device)->with('status', 'Gerät wurde angelegt.');
    }

    public function show(HomekitDevice $device)
    {
        return view('homekit.show', ['device' => $device->load('documents')]);
    }

    public function edit(HomekitDevice $device)
    {
        return view('homekit.form', $this->formData($device));
    }

    public function update(HomekitDeviceRequest $request, HomekitDevice $device): RedirectResponse
    {
        $this->fill($device, $request);

        return redirect()->route('homekit.show', $device)->with('status', 'Änderungen gespeichert.');
    }

    public function destroy(HomekitDevice $device): RedirectResponse
    {
        $this->deleteImage($device);
        $device->delete();

        return redirect()->route('homekit.index')->with('status', 'Gerät wurde gelöscht.');
    }

    /** Einrichtungscode nur auf ausdrückliche Anfrage. */
    public function revealCode(HomekitDevice $device): JsonResponse
    {
        abort_unless($device->hasSetupCode(), 404);

        return response()->json(['secret' => $device->setup_code]);
    }

    /** Gerätefoto (kein Geheimnis, aber nur nach Login). */
    public function photo(HomekitDevice $device): Response
    {
        abort_unless($device->hasPhoto() && $this->disk()->exists($device->photo_path), 404);

        return response($this->disk()->get($device->photo_path), 200, [
            'Content-Type' => $this->disk()->mimeType($device->photo_path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** QR-Bild (hochgeladen oder aus dem Payload erzeugt) – nur nach Login. */
    public function qr(HomekitDevice $device): Response
    {
        if ($device->hasQrImage() && $this->disk()->exists($device->imagePath())) {
            return response($this->disk()->get($device->imagePath()), 200, [
                'Content-Type' => $this->disk()->mimeType($device->imagePath()) ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort_unless($device->hasQrPayload(), 404);

        $svg = $this->qrSvg($device->qr_payload, 480);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function fill(HomekitDevice $device, HomekitDeviceRequest $request): void
    {
        $device->fill($request->safe()->only(['name', 'manufacturer', 'model', 'room', 'status', 'protocol', 'notes']));

        if ($request->boolean('clear_setup_code')) {
            $device->setup_code = null;
        } elseif ($request->filled('setup_code')) {
            $code = $request->input('setup_code');
            $device->setup_code = Matter::validManualCode($code) ? Matter::formatManual($code) : $code;
        }

        if ($request->boolean('clear_qr')) {
            $device->qr_payload = null;
            $this->deleteImage($device);
        } elseif ($request->filled('qr_payload')) {
            $device->qr_payload = $request->input('qr_payload');
        }

        if ($file = $request->file('qr_file')) {
            $this->deleteImage($device);
            $path = 'uploads/homekit-qr/'.Str::random(40).'.'.$file->guessExtension();
            $this->disk()->put($path, $file->get());
            $device->qr_image_path = $path;
        }

        if ($request->boolean('clear_photo') || $request->hasFile('photo')) {
            if ($device->photo_path) {
                $this->disk()->delete($device->photo_path);
            }
            $device->photo_path = null;
        }
        if ($photo = $request->file('photo')) {
            $path = 'uploads/device-photos/'.Str::random(40).'.'.$photo->guessExtension();
            $this->disk()->put($path, $photo->get());
            $device->photo_path = $path;
        }

        $device->save();
    }

    private function deleteImage(HomekitDevice $device): void
    {
        if ($device->hasQrImage()) {
            $this->disk()->delete($device->imagePath());
            $device->qr_image_path = null;
        }
    }

    private function formData(HomekitDevice $device): array
    {
        return [
            'device' => $device,
            'statuses' => HomekitStatus::cases(),
            'protocols' => DeviceProtocol::cases(),
            'rooms' => HomekitDevice::whereNotNull('room')->distinct()->orderBy('room')->pluck('room'),
            'manufacturers' => HomekitDevice::whereNotNull('manufacturer')->distinct()->orderBy('manufacturer')->pluck('manufacturer'),
        ];
    }
}
