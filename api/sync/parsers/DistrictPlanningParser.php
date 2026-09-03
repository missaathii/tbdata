<?php
require_once __DIR__ . '/ParserInterface.php';

class DistrictPlanningParser implements ParserInterface {
    public function parse(array $rows, array $sheetRegistry): array {
        if (count($rows) < 2) {
            return [];
        }

        $parsed = [];
        $headers = array_map('trim', $rows[0]);

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) {
                continue;
            }

            $rawStr = implode('|', $row);
            $rowHash = hash('sha256', $rawStr);

            $rowData = [];
            foreach ($headers as $idx => $header) {
                if ($header !== '') {
                    $rowData[$header] = $row[$idx] ?? '';
                }
            }

            $parsed[] = [
                'sheet_registry_id' => $sheetRegistry['id'],
                'row_hash'          => $rowHash,
                'row_number'        => $i + 1,
                'section_tab'       => $sheetRegistry['sheet_tab_name'] ?? 'Planning',
                'state'             => $sheetRegistry['state_name'],
                'district'          => $row[1] ?? ($row[0] ?? ''),
                'block_name'        => $row[2] ?? '',
                'indicator_name'    => $row[3] ?? '',
                'target_value'      => (string)($row[4] ?? ''),
                'achievement_value' => (string)($row[5] ?? ''),
                'raw_json'          => json_encode($rowData, JSON_UNESCAPED_UNICODE),
            ];
        }

        return $parsed;
    }
}
