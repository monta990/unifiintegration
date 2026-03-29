<?php
/**
 * UniFi Integration — inc/api.class.php
 *
 * HTTP client for the UniFi Site Manager Cloud API.
 * Base URL: https://api.ui.com/ea/
 * Auth:     X-API-KEY header
 * Limit:    100 req/min (read-only)
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginUnifiintegrationApi
{
    private const BASE_URL = 'https://api.ui.com/ea/';
    private const TIMEOUT  = 15;

    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    // --------------------------------------------------------------------------
    // Public endpoints
    // --------------------------------------------------------------------------

    /**
     * GET /ea/hosts — all consoles/gateways linked to the account
     */
    public function getHosts(): array
    {
        return $this->get('hosts');
    }

    /**
     * GET /ea/hosts/{id}
     */
    public function getHost(string $id): array
    {
        return $this->get('hosts/' . urlencode($id));
    }

    /**
     * GET /ea/sites — all UniFi sites
     */
    public function getSites(): array
    {
        return $this->get('sites');
    }

    /**
     * GET /ea/devices — all devices across all hosts
     * Optional: filter by hostIds (array) and/or updatedAfter (ISO-8601 string)
     */
    public function getDevices(array $hostIds = [], string $updatedAfter = ''): array
    {
        $params = [];
        if ($hostIds) {
            // API accepts repeated hostId[] params
            $params['hostId'] = $hostIds;
        }
        if ($updatedAfter) {
            $params['updatedAfter'] = $updatedAfter;
        }
        return $this->get('devices', $params);
    }

    // --------------------------------------------------------------------------
    // Connection test — returns ['success'=>true,'hosts'=>N] or ['success'=>false,'error'=>'...']
    // --------------------------------------------------------------------------
    public function testConnection(): array
    {
        try {
            $data = $this->getHosts();
            $count = is_array($data['data'] ?? null) ? count($data['data']) : 0;
            return ['success' => true, 'hosts' => $count];
        } catch (RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // --------------------------------------------------------------------------
    // HTTP helpers
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

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'X-API-KEY: ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
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

        if ($code === 401 || $code === 403) {
            throw new RuntimeException(
                __('Authentication failed. Check your API Key.', 'unifiintegration')
            );
        }

        if ($code < 200 || $code >= 300) {
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

    /**
     * Build query string supporting array values (repeated keys).
     */
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
