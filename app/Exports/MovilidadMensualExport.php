<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MovilidadMensualExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;
    protected $diasDelMes;

    public function __construct($data, $diasDelMes)
    {
        $this->data = $data;
        $this->diasDelMes = $diasDelMes;
    }

    public function title(): string
    {
        return 'Reporte Movilidad';
    }

    public function headings(): array
    {
        $headers = [
            'DNI',
            'Apellidos',
            'Nombres',
            'Cargo',
            'Departamento',
            'Ciudad',
            'Fecha Ingreso',
            'Total Días',
            'Días Vacación',
            'Días DM',
            'Días NM',
            'Días con Movilidad',
            'Monto Movilidad',
            'Total a Pagar',
        ];

        // Agregar columnas para cada día del mes
        foreach ($this->diasDelMes as $dia) {
            $headers[] = $dia;
        }

        return $headers;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data as $item) {
            $emp = $item['employee'];
            $summary = $item['summary'];

            $row = [
                $emp['dni'],
                $emp['last_name'],
                $emp['first_name'],
                $emp['position_name'],
                $emp['department_name'],
                $emp['city'] ?? '',
                $emp['create_time'] ?? '',
                $summary['total_days'],
                $summary['vacation_days'],
                $summary['medical_leave_days'],
                $summary['no_mark_days'],
                $summary['days_with_mobility'],
                number_format($summary['mobility_amount'], 2),
                number_format($summary['total_mobility_to_pay'], 2),
            ];

            // Agregar valores para cada día
            foreach ($this->diasDelMes as $dia) {
                if (isset($item[$dia])) {
                    $row[] = $item[$dia]['code'];
                } else {
                    $row[] = '';
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // DNI
            'B' => 25,  // Apellidos
            'C' => 20,  // Nombres
            'D' => 20,  // Cargo
            'E' => 25,  // Departamento
            'F' => 15,  // Ciudad
            'G' => 15,  // Fecha Ingreso
            'H' => 12,  // Total Días
            'I' => 12,  // Días Vacación
            'J' => 12,  // Días DM
            'K' => 12,  // Días NM
            'L' => 15,  // Días con Movilidad
            'M' => 15,  // Monto Movilidad
            'N' => 15,  // Total a Pagar
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $totalColumns = 14 + count($this->diasDelMes);
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumns);
        $totalRows = count($this->data) + 1;

        // Estilo de encabezado
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Bordes para todas las celdas
        $sheet->getStyle("A1:{$lastColumn}{$totalRows}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        // Centrar celdas de datos
        $sheet->getStyle("A2:{$lastColumn}{$totalRows}")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Alinear texto a la izquierda para nombres y ciudad
        $sheet->getStyle("B2:F{$totalRows}")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
        ]);

        return $sheet;
    }
}
