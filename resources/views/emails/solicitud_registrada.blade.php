<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Solicitud registrada</title>
</head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#eef2f6;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="720" cellpadding="0" cellspacing="0" style="border-collapse:separate;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 12px 30px rgba(15,23,42,0.10);">
                    <tr>
                        <td style="padding:22px 28px;background:linear-gradient(90deg,#0b5aa6 0%,#17324d 100%);">
                            <div style="color:#ffffff;font-size:20px;font-weight:800;line-height:1.2;">Solicitud registrada</div>
                            <div style="color:rgba(255,255,255,0.9);font-size:13px;margin-top:6px;">{{ $ticket }}</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 28px 10px 28px;color:#0f172a;font-size:14px;line-height:1.65;">
                            Se registró una nueva solicitud
                            <strong>{{ $ticket }}</strong>
                            @if($isPurchaseOrder)
                                como <strong>pedido de compra</strong>
                            @endif
                            .
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 14px 28px;color:#0f172a;font-size:14px;line-height:1.65;">
                            <strong>Solicitante:</strong>
                            {{ trim(($solicitante['firstname'] ?? '').' '.($solicitante['lastname'] ?? '')) }}
                            @if(!empty($solicitante['email']))
                                &lt;{{ $solicitante['email'] }}&gt;
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 14px 28px;color:#0f172a;font-size:14px;line-height:1.65;">
                            <strong>Áreas involucradas:</strong>
                            {{ implode(', ', array_map('strval', $areas)) }}
                        </td>
                    </tr>

                    @if(!empty($justificacion))
                        <tr>
                            <td style="padding:0 28px 14px 28px;color:#0f172a;font-size:14px;line-height:1.65;">
                                <strong>Justificación:</strong> {{ $justificacion }}
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:0 28px 20px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #dbe3ea;">
                                <tr style="background:#f8fafc;">
                                    <th align="left" style="padding:10px 12px;font-size:12px;border-bottom:1px solid #dbe3ea;">Inventario</th>
                                    <th align="left" style="padding:10px 12px;font-size:12px;border-bottom:1px solid #dbe3ea;">Categoría</th>
                                    <th align="left" style="padding:10px 12px;font-size:12px;border-bottom:1px solid #dbe3ea;">Cantidad</th>
                                    <th align="left" style="padding:10px 12px;font-size:12px;border-bottom:1px solid #dbe3ea;">Observación</th>
                                </tr>
                                @foreach($items as $item)
                                    <tr>
                                        <td style="padding:10px 12px;border-bottom:1px solid #eef2f6;">{{ $item['id_inventario'] }} - {{ $item['product_name'] }}</td>
                                        <td style="padding:10px 12px;border-bottom:1px solid #eef2f6;">{{ strtoupper($item['category']) }}</td>
                                        <td style="padding:10px 12px;border-bottom:1px solid #eef2f6;">{{ $item['quantity'] }}</td>
                                        <td style="padding:10px 12px;border-bottom:1px solid #eef2f6;">{{ $item['observacion'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    @if(!empty($uploadedFiles))
                        <tr>
                            <td style="padding:0 28px 18px 28px;color:#0f172a;font-size:13px;line-height:1.65;">
                                <strong>Archivos adjuntos:</strong>
                                <ul style="margin:8px 0 0 18px;padding:0;">
                                    @foreach($uploadedFiles as $file)
                                        <li>{{ $file['path'] }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:0 28px 24px 28px;color:#475569;font-size:12px;line-height:1.6;">
                            Este mensaje fue generado automáticamente por {{ config('app.name') }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
