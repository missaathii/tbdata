<?php
require_once __DIR__ . '/ParserInterface.php';

class QRReferralParser implements ParserInterface {
    public function parse(array $rows, array $sheetRegistry): array {
        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map('trim', $rows[0]);
        $parsed = [];

        // Build header index lookup
        $colMap = [];
        foreach ($headers as $idx => $h) {
            $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $h));
            $colMap[$norm] = $idx;
        }

        $getVal = function(array $row, array $aliases) use ($colMap) {
            foreach ($aliases as $alias) {
                $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $alias));
                if (isset($colMap[$norm]) && isset($row[$colMap[$norm]])) {
                    return trim((string)$row[$colMap[$norm]]);
                }
            }
            return null;
        };

        $parseDate = function(?string $val) {
            if (!$val) return null;
            $ts = strtotime(str_replace('/', '-', $val));
            return $ts ? date('Y-m-d H:i:s', $ts) : null;
        };

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) {
                continue; // Skip empty rows
            }

            $rawStr = implode('|', $row);
            $rowHash = hash('sha256', $rawStr);

            $timestampStr = $getVal($row, ['Timestamp', 'Date']);
            $dt = $parseDate($timestampStr);

            $parsed[] = [
                'sheet_registry_id'   => $sheetRegistry['id'],
                'row_hash'            => $rowHash,
                'row_number'          => $i + 1,
                'timestamp'           => $dt,
                'state'               => $getVal($row, ['State', 'State Name']) ?: $sheetRegistry['state_name'],
                'district'            => $getVal($row, ['District', 'District Name']),
                'block_name'          => $getVal($row, ['Block', 'Name of the block']),
                'referring_facility'  => $getVal($row, ['Referring Facility', 'Name of the facility']),
                'staff_name'          => $getVal($row, ['Staff Name', 'Name of the staff']),
                'designation'         => $getVal($row, ['Designation']),
                'designation_other'   => $getVal($row, ['If Other Designation', 'Other']),
                'child_name'          => $getVal($row, ['Name of the child', 'Child Name', 'Patient Name']),
                'child_age'           => (int)$getVal($row, ['Age of the child', 'Age']),
                'symptoms'            => $getVal($row, ['Symptoms of the child', 'Symptoms']),
                'referred_to_facility'=> $getVal($row, ['Facility Referred to', 'Referred Facility']),
                'parent_contact'      => $getVal($row, ['Parent/Guardian contact number', 'Contact', 'Phone']),
                'facility_visit_type' => $getVal($row, ['Type of facility visited', 'Visit Type']),
                'facility_visit_date' => $parseDate($getVal($row, ['Date of facility visit', 'Visit Date'])),
                'referral_id'         => $getVal($row, ['Referral ID', 'Unique ID', 'ID']),
                'facility_visit_name' => $getVal($row, ['Name of the visited facility', 'Visited Facility']),
                'other_illness'       => $getVal($row, ['Other illness reported']),
                'remarks'             => $getVal($row, ['Remarks', 'Any other comments']),
            ];
        }

        return $parsed;
    }
}
