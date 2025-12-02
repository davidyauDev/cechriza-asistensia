<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DetalleAsistenciaExport implements FromArray, WithHeadings
{
    protected $params;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function headings(): array
    {
        return [
            'DNI','Apellidos','Nombres','Departamento','Empresa',
            'Fecha','H. Ingreso','H. Salida','M. Ingreso','M. Salida',
            'Tardanza','Total Trabajado'
        ];
    }

    public function array(): array
    {
        $sql = $this->params['sql'];
        $bindings = $this->params['bindings'];

        $result = DB::connection('pgsql_external')->select($sql, $bindings);
        return json_decode(json_encode($result), true);
    }
}