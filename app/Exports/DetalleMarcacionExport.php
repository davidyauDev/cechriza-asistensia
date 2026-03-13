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

class DetalleMarcacionExport implements FromArray, WithHeadings, WithEvents, WithColumnWidths
{
    protected $data;
    protected bool $simpleLayout;

    public function __construct($data)
    {
        $this->data = $data;

        $first = is_array($data) && isset($data[0]) ? $data[0] : null;
        $this->simpleLayout = is_object($first) && property_exists($first, 'Ingreso');
    }

    public function headings(): array
    {
        if ($this->simpleLayout) {
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

        return [
            'DNI',
            'Apellidos',
            'Nombres',
            'Departamento',
            'Empresa',
            'Marca',
            'Tipo',
            'Fecha',
            'Hora',
            'Dirección',
            'Mapa',
            'Foto',
        ];
    }

    public function array(): array
    {
        $rows = [];

        if ($this->simpleLayout) {
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

        foreach ($this->data as $row) {
            $fecha = '';
            if (isset($row->Fecha) && $row->Fecha) {
                try {
                    $fecha = Carbon::parse((string) $row->Fecha)->format('j/m/Y');
                } catch (\Throwable $e) {
                    $fecha = (string) $row->Fecha;
                }
            } elseif (isset($row->Fecha_Hora_Marcacion) && $row->Fecha_Hora_Marcacion) {
                try {
                    $fecha = Carbon::parse((string) $row->Fecha_Hora_Marcacion)->format('j/m/Y');
                } catch (\Throwable $e) {
                    $fecha = (string) $row->Fecha_Hora_Marcacion;
                }
            }

            $hora = '';
            if (isset($row->Hora_Marcacion) && $row->Hora_Marcacion) {
                $hora = (string) $row->Hora_Marcacion;
            } elseif (isset($row->Fecha_Hora_Marcacion) && $row->Fecha_Hora_Marcacion) {
                try {
                    $hora = Carbon::parse((string) $row->Fecha_Hora_Marcacion)->format('H:i:s');
                } catch (\Throwable $e) {
                    $hora = '';
                }
            }

            $rows[] = [
                (string) ($row->DNI ?? ''),
                (string) ($row->Apellidos ?? ''),
                (string) ($row->Nombres ?? ''),
                (string) ($row->Departamento ?? ''),
                (string) ($row->Empresa ?? ''),
                (string) ($row->ID_Marcacion ?? ''),
                (string) ($row->Tipo_Marcacion ?? ''),
                $fecha,
                $hora,
                (string) ($row->Ubicacion ?? ''),
                (string) ($row->map_url ?? ''),
                (string) ($row->Imagen ?? ''),
            ];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        if ($this->simpleLayout) {
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

        return [
            'A' => 12, // DNI
            'B' => 22, // Apellidos
            'C' => 20, // Nombres
            'D' => 18, // Departamento
            'E' => 18, // Empresa
            'F' => 10, // Marca
            'G' => 8,  // Tipo
            'H' => 12, // Fecha
            'I' => 10, // Hora
            'J' => 34, // Dirección
            'K' => 34, // Mapa
            'L' => 34, // Foto
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastColumn = $this->simpleLayout ? 'I' : 'L';

                $sheet->setShowGridlines(false);
                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
                $sheet->getRowDimension(1)->setRowHeight(18);

                // Borders
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
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
                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
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

                if ($lastRow >= 2) {
                    // Body background (light blue, like the sample)
                    $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['argb' => 'FFDDEBF7'],
                        ],
                    ]);
                }

                if ($this->simpleLayout) {
                    // Alignments
                    $sheet->getStyle("A2:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("F2:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("A2:I{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                    // Ingreso in red when tardanza = 1 (or ingreso > horario if available in the original objects)
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

                    return;
                }

                // Alignments for detailed report
                $sheet->getStyle("A2:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("F2:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("J2:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("A2:L{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("J2:L{$lastRow}")->getAlignment()->setWrapText(true);
            },
        ];
    }
}
