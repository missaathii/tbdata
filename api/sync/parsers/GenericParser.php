<?php
require_once __DIR__ . '/ParserInterface.php';

class GenericParser implements ParserInterface {
    public function parse(array $rows, array $sheetRegistry): array {
        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map('trim', $rows[0]);
        $parsed = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) {
                continue;
            }

            $rawStr = implode('|', $row);
            $rowHash = hash('sha256', $rawStr);

            $assocData = [];
            foreach ($headers as $idx => $header) {
                $colKey = !empty($header) ? $header : "col_{$idx}";
                $assocData[$colKey] = $row[$idx] ?? '';
            }

            $state = $sheetRegistry['state_name'];
            $district = null;

            // Attempt to auto-detect district from first few columns
            foreach ($assocData as $k => $v) {
                $lk = strtolower($k);
                if (str_contains($lk, 'district') && !empty($v)) {
                    $district = trim((string)$v);
                    break;
                }
            }

            $parsed[] = [
                'sheet_registry_id' => $sheetRegistry['id'],
                'tracker_type_id'   => $sheetRegistry['tracker_type_id'],
                'row_hash'          => $rowHash,
                'row_number'        => $i + 1,
                'state'             => $state,
                'district'          => $district,
                'raw_data'          => json_encode($assocData, JSON_UNESCAPED_UNICODE),
            ];
        }

        return $parsed;
    }
}
