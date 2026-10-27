<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Receipt {{ $delivery->dr_number }}</title>
    <style>
        @page { margin: 18px 22px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111111; font-family: DejaVu Sans, sans-serif; font-size: 8.5px; }
        p { margin: 0; }
        table.copies { width: 100%; border-collapse: collapse; }
        table.copies > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 10px; }
        table.copies > tbody > tr > td + td { border-left: 1px dashed #999999; }
        .copy-label { font-size: 7.5px; font-weight: bold; letter-spacing: 1px; color: #666666; text-align: right; }
        .brand { text-align: center; }
        .brand-name { color: #1b2a4e; font-size: 18px; font-weight: bold; letter-spacing: 1px; line-height: 1; }
        .brand-drop { display: inline-block; width: 7px; height: 10px; vertical-align: top; margin-left: 2px; }
        .brand-sub { color: #4aa3df; font-size: 9px; font-weight: bold; letter-spacing: 2px; margin-top: 1px; }
        .address { margin-top: 4px; color: #444444; font-size: 7.5px; text-align: center; text-transform: uppercase; }
        .title { margin: 6px 0; font-size: 13px; font-weight: bold; text-align: center; }
        table.head { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.head td { padding: 1px 0; font-weight: bold; vertical-align: top; }
        table.head td.right { text-align: right; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { border: 1px solid #111111; background: #eeeeee; font-size: 7.5px; padding: 2px 4px; text-align: center; }
        table.lines td { border: 1px solid #111111; padding: 2px 4px; }
        table.lines td.num { text-align: center; }
        table.lines td.money { text-align: right; white-space: nowrap; }
        table.lines tr.total td { font-weight: bold; }
        .remaining { margin-top: 5px; font-size: 7.5px; }
        .remaining strong { font-size: 8px; }
        .notes { margin-top: 4px; font-size: 7.5px; }
        table.sign { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.sign td { width: 50%; border: 1px solid #111111; padding: 3px 4px 18px; font-size: 7.5px; font-weight: bold; vertical-align: top; }
        .sign-name { font-weight: normal; margin-top: 2px; }
    </style>
</head>
<body>
    @php
        $peso = fn ($value) => '₱'.number_format((float) $value, 2);
        $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
        $drop = 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('images/soa-drop.png')));
        $lines = $delivery->items->sortBy('id');
        $multipleOrders = $lines->pluck('job_order_id')->unique()->count() > 1;
    @endphp

    <table class="copies">
        <tr>
            @foreach(['CUSTOMER COPY', 'OFFICE COPY'] as $copyLabel)
                <td>
                    <p class="copy-label">{{ $copyLabel }}</p>
                    <div class="brand">
                        <div class="brand-name">SPIN KLEAN<img class="brand-drop" src="{{ $drop }}" alt=""></div>
                        <div class="brand-sub">LAUNDRY EXPRESS</div>
                    </div>
                    @if(filled($delivery->branch?->address))
                        <p class="address">{{ $delivery->branch->address }}</p>
                    @endif
                    <div class="title">DELIVERY RECEIPT</div>

                    <table class="head">
                        <tr>
                            <td>BILL TO: {{ strtoupper($delivery->customer?->name) }}</td>
                            <td class="right">DATE: {{ strtoupper($delivery->delivered_on->format('F j, Y')) }}</td>
                        </tr>
                        <tr>
                            <td>{{ $delivery->customer?->address }}</td>
                            <td class="right">SERIAL NO. {{ $delivery->dr_number }}</td>
                        </tr>
                    </table>

                    <table class="lines">
                        <thead>
                            <tr>
                                <th>ITEM NAME</th>
                                @if($multipleOrders)
                                    <th>JOB ORDER</th>
                                @endif
                                <th>PRICE</th>
                                <th>TOTAL</th>
                                <th>AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $line)
                                <tr>
                                    <td>{{ $line->description }}</td>
                                    @if($multipleOrders)
                                        <td class="num">{{ $line->jobOrder?->job_order_number }}</td>
                                    @endif
                                    <td class="money">{{ $peso($line->unit_price) }}</td>
                                    <td class="num">{{ $qty($line->quantity) }}</td>
                                    <td class="money">{{ $peso($line->amount) }}</td>
                                </tr>
                            @endforeach
                            <tr class="total">
                                <td colspan="{{ $multipleOrders ? 4 : 3 }}">TOTAL</td>
                                <td class="money">{{ $peso($delivery->amount) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="remaining">
                        @foreach($remainingAfter as $order)
                            <p>
                                {{ $order['job_order_number'] }} ({{ $order['po_number'] }}):
                                @if($order['remaining'] > 0)
                                    <strong>PARTIAL DELIVERY &mdash; {{ $qty($order['remaining']) }} still to be delivered</strong>
                                @else
                                    <strong>COMPLETE &mdash; all laundry delivered</strong>
                                @endif
                            </p>
                        @endforeach
                    </div>

                    @if(filled($delivery->notes))
                        <p class="notes">Notes: {{ $delivery->notes }}</p>
                    @endif

                    <table class="sign">
                        <tr>
                            <td>
                                AUTHORIZED NAME &amp; SIGNATURE:
                                <p class="sign-name">{{ strtoupper($delivery->creator?->name ?? '') }}</p>
                            </td>
                            <td>RECEIVED BY:</td>
                        </tr>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>
</body>
</html>
