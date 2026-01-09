<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DetalleMarcacionExport implements FromArray, WithHeadings
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function headings(): array
    {
        return [
            'ID Marcacion',
            'Ubicacion',
            'Imagen',
            'Map URL',
            'Fecha Hora Marcacion',
            'Tipo Marcacion',
            'Tiene Incidencia',
            'DNI',
            'Apellidos',
            'Nombres',
            'Empleado ID',
            'Departamento',
            'Departamento ID',
            'Empresa',
            'Empresa ID',
            'Tecnico',
            'Horario',
            'Ingreso',
            'Salida',
            'Tardanza',
            'Ausencia',
            'Fecha'
        ];
    }

    public function array(): array
    {
        return json_decode(json_encode($this->data), true);
    }
}
