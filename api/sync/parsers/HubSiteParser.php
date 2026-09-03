<?php
require_once __DIR__ . '/ParserInterface.php';

class HubSiteParser implements ParserInterface {
    public function parse(array $rows, array $sheetRegistry): array {
        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map('trim', $rows[0]);
        $parsed = [];

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
                'sheet_registry_id'     => $sheetRegistry['id'],
                'row_hash'              => $rowHash,
                'row_number'            => $i + 1,
                'state'                 => $getVal($row, ['State']) ?: $sheetRegistry['state_name'],
                'district'              => $getVal($row, ['District']),
                'tu_block'              => $getVal($row, ['TU / Block', 'TU', 'Block']),
                'facility_name'         => $getVal($row, ['Name of the Facility', 'Facility Name']),
                'facility_type'         => $getVal($row, ['Type of Facility', 'Facility Type']),
                'is_child_referred'     => $getVal($row, ['Was child referred', 'Referred']),
                'referred_by'           => $getVal($row, ['Referred by whom', 'Referred by']),
                'qr_referral_id'        => $getVal($row, ['QR Code Referral ID', 'QR Referral ID']),
                'hub_site_referral_id'  => $getVal($row, ['Hub Site Referral ID', 'Hub Referral ID']),
                'child_name'            => $getVal($row, ['Name of the child', 'Child Name']),
                'age'                   => (int)$getVal($row, ['Age (in Years)', 'Age']),
                'gender'                => $getVal($row, ['Gender', 'Sex']),
                'parent_name'           => $getVal($row, ['Parent / Guardian Name', 'Parent Name']),
                'contact_number'        => $getVal($row, ['Contact number of parent', 'Phone', 'Contact']),
                'address'               => $getVal($row, ['Address']),
                'pr_tb_register_date'   => $parseDate($getVal($row, ['Date of entry in PR-TB register', 'Register Date'])),
                'nrc_admission_date'    => $parseDate($getVal($row, ['Date of admission in NRC', 'NRC Date'])),
                'symptoms'              => $getVal($row, ['Symptoms of TB', 'Symptoms']),
                'ep_tb_symptoms'        => $getVal($row, ['EP-TB Symptoms']),
                'whatsapp_expert_consulted' => $getVal($row, ['WhatsApp expert consulted', 'Expert Consulted']),
                'test_type'             => $getVal($row, ['Test recommended', 'Test Type']),
                'cxr_date'              => $parseDate($getVal($row, ['Date of CXR', 'CXR Date'])),
                'cxr_location'          => $getVal($row, ['Location of CXR', 'CXR Facility']),
                'cxr_expert_interpreted'=> $getVal($row, ['Expert interpreted CXR', 'CXR Interpreted']),
                'cxr_result_available'  => $getVal($row, ['CXR result available']),
                'cxr_result'            => $getVal($row, ['Result of CXR', 'CXR Result']),
                'cxr_findings'          => $getVal($row, ['CXR findings detail']),
                'sample_collection'     => $getVal($row, ['Sample collection status']),
                'sample_type'           => $getVal($row, ['Type of sample collected', 'Sample Type']),
                'sample_collection_date'=> $parseDate($getVal($row, ['Date of sample collection', 'Sample Date'])),
                'microscopy_date'       => $parseDate($getVal($row, ['Date of Smear Microscopy', 'Microscopy Date'])),
                'microscopy_result'     => $getVal($row, ['Result of Smear Microscopy', 'Microscopy Result']),
                'naat_date'             => $parseDate($getVal($row, ['Date of NAAT (CBNAAT/Truenat)', 'NAAT Date'])),
                'naat_interpretation'   => $getVal($row, ['Interpretation of NAAT', 'NAAT Result']),
                'other_tests'           => $getVal($row, ['Other investigations performed']),
                'other_test_date'       => $parseDate($getVal($row, ['Date of other test'])),
                'other_test_result'     => $getVal($row, ['Result of other test']),
                'referred_facility'     => $getVal($row, ['Referred to which facility']),
                'tb_diagnosis'          => $getVal($row, ['Final Diagnosis of TB', 'TB Diagnosis']),
                'diagnosis_date'        => $parseDate($getVal($row, ['Date of diagnosis', 'Diagnosis Date'])),
                'treatment_date'        => $parseDate($getVal($row, ['Date of treatment initiation', 'Treatment Date'])),
                'episode_id'            => $getVal($row, ['Nikshay Episode ID', 'Episode ID']),
                'earliest_date'         => $parseDate($getVal($row, ['Earliest date of visit'])),
                'remarks'               => $getVal($row, ['Remarks / Follow-up notes', 'Remarks']),
            ];
        }

        return $parsed;
    }
}
