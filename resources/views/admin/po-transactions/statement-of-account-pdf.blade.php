<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement of Account</title>
    <style>
        @page { margin: 22px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111111; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        p { margin: 0; }
        .brand { text-align: center; }
        .brand-name { color: #1b2a4e; font-size: 24px; font-weight: bold; letter-spacing: 1px; line-height: 1; }
        .brand-drop { display: inline-block; width: 9px; height: 13px; vertical-align: top; margin-left: 2px; }
        .brand-sub { color: #4aa3df; font-size: 12px; font-weight: bold; letter-spacing: 2px; margin-top: 2px; }
        .branch-address { margin-top: 8px; color: #444444; font-size: 9px; text-align: center; text-transform: uppercase; }
        .tin { color: #444444; font-size: 9px; text-align: center; }
        .title { margin: 10px 0 10px; font-size: 16px; font-weight: bold; text-align: center; }
        table.bill { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.bill td { padding: 1px 0; font-weight: bold; text-transform: uppercase; vertical-align: top; }
        table.bill td.label { text-transform: none; }
        table.bill td.right { text-align: right; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { background: #1f2d5a; color: #ffffff; font-size: 8.5px; font-weight: bold; padding: 3px 6px; text-align: center; }
        table.lines td { border-bottom: 1px solid #d9d9d9; padding: 3px 6px; font-size: 9px; }
        table.lines td.date { background: #f2f2f2; border-right: 1px solid #d9d9d9; text-align: right; width: 18%; }
        table.lines td.num { text-align: center; }
        table.lines td.money { text-align: right; white-space: nowrap; }
        table.lines tr.total td { border-bottom: none; border-top: 1px solid #111111; font-weight: bold; padding-top: 5px; }
        .footer { margin-top: 10px; font-size: 9px; line-height: 1.35; }
        .footer .gap { margin-top: 8px; }
        .thanks { margin-top: 14px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
    @php
        $peso = fn ($value) => '₱'.number_format((float) $value, 2);
        $from = \Illuminate\Support\Carbon::parse($dateFrom);
        $to = \Illuminate\Support\Carbon::parse($dateTo);
        $monthLabel = fn ($date) => strtoupper($date->format('M')).'. '.$date->format('Y');
        $statementDate = $from->isSameMonth($to) ? $monthLabel($to) : $monthLabel($from).' - '.$monthLabel($to);
        $branchAddress = $issuingBranch?->address;
        $priceLabel = fn (array $prices) => collect($prices)->filter(fn ($price) => $price > 0)->sort()->map($peso)->implode(' / ');
        $hasDelivery = $soa['has_delivery'];
        $drop = 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('images/soa-drop.png')));
    @endphp

    <div class="brand">
        <div class="brand-name">SPIN KLEAN<img class="brand-drop" src="{{ $drop }}" alt=""></div>
        <div class="brand-sub">LAUNDRY EXPRESS</div>
    </div>

    @if(filled($branchAddress))
        <p class="branch-address">{{ $branchAddress }}</p>
    @endif
    <p class="tin">Non VAT Reg TIN: {{ $statementDetails['soa_tin'] }}</p>

    <div class="title">STATEMENT OF ACCOUNT</div>

    <table class="bill">
        <tr>
            <td class="label">Bill to:</td>
            <td></td>
        </tr>
        <tr>
            <td>{{ $customer->name }}</td>
            <td class="right label">Statement Date:</td>
        </tr>
        <tr>
            <td>{{ $customer->address }}</td>
            <td class="right">{{ $statementDate }}</td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th></th>
                <th>Total Load</th>
                <th>Price per Load</th>
                <th>dry extension</th>
                @if($hasDelivery)
                    <th>Delivery</th>
                @endif
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($soa['days'] as $day)
                @php
                    $loads = $day['loads'];
                    $price = $priceLabel($day['prices']) ?: ($soa['usual_price'] ? $peso($soa['usual_price']) : '');
                @endphp
                <tr>
                    <td class="date">{{ $day['date']->format('F j') }}</td>
                    <td class="num">{{ $loads > 0 ? rtrim(rtrim(number_format($loads, 2), '0'), '.') : '' }}</td>
                    <td class="money">{{ $price }}</td>
                    <td class="money">{{ $day['dry_extension'] > 0 ? $peso($day['dry_extension']) : '' }}</td>
                    @if($hasDelivery)
                        <td class="money">{{ $day['delivery'] > 0 ? $peso($day['delivery']) : '' }}</td>
                    @endif
                    <td class="money">{{ $peso($day['amount']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td></td>
                <td></td>
                <td></td>
                <td class="money" colspan="{{ $hasDelivery ? 2 : 1 }}">TOTAL</td>
                <td class="money">{{ $peso($soa['total']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Please make any payment due, by electronic transfer to our bank account</p>
        <p><strong>Bank:</strong> {{ $statementDetails['soa_bank_name'] }}</p>
        <p><strong>Account name:</strong> {{ $statementDetails['soa_account_name'] }}</p>
        <p><strong>Account number:</strong> {{ $statementDetails['soa_account_number'] }}</p>

        <p class="gap">Please send a screenshot confirmation of your payment</p>
        <p><strong>email: {{ $statementDetails['soa_email'] }}</strong></p>
        <p><strong>viber {{ $statementDetails['soa_viber'] }}</strong></p>
    </div>

    <p class="thanks">Thank you for your business!</p>
</body>
</html>
