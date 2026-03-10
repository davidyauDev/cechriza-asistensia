<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Tardanza acumulada</title>
</head>
<body style="margin:0;padding:0;background:#eef2f6;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Tardanza acumulada: {{ $tardanzaTexto }} ({{ $periodoTexto ?? 'periodo' }}).
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#eef2f6;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="border-collapse:separate;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 12px 30px rgba(15,23,42,0.10);">
                    <tr>
                        <td style="padding:0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="padding:22px 26px;background:#0b5aa6;background-image:linear-gradient(90deg,#0a5aa8 0%,#65738a 45%,#a4633c 100%);">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                            <tr>
                                                <td align="left" style="vertical-align:middle;">
                                                    <div style="font-family:Arial, Helvetica, sans-serif;color:#ffffff;font-weight:800;font-size:22px;letter-spacing:0.2px;line-height:1.1;">
                                                        RR.HH.
                                                    </div>
                                                    <div style="font-family:Arial, Helvetica, sans-serif;color:rgba(255,255,255,0.92);font-weight:600;font-size:13px;margin-top:6px;line-height:1.2;">
                                                        Recursos Humanos
                                                    </div>
                                                </td>
                                                <td align="right" style="vertical-align:middle;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                                        <tr>
                                                            <td style="width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.18);" align="center" valign="middle">
                                                                <span style="display:inline-block;width:22px;height:22px;line-height:0;">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                                                                        <path d="M12 22a10 10 0 1 0-10-10 10 10 0 0 0 10 10Z" stroke="#ffffff" stroke-width="2"/>
                                                                        <path d="M12 6v6l4 2" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                    </svg>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:26px 28px 10px 28px;">
                            <div style="font-family:Arial, Helvetica, sans-serif;color:#0f172a;font-size:16px;line-height:1.55;">
                                Hola <span style="color:#0b5aa6;font-weight:800;">{{ $nombre }}</span>,
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 18px 28px;">
                            <div style="font-family:Arial, Helvetica, sans-serif;color:#0f172a;font-size:15px;line-height:1.65;">
                                @if(!empty($tardanzaMinutos) && $tardanzaMinutos > 60)
                                    Se te comparte el récord de asistencia con
                                    <strong>fecha de corte {{ $fechaCorteTexto ?? '—' }}</strong>
                                    para el proceso de planilla. Te informamos que acumulaste un total de
                                    <strong style="color:#c0262d;">{{ $tardanzaMinutos }} minutos de tardanza</strong>,
                                    en el periodo <strong>{{ $periodoTexto ?? '—' }}</strong>.
                                @else
                                    Te escribimos para informarte sobre tu registro de asistencia en el periodo
                                    <strong>{{ $periodoTexto ?? '—' }}</strong>.
                                @endif
                            </div>
                        </td>
                    </tr>

                    @if(!empty($tardanzaMinutos) && $tardanzaMinutos > 60)
                        <tr>
                            <td style="padding:0 28px 18px 28px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #2b2b2b;">
                                    <tr style="background:#bfe3f3;">
                                        <th align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:10px 8px;border-right:1px solid #2b2b2b;">N°</th>
                                        <th align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:10px 8px;border-right:1px solid #2b2b2b;">DNI</th>
                                        <th align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:10px 8px;border-right:1px solid #2b2b2b;">APELLIDOS</th>
                                        <th align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:10px 8px;border-right:1px solid #2b2b2b;">NOMBRE</th>
                                        <th align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:10px 8px;border-right:1px solid #2b2b2b;">DEPARTAMENTO</th>
                                        <th align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:10px 8px;border-right:1px solid #2b2b2b;">EMPRESA</th>
                                        <th align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:10px 8px;border-right:1px solid #2b2b2b;background:#5b92d6;color:#ffffff;">Tiempo tardanza</th>
                                        <th align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:10px 8px;background:#5b92d6;color:#ffffff;">Minutos</th>
                                    </tr>
                                    <tr>
                                        <td align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:9px 8px;border-top:1px solid #2b2b2b;border-right:1px solid #2b2b2b;">{{ $nro ?? '—' }}</td>
                                        <td align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:9px 8px;border-top:1px solid #2b2b2b;border-right:1px solid #2b2b2b;">{{ $dni ?? '—' }}</td>
                                        <td align="left" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:9px 8px;border-top:1px solid #2b2b2b;border-right:1px solid #2b2b2b;">{{ $apellidos ?? '—' }}</td>
                                        <td align="left" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:9px 8px;border-top:1px solid #2b2b2b;border-right:1px solid #2b2b2b;">{{ $nombre ?? '—' }}</td>
                                        <td align="left" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:9px 8px;border-top:1px solid #2b2b2b;border-right:1px solid #2b2b2b;">{{ $departamento ?? '—' }}</td>
                                        <td align="left" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:9px 8px;border-top:1px solid #2b2b2b;border-right:1px solid #2b2b2b;">{{ $empresa ?? '—' }}</td>
                                        <td align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:9px 8px;border-top:1px solid #2b2b2b;border-right:1px solid #2b2b2b;color:#c0262d;font-weight:800;">{{ $tardanzaHHMM ?? '—' }}</td>
                                        <td align="center" style="font-family:Arial, Helvetica, sans-serif;font-size:12px;padding:9px 8px;border-top:1px solid #2b2b2b;color:#c0262d;font-weight:800;">{{ $tardanzaMinutos }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:0 28px 18px 28px;">
                                <div style="font-family:Arial, Helvetica, sans-serif;color:#0f172a;font-size:13px;line-height:1.65;">
                                    De acuerdo al RIT, las tardanzas acumuladas que pasan los <strong>60 minutos</strong> están sujetas al descuento respectivo.
                                </div>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td style="padding:0 28px 18px 28px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;background:#fbf4f0;border-radius:10px;border-left:4px solid #c05621;">
                                    <tr>
                                        <td style="padding:14px 16px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                                <tr>
                                                    <td style="width:26px;vertical-align:top;padding-top:2px;">
                                                        <span style="display:inline-block;width:18px;height:18px;line-height:0;">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                                <path d="M12 22a10 10 0 1 0-10-10 10 10 0 0 0 10 10Z" stroke="#c05621" stroke-width="2"/>
                                                                <path d="M12 7v6" stroke="#c05621" stroke-width="2" stroke-linecap="round"/>
                                                                <path d="M12 17h.01" stroke="#c05621" stroke-width="3" stroke-linecap="round"/>
                                                            </svg>
                                                        </span>
                                                    </td>
                                                    <td style="vertical-align:top;">
                                                        <div style="font-family:Arial, Helvetica, sans-serif;color:#0f172a;font-weight:800;font-size:14px;line-height:1.3;">
                                                            Resumen del Período
                                                        </div>
                                                        <div style="font-family:Arial, Helvetica, sans-serif;color:#334155;font-size:13px;line-height:1.55;margin-top:6px;">
                                                            Tardanza acumulada:
                                                            <span style="color:#c05621;font-weight:900;">{{ $tardanzaTexto }}</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    @if(empty($tardanzaMinutos) || $tardanzaMinutos <= 60)
                    <tr>
                        <td style="padding:0 28px 18px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;background:#f0f7f4;border-radius:12px;border:1px solid #d7efe4;">
                                <tr>
                                    <td style="padding:16px 16px 14px 16px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                            <tr>
                                                <td style="width:26px;vertical-align:top;padding-top:2px;">
                                                    <span style="display:inline-block;width:18px;height:18px;line-height:0;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z" stroke="#0b5aa6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </span>
                                                </td>
                                                <td style="vertical-align:top;">
                                                    <div style="font-family:Arial, Helvetica, sans-serif;color:#0f172a;font-weight:900;font-size:14px;line-height:1.3;">
                                                        Próximos Pasos
                                                    </div>
                                                    <div style="font-family:Arial, Helvetica, sans-serif;color:#334155;font-size:13px;line-height:1.6;margin-top:6px;">
                                                        Nos gustaría conocer si hay circunstancias que están afectando tu puntualidad. Te invitamos a:
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:10px;">
                                            <tr>
                                                <td style="font-family:Arial, Helvetica, sans-serif;color:#0f172a;font-size:13px;line-height:1.85;">
                                                    <div>
                                                        <span style="color:#0b5aa6;font-weight:900;">✓</span>
                                                        <span style="margin-left:8px;display:inline-block;">Comunicarte con tu jefe directo</span>
                                                    </div>
                                                    <div>
                                                        <span style="color:#0b5aa6;font-weight:900;">✓</span>
                                                        <span style="margin-left:8px;display:inline-block;">Contactar al área de RR.HH. para buscar soluciones</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 22px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;background:#f3f7fb;border-radius:10px;border:1px solid #d9e5f2;">
                                <tr>
                                    <td style="padding:12px 14px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                            <tr>
                                                <td style="width:26px;vertical-align:top;padding-top:2px;">
                                                    <span style="display:inline-block;width:18px;height:18px;line-height:0;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78Z" stroke="#0b5aa6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </span>
                                                </td>
                                                <td style="vertical-align:top;">
                                                    <div style="font-family:Arial, Helvetica, sans-serif;color:#0f172a;font-size:13px;line-height:1.55;">
                                                        Valoramos tu compromiso con el equipo y queremos trabajar juntos para mejorar.
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <tr>
                        <td style="padding:0 28px 14px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="border-top:1px solid #e5e7eb;padding-top:16px;">
                                        <div style="font-family:Arial, Helvetica, sans-serif;color:#0f172a;font-size:14px;line-height:1.6;">
                                            Atentamente,
                                        </div>
                                        <div style="font-family:Arial, Helvetica, sans-serif;color:#0b5aa6;font-weight:900;font-size:14px;line-height:1.6;margin-top:6px;">
                                            Equipo de Recursos Humanos
                                        </div>
                                        <div style="font-family:Arial, Helvetica, sans-serif;color:#64748b;font-size:12px;line-height:1.6;">
                                            {{ config('mail.from.address') ?: 'contacto@empresa.com' }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f5f7fb;padding:18px 22px;border-top:1px solid #e5e7eb;" align="center">
                            <div style="font-family:Arial, Helvetica, sans-serif;color:#64748b;font-size:12px;line-height:1.45;">
                                Este es un mensaje automático. Por favor, no responda a este correo.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
