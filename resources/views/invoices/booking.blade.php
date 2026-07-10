@use('App\Support\Money')
{{-- GST tax invoice (M09). Rendered by dompdf, so: no external assets, no
     flexbox/grid, tables only. DejaVu Sans is dompdf's bundled unicode font —
     it carries the rupee sign, unlike the PDF core fonts. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $number }}</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; color: #18181b; font-size: 11px; line-height: 1.45; }
        h1 { font-size: 18px; margin: 0 0 2px; letter-spacing: .5px; }
        .muted { color: #71717a; }
        .right { text-align: right; }
        .head td { vertical-align: top; padding: 0; }
        .meta { margin-top: 18px; border-collapse: collapse; width: 100%; }
        .meta td { width: 50%; vertical-align: top; padding: 10px 12px; border: 1px solid #e4e4e7; }
        .label { text-transform: uppercase; font-size: 8.5px; letter-spacing: .8px; color: #71717a; margin-bottom: 4px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.lines th { background: #f4f4f5; text-align: left; padding: 7px 8px; border-bottom: 1px solid #e4e4e7; font-size: 9px; text-transform: uppercase; letter-spacing: .6px; }
        table.lines td { padding: 7px 8px; border-bottom: 1px solid #f4f4f5; vertical-align: top; }
        table.totals { width: 46%; margin-left: 54%; margin-top: 12px; border-collapse: collapse; }
        table.totals td { padding: 4px 0; }
        table.totals tr.grand td { border-top: 1px solid #18181b; padding-top: 8px; font-size: 13px; font-weight: bold; }
        .addons { color: #71717a; font-size: 9.5px; }
        footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid #e4e4e7; font-size: 9px; color: #71717a; }
    </style>
</head>
<body>
@php
    $money = fn ($amount) => Money::format($amount, $currency);
    $half = $tax['percent'] / 2;
    $statusLabels = [
        'unpaid' => __('Unpaid'),
        'paid' => __('Paid'),
        'refunded' => __('Refunded'),
        'partial_refund' => __('Partially refunded'),
    ];
    $methodLabels = [
        'cash' => __('Pay after service'),
        'gateway' => __('Online payment'),
        'wallet' => __('Wallet'),
    ];
@endphp

<table class="head" width="100%">
    <tr>
        <td>
            <h1>{{ $seller['name'] }}</h1>
            @if ($seller['address'])<div class="muted">{{ $seller['address'] }}</div>@endif
            @if ($seller['state'])<div class="muted">{{ $seller['state'] }}</div>@endif
            @if ($seller['gstin'])<div class="muted">{{ __('GSTIN') }}: {{ $seller['gstin'] }}</div>@endif
        </td>
        <td class="right">
            <div class="label">{{ __('Tax invoice') }}</div>
            <div><strong>{{ $number }}</strong></div>
            <div class="muted">{{ $issued_at->format('j M Y') }}</div>
            <div class="muted">{{ __('Booking') }}: {{ $booking->code }}</div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td>
            <div class="label">{{ __('Billed to') }}</div>
            <div><strong>{{ $buyer['name'] ?? __('Deleted customer') }}</strong></div>
            <div>{{ $buyer['address']['line1'] ?? '' }}</div>
            @if (! empty($buyer['address']['line2']))<div>{{ $buyer['address']['line2'] }}</div>@endif
            <div>{{ $buyer['address']['city'] ?? '' }} {{ $buyer['address']['postal_code'] ?? '' }}</div>
            @if ($buyer['phone'])<div class="muted">{{ $buyer['phone'] }}</div>@endif
            @if ($buyer['email'])<div class="muted">{{ $buyer['email'] }}</div>@endif
        </td>
        <td>
            <div class="label">{{ __('Place of supply') }}</div>
            <div>{{ $place_of_supply ?? '—' }}</div>
            <div class="label" style="margin-top:10px">{{ __('Payment') }}</div>
            <div>
                {{ $methodLabels[$booking->payment_method->value] ?? $booking->payment_method->value }}
                — {{ $statusLabels[$booking->payment_status->value] ?? $booking->payment_status->value }}
            </div>
            @foreach ($payments as $payment)
                @if ($payment['reference'])
                    <div class="muted">{{ $payment['gateway'] }}: {{ $payment['reference'] }}</div>
                @endif
            @endforeach
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th>{{ __('Service') }}</th>
            <th class="right" width="60">{{ __('Qty') }}</th>
            <th class="right" width="100">{{ __('Rate') }}</th>
            <th class="right" width="110">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr>
                <td>
                    {{ $item['name'] }}
                    @foreach ($item['addons'] as $addon)
                        <div class="addons">+ {{ $addon['name'] }} ({{ $money($addon['price']) }})</div>
                    @endforeach
                </td>
                <td class="right">{{ $item['qty'] }}</td>
                <td class="right">{{ $money($item['unit_price']) }}</td>
                <td class="right">{{ $money($item['line_total']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="muted">{{ __('Subtotal') }}</td>
        <td class="right">{{ $money($booking->subtotal) }}</td>
    </tr>
    @if ((float) $booking->addon_total > 0)
        <tr>
            <td class="muted">{{ __('Add-ons') }}</td>
            <td class="right">{{ $money($booking->addon_total) }}</td>
        </tr>
    @endif
    @if ((float) $booking->discount > 0)
        <tr>
            <td class="muted">{{ __('Discount') }}</td>
            <td class="right">−{{ $money($booking->discount) }}</td>
        </tr>
    @endif
    @if ($tax['igst'] > 0)
        <tr>
            <td class="muted">{{ __('IGST') }} @ {{ rtrim(rtrim(number_format($tax['percent'], 2), '0'), '.') }}%</td>
            <td class="right">{{ $money($tax['igst']) }}</td>
        </tr>
    @else
        <tr>
            <td class="muted">{{ __('CGST') }} @ {{ rtrim(rtrim(number_format($half, 2), '0'), '.') }}%</td>
            <td class="right">{{ $money($tax['cgst']) }}</td>
        </tr>
        <tr>
            <td class="muted">{{ __('SGST') }} @ {{ rtrim(rtrim(number_format($half, 2), '0'), '.') }}%</td>
            <td class="right">{{ $money($tax['sgst']) }}</td>
        </tr>
    @endif
    <tr class="grand">
        <td>{{ __('Total') }}</td>
        <td class="right">{{ $money($booking->total) }}</td>
    </tr>
</table>

<footer>
    {{ __('This is a computer-generated invoice and does not require a signature.') }}
</footer>
</body>
</html>
