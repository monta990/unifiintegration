<?php
/**
 * UniFi Integration — inc/api.class.php
 *
 * HTTP client for the UniFi Site Manager Cloud API v1.
 * Base URL: https://api.ui.com/v1/
 * Auth:     X-API-Key header (note: header is X-API-Key, not X-API-KEY)
 * Limit:    100 req/min (read-only)
 *
 * Endpoints:
 *   GET /v1/hosts                — list all consoles/gateways
 *   GET /v1/sites                — list all sites (paginated via nextToken)
 *   GET /v1/devices              — list all devices grouped by host (paginated)
 *
 * Response format: { "data": [...], "httpStatusCode": 200, "nextToken": "..." }
 * Sites:   data[].siteId, data[].hostId, data[].meta.name
 * Devices: data[].hostId, data[].hostName, data[].devices[].{id,mac,name,model,ip,...}
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginUnifiintegrationApi
{
    private const BASE_URL  = 'https://api.ui.com/v1/';
    private const TIMEOUT   = 20;
    private const PAGE_SIZE = 200;  // max devices per page

    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    // --------------------------------------------------------------------------
    // Public endpoints
    // --------------------------------------------------------------------------

    /**
     * GET /v1/hosts — all consoles/gateways linked to the account.
     * Returns full response array with 'data' key.
     */
    public function getHosts(): array
    {
        return $this->get('hosts');
    }

    /**
     * GET /v1/sites — all UniFi sites, auto-paginated.
     * Returns merged array of all site objects.
     */
    public function getAllSites(): array
    {
        $all       = [];
        $nextToken = null;

        do {
            $params = ['pageSize' => self::PAGE_SIZE];
            if ($nextToken) {
                $params['nextToken'] = $nextToken;
            }
            $resp      = $this->get('sites', $params);
            $page      = $resp['data'] ?? [];
            $all       = array_merge($all, $page);
            $nextToken = $resp['nextToken'] ?? null;

            PluginUnifiintegrationUtils::log(
                'API /v1/sites page: ' . count($page) . ' site(s)' .
                ($nextToken ? ' [more pages]' : ' [last page]'),
                true
            );
        } while ($nextToken);

        return $all;
    }

    /**
     * GET /v1/devices — all devices grouped by host, auto-paginated.
     * Returns flat array of host-group objects:
     *   [{ hostId, hostName, devices: [{id, mac, name, model, ip, status, firmwareStatus, version, isConsole, ...}] }]
     */
    public function getAllDevices(): array
    {
        $allGroups = [];
        $nextToken = null;
        $page_num  = 0;

        do {
            $page_num++;
            $params = ['pageSize' => self::PAGE_SIZE];
            if ($nextToken) {
                $params['nextToken'] = $nextToken;
            }

            $resp      = $this->get('devices', $params);
            $page      = $resp['data'] ?? [];
            $nextToken = $resp['nextToken'] ?? null;

            $deviceCount = 0;
            foreach ($page as $group) {
                $deviceCount += count($group['devices'] ?? []);
            }

            PluginUnifiintegrationUtils::log(
                "API /v1/devices page {$page_num}: " . count($page) . " host group(s), {$deviceCount} device(s)" .
                ($nextToken ? ' [more pages]' : ' [last page]')
            );

            $allGroups = array_merge($allGroups, $page);
        } while ($nextToken);

        return $allGroups;
    }

    // --------------------------------------------------------------------------
    // Connection test
    // --------------------------------------------------------------------------
    public function testConnection(): array
    {
        try {
            $data  = $this->getHosts();
            $count = is_array($data['data'] ?? null) ? count($data['data']) : 0;

            // Log host names for diagnostics
            foreach ($data['data'] ?? [] as $host) {
                $name = $host['userData']['name'] ?? ($host['reportedState']['hostname'] ?? $host['id'] ?? 'unknown');
                PluginUnifiintegrationUtils::log("  Host found: {$name} (id: " . ($host['id'] ?? '') . ")", true);
            }

            return ['success' => true, 'hosts' => $count];
        } catch (RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // --------------------------------------------------------------------------
    // HTTP GET with logging
    // --------------------------------------------------------------------------
    private function get(string $endpoint, array $query = []): array
    {
        if (!$this->apiKey) {
            throw new RuntimeException(__('API Key is not configured.', 'unifiintegration'));
        }

        $url = self::BASE_URL . $endpoint;
        if ($query) {
            $url .= '?' . $this->buildQuery($query);
        }

        PluginUnifiintegrationUtils::log("GET {$url}", true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'X-API-Key: ' . $this->apiKey,   // official header name
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'GLPI-UniFiIntegration/' . PLUGIN_UNIFIINTEGRATION_VERSION,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException(
                sprintf(__('cURL error: %s', 'unifiintegration'), $err)
            );
        }

        PluginUnifiintegrationUtils::log("HTTP {$code} ← {$endpoint}", true);

        if ($code === 401 || $code === 403) {
            throw new RuntimeException(
                __('Authentication failed. Check your API Key.', 'unifiintegration')
            );
        }

        if ($code === 429) {
            throw new RuntimeException(
                __('Rate limit exceeded (100 req/min). Try again later.', 'unifiintegration')
            );
        }

        if ($code < 200 || $code >= 300) {
            PluginUnifiintegrationUtils::log("Error response body: " . substr((string)$raw, 0, 500), true);
            throw new RuntimeException(
                sprintf(__('HTTP error %d from api.ui.com', 'unifiintegration'), $code)
            );
        }

        $json = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                sprintf(__('Invalid JSON response: %s', 'unifiintegration'), json_last_error_msg())
            );
        }

        return $json;
    }

    private function buildQuery(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = urlencode($key) . '[]=' . urlencode((string)$v);
                }
            } else {
                $parts[] = urlencode($key) . '=' . urlencode((string)$value);
            }
        }
        return implode('&', $parts);
    }
}
