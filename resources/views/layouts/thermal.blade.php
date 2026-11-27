<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Voucher batch :batch', ['batch' => $sheet->batchId]) }}</title>
    {{-- A thermal roll, $width mm printable: one card after another, black and white. The paper size is set on the printer. --}}
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; color: #000; }
        .bar { display: flex; gap: 12px; align-items: center; padding: 12px; border-bottom: 1px solid #e4e4e7; font-size: 14px; }
        .bar button { padding: 6px 14px; border: 0; border-radius: 6px; background: #166534; color: #fff; font-size: 14px; cursor: pointer; }
        .roll { width: {{ $width }}mm; margin: 0 auto; }
        .card { padding: 4mm 0; border-bottom: 1px dashed #000; text-align: center; break-inside: avoid; display: flex; flex-direction: column; align-items: center; gap: 0.8mm; font-size: {{ $width > 60 ? '10pt' : '9pt' }}; }
        .title { font-weight: 700; font-size: {{ $width > 60 ? '13pt' : '11pt' }}; max-width: 100%; overflow-wrap: anywhere; }
        .code { font-family: ui-monospace, monospace; font-weight: 700; font-size: {{ $width > 60 ? '20pt' : '16pt' }}; letter-spacing: 1px; max-width: 100%; overflow-wrap: anywhere; }
        .label { font-size: 7.5pt; text-transform: uppercase; }
        .qr svg { width: {{ $width > 60 ? 40 : 30 }}mm; height: {{ $width > 60 ? 40 : 30 }}mm; display: block; }
        .footer { font-size: 8pt; max-width: 100%; overflow-wrap: anywhere; }
        @if ($sheet->logo !== null)
            {{-- The logo goes into the page once, here, not once per card. --}}
            .logo { width: 100%; height: {{ $width > 60 ? 14 : 11 }}mm; background: url("{{ $sheet->logo }}") no-repeat center / contain; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @endif
        @media print { .bar { display: none; } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <span>{{ trans_choice(':count card|:count cards', count($sheet->cards)) }}, {{ $sheet->planName }}</span>
    </div>
    <div class="roll">
        @foreach ($sheet->cards as $card)
            <div class="card">
                @if ($sheet->logo !== null)
                    <div class="logo"></div>
                @endif
                <div class="title">{{ $sheet->title }}</div>
                @if ($sheet->showPlan)
                    <div>{{ $sheet->planName }}</div>
                @endif
                @if ($sheet->showPrice || $sheet->showValidity)
                    <div>{{ implode(', ', array_filter([$sheet->showPrice ? $sheet->price : '', $sheet->showValidity ? $sheet->validity : ''])) }}</div>
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
                @if ($card['qr'] !== null)
                    <div class="qr">{!! $card['qr'] !!}</div>
                @endif
                @foreach ($sheet->footer as $line)
                    <div class="footer">{{ $line }}</div>
                @endforeach
            </div>
        @endforeach
    </div>
</body>
</html>
