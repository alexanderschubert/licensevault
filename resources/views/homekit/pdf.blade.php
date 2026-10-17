<!doctype html>
<html lang="de"><head><meta charset="utf-8"><title>LicenseVault – QR-Codes</title>
<style>
@page { margin: 18mm 14mm; }
body { font-family: Helvetica, Arial, sans-serif; color: #14152b; font-size: 11pt; }
h1 { font-size: 16pt; margin: 0 0 2mm; }
.note { color: #8d1c2b; font-size: 8.5pt; margin-bottom: 7mm; }
table.grid { width: 100%; border-collapse: separate; border-spacing: 4mm; }
td.card { width: 33%; border: 0.3mm solid #c9ccdf; border-radius: 3mm; padding: 4mm; text-align: center; vertical-align: top; page-break-inside: avoid; }
td.card img { width: 48mm; height: 48mm; }
.name { font-weight: bold; font-size: 10.5pt; margin-top: 2mm; }
.meta { color: #5d6078; font-size: 8.5pt; margin-top: 0.5mm; }
.single { text-align: center; margin-top: 12mm; }
.single img { width: 110mm; height: 110mm; }
.single .name { font-size: 18pt; margin-top: 6mm; }
.single .meta { font-size: 11pt; }
.foot { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; color: #8d90a8; font-size: 7.5pt; }
</style></head><body>
<h1>HomeKit &amp; Matter – QR-Codes</h1>
<div class="note">Enthält Kopplungsdaten. Nach dem Ausdrucken sicher aufbewahren und die Datei nicht weitergeben.</div>

@if($single)
    @php $d = $items[0]['device']; @endphp
    <div class="single">
        @if($items[0]['image'])<img src="{{ $items[0]['image'] }}" alt="QR-Code">@else<p>QR-Bild nicht darstellbar.</p>@endif
        <div class="name">{{ $d->name }}</div>
        <div class="meta">{{ collect($d->protocol->parts())->map->label()->implode(' + ') }}{{ $d->room ? ' · '.$d->room : '' }}{{ $d->manufacturer ? ' · '.$d->manufacturer : '' }}{{ $d->model ? ' '.$d->model : '' }}</div>
    </div>
@elseif(count($items) === 0)
    <p>Keine Geräte mit QR-Code für diesen Filter.</p>
@else
    <table class="grid">
        @foreach(array_chunk($items, 3) as $row)
            <tr>
                @foreach($row as $it)
                    @php $d = $it['device']; @endphp
                    <td class="card">
                        @if($it['image'])<img src="{{ $it['image'] }}" alt="QR-Code">@else<div class="meta">QR-Bild nicht darstellbar</div>@endif
                        <div class="name">{{ $d->name }}</div>
                        <div class="meta">{{ collect($d->protocol->parts())->map->label()->implode(' + ') }}{{ $d->room ? ' · '.$d->room : '' }}</div>
                        @if($d->model)<div class="meta">{{ $d->model }}</div>@endif
                    </td>
                @endforeach
                @for($i = count($row); $i < 3; $i++)<td></td>@endfor
            </tr>
        @endforeach
    </table>
@endif
<div class="foot">LicenseVault · erstellt {{ now()->format('d.m.Y H:i') }}</div>
</body></html>
