@component('mail::message')
# Aviso de Asistencia

Hola {{ $user->name }},

Detectamos que **no marcaste tu salida el día de hoy**.  
Por favor verifica tu registro en el sistema.

Gracias,<br>
{{ config('app.name') }}
@endcomponent
