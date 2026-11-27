<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Voucher batch :batch', ['batch' => $sheet->batchId]) }}</title>
    {{-- 4 x 10 small cards: no room for the logo, the plan name or the footer. --}}
    <style>
        @page { size: A4; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; color: #18181b; }
        .bar { display: flex; gap: 12px; align-items: center; padding: 12px; border-bottom: 1px solid #e4e4e7; font-size: 14px; }
        .bar button { padding: 6px 14px; border: 0; border-radius: 6px; background: #166534; color: #fff; font-size: 14px; cursor: pointer; }
        .sheet { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5mm; padding: 1.5mm; }
        .card { height: 26mm; border: 1px dashed #a1a1aa; border-radius: 1.5mm; padding: 1.8mm; display: flex; gap: 1.5mm; break-inside: avoid; overflow: hidden; }
        .text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.3mm; font-size: 6.5pt; }
        .title { font-weight: 700; font-size: 7.5pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .muted { color: #52525b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .code { font-family: ui-monospace, monospace; font-size: 9.5pt; font-weight: 700; }
        .label { font-size: 5.5pt; text-transform: uppercase; color: #71717a; }
        .qr { width: 16mm; flex: none; align-self: center; }
        .qr svg { width: 16mm; height: 16mm; display: block; }
        @if ($sheet->band !== null)
            .band { background: {{ $sheet->band }}; color: {{ $sheet->bandText }}; padding: 0.4mm 1mm; border-radius: 0.8mm; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @endif
        @media print { .bar { display: none; } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <span>{{ trans_choice(':count card|:count cards', count($sheet->cards)) }}, {{ $sheet->planName }}</span>
    </div>
    <div class="sheet">
        @foreach ($sheet->cards as $card)
            <div class="card">
                <div class="text">
                    <div @class(['title', 'band' => $sheet->band !== null])>{{ $sheet->title }}</div>
                    @if ($sheet->showPrice || $sheet->showValidity)
                        <div class="muted">{{ implode(', ', array_filter([$sheet->showPrice ? $sheet->price : '', $sheet->showValidity ? $sheet->validity : ''])) }}</div>
                    @endif
                    @if ($sheet->pin)
                        <div class="label">{{ __('PIN') }}</div>
                        <div class="code">{{ $card['code'] }}</div>
                    @else
                        <div class="label">{{ __('Username') }}</div>
                        <div class="code">{{ $card['code'] }}</div>
                        <div class="label">{{ __('Password') }}</div>
                        <div class="code">{{ $card['password'] }}</div>
                    @endif
                </div>
                @if ($card['qr'] !== null)
                    <div class="qr">{!! $card['qr'] !!}</div>
                @endif
            </div>
        @endforeach
    </div>
</body>
</html>
