<?php

namespace App\Services;

class EquipmentFileParser
{
    private string $row1Pattern = '/
        ^\|
        (?P<material>\S+)\s+
        (?P<material_description>.+?)\s{2,}
        (?P<size_dimensions>\d+X[\dX]*)?\s*
        (?P<equipment>\d+)\s+
        (?P<stat1>[A-Z]{3,4})\s+
        (?P<stat2>[A-Z]{3,4})\s+
        (?P<location>\S+)\s+
        (?P<room>\S+)\s+
        (?P<tail>.+?)
        \s*\|?$
    /x';

    private string $row2Pattern = '/
        ^\|
        (?P<description_tech_object>.+?)\s{2,}
        (?P<size_dimensions2>\d+X[\dX]*)?\s*
        (?P<gross_weight>[\d.,]+\s+KG)\s+
        [A-Z]+\s+
        (?P<length>[\d.,]+)\s+
        (?P<width>[\d.,]+)\s+
        (?P<height>[\d.,]+)\s+
        (?P<Uni_MS_Plnt>DE\d{2})?\s*
        (?P<Plnt>DE\d{2})\s+
        (?P<Cost_Ctr>DE\d{4,})?\s*
        (?P<valid_from>\d{2}\.\d{2}\.\d{4})\s+
        (?P<valid_to>\d{2}\.\d{2}\.\d{4})\s+
        (?P<PP_WkCtr>\d{8})\s+
        (?P<Work_ctr>\S+)\s+
        (?P<WorkCtr>\d{8})
        \s*\|?$
    /x';

    private string $row3Pattern = '/
        ^\|\s*
        (?P<net_weight>[\d.,]+\s+KG)\s+
        (?P<old_material_no>\S+)\s+
        (?P<ms2>\S+)\s+
        (?P<pp2>\d*)\s*
        (?P<s>\S*)\s+
        (?P<created_on>\d{2}\.\d{2}\.\d{4})\s+
        (?P<created_by>\S+)\s+
        (?P<chngd_on>\d{2}\.\d{2}\.\d{4})\s+
        (?P<changed_by>\S+)\s+
        (?P<short_description>.+?)\s{2,}
        (?P<short_desc2>.+?)
        \s*\|?$
    /x';

    public function parse(string $filePath): array
    {
        $raw = file_get_contents($filePath);
        $cleaned = preg_replace('/([^|\-\n])\n([^|\-])/', '$1 $2', $raw);

        $dataLines = $this->extractDataLines(explode("\n", $cleaned));

        $records = [];

        for ($i = 0; $i < count($dataLines); $i += 3) {

            if (!isset($dataLines[$i+2])) {
                break;
            }

            $records[] = array_merge(
                $this->parseRow($dataLines[$i], $this->row1Pattern),
                $this->parseRow($dataLines[$i+1], $this->row2Pattern),
                $this->parseRow($dataLines[$i+2], $this->row3Pattern),
            );
        }

        return $records;
    }

    private function parseRow(string $line, string $pattern): array
    {
        if (preg_match($pattern, $line, $matches)) {
            return array_map('trim', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
        }

        return [];
    }

    private function extractDataLines(array $lines): array
    {
        $separatorCount = 0;
        $dataLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '|---')) {
                $separatorCount++;
                continue;
            }

            if ($separatorCount >= 2 && str_starts_with($trimmed, '|')) {
                $dataLines[] = $line;
            }
        }

        return $dataLines;
    }

    public function validate(string $filePath, int $maxErrorRatePercent = 5): array
    {
        if (!file_exists($filePath)) {
            return ['ok' => false, 'reason' => 'File not found'];
        }

        $raw = file_get_contents($filePath);

        // Basic header presence check (very cheap and effective)
        $mustContain = [
            '|Material',
            '|Description of Technical Object',
            '|Work ctr',
        ];

        foreach ($mustContain as $needle) {
            if (strpos($raw, $needle) === false) {
                return ['ok' => false, 'reason' => "Missing expected header: {$needle}"];
            }
        }

        // Reuse existing cleaning/extraction logic
        $cleaned = preg_replace('/([^|\-\n])\n([^|\-])/', '$1 $2', $raw);
        $dataLines = $this->extractDataLines(explode("\n", $cleaned));

        if (count($dataLines) === 0) {
            return ['ok' => false, 'reason' => 'No data lines found after headers'];
        }

        if (count($dataLines) % 3 !== 0) {
            return ['ok' => false, 'reason' => 'Data lines not divisible by 3 (records are 3 lines each)', 'data_lines' => count($dataLines)];
        }

        // Estimate parse error rate
        $totalRecords = intdiv(count($dataLines), 3);
        $errors = 0;

        for ($i = 0; $i < count($dataLines); $i += 3) {
            $r1 = $this->matchRow($dataLines[$i], $this->row1Pattern);
            $r2 = $this->matchRow($dataLines[$i + 1], $this->row2Pattern);
            $r3 = $this->matchRow($dataLines[$i + 2], $this->row3Pattern);

            // If any row fails in a record, count as an error
            if (!$r1 || !$r2 || !$r3) {
                $errors++;
            }
        }

        $errorRate = ($errors / max(1, $totalRecords)) * 100;

        if ($errorRate > $maxErrorRatePercent) {
            return [
                'ok' => false,
                'reason' => 'Too many parse errors',
                'total_records' => $totalRecords,
                'errors' => $errors,
                'error_rate_percent' => round($errorRate, 2),
            ];
        }

        return [
            'ok' => true,
            'total_records' => $totalRecords,
            'errors' => $errors,
            'error_rate_percent' => round($errorRate, 2),
        ];
    }

    private function matchRow(string $line, string $pattern): bool
    {
        return (bool) preg_match($pattern, $line);
    }
}
