<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ResumenAsistenciaExport implements FromArray, WithHeadings
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
            'S1 Tardanza','S1 Trabajadas',
            'S2 Tardanza','S2 Trabajadas',
            'S3 Tardanza','S3 Trabajadas',
            'S4 Tardanza','S4 Trabajadas'
        ];
    }

    public function array(): array
    {
        $sql = $this->params['sql'];
        $result = DB::connection('pgsql_external')->select($sql);
        return json_decode(json_encode($result), true);
    }
}