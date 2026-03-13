<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DetalleAsistenciaExport implements FromArray, WithHeadings, WithEvents, WithColumnWidths, WithCustomStartCell
{
    protected $params;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function startCell(): string
    {
        // Leave an empty row above the header (like the sample)
        return 'A2';
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

    public function columnWidths(): array
    {
        return [
            'A' => 12, // DNI
            'B' => 22, // Apellidos
            'C' => 18, // Nombres
            'D' => 18, // Departamento
            'E' => 18, // Empresa
            'F' => 12, // Fecha
            'G' => 10, // H. Ingreso
            'H' => 10, // H. Salida
            'I' => 10, // M. Ingreso
            'J' => 10, // M. Salida
            'K' => 10, // Tardanza
            'L' => 14, // Total Trabajado
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->setShowGridlines(false);

                // Header starts at row 2 (because startCell A2)
                $headerRow = 2;
                $dataStartRow = 3;
                $lastColumn = 'L';

                $sheet->freezePane('A' . ($dataStartRow));
                $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$lastRow}");

                $sheet->getRowDimension(1)->setRowHeight(6);
                $sheet->getRowDimension($headerRow)->setRowHeight(18);

                // Borders for the whole table
                $sheet->getStyle("A{$headerRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                        'outline' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Header style
                $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FF000000'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['argb' => 'FFD9D9D9'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                // Align text
                $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$dataStartRow}:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("F{$dataStartRow}:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$dataStartRow}:L{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                // Default styling for mark columns (M. Ingreso / M. Salida) in blue
                if ($lastRow >= $dataStartRow) {
                    $sheet->getStyle("I{$dataStartRow}:J{$lastRow}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['argb' => 'FF1F4E79'],
                        ],
                    ]);
                    $sheet->getStyle("L{$dataStartRow}:L{$lastRow}")->applyFromArray([
                        'font' => ['bold' => true],
                    ]);
                }

                // If tardanza > 0, paint M. Ingreso and Tardanza in red
                for ($row = $dataStartRow; $row <= $lastRow; $row++) {
                    $tardanza = (string) $sheet->getCell("K{$row}")->getValue();
                    $tardanza = trim($tardanza);

                    if ($tardanza === '' || $tardanza === '0' || $tardanza === '00:00:00') {
                        continue;
                    }

                    $sheet->getStyle("I{$row}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['argb' => 'FFFF0000'],
                        ],
                    ]);
                    $sheet->getStyle("K{$row}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['argb' => 'FFFF0000'],
                        ],
                    ]);
                }
            },
        ];
    }
}
