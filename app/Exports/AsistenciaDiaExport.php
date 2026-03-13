<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AsistenciaDiaExport implements FromArray, WithHeadings, WithEvents, WithColumnWidths
{
    public function __construct(private readonly array $data)
    {
    }

    public function headings(): array
    {
        return [
            'DNI',
            'Apellidos',
            'Nombres',
            'Departamento',
            'Empresa',
            'Horario',
            'Fecha',
            'Ingreso',
            'Salida',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data as $row) {
            $fecha = '';
            if (isset($row->Fecha) && $row->Fecha) {
                try {
                    $fecha = Carbon::parse((string) $row->Fecha)->format('j/m/Y');
                } catch (\Throwable $e) {
                    $fecha = (string) $row->Fecha;
                }
            }

            $rows[] = [
                (string) ($row->DNI ?? ''),
                (string) ($row->Apellidos ?? ''),
                (string) ($row->Nombres ?? ''),
                (string) ($row->Departamento ?? ''),
                (string) ($row->Empresa ?? ''),
                (string) ($row->Horario ?? ''),
                $fecha,
                (string) ($row->Ingreso ?? ''),
                (string) ($row->Salida ?? ''),
            ];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 22,
            'C' => 20,
            'D' => 18,
            'E' => 18,
            'F' => 10,
            'G' => 12,
            'H' => 10,
            'I' => 10,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->setShowGridlines(false);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:I{$lastRow}");
                $sheet->getRowDimension(1)->setRowHeight(18);

                // Borders
                $sheet->getStyle("A1:I{$lastRow}")->applyFromArray([
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

                // Header style (orange)
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FFFFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['argb' => 'FFC65911'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Body background (light blue)
                if ($lastRow >= 2) {
                    $sheet->getStyle("A2:I{$lastRow}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['argb' => 'FFDDEBF7'],
                        ],
                    ]);
                }

                // Alignments
                $sheet->getStyle("A2:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("F2:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A2:I{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                // Ingreso in red when tardanza = 1 (or ingreso > horario if available)
                $rowIndex = 2;
                foreach ($this->data as $originalRow) {
                    $isLate = false;

                    if (isset($originalRow->Tardanza) && (string) $originalRow->Tardanza === '1') {
                        $isLate = true;
                    } else {
                        $horario = (string) ($originalRow->Horario ?? '');
                        $ingreso = (string) ($originalRow->Ingreso ?? '');
                        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $horario) && preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $ingreso)) {
                            $h = strtotime("1970-01-01 {$horario}");
                            $i = strtotime("1970-01-01 {$ingreso}");
                            if ($h !== false && $i !== false && $i > $h) {
                                $isLate = true;
                            }
                        }
                    }

                    if ($isLate) {
                        $sheet->getStyle("H{$rowIndex}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'color' => ['argb' => 'FFFF0000'],
                            ],
                        ]);
                    }

                    $rowIndex++;
                }
            },
        ];
    }
}

