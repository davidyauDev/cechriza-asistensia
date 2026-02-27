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

class IncidenciasExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;
    protected $diasDelMes;
    protected $domingos;

    public function __construct($data, $diasDelMes, $domingos = [])
    {
        $this->data = $data;
        $this->diasDelMes = $diasDelMes;
        $this->domingos = $domingos;
    }

    public function title(): string
    {
        return 'Incidencias';
    }

    public function headings(): array
    {
        $headers = [
            'DNI',
            'Apellidos',
            'Nombre',
            'Empresa',
        ];

        // Agregar columnas para cada día del mes
        foreach ($this->diasDelMes as $dia) {
            $headers[] = $dia;
        }

        $headers[] = 'Bruto (HH:MM)';
        $headers[] = 'Incidencias (HH:MM)';
        $headers[] = 'Neto (HH:MM)';

        return $headers;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data as $item) {
            $row = [
                $item['dni'],
                $item['apellidos'],
                $item['nombre'],
                $item['empresa'] ?? '',
            ];

            // Agregar valores para cada día
            $dias = (array) $item['dias'];
            foreach ($this->diasDelMes as $dia) {
                if (isset($dias[$dia])) {
                    $valor = $dias[$dia]['valor'];
                    $row[] = $valor;
                } else {
                    $row[] = '';
                }
            }

            $row[] = $item['bruto_hhmm'];
            $row[] = $item['incidencias_hhmm'];
            $row[] = $item['neto_hhmm'];

            $rows[] = $row;
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // DNI
            'B' => 25,  // Apellidos
            'C' => 20,  // Nombre
            'D' => 30,  // Empresa
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $totalColumns = 7 + count($this->diasDelMes);
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumns);
        $totalRows = count($this->data) + 1;

        $lastDayColumnIndex = $totalColumns - 3;
        $lastDayColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastDayColumnIndex);
        $brutoColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumns - 2);
        $incidenciasColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumns - 1);
        $netoColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumns);

        // Bordes negros finos y alineación vertical
        $sheet->getStyle('A1:' . $lastColumn . $totalRows)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Encabezado: texto negro, centrado y fondo azul claro
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '000000'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '9DC3E6'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Ajustar altura de encabezado
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Centrar columnas de tiempo y días
        $firstDayColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5);
        $sheet->getStyle($firstDayColumn . '2:' . $lastColumn . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Encabezados de Bruto/Incidencias/Neto con colores del diseño
        $sheet->getStyle($brutoColumn . '1')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'C0504D'], // rojo
            ],
        ]);
        $sheet->getStyle($incidenciasColumn . '1')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '00B0F0'], // azul/celeste
            ],
        ]);
        $sheet->getStyle($netoColumn . '1')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFF00'], // amarillo
            ],
        ]);

        // Pintar en rojo si Bruto o Neto supera la hora (>= 60 min)
        for ($row = 2; $row <= $totalRows; $row++) {
            $dataIndex = $row - 2;
            if (!isset($this->data[$dataIndex])) {
                continue;
            }
            if (($this->data[$dataIndex]['bruto_minutos'] ?? 0) >= 60) {
                $sheet->getStyle($brutoColumn . $row)->applyFromArray([
                    'font' => [
                        'color' => ['rgb' => 'FF0000'],
                        'bold' => true,
                    ],
                ]);
            }
            if (($this->data[$dataIndex]['neto_minutos'] ?? 0) >= 60) {
                $sheet->getStyle($netoColumn . $row)->applyFromArray([
                    'font' => [
                        'color' => ['rgb' => 'FF0000'],
                        'bold' => true,
                    ],
                ]);
            }
        }

        // Alternar color de filas en el bloque principal (A hasta último día)
        for ($row = 2; $row <= $totalRows; $row++) {
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':' . $lastDayColumn . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'DDEBF7'],
                    ],
                ]);
            }
        }

        // Pintar columnas de domingo con un color diferente
        foreach ($this->diasDelMes as $index => $dia) {
            if (!in_array($dia, $this->domingos, true)) {
                continue;
            }
            $colIndex = 5 + $index;
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getStyle($columnLetter . '1:' . $columnLetter . $totalRows)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FCE4D6'], // tono distinto para domingo
                ],
            ]);
        }

        // Ancho columnas de días
        for ($col = 5; $col <= $lastDayColumnIndex; $col++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($columnLetter)->setWidth(10);
        }
        // Ancho columnas de totales
        $sheet->getColumnDimension($brutoColumn)->setWidth(15);
        $sheet->getColumnDimension($incidenciasColumn)->setWidth(18);
        $sheet->getColumnDimension($netoColumn)->setWidth(15);

        // Filtros automáticos
        $sheet->setAutoFilter('A1:' . $lastColumn . '1');

        // Congelar primera fila y primeras 4 columnas
        $sheet->freezePane('E2');

        return [];
    }
}
