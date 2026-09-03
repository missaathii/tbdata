<?php
require_once __DIR__ . '/ParserInterface.php';

class DownTrainingParser implements ParserInterface {
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

        $getVal = function(array $row, array $aliases, $default = null) use ($colMap) {
            foreach ($aliases as $alias) {
                $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $alias));
                if (isset($colMap[$norm]) && isset($row[$colMap[$norm]])) {
                    $v = trim((string)$row[$colMap[$norm]]);
                    return $v !== '' ? $v : $default;
                }
            }
            return $default;
        };

        $parseInt = function($val) {
            return is_numeric($val) ? (int)$val : 0;
        };

        $parseDate = function(?string $val) {
            if (!$val) return null;
            $ts = strtotime(str_replace('/', '-', $val));
            return $ts ? date('Y-m-d', $ts) : null;
        };

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) {
                continue;
            }

            $rawStr = implode('|', $row);
            $rowHash = hash('sha256', $rawStr);

            $parsed[] = [
                'sheet_registry_id'         => $sheetRegistry['id'],
                'row_hash'                  => $rowHash,
                'row_number'                => $i + 1,
                'state'                     => $getVal($row, ['State', 'State Name']) ?: $sheetRegistry['state_name'],
                'district'                  => $getVal($row, ['District', 'District Name']),
                'training_date'             => $parseDate($getVal($row, ['Date of sensitisation training', 'Date of training', 'Date'])),
                'attended_by_project_staff' => $getVal($row, ['Was the sensitisation attended by Project Staff', 'Attended by Staff']),
                'platform'                  => $getVal($row, ['Which platform this sensitization training conducted', 'Platform']),
                'training_level'            => $getVal($row, ['Level of sensitisation training conducted', 'Level']),
                'block_name'                => $getVal($row, ['Name of the block the staff belong to', 'Block', 'Block Name']),
                'tb_unit'                   => $getVal($row, ['TB Unit', 'TU']),
                'phi'                       => $getVal($row, ['PHI', 'Primary Health Institute']),
                'aam_phc_center'            => $getVal($row, ['AAMPHC centers as per HMIS', 'AAM PHC Centers']),
                'venue'                     => $getVal($row, ['Name of the centre venue Place of the sensitization training', 'Venue', 'Place']),
                'participant_types'         => $getVal($row, ['Type of participants who attended', 'Participant Types']),
                'no_phc_mo'                 => $parseInt($getVal($row, ['No of PHC MOs sensitized', 'PHC MOs'])),
                'no_rbsk_mo'                => $parseInt($getVal($row, ['No of RBSK MOs sensitized', 'RBSK MOs'])),
                'no_rbsk_nurse'             => $parseInt($getVal($row, ['No of RBSK nurse sensitized', 'RBSK Nurses'])),
                'no_rbsk_pharmacist'        => $parseInt($getVal($row, ['No of RBSK Pharmacist sensitized', 'RBSK Pharmacists'])),
                'no_cho'                    => $parseInt($getVal($row, ['No of CHOs sensitized', 'CHOs'])),
                'no_anm'                    => $parseInt($getVal($row, ['No of ANMs sensitized', 'ANMs'])),
                'no_bcpm'                   => $parseInt($getVal($row, ['No of BCPM sensitized', 'BCPM'])),
                'no_asha_supervisor'        => $parseInt($getVal($row, ['No of ASHA supervisor sensitized', 'ASHA Supervisors'])),
                'no_asha'                   => $parseInt($getVal($row, ['No of ASHAs sensitized', 'ASHAs'])),
                'no_cdpo'                   => $parseInt($getVal($row, ['No of CDPO sensitized', 'CDPOs'])),
                'no_aww_supervisor'         => $parseInt($getVal($row, ['No of AWW supervisor sensitized', 'AWW Supervisors'])),
                'no_aww'                    => $parseInt($getVal($row, ['No of AWWs sensitized', 'AWWs'])),
                'no_ngo_cbo'                => $parseInt($getVal($row, ['No of NGO CBO members sensitized', 'NGO CBO'])),
                'no_other'                  => $parseInt($getVal($row, ['No of Other Participants sensitized', 'Other Participants'])),
                'total_participants'        => $parseInt($getVal($row, ['No of paticipants attended', 'Total Participants', 'Total attended'])),
                'has_attendance_sheet'      => $getVal($row, ['Do you have a record of the attendance sheet', 'Attendance Sheet']),
                'upload_link'               => $getVal($row, ['If Yes Please upload', 'Upload Link']),
            ];
        }

        return $parsed;
    }
}
