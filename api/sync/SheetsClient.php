<?php

class SheetsClient {
    private ?string $serviceAccountPath;

    public function __construct(?string $serviceAccountPath = null) {
        $this->serviceAccountPath = $serviceAccountPath ?: (__DIR__ . '/../config/google_credentials.json');
    }

    /**
     * Fetch values from a Google Sheet tab
     *
     * @param string $spreadsheetId The Google Sheet ID
     * @param string $tabName The sheet tab name (e.g. Sheet1)
     * @return array 2D array of rows
     */
    public function getSheetValues(string $spreadsheetId, string $tabName = 'Sheet1'): array {
        // 1. If Google Service Account exists, generate OAuth2 Bearer token
        if (file_exists($this->serviceAccountPath)) {
            $token = $this->getServiceAccountAccessToken();
            if ($token) {
                return $this->fetchViaSheetsApi($spreadsheetId, $tabName, $token);
            }
        }

        // 2. Fallback: Direct CSV export endpoint (works if sheet is shared with Viewer permissions)
        return $this->fetchViaCsvExport($spreadsheetId, $tabName);
    }

    private function fetchViaCsvExport(string $spreadsheetId, string $tabName): array {
        $encodedTab = urlencode($tabName);
        $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&sheet={$encodedTab}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TBDataMIS/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $csvData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($csvData)) {
            throw new Exception("Failed to fetch Google Sheet data (HTTP {$httpCode}). Ensure the sheet is shared as Viewer.");
        }

        $rows = [];
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csvData);
        rewind($stream);

        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function getServiceAccountAccessToken(): ?string {
        try {
            $creds = json_decode(file_get_contents($this->serviceAccountPath), true);
            if (!$creds || !isset($creds['private_key'], $creds['client_email'])) {
                return null;
            }

            $now = time();
            $header = ['alg' => 'RS256', 'typ' => 'JWT'];
            $claim = [
                'iss' => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now
            ];

            $b64Header = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
            $b64Claim = rtrim(strtr(base64_encode(json_encode($claim)), '+/', '-_'), '=');
            $signatureInput = $b64Header . '.' . $b64Claim;

            openssl_sign($signatureInput, $signature, $creds['private_key'], 'SHA256');
            $b64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            $jwt = $signatureInput . '.' . $b64Signature;

            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]),
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $res = json_decode(curl_exec($ch), true);
            curl_close($ch);

            return $res['access_token'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function fetchViaSheetsApi(string $spreadsheetId, string $tabName, string $accessToken): array {
        $encodedRange = urlencode("{$tabName}!A1:ZZ");
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$encodedRange}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$accessToken}"],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $res = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !isset($res['values'])) {
            throw new Exception("Google Sheets API error (HTTP {$httpCode}): " . ($res['error']['message'] ?? 'Unknown error'));
        }

        return $res['values'];
    }
}
