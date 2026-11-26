<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Voucher batch :batch', ['batch' => $batch->id]) }}</title>
    {{-- A page of its own with its own styles: core builds its CSS from its own views only. --}}
    <style>
        @page { size: A4; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; color: #18181b; }
        .bar { display: flex; gap: 12px; align-items: center; padding: 12px; border-bottom: 1px solid #e4e4e7; font-size: 14px; }
        .bar button { padding: 6px 14px; border: 0; border-radius: 6px; background: #166534; color: #fff; font-size: 14px; cursor: pointer; }
        .sheet { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2mm; padding: 2mm; }
        .card { height: 38mm; border: 1px dashed #a1a1aa; border-radius: 2mm; padding: 2.5mm; display: flex; gap: 2mm; break-inside: avoid; overflow: hidden; }
        .text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.6mm; font-size: 8pt; }
        .title { font-weight: 700; font-size: 9pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .muted { color: #52525b; }
        .code { font-family: ui-monospace, monospace; font-size: 12pt; font-weight: 700; letter-spacing: 0.5px; }
        .label { font-size: 6.5pt; text-transform: uppercase; color: #71717a; }
        .qr { width: 22mm; flex: none; align-self: center; }
        .qr svg { width: 22mm; height: 22mm; display: block; }
        @media print { .bar { display: none; } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <span>{{ trans_choice(':count card|:count cards', count($cards)) }}, {{ $batch->plan_name }}</span>
    </div>
    <div class="sheet">
        @foreach ($cards as $card)
            <div class="card">
                <div class="text">
                    <div class="title">{{ $title }}</div>
                    <div class="muted">{{ $batch->plan_name }}, {{ $price }}</div>
                    <div class="muted">{{ $validity }}</div>
                    @if ($pin)
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
