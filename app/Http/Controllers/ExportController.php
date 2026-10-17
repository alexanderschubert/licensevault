<?php

namespace App\Http\Controllers;

use App\Models\HomekitDevice;
use App\Models\License;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV-Exporte OHNE Geheimnisse (Lizenzschlüssel, Einrichtungscodes, QR-Payloads). */
class ExportController extends Controller
{
    public function licenses(): StreamedResponse
    {
        return $this->csv('lizenzen', ['Name', 'Hersteller', 'Version', 'Typ', 'Intervall', 'Preis', 'Währung', 'Kaufdatum', 'Ablauf', 'Verlängerung', 'Geräte-Limit', 'Plattformen', 'Installiert auf', 'Kundennummer', 'Support', 'Konto (Login)', 'Kategorie', 'Tags', 'Notizen'],
            License::with(['category', 'tags', 'devices'])->orderBy('name')->cursor(),
            fn (License $l) => [$l->name, $l->vendor, $l->product_version, $l->license_type->label(), $l->billing_interval->label(), $l->purchase_price, $l->currency,
                $l->purchase_date?->format('Y-m-d'), $l->expires_at?->format('Y-m-d'), $l->renewal_date?->format('Y-m-d'), $l->device_limit,
                collect($l->platformEnums())->map->label()->implode(', '), $l->devices->pluck('name')->implode(', '), $l->customer_number, $l->support_contact, $l->account_login,
                $l->category?->name, $l->tags->pluck('name')->implode(', '), $l->notes]);
    }

    public function homekit(): StreamedResponse
    {
        return $this->csv('homekit', ['Name', 'Protokoll', 'Hersteller', 'Modell', 'Raum', 'Status', 'Notizen'],
            HomekitDevice::orderBy('name')->cursor(),
            fn (HomekitDevice $d) => [$d->name, $d->protocol->label(), $d->manufacturer, $d->model, $d->room, $d->status->label(), $d->notes]);
    }

    private function csv(string $name, array $header, iterable $rows, callable $map): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows, $map) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8-BOM für Excel
            fputcsv($out, $header, ';', escape: '');
            foreach ($rows as $r) {
                fputcsv($out, array_map([$this, 'safe'], $map($r)), ';', escape: '');
            }
            fclose($out);
        }, 'licensevault-'.$name.'-'.now()->format('Ymd').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /** Schutz vor CSV-/Formel-Injection in Tabellenkalkulationen. */
    private function safe(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && str_contains("=+-@\t\r", $v[0]) ? "'".$v : $v;
    }
}
