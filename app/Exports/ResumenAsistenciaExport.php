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

class ResumenAsistenciaExport implements FromArray, WithHeadings, WithEvents, WithColumnWidths, WithCustomStartCell
{
    protected $params;
    protected array $semanas;

    public function __construct($params)
    {
        $this->params = $params;
        $this->semanas = (array) ($params['semanas'] ?? []);
    }

    private function rangoSemana(string $key): string
    {
        $inicio = $this->semanas[$key]['inicio'] ?? null;
        $fin = $this->semanas[$key]['fin'] ?? null;

        if (!$inicio || !$fin) {
            return '';
        }

        return "{$inicio} / {$fin}";
    }

    public function startCell(): string
    {
        // Row 1-2 are for grouped headers and date ranges (painted in AfterSheet)
        return 'A3';
    }

    private function timeToSeconds(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        // e.g. "1 day 02:03:04" or "2 days 02:03:04.123"
        if (preg_match('/^(?<days>\d+)\s+day(?:s)?\s+(?<h>\d{1,3}):(?<m>\d{2}):(?<s>\d{2})(?:\.\d+)?$/i', $text, $m)) {
            return ((int) $m['days'] * 86400) + ((int) $m['h'] * 3600) + ((int) $m['m'] * 60) + (int) $m['s'];
        }

        // e.g. "175:58:20" or "00:29:59.000"
        if (preg_match('/^(?<h>\d{1,3}):(?<m>\d{2}):(?<s>\d{2})(?:\.\d+)?$/', $text, $m)) {
            return ((int) $m['h'] * 3600) + ((int) $m['m'] * 60) + (int) $m['s'];
        }

        return null;
    }

    private function secondsToHms(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        if ($seconds < 0) {
            $seconds = 0;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        $hourStr = $hours >= 100
            ? (string) $hours
            : str_pad((string) $hours, 2, '0', STR_PAD_LEFT);

        return sprintf('%s:%02d:%02d', $hourStr, $minutes, $secs);
    }

    public function headings(): array
    {
        return [
            'DNI',
            'Apellidos',
            'Nombres',
            'Departamento',
            'Empresa',
            'S1 Tardanza',
            'S1 Trabajadas',
            'S2 Tardanza',
            'S2 Trabajadas',
            'S3 Tardanza',
            'S3 Trabajadas',
            'S4 Tardanza',
            'S4 Trabajadas',
            'Tardanza',
            'Acumulado',
        ];
    }

    public function array(): array
    {
        $sql = $this->params['sql'];
        $result = DB::connection('pgsql_external')->select($sql);

        $rows = [];

        foreach ($result as $r) {
            $s1T = $this->timeToSeconds($r->s1_tardanza ?? null);
            $s2T = $this->timeToSeconds($r->s2_tardanza ?? null);
            $s3T = $this->timeToSeconds($r->s3_tardanza ?? null);
            $s4T = $this->timeToSeconds($r->s4_tardanza ?? null);

            $s1W = $this->timeToSeconds($r->s1_trabajadas ?? null);
            $s2W = $this->timeToSeconds($r->s2_trabajadas ?? null);
            $s3W = $this->timeToSeconds($r->s3_trabajadas ?? null);
            $s4W = $this->timeToSeconds($r->s4_trabajadas ?? null);

            $tardanzaSeconds = ($s1T ?? 0) + ($s2T ?? 0) + ($s3T ?? 0) + ($s4T ?? 0);
            $acumuladoSeconds = ($s1W ?? 0) + ($s2W ?? 0) + ($s3W ?? 0) + ($s4W ?? 0);

            $rows[] = [
                (string) ($r->dni ?? ''),
                (string) ($r->apellidos ?? ''),
                (string) ($r->nombres ?? ''),
                (string) ($r->departamento ?? ''),
                (string) ($r->empresa ?? ''),
                $s1T === null ? '' : ($this->secondsToHms($s1T) ?? ''),
                $s1W === null ? '' : ($this->secondsToHms($s1W) ?? ''),
                $s2T === null ? '' : ($this->secondsToHms($s2T) ?? ''),
                $s2W === null ? '' : ($this->secondsToHms($s2W) ?? ''),
                $s3T === null ? '' : ($this->secondsToHms($s3T) ?? ''),
                $s3W === null ? '' : ($this->secondsToHms($s3W) ?? ''),
                $s4T === null ? '' : ($this->secondsToHms($s4T) ?? ''),
                $s4W === null ? '' : ($this->secondsToHms($s4W) ?? ''),
                $this->secondsToHms($tardanzaSeconds) ?? '00:00:00',
                $this->secondsToHms($acumuladoSeconds) ?? '00:00:00',
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
            'F' => 14,
            'G' => 16,
            'H' => 14,
            'I' => 16,
            'J' => 14,
            'K' => 16,
            'L' => 14,
            'M' => 16,
            'N' => 12,
            'O' => 14,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // Header rows 1-2 (group + date range)
                $sheet->setCellValue('F1', '1era semana');
                $sheet->setCellValue('H1', '2da semana');
                $sheet->setCellValue('J1', '3era semana');
                $sheet->setCellValue('L1', '4ta semana');
                $sheet->setCellValue('N1', 'Totales');

                $sheet->setCellValue('F2', $this->rangoSemana('s1'));
                $sheet->setCellValue('H2', $this->rangoSemana('s2'));
                $sheet->setCellValue('J2', $this->rangoSemana('s3'));
                $sheet->setCellValue('L2', $this->rangoSemana('s4'));

                // Merge week group headers + date ranges
                $sheet->mergeCells('F1:G1');
                $sheet->mergeCells('H1:I1');
                $sheet->mergeCells('J1:K1');
                $sheet->mergeCells('L1:M1');
                $sheet->mergeCells('N1:O2');

                $sheet->mergeCells('F2:G2');
                $sheet->mergeCells('H2:I2');
                $sheet->mergeCells('J2:K2');
                $sheet->mergeCells('L2:M2');

                $sheet->freezePane('F4');
                $sheet->setAutoFilter("A3:O{$lastRow}");
                $sheet->setShowGridlines(false);

                $sheet->getRowDimension(1)->setRowHeight(20);
                $sheet->getRowDimension(2)->setRowHeight(18);
                $sheet->getRowDimension(3)->setRowHeight(18);

                $borderAll = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ];

                $center = [
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ];

                $sheet->getStyle("A1:O{$lastRow}")->applyFromArray($borderAll);

                // Base header (row 3)
                $sheet->getStyle('A3:E3')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['argb' => 'FFD9D9D9'],
                    ],
                ]);

                $sheet->getStyle('F3:G3')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFF4CCCC']],
                ]);
                $sheet->getStyle('H3:I3')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFFCE4D6']],
                ]);
                $sheet->getStyle('J3:K3')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFC6EFCE']],
                ]);
                $sheet->getStyle('L3:M3')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFE4C1F9']],
                ]);
                $sheet->getStyle('N3:O3')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFD9D9D9']],
                ]);

                // Group header (row 1)
                $sheet->getStyle('F1:G1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFC00000']],
                ]);
                $sheet->getStyle('H1:I1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFED7D31']],
                ]);
                $sheet->getStyle('J1:K1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF00B050']],
                ]);
                $sheet->getStyle('L1:M1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF7030A0']],
                ]);
                $sheet->getStyle('N1:O2')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF595959']],
                ]);

                // Date row (row 2)
                $sheet->getStyle('F2:G2')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFFF0000']],
                ]);
                $sheet->getStyle('H2:I2')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFF4B084']],
                ]);
                $sheet->getStyle('J2:K2')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF92D050']],
                ]);
                $sheet->getStyle('L2:M2')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFB57EDC']],
                ]);

                $sheet->getStyle('A1:O3')->applyFromArray($center);
                $sheet->getStyle("A4:O{$lastRow}")->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Column background for data area
                if ($lastRow >= 4) {
                    $sheet->getStyle("F4:G{$lastRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFFFC7CE']],
                    ]);
                    $sheet->getStyle("H4:I{$lastRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFFFD9B3']],
                    ]);
                    $sheet->getStyle("J4:K{$lastRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFC6EFCE']],
                    ]);
                    $sheet->getStyle("L4:M{$lastRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFEAD1F5']],
                    ]);
                    $sheet->getStyle("N4:O{$lastRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFE6E6E6']],
                    ]);
                }

                // Left columns text alignment
                $sheet->getStyle("A4:E{$lastRow}")->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
            },
        ];
    }
}
