<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dock Receipt - {{ $shipment->reference_no }}</title>
    <style>
        @page {
            margin: 18px;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8.5pt;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        .container {
            padding: 6px;
        }

        /* ── Header ── */
        table.hdr {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2.5px solid #001f3f;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        td.hdr-logo {
            width: 60px;
            vertical-align: middle;
            padding-right: 8px;
        }

        td.hdr-info {
            width: auto;
            vertical-align: middle;
        }

        td.hdr-right {
            width: 200px;
            text-align: right;
            vertical-align: middle;
        }

        .logo {
            max-height: 50px;
            max-width: 80px;
            object-fit: contain;
        }

        .co-name {
            font-size: 11pt;
            font-family: 'Helvetica-Bold', sans-serif;
            color: #001f3f;
            display: block;
            line-height: 1.1;
        }

        .co-detail {
            font-size: 7.5pt;
            color: #555;
        }

        .qr-code {
            width: 58px;
            height: 58px;
            display: block;
            margin-left: auto;
            margin-bottom: 2px;
        }

        .fmc {
            font-size: 6.5pt;
            color: #777;
            text-transform: uppercase;
            text-align: right;
            display: block;
        }

        /* ── Title bar ── */
        table.title {
            width: 100%;
            border-collapse: collapse;
            background: #001f3f;
            margin-bottom: 8px;
        }

        td.doc-title {
            color: #fff;
            font-family: 'Helvetica-Bold', sans-serif;
            font-size: 18pt;
            text-transform: uppercase;
            padding: 5px 10px;
        }

        td.ref-cell {
            text-align: right;
            padding: 5px 10px;
            color: #fff;
        }

        .ref-lbl {
            font-size: 6.5pt;
            text-transform: uppercase;
            opacity: .7;
            display: block;
        }

        .ref-val {
            font-family: 'Helvetica-Bold', sans-serif;
            font-size: 11pt;
            color: #ffcc00;
        }

        /* ── Generic Grid ── */
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        table.grid td {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: top;
        }

        .lbl {
            font-size: 6.5pt;
            font-family: 'Helvetica-Bold', sans-serif;
            text-transform: uppercase;
            color: #888;
            display: block;
            margin-bottom: 2px;
        }

        .val {
            font-family: 'Helvetica-Bold', sans-serif;
            font-size: 8.5pt;
            color: #111;
        }

        .sub {
            font-size: 7.5pt;
            color: #555;
            display: block;
            margin-top: 1px;
            line-height: 1.15;
        }

        .sub1 {
            font-size: 10.5pt;
            color: #333;
            display: block;
            margin-top: 1px;
            line-height: 1.15;
        }

        /* ── Logistics row (4-column) ── */
        table.lg4 {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        table.lg4 td {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: top;
            width: 25%;
        }

        /* ── Cargo Table ── */
        table.cargo {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        table.cargo th {
            background: #001f3f;
            color: #fff;
            font-size: 7pt;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #001f3f;
            text-align: left;
        }

        table.cargo td {
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 8pt;
            vertical-align: top;
        }

        table.cargo tr:nth-child(even)>td {
            background: #f9f9f9;
        }

        table.cargo tfoot td {
            border-top: 2px solid #001f3f;
            font-family: 'Helvetica-Bold', sans-serif;
        }

        .unit-id {
            font-family: 'Helvetica-Bold', sans-serif;
            font-size: 7pt;
            color: #001f3f;
            display: block;
            margin-bottom: 2px;
        }

        .v-title {
            font-family: 'Helvetica-Bold', sans-serif;
            font-size: 10.5pt;
            display: block;
        }

        .vin {
            font-family: 'Courier', monospace;
            font-size: 10.5pt;
            color: #333;
        }

        /* ── Footer / Policy ── */
        table.footer {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        table.footer td {
            vertical-align: top;
            padding: 0 6px 0 0;
        }

        .terms-box {
            font-size: 7pt;
            color: #666;
            line-height: 1.35;
            text-align: justify;
        }

        .terms-ttl {
            font-family: 'Helvetica-Bold', sans-serif;
            font-size: 7pt;
            text-transform: uppercase;
            color: #444;
            display: block;
            margin-bottom: 3px;
        }

        table.rates {
            width: 100%;
            border-collapse: collapse;
        }

        table.rates th {
            font-size: 6.5pt;
            text-transform: uppercase;
            background: #f4f4f4;
            border: 1px solid #000;
            padding: 4px 5px;
            text-align: left;
            color: #666;
        }

        table.rates td {
            font-size: 8pt;
            border: 1px solid #000;
            padding: 4px 5px;
            font-family: 'Helvetica-Bold', sans-serif;
        }

        /* ── Signatures ── */
        .sig-area {
            margin-top: 10px;
            width: 100%;
        }

        .sig-line {
            border-top: 1px solid #333;
            width: 200px;
            padding-top: 3px;
            font-size: 7pt;
            color: #666;
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    <div class="container">

        {{-- ── HEADER ── --}}
        <table class="hdr">
            <tr>
                <td class="hdr-logo">
                    @if($settings->logoBase64())
                        <img src="{{ $settings->logoBase64() }}" class="logo" alt="Logo">
                    @endif
                </td>
                <td class="hdr-info">
                    <span class="co-name">{{ $settings->company_name }}</span>
                    <span class="co-detail">
                        {{ $settings->address }}, {{ $settings->city?->name }}, {{ $settings->state?->name }}<br>
                        {{ $settings->country?->name }} {{ $settings->zipcode }} | Tel: {{ $settings->phone }} |
                        {{ $settings->email }}
                    </span>
                </td>
                <td class="hdr-right">
                    <table style="border-collapse:collapse;float:right;">
                        <tr>
                            <td style="vertical-align:middle;text-align:right;">
                                <span
                                    style="font-size:10pt;font-weight:bold;text-transform:uppercase;color:#000000;display:block;letter-spacing:.5px;">FMC
                                    #</span>
                                <span
                                    style="font-family:'Helvetica-Bold',sans-serif;font-size:24pt;color:#001f3f;display:block;letter-spacing:1px;">{{ $settings->fmc_number ?: 'N/A' }}</span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ── TITLE BAR ── --}}
        <table class="title">
            <tr>
                <td class="doc-title">Dock Receipt</td>
                <td class="ref-cell">
                    <span class="ref-lbl">Reference No.</span>
                    <span class="ref-val">{{ $shipment->reference_no }}</span>
                </td>
            </tr>
        </table>

        {{-- ── ROW 1: Agent | Export Details ── --}}
        <table class="grid">
            <tr>
                <td style="width:50%;">
                    <span class="lbl">1. Shipper / Exporter</span>
                    @if($shipment->exporter_name)
                        <div class="val">{{ $shipment->exporter_name }}</div>
                        <span class="sub">
                            {{ $shipment->exporter_address }}<br>
                            {{ collect([$shipment->exporter_state, $shipment->exporter_country])->filter()->implode(', ') }}
                            {{ $shipment->exporter_zipcode }}
                        </span>
                    @elseif($shipment->shipper)
                        <div class="val">{{ $shipment->shipper->company_name ?? $shipment->shipper->user?->name }}</div>
                        <span class="sub">{{ $shipment->shipper->address }}, {{ $shipment->shipper->city?->name }},
                            {{ $shipment->shipper->state?->code }} {{ $shipment->shipper->zip_code }}</span>
                    @else
                        <div class="val">N/A</div>
                    @endif
                </td>
                <td style="width:50%; padding:0;">
                    <table style="width:100%;border-collapse:collapse;">
                        <tr>
                            <td style="border:none;border-right:1px solid #000;padding:5px 6px;width:33%;">
                                <span class="lbl">2. B/L Number</span>
                                <div class="val">{{ $shipment->bill_of_lading_number ?: 'TBD' }}</div>
                            </td>
                            <td style="border:none;border-right:1px solid #000;padding:5px 6px;width:33%;">
                                <span class="lbl">3. Booking #</span>
                                <div class="val">{{ $shipment->booking_number ?: 'N/A' }}</div>
                            </td>
                            <td style="border:none;padding:5px 6px;width:34%;">
                                <span class="lbl">4. Reference #</span>
                                <div class="val">{{ $shipment->reference_no ?: 'PENDING' }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ── ROW 2: Shipper | Consignee ── --}}
        <table class="grid">
            <tr>
                <td style="width:50%;">
                    <span class="lbl">5. Consignee</span>
                    @if($shipment->consignee)
                        <div class="val">{{ $shipment->consignee->name }}</div>
                        <span class="sub">
                            {{ $shipment->consignee->address }}<br>
                            @if($shipment->consignee->state || $shipment->consignee->country)
                                {{ $shipment->consignee->state?->name }}{{ ($shipment->consignee->state && $shipment->consignee->country) ? ', ' : '' }}{{ $shipment->consignee->country?->name }}<br>
                            @endif
                            {{ $shipment->consignee->phone }}
                        </span>
                    @else
                        <div class="val">N/A</div>
                    @endif
                </td>
                <td style="width:50%;" rowspan="2">
                    <span class="lbl">6. Forwarding Agent / Identity</span>
                    <div class="val">{{ $settings->forwarding_agent_name ?: $settings->company_name }}
                        FMC# {{$settings->fmc_number }}</div>
                    <span class="sub">
                        {{ $settings->forwarding_agent_address ?: $settings->address }}<br>
                        Tel: {{ $settings->forwarding_agent_phone ?: $settings->phone }}
                    </span>

                </td>
            </tr>
            <tr>
                <td style="width:50%;">
                    <span class="lbl">7. Notify Party / Intermediate Consignee <i
                            style="font-size:6.5pt;font-weight:normal;text-transform:none;">(Name and
                            address)</i></span>
                    @if($shipment->notifyParty)
                        <div class="val">{{ $shipment->notifyParty->name }}</div>
                        <span class="sub">
                            {{ $shipment->notifyParty->address }}<br>
                            @if($shipment->notifyParty->state || $shipment->notifyParty->country)
                                {{ $shipment->notifyParty->state?->name }}{{ ($shipment->notifyParty->state && $shipment->notifyParty->country) ? ', ' : '' }}{{ $shipment->notifyParty->country?->name }}<br>
                            @endif
                            {{ $shipment->notifyParty->phone }}
                        </span>
                    @else
                        <div class="val" style="margin-top:5px; font-family:'Helvetica-Bold', sans-serif; font-size:9pt;">
                            SAME AS ABOVE</div>
                    @endif
                </td>
            </tr>
        </table>

        {{-- ── ROW 3: Vessel / Pier / Ports / Routing (4 cols) ── --}}
        <table class="lg4">
            <tr>
                <td>
                    <span class="lbl">8. Vessel / Voyage</span>
                    <div class="val">{{ $shipment->vessel_name ?: 'TBD' }} / {{ $shipment->voyage_no ?: 'TBD' }}</div>
                </td>
                <td>
                    <span class="lbl">9. Loading Pier/Terminal</span>
                    <div class="val">{{ $shipment->loading_pier ?: 'N/A' }}</div>
                </td>
                <td>
                    <span class="lbl">10. Port of Loading</span>
                    <div class="val">{{ $shipment->originPort?->name }}</div>
                    <span class="sub">ETD: {{ $shipment->departure_date?->format('d M Y') ?: 'TBD' }}</span>
                </td>
                <td>
                    <span class="lbl">11. Port of Unloading</span>
                    <div class="val">{{ $shipment->destinationPort?->name }}</div>
                    <span class="sub">ETA: {{ $shipment->arrival_date?->format('d M Y') ?: 'TBD' }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="lbl"> 12. Domestic Routing / Export Instructions</span>
                    <div style="font-size:7.5pt;color:#333;min-height:14px;">
                        @if($shipment->originPort && $shipment->originPort->terminal_name)
                            {{ $shipment->originPort->terminal_name }}<br>
                            {{ $shipment->originPort->terminal_address }}<br>
                            {{ $shipment->originPort->terminal_state }} {{ $shipment->originPort->terminal_zipcode }}<br>
                            @if($shipment->originPort->terminal_phone) Phone:
                            {{ $shipment->originPort->terminal_phone }}<br> @endif
                            {{ $shipment->originPort->terminal_email }}
                        @endif
                        {{-- {{ $shipment->domestic_routing }} --}}
                    </div>
                </td>
                <td style="text-align:center; vertical-align:middle;">
                    <span class="lbl" style="text-align:left;">13. CONTAINERIZED (Vessel only)</span>
                    <div style="margin-top:4px; font-size:9.5pt; color:#001f3f;">
                        @if($shipment->isContainer())
                            <span style="font-family:'DejaVu Sans', sans-serif; font-size:11pt;">&#9746;</span> Yes
                            &nbsp;&nbsp;&nbsp;&nbsp;
                            <span style="font-family:'DejaVu Sans', sans-serif; font-size:11pt;">&#9744;</span> No
                        @else
                            <span style="font-family:'DejaVu Sans', sans-serif; font-size:11pt;">&#9744;</span> Yes
                            &nbsp;&nbsp;&nbsp;&nbsp;
                            <span style="font-family:'DejaVu Sans', sans-serif; font-size:11pt;">&#9746;</span> No
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        {{-- ── CARGO TABLE ── --}}
        <table class="cargo">
            <thead>
                <tr>
                    <th style="width:22%;">Marks & Nos / Container</th>
                    <th style="width:42%;">Description of Packages & Goods</th>
                    <th style="width:18%;text-align:center;line-height:1.1;">Gross Weight<br><i
                            style="font-size:6pt;font-weight:normal;text-transform:none;">(Kilos)</i></th>
                    <th style="width:18%;text-align:center;line-height:1.1;">Measurement</th>
                </tr>
            </thead>
            <tbody>
                @foreach($shipment->vehicles as $index => $vehicle)
                    <tr>
                        <td>
                            @if($index === 0 && $shipment->isContainer())
                                <span class="unit-id">Container ID</span>
                                <strong style="font-size:8pt;">{{ $shipment->container_no }}</strong>
                                <span class="sub">Seal: {{ $shipment->seal_no }} | Type: {{ $shipment->container_type }}</span>
                            @else
                                <span class="unit-id">Unit #{{ $index + 1 }} –
                                    {{ strtoupper($shipment->shipping_mode->value) }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="v-title">{{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}</span>
                            <span class="vin">VIN: {{ $vehicle->vin }}</span>
                            <span class="sub1">AES ITN: {{ $shipment->itn_number }}</span>
                            <span class="sub">Value: ${{ number_format((float) $vehicle->value, 2) }}</span>
                            @if(!$shipment->isContainer())
                                <br /><span class="sub1"> {{ $vehicle->vehicle_is->label() }}</span>
                            @endif
                        </td>
                        @php
                            $w = (float) $vehicle->weight;
                            $wu = strtoupper($vehicle->weight_unit ?? 'LB');
                            $wLb = in_array($wu, ['LB', 'LBS']) ? $w : $w * 2.20462262;
                            $wKg = in_array($wu, ['LB', 'LBS']) ? $w / 2.20462262 : $w;

                            $m = (float) $vehicle->measurement;
                            $mCbm = $m;
                            $mVlb = $m * 367.662897;
                        @endphp
                        <td style="text-align:center;">
                            <span class="val">{{ number_format($wKg, 2) }} Kg</span><br>
                            <span style="font-size:7.5pt;color:#333;">"{{ number_format($wLb, 2) }} Lb"</span>
                        </td>
                        <td style="text-align:center;">
                            <span class="val">{{ number_format($mCbm, 2) }} m&sup3;</span><br>
                            <span style="font-size:7.5pt;color:#333;">"{{ number_format($mVlb, 2) }} Vlb"</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ── FOOTER POLICY ── --}}
        <table class="footer">
            <tr>
                <td style="width:62%;">
                    <div class="terms-box">
                        <span class="terms-ttl">Terms &amp; Conditions / Carriage Policy</span>
                        {!! nl2br(e($settings->terms_and_conditions ?: 'Received the above described merchandise in apparent good order and condition, except as noted, to be held and transported subject to all the terms and conditions contained in the Bill of Lading.')) !!}
                    </div>
                </td>
                <td style="width:38%;padding:0;">
                    <span class="terms-ttl">Storage Rate Schedule</span>
                    @php($rates = $settings->storage_rates_json ?? [8, 18, 28])
                    <table class="rates">
                        <thead>
                            <tr>
                                <th>Cargo Type (After 30 Days)</th>
                                <th>Daily Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Cars &amp; Vans</td>
                                <td>${{ number_format((float) ($rates[0] ?? 8), 2) }}</td>
                            </tr>
                            <tr>
                                <td>Trucks &amp; Static Cargo</td>
                                <td>${{ number_format((float) ($rates[1] ?? 18), 2) }}</td>
                            </tr>
                            <tr>
                                <td>High Heavy Cargo</td>
                                <td>${{ number_format((float) ($rates[2] ?? 28), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ── SIGNATURES ── --}}
        <div class="sig-area">
            <table style="width:100%;border-collapse:collapse;margin-top:8px;">
                <tr>
                    <td style="width:30%;vertical-align:bottom;">
                        <div class="sig-line">Authorized Representative</div>
                    </td>
                    <td style="width:20%;text-align:center;vertical-align:bottom;">
                        <div
                            style="font-size:7pt;color:#aaa;border:1.5px dashed #ccc;padding:4px;border-radius:50%;width:55px;height:55px;line-height:55px;margin:0 auto;text-align:center;">
                            Stamp</div>
                    </td>
                    <td style="width:20%;text-align:center;vertical-align:bottom;">
                        @if($qrCode)
                            <img src="{{ $qrCode }}" class="qr-code" alt="QR" style="margin:0 auto;">
                        @endif
                    </td>
                    <td style="width:30%;text-align:right;vertical-align:bottom;">
                        <div class="sig-line" style="margin-left:auto;">Customer / Driver Initials</div>
                        <div style="font-size:7pt;color:#666;margin-top:3px;">Date: {{ now()->format('d M Y') }}</div>
                    </td>
                </tr>
            </table>
        </div>

    </div>
</body>

</html>