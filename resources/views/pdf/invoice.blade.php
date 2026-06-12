<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background: #f5f5f5;
        }

        .right {
            text-align: right;
        }

        .header {
            margin-bottom: 20px;
        }

        .total {
            margin-top: 20px;
            font-size: 16px;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="header">
    <h2>AHA! Child Development Center</h2>

    <h3>INVOICE</h3>

    <p>
        Invoice Number:
        {{ $billing->invoice_number }}
    </p>

    <p>
        Registration Number:
        {{ $billing->registration->registration_number }}
    </p>

    <p>
        Child:
        {{ $billing->registration->child->name }}
    </p>

    <p>
        Payer:
        {{ $billing->registration->payer->name ?? '-' }}
    </p>

    <p>
        Status:
        {{ $billing->paymentStatus->name }}
    </p>
</div>

<table>
    <thead>
    <tr>
        <th>Description</th>

        <th>Qty</th>

        <th>Price</th>

        <th>Subtotal</th>
    </tr>
    </thead>

    <tbody>
    @foreach($billing->items as $item)
        <tr>
            <td>
                {{ $item->description }}
            </td>

            <td class="right">
                {{ $item->quantity }}
            </td>

            <td class="right">
                Rp {{ number_format($item->price, 0, ',', '.') }}
            </td>

            <td class="right">
                Rp {{ number_format($item->subtotal, 0, ',', '.') }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="total">
    TOTAL:
    Rp {{ number_format($billing->total_amount, 0, ',', '.') }}
</div>

</body>
</html>