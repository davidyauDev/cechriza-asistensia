<?php
namespace App\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use App\Mail\TardanzaNotificadaMail;
use Carbon\Carbon;


class TardanzaController extends Controller
{
    public function enviarCorreoTardanza(Request $request)
    {
        $data = $request->validate([
            'email'           => 'required|email',
            'nombre'          => 'required|string',
            'scheduled_time'  => 'nullable|min:1',
        ]);

        $fechaActual = Carbon::now()->format('Y-m-d');
        $horaActual = Carbon::now()->format('H:i');

        Mail::to($data['email'])->queue(
            new TardanzaNotificadaMail(
                $data['nombre'],
                $data['scheduled_time'], // ahora es minutos acumulados
                $horaActual,
                null, // hora programada ya no aplica
                $fechaActual
            )
        );

        return response()->json([
            'message' => 'Correo de tardanza enviado correctamente',
            'minutos_tardanza' => $data['scheduled_time']
        ]);
    }
}