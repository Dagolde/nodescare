<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Excel Export Service — generates .xlsx files using native PHP (no extra packages).
 */
class ExcelExportService
{
    /**
     * Generate Excel file for a report.
     */
    public function generateReportExcel(string $reportType, array $data, array $metadata): Response
    {
        try {
            [$headers, $rows, $filename] = match ($reportType) {
                'daily_summary'      => $this->buildDailySummary($data),
                'staff_performance'  => $this->buildStaffPerformance($data),
                'efficiency_metrics' => $this->buildEfficiencyMetrics($data),
                default              => $this->buildGeneric($data, $reportType),
            };

            $xlsx = $this->buildXlsx($headers, $rows);

            return response($xlsx, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Content-Length'      => strlen($xlsx),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Excel export', [
                'report_type' => $reportType,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ─── Data builders ────────────────────────────────────────────────────────

    private function buildDailySummary(array $data): array
    {
        $headers = ['Patient Name', 'Patient Number', 'Arrival', 'Departure', 'Wait Time (min)', 'Service Time (min)', 'Outcome'];
        $rows = [];
        foreach ($data['patient_list'] ?? [] as $p) {
            $rows[] = [
                $p['patient_name'] ?? '',
                $p['patient_number'] ?? '',
                $p['time_of_arrival'] ?? '',
                $p['time_of_departure'] ?? '',
                $p['wait_time'] ?? '',
                $p['service_time'] ?? '',
                $p['outcome'] ?? '',
            ];
        }
        $dept = str_replace(' ', '_', $data['department_name'] ?? 'dept');
        $date = $data['date'] ?? now()->format('Y-m-d');
        return [$headers, $rows, "daily_summary_{$dept}_{$date}.xlsx"];
    }

    private function buildStaffPerformance(array $data): array
    {
        $headers = ['Staff Name', 'Role', 'Patients Handled', 'Avg Service Time (min)', 'Notes Added'];
        $rows = [];
        foreach ($data['staff_metrics'] ?? [] as $s) {
            $rows[] = [
                $s['staff_name'] ?? '',
                $s['role'] ?? '',
                $s['patients_handled'] ?? 0,
                $s['average_service_time'] ?? 0,
                $s['notes_added'] ?? 0,
            ];
        }
        $dept  = str_replace(' ', '_', $data['department_name'] ?? 'dept');
        $range = ($data['start_date'] ?? '') . '_to_' . ($data['end_date'] ?? '');
        return [$headers, $rows, "staff_performance_{$dept}_{$range}.xlsx"];
    }

    private function buildEfficiencyMetrics(array $data): array
    {
        $headers = ['Metric', 'Value', 'Trend', 'Change vs Previous Period'];
        $metrics = $data['metrics'] ?? [];
        $trends  = $data['trend_indicators'] ?? [];
        $rows = [
            ['Average Wait Time',    ($metrics['average_wait_time'] ?? 0) . ' min',  $trends['average_service_time']['direction'] ?? 'N/A', ($trends['average_service_time']['percentage_change'] ?? 0) . '%'],
            ['Average Service Time', ($metrics['average_service_time'] ?? 0) . ' min', $trends['average_service_time']['direction'] ?? 'N/A', ($trends['average_service_time']['percentage_change'] ?? 0) . '%'],
            ['Patient Throughput',   ($metrics['patient_throughput'] ?? 0) . '/day',  $trends['throughput']['direction'] ?? 'N/A',           ($trends['throughput']['percentage_change'] ?? 0) . '%'],
            ['Staff Utilization',    ($metrics['staff_utilization'] ?? 0) . '%',      $trends['staff_utilization']['direction'] ?? 'N/A',    ($trends['staff_utilization']['percentage_change'] ?? 0) . '%'],
        ];
        $dept  = str_replace(' ', '_', $data['department_name'] ?? 'dept');
        $range = ($data['start_date'] ?? '') . '_to_' . ($data['end_date'] ?? '');
        return [$headers, $rows, "efficiency_metrics_{$dept}_{$range}.xlsx"];
    }

    private function buildGeneric(array $data, string $reportType): array
    {
        $headers = array_keys($data[0] ?? []);
        $rows    = array_map('array_values', $data);
        return [$headers, $rows, "{$reportType}_" . now()->format('Y-m-d') . ".xlsx"];
    }

    // ─── XLSX builder (no external packages) ─────────────────────────────────

    /**
     * Build a minimal but valid .xlsx binary string.
     */
    private function buildXlsx(array $headers, array $rows): string
    {
        // Build worksheet XML
        $sharedStrings = [];
        $ssIndex = 0;

        $getStringIndex = function (string $value) use (&$sharedStrings, &$ssIndex): int {
            if (!isset($sharedStrings[$value])) {
                $sharedStrings[$value] = $ssIndex++;
            }
            return $sharedStrings[$value];
        };

        $sheetRows = '';
        $rowNum = 1;

        // Header row
        $sheetRows .= "<row r=\"{$rowNum}\">";
        foreach ($headers as $colIdx => $header) {
            $col = $this->colLetter($colIdx);
            $si  = $getStringIndex((string) $header);
            $sheetRows .= "<c r=\"{$col}{$rowNum}\" t=\"s\"><v>{$si}</v></c>";
        }
        $sheetRows .= '</row>';
        $rowNum++;

        // Data rows
        foreach ($rows as $row) {
            $sheetRows .= "<row r=\"{$rowNum}\">";
            foreach (array_values($row) as $colIdx => $cell) {
                $col = $this->colLetter($colIdx);
                if (is_numeric($cell)) {
                    $sheetRows .= "<c r=\"{$col}{$rowNum}\"><v>" . htmlspecialchars((string) $cell, ENT_XML1) . "</v></c>";
                } else {
                    $si = $getStringIndex((string) $cell);
                    $sheetRows .= "<c r=\"{$col}{$rowNum}\" t=\"s\"><v>{$si}</v></c>";
                }
            }
            $sheetRows .= '</row>';
            $rowNum++;
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '</worksheet>';

        // Shared strings XML
        $ssCount = count($sharedStrings);
        $ssXml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . "<sst xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" count=\"{$ssCount}\" uniqueCount=\"{$ssCount}\">";
        foreach (array_keys($sharedStrings) as $str) {
            $ssXml .= '<si><t>' . htmlspecialchars($str, ENT_XML1) . '</t></si>';
        }
        $ssXml .= '</sst>';

        // Workbook XML
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        // Relationships
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>';

        // Build ZIP in memory
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',              $contentTypes);
        $zip->addFromString('_rels/.rels',                      $rootRels);
        $zip->addFromString('xl/workbook.xml',                  $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels',       $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml',         $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml',             $ssXml);
        $zip->close();

        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        return $content;
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index  = intdiv($index, 26);
        }
        return $letter;
    }
}
