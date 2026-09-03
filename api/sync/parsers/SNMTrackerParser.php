<?php
require_once __DIR__ . '/ParserInterface.php';

class SNMTrackerParser implements ParserInterface {
    public function parse(array $rows, array $sheetRegistry): array {
        if (count($rows) < 2) {
            return [];
        }

        $parsed = [];
        $headers = array_map('trim', $rows[0]);

        $colMap = [];
        foreach ($headers as $idx => $h) {
            $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $h));
            $colMap[$norm] = $idx;
        }

        $getVal = function(array $row, array $aliases, $default = 0) use ($colMap) {
            foreach ($aliases as $alias) {
                $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $alias));
                if (isset($colMap[$norm]) && isset($row[$colMap[$norm]])) {
                    $v = trim((string)$row[$colMap[$norm]]);
                    return is_numeric($v) ? (int)$v : $v;
                }
            }
            return $default;
        };

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) {
                continue;
            }

            $rawStr = implode('|', $row);
            $rowHash = hash('sha256', $rawStr);

            $parsed[] = [
                'sheet_registry_id'       => $sheetRegistry['id'],
                'row_hash'                => $rowHash,
                'state'                   => (string)$getVal($row, ['State', 'State Name'], $sheetRegistry['state_name']),
                'district'                => (string)$getVal($row, ['District', 'District Name'], ''),
                'report_month'            => date('Y-m-01'),
                'hub_site_count'          => (int)$getVal($row, ['Total Hub Sites', 'Hub Sites']),
                'oss_done_sites'          => (int)$getVal($row, ['OSS Done', 'OSS Sites']),
                'staff_trained_sites'     => (int)$getVal($row, ['Staff Trained Sites', 'Staff Trained']),
                'nrc_count'               => (int)$getVal($row, ['Total NRCs', 'NRC Count']),
                'nrc_staff_trained'       => (int)$getVal($row, ['NRC Staff Trained']),
                'nrc_doing_ga'            => (int)$getVal($row, ['NRC doing GA']),
                'sample_consumables_sites'=> (int)$getVal($row, ['Consumables Available Sites']),
                'sample_demo_sites'       => (int)$getVal($row, ['Sample Demo Done Sites']),
                'ga_initiated_sites'      => (int)$getVal($row, ['GA Initiated Sites']),
                'is_initiated_sites'      => (int)$getVal($row, ['IS Initiated Sites']),
                'week1_ga'                => (int)$getVal($row, ['Week 1 GA', 'W1 GA']),
                'week1_is'                => (int)$getVal($row, ['Week 1 IS', 'W1 IS']),
                'week2_ga'                => (int)$getVal($row, ['Week 2 GA', 'W2 GA']),
                'week2_is'                => (int)$getVal($row, ['Week 2 IS', 'W2 IS']),
                'week3_ga'                => (int)$getVal($row, ['Week 3 GA', 'W3 GA']),
                'week3_is'                => (int)$getVal($row, ['Week 3 IS', 'W3 IS']),
                'week4_ga'                => (int)$getVal($row, ['Week 4 GA', 'W4 GA']),
                'week4_is'                => (int)$getVal($row, ['Week 4 IS', 'W4 IS']),
                'week5_ga'                => (int)$getVal($row, ['Week 5 GA', 'W5 GA']),
                'week5_is'                => (int)$getVal($row, ['Week 5 IS', 'W5 IS']),
                'site_visits'             => (int)$getVal($row, ['Site Visits Done', 'Visits']),
            ];
        }

        return $parsed;
    }
}
