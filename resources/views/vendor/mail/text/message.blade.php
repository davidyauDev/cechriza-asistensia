@php
    $brandName = trim(config('mail.from.name') ?: config('app.name'));
    $brandName = $brandName === 'Laravel' ? 'Sistema de Asistencia' : $brandName;
@endphp

{{ $brandName }}

{!! $slot !!}

@isset($subcopy)

{!! $subcopy !!}

@endisset

© {{ date('Y') }} {{ $brandName }}. Este mensaje fue generado automáticamente.

