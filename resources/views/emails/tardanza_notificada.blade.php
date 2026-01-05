@component('mail::message')
<div style="text-align:center; margin-bottom: 24px;">
	<h1 style="color:#e3342f; font-size:2.2em; margin-bottom: 0.2em;">Tardanza Acumulada  ( Mensaje de Prueba Ignorar) </h1>
	<p style="font-size:1.1em; color:#555; margin-top:0;">Notificación automática de asistencia</p>
</div>

<div style="background:#f8fafc; border-radius:8px; padding:20px; margin-bottom:24px; border:1px solid #e2e8f0;">
	<p style="font-size:1.1em; margin-bottom: 0.5em;">Hola <strong>{{ $nombre }}</strong>,</p>

	<table style="width:100%; font-size:1em; margin-bottom: 1.2em;">
		@if(!empty($horaProgramada))
		<tr>
			<td style="padding: 6px 0;"><strong>Hora programada:</strong></td>
			<td style="padding: 6px 0;">{{ $horaProgramada }}</td>
		</tr>
		@endif
		<tr>
			<td style="padding: 6px 0;"><strong>Minutos de tardanza acumulados:</strong></td>
			<td style="padding: 6px 0; color:#e3342f; font-weight:bold;">{{ $minutosTardanza }}</td>
		</tr>
	</table>
	<div style="background:#fff3cd; border:1px solid #ffeeba; border-radius:6px; padding:12px; margin-bottom:1em;">
		<strong>Tardanza acumulada hasta ahora:</strong> <span style="color:#e3342f; font-weight:bold;">{{ $minutosTardanza }} minutos</span>
	</div>
	<p style="color:#636363;">Por favor, toma las medidas necesarias para evitar futuras tardanzas.</p>
</div>

<div style="text-align:center; color:#b0b0b0; font-size:0.95em; margin-top:32px;">
	<em>Este es un mensaje automático generado por el sistema de asistencia.</em>
</div>
@endcomponent
