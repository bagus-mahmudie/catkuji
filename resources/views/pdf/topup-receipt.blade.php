<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $transaction->order_no }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 13px;
            margin: 0;
            padding: 28px;
        }

        .page {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            overflow: hidden;
        }

        .header {
            background: #0f172a;
            color: #ffffff;
            padding: 28px 32px;
        }

        .brand {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .subtitle {
            margin-top: 6px;
            color: #cbd5e1;
            font-size: 13px;
        }

        .content {
            padding: 28px 32px 32px;
        }

        .top-row {
            width: 100%;
            margin-bottom: 22px;
        }

        .top-row td {
            vertical-align: top;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 6px;
            color: #111827;
        }

        .muted {
            color: #6b7280;
            font-size: 13px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: 700;
        }

        .section {
            margin-top: 24px;
        }

        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 12px;
        }

        .summary-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 10px 20px;
        }

        .summary-table,
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td,
        .meta-table td {
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .summary-table tr:last-child td,
        .meta-table tr:last-child td {
            border-bottom: none;
        }

        .summary-table td:last-child,
        .meta-table td:last-child {
            text-align: right;
            font-weight: 600;
            color: #111827;
        }

        .amount-box {
            margin-top: 18px;
            border-radius: 16px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 10px 20px;
        }

        .amount-label {
            color: #9a3412;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .amount-value {
            margin-top: 6px;
            font-size: 24px;
            font-weight: 700;
            color: #c2410c;
        }

        .divider {
            margin: 10px 0;
            border-top: 1px dashed #cbd5e1;
        }

        .footer {
            margin-top: 0px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.7;
        }

        .footer-note {
            margin-top: 18px;
            padding: 10px 16px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <img src="{{ public_path('images/logo-catkuji.png') }}" alt="CATKUJI" height="40">
            <div class="subtitle">Interactive entertainment commerce receipt</div>
        </div>

        <div class="content">
            <table class="top-row">
                <tr>
                    <td>
                        <div class="title">Top Up Receipt</div>
                        <div class="muted">No. Transaksi: {{ $transaction->order_no }}</div>
                        <div class="muted">Tanggal: {{ optional($transaction->created_at)->format('d M Y • H:i') }}</div>
                    </td>
                    <td class="text-right">
                        <span class="status-badge">SUKSES</span>
                    </td>
                </tr>
            </table>

            <div class="section">
                <div class="section-title">Informasi Pelanggan</div>
                <div class="summary-box">
                    <table class="meta-table">
                        <tr>
                            <td>Nama</td>
                            <td>{{ $customer->name }}</td>
                        </tr>
                        <tr>
                            <td>Email</td>
                            <td>{{ $customer->email }}</td>
                        </tr>
                        <tr>
                            <td>Metode Bayar</td>
                            <td>{{ $paymentMethodLabel }}</td>
                        </tr>
                        <tr>
                            <td>Status</td>
                            <td>Sukses</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Ringkasan Pembelian</div>
                <div class="summary-box">
                    <table class="summary-table">
                        <tr>
                            <td>Produk</td>
                            <td>Top Up Cat Koin</td>
                        </tr>
                        <tr>
                            <td>Jumlah Cat Koin</td>
                            <td>{{ number_format($coins, 0, ',', '.') }} Cat Koin</td>
                        </tr>
                        <tr>
                            <td>No. Referensi</td>
                            <td>{{ $transaction->order_no }}</td>
                        </tr>
                    </table>

                    <div class="amount-box">
                        <div class="amount-label">Total Pembayaran</div>
                        <div class="amount-value">
                            Rp {{ number_format((float) $transaction->gross_amount, 0, ',', '.') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer">
                <div class="footer-note">
                    Simpan file ini sebagai bukti pembayaran. Jika ada kendala, sertakan nomor transaksi
                    <strong>{{ $transaction->order_no }}</strong> saat menghubungi support.
                </div>
            </div>
        </div>
    </div>
</body>
</html>