<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Voucher batch :batch', ['batch' => $sheet->batchId]) }}</title>
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
        .footer { margin-top: auto; font-size: 6.5pt; color: #52525b; line-height: 1.25; }
        .footer div { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .side { width: 22mm; flex: none; align-self: center; display: flex; flex-direction: column; gap: 1mm; }
        .qr svg { width: 22mm; height: 22mm; display: block; }
        {{-- Username and password cards with a footer: a little tighter, so the footer still fits. --}}
        .tight { padding: 2mm; }
        .tight .text { gap: 0; }
        .tight .code { font-size: 11pt; }
        .logo, .band { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @if ($sheet->logo !== null)
            {{-- The logo goes into the page once, here, not once per card. --}}
            .logo { width: 22mm; height: 6mm; background: url("{{ $sheet->logo }}") no-repeat left center / contain; }
        @endif
        @if ($sheet->band !== null)
            .band { background: {{ $sheet->band }}; color: {{ $sheet->bandText }}; padding: 0.6mm 1.5mm; border-radius: 1mm; }
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
            <div @class(['card', 'tight' => ! $sheet->pin && $sheet->footer !== []])>
                <div class="text">
                    <div @class(['title', 'band' => $sheet->band !== null])>{{ $sheet->title }}</div>
                    @if ($sheet->showPlan && $sheet->showPrice)
                        <div class="muted">{{ $sheet->planName }}, {{ $sheet->price }}</div>
                    @elseif ($sheet->showPlan)
                        <div class="muted">{{ $sheet->planName }}</div>
                    @elseif ($sheet->showPrice)
                        <div class="muted">{{ $sheet->price }}</div>
                    @endif
                    @if ($sheet->showValidity)
                        <div class="muted">{{ $sheet->validity }}</div>
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
                    @if ($sheet->footer !== [])
                        <div class="footer">
                            @foreach ($sheet->footer as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if ($sheet->logo !== null || $card['qr'] !== null)
                    <div class="side">
                        @if ($sheet->logo !== null)
                            <div class="logo"></div>
                        @endif
                        @if ($card['qr'] !== null)
                            <div class="qr">{!! $card['qr'] !!}</div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</body>
</html>
