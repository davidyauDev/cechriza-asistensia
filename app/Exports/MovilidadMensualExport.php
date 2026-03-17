<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MovilidadMensualExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;
    protected $diasDelMes;

    private const FIXED_EMPLOYEE_HEADERS = [
        'DNI',
        'Apellidos',
        'Nombres',
        'Cargo',
        'Departamento',
        'Ciudad',
        'Fecha Ingreso',
    ];

    private const SUMMARY_HEADERS = [
        'TOTAL',
        'AS',
        'SR',
        'VACACION',
        'VE',
        'NO MARCADO',
        'DM',
        'DE',
        'LCGH',
        'LSGH',
        'CESE',
        'Vac >23',
        'MONTO APROX A DEPOSTAR',
        'COMENTARIO',
    ];

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
        return array_merge(
            self::FIXED_EMPLOYEE_HEADERS,
            $this->diasDelMes,
            self::SUMMARY_HEADERS
        );
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
            ];

            $summaryCodeCounts = [
                'LCGH' => 0,
                'LSGH' => 0,
            ];

            // Agregar valores para cada día
            foreach ($this->diasDelMes as $dia) {
                if (isset($item[$dia])) {
                    $code = $item[$dia]['code'];
                    $row[] = $code;

                    $normalized = strtoupper(trim((string) $code));
                    if (isset($summaryCodeCounts[$normalized])) {
                        $summaryCodeCounts[$normalized]++;
                    }
                } else {
                    $row[] = '';
                }
            }

            $vacationDays = (int) ($summary['vacation_days'] ?? 0);
            $attendanceDays = (int) ($summary['attendance_days'] ?? 0);
            $sinRutaDays = (int) ($summary['sin_ruta_days'] ?? 0);
            $vacExtemporaneousDays = (int) ($summary['vacation_extemporaneous_days'] ?? 0);
            $medicalLeaveDays = (int) ($summary['medical_leave_days'] ?? 0);
            $extemporaneousRestDays = (int) ($summary['extemporaneous_rest_days'] ?? 0);
            $lcghDays = (int) ($summary['lcgh_days'] ?? $summaryCodeCounts['LCGH']);
            $lsghDays = (int) ($summary['lsgh_days'] ?? $summaryCodeCounts['LSGH']);
            $ceseDays = (int) ($summary['cese_days'] ?? 0);
            $vacOver23 = $vacExtemporaneousDays > 0
                ? $vacExtemporaneousDays
                : max(0, $vacationDays - 23);

            $row[] = (int) ($summary['total_days'] ?? 0);
            $row[] = $attendanceDays;
            $row[] = $sinRutaDays;
            $row[] = $vacationDays;
            $row[] = $vacExtemporaneousDays;
            $row[] = (int) ($summary['no_mark_days'] ?? 0);
            $row[] = $medicalLeaveDays;
            $row[] = $extemporaneousRestDays;
            $row[] = $lcghDays;
            $row[] = $lsghDays;
            $row[] = $ceseDays;
            $row[] = $vacOver23;
            $row[] = number_format((float) ($summary['total_mobility_to_pay'] ?? 0), 2);
            $row[] = (string) ($summary['monthly_comment'] ?? '');

            $rows[] = $row;
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 12,  // DNI
            'B' => 25,  // Apellidos
            'C' => 20,  // Nombres
            'D' => 20,  // Cargo
            'E' => 25,  // Departamento
            'F' => 15,  // Ciudad
            'G' => 15,  // Fecha Ingreso
        ];

        $firstDayColumnIndex = count(self::FIXED_EMPLOYEE_HEADERS) + 1; // A=1
        foreach ($this->diasDelMes as $index => $_dia) {
            $columnLetter = Coordinate::stringFromColumnIndex($firstDayColumnIndex + $index);
            $widths[$columnLetter] = 6;
        }

        $firstSummaryColumnIndex = $firstDayColumnIndex + count($this->diasDelMes);
        $summaryWidths = [10, 8, 8, 10, 8, 12, 8, 8, 8, 8, 8, 10, 18, 35];
        foreach (self::SUMMARY_HEADERS as $index => $_header) {
            $columnLetter = Coordinate::stringFromColumnIndex($firstSummaryColumnIndex + $index);
            $widths[$columnLetter] = $summaryWidths[$index] ?? 12;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $totalColumns = count(self::FIXED_EMPLOYEE_HEADERS) + count($this->diasDelMes) + count(self::SUMMARY_HEADERS);
        $lastColumn = Coordinate::stringFromColumnIndex($totalColumns);
        $totalRows = count($this->data) + 1;
        $firstDayColumnIndex = count(self::FIXED_EMPLOYEE_HEADERS) + 1;

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
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Resaltar encabezados finales (Vac >23 y Monto aprox) como en el ejemplo
        $firstSummaryColumnIndex = $firstDayColumnIndex + count($this->diasDelMes);
        $vacOver23Index = array_search('Vac >23', self::SUMMARY_HEADERS, true);
        $montoIndex = array_search('MONTO APROX A DEPOSTAR', self::SUMMARY_HEADERS, true);
        $vacOver23HeaderCol = Coordinate::stringFromColumnIndex($firstSummaryColumnIndex + (int) $vacOver23Index);
        $montoHeaderCol = Coordinate::stringFromColumnIndex($firstSummaryColumnIndex + (int) $montoIndex);
        $sheet->getStyle("{$vacOver23HeaderCol}1:{$montoHeaderCol}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '000000'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFD966'],
            ],
        ]);

        // Bordes para todas las celdas (negro para cuadrícula marcada)
        $sheet->getStyle("A1:{$lastColumn}{$totalRows}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
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

        // Alinear texto a la izquierda para la columna comentario
        $commentIndex = array_search('COMENTARIO', self::SUMMARY_HEADERS, true);
        if ($commentIndex !== false) {
            $firstSummaryColumnIndex = $firstDayColumnIndex + count($this->diasDelMes);
            $commentCol = Coordinate::stringFromColumnIndex($firstSummaryColumnIndex + (int) $commentIndex);
            $sheet->getStyle("{$commentCol}2:{$commentCol}{$totalRows}")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
            ]);
        }

        // Filas con rayas (zebra)
        for ($row = 2; $row <= $totalRows; $row++) {
            if ($row % 2 == 0) {
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'DDEBF7'],
                    ],
                ]);
            }
        }

        // Resaltar detalle por dia segun codigo (V, DM, NM, SR, etc.)
        $dayValueStyles = [
            'V'  => ['fill' => 'F8CBAD', 'font' => '9C0006'], // Vacaciones
            'VE' => ['fill' => 'F8CBAD', 'font' => '9C0006'], // Vacaciones extemporaneas
            'DM' => ['fill' => 'F8CBAD', 'font' => '9C0006'], // Descanso medico
            'DE' => ['fill' => 'F8CBAD', 'font' => '9C0006'], // Descanso extemporaneas
            'MF' => ['fill' => 'BDD7EE', 'font' => '1F4E79'], // Minutos justificados
            'F'  => ['fill' => 'F4B084', 'font' => '7F3F00'], // Falta
            'TC' => ['fill' => 'C6E0B4', 'font' => '215E1B'], // Trabajo en campo
            'SR' => ['fill' => 'D9D9D9', 'font' => '404040'], // Sin registro
            'NM' => ['fill' => 'FFE699', 'font' => '7F6000'], // No marcado
            'X'  => ['fill' => 'C9C9C9', 'font' => '000000'], // Cese
        ];

        for ($row = 2; $row <= $totalRows; $row++) {
            $dataIndex = $row - 2;
            if (!isset($this->data[$dataIndex])) {
                continue;
            }
            $item = $this->data[$dataIndex];
            foreach ($this->diasDelMes as $index => $dia) {
                if (!isset($item[$dia]['code'])) {
                    continue;
                }
                $valor = strtoupper(trim((string) $item[$dia]['code']));
                if (!isset($dayValueStyles[$valor])) {
                    continue;
                }
                $colIndex = $firstDayColumnIndex + $index;
                $columnLetter = Coordinate::stringFromColumnIndex($colIndex);
                $style = $dayValueStyles[$valor];
                $sheet->getStyle($columnLetter . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $style['fill']],
                    ],
                    'font' => [
                        'color' => ['rgb' => $style['font']],
                        'bold' => true,
                    ],
                ]);
            }
        }

        // Pintar en verde la columna TOTAL (resumen)
        $totalHeaderCol = Coordinate::stringFromColumnIndex($firstSummaryColumnIndex);
        $sheet->getStyle("{$totalHeaderCol}1:{$totalHeaderCol}{$totalRows}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'C6EFCE'],
            ],
        ]);
        $sheet->getStyle("{$totalHeaderCol}1")->applyFromArray([
            'font' => [
                'color' => ['rgb' => '000000'],
            ],
        ]);

        return $sheet;
    }
}
