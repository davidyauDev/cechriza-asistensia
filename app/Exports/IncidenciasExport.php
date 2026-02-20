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

    public function __construct($data, $diasDelMes)
    {
        $this->data = $data;
        $this->diasDelMes = $diasDelMes;
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
            'Bruto (HH:MM)',
            'Incidencias (HH:MM)',
            'Neto (HH:MM)',
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
            $row = [
                $item['dni'],
                $item['apellidos'],
                $item['nombre'],
                $item['empresa'] ?? '',
                $item['bruto_hhmm'],
                $item['incidencias_hhmm'],
                $item['neto_hhmm'],
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
            'E' => 15,  // Bruto
            'F' => 18,  // Incidencias
            'G' => 15,  // Neto
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $totalColumns = 7 + count($this->diasDelMes);
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumns);
        $totalRows = count($this->data) + 1;

        // Encabezado: azul oscuro y blanco, bordes dorados
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFD700'],
                ],
            ],
        ]);

        // Ajustar altura de encabezado
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Estilo de datos
        $sheet->getStyle('A2:' . $lastColumn . $totalRows)->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        // Centrar columnas de tiempo y días
        $sheet->getStyle('E2:' . $lastColumn . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Colorear columnas de totales con colores suaves y diferenciados
        $sheet->getStyle('E2:E' . $totalRows)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'B4C6E7'], // azul suave
            ],
        ]);
        $sheet->getStyle('F2:F' . $totalRows)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFD966'], // dorado suave
            ],
        ]);
        $sheet->getStyle('G2:G' . $totalRows)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'A9D08E'], // verde profesional
            ],
        ]);

        // Resaltar celdas con más de 1 hora en dorado profesional
        for ($row = 2; $row <= $totalRows; $row++) {
            $dataIndex = $row - 2;
            if (isset($this->data[$dataIndex])) {
                if ($this->data[$dataIndex]['bruto_minutos'] >= 60) {
                    $sheet->getStyle('E' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFC000'], // dorado fuerte
                        ],
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '002060'],
                        ],
                    ]);
                }
                if ($this->data[$dataIndex]['incidencias_minutos'] >= 60) {
                    $sheet->getStyle('F' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFC000'],
                        ],
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '002060'],
                        ],
                    ]);
                }
                if ($this->data[$dataIndex]['neto_minutos'] >= 60) {
                    $sheet->getStyle('G' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFC000'],
                        ],
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '002060'],
                        ],
                    ]);
                }
            }
        }

        // Alternar colores de filas con gris claro y azul claro para días
        for ($row = 2; $row <= $totalRows; $row++) {
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F2F2F2'],
                    ],
                ]);
                if ($totalColumns > 7) {
                    $firstDayColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(8);
                    $sheet->getStyle($firstDayColumn . $row . ':' . $lastColumn . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'B4C6E7'], // azul claro profesional
                        ],
                    ]);
                }
            }
        }

        // Ancho automático para columnas de días
        for ($col = 8; $col <= $totalColumns; $col++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($columnLetter)->setWidth(12);
        }

        // Filtros automáticos
        $sheet->setAutoFilter('A1:' . $lastColumn . '1');

        // Congelar primera fila y primeras 4 columnas
        $sheet->freezePane('E2');

        return [];
    }
}
