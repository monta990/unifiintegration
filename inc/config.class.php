<?php
/**
 * UniFi Integration — inc/config.class.php
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginUnifiintegrationConfig extends CommonGLPI
{
    public static $rightname = 'plugin_unifiintegration_config';

    // --------------------------------------------------------------------------
    // Load / Save
    // --------------------------------------------------------------------------
    public static function getConfig(): array
    {
        global $DB;

        $iter = $DB->request([
            'FROM'  => 'glpi_plugin_unifiintegration_configs',
            'WHERE' => ['id' => 1],
            'LIMIT' => 1,
        ]);

        return $iter->count() ? $iter->current() : [];
    }

    public static function saveConfig(array $input): bool
    {
        global $DB;

        $cfg = self::getConfig();
        if (empty($cfg)) {
            return $DB->insert('glpi_plugin_unifiintegration_configs', array_merge(['id' => 1], $input));
        }

        return $DB->update('glpi_plugin_unifiintegration_configs', $input, ['id' => 1]);
    }

    // --------------------------------------------------------------------------
    // Display form (called from front/config.form.php)
    // --------------------------------------------------------------------------
    public function showConfigForm(): void
    {
        global $CFG_GLPI;

        $cfg = self::getConfig();

        // ── CSRF token ─────────────────────────────────────────────────────
        $csrf = Html::getCsrfToken();

        $api_key       = htmlspecialchars($cfg['api_key']       ?? '', ENT_QUOTES);
        $sync_devices  = (int)($cfg['sync_devices']  ?? 1);
        $sync_sites    = (int)($cfg['sync_sites']    ?? 1);
        $sync_hosts    = (int)($cfg['sync_hosts']    ?? 1);
        $cron_interval = (int)($cfg['cron_interval'] ?? 600);
        $last_sync     = $cfg['last_sync']     ?? null;
        $last_status   = $cfg['last_sync_status']  ?? null;
        $last_msg      = htmlspecialchars($cfg['last_sync_message'] ?? '', ENT_QUOTES);

        $rootdoc = $CFG_GLPI['root_doc'];

        // cron interval options
        $intervals = [
            300  => __('5 minutes', 'unifiintegration'),
            600  => __('10 minutes', 'unifiintegration'),
            900  => __('15 minutes', 'unifiintegration'),
            1800 => __('30 minutes', 'unifiintegration'),
            3600 => __('1 hour', 'unifiintegration'),
        ];

        echo <<<HTML
<div class="container-lg px-3 py-3">
  <div class="card mb-3">
    <div class="card-header d-flex align-items-center gap-2">
      <i class="ti ti-settings fs-4 text-primary"></i>
      <span class="fw-bold fs-5">{$this->t('UniFi Integration — Configuration')}</span>
    </div>
    <div class="card-body">
      <form method="post" action="{$rootdoc}/plugins/unifiintegration/front/config.form.php"
            id="unifi-config-form">
        <input type="hidden" name="_glpi_csrf_token" value="{$csrf}">
        <input type="hidden" name="save_config" value="1">

        <!-- API Key -->
        <div class="mb-3">
          <label class="form-label fw-bold">
            <i class="ti ti-key me-1"></i>{$this->t('API Key')}
          </label>
          <div class="input-group">
            <input type="password" id="api_key" name="api_key"
                   class="form-control font-monospace"
                   value="{$api_key}"
                   placeholder="{$this->t('Paste your UniFi Site Manager API Key')}"
                   autocomplete="new-password">
            <button type="button" class="btn btn-outline-secondary" id="toggleKey"
                    title="{$this->t('Show / Hide')}">
              <i class="ti ti-eye" id="eyeIcon"></i>
            </button>
          </div>
          <div class="form-text small text-muted mt-1">
            <i class="ti ti-info-circle me-1"></i>
            {$this->t('Generate at')} <a href="https://unifi.ui.com" target="_blank">unifi.ui.com</a>
            → API → Create API Key. {$this->t('The key is read-only at api.ui.com (100 req/min).')}
          </div>
        </div>

        <!-- Test connection -->
        <div class="mb-4">
          <button type="button" class="btn btn-outline-primary btn-sm" id="btn-test-api">
            <i class="ti ti-plug me-1"></i>{$this->t('Test connection')}
          </button>
          <span id="test-result" class="ms-2 small"></span>
        </div>

        <hr>

        <!-- Sync options -->
        <div class="mb-3">
          <label class="form-label fw-bold">
            <i class="ti ti-refresh me-1"></i>{$this->t('Synchronization options')}
          </label>
          <div class="d-flex flex-column gap-2 ms-2">
HTML;

        $chk = function(string $name, string $label, int $val) {
            $checked = $val ? 'checked' : '';
            return <<<HTML
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="{$name}" id="{$name}"
                     value="1" {$checked}>
              <label class="form-check-label" for="{$name}">{$label}</label>
            </div>
HTML;
        };

        echo $chk('sync_devices', __('Sync devices (APs, switches, routers) → NetworkEquipment', 'unifiintegration'), $sync_devices);
        echo $chk('sync_sites',   __('Sync sites → Location', 'unifiintegration'), $sync_sites);
        echo $chk('sync_hosts',   __('Sync hosts/consoles (UDM, UCG…) → NetworkEquipment', 'unifiintegration'), $sync_hosts);

        echo <<<HTML
          </div>
        </div>

        <!-- Cron interval -->
        <div class="mb-4 col-md-4">
          <label class="form-label fw-bold" for="cron_interval">
            <i class="ti ti-clock me-1"></i>{$this->t('Sync interval (cron)')}
          </label>
          <select name="cron_interval" id="cron_interval" class="form-select form-select-sm">
HTML;
        foreach ($intervals as $sec => $lbl) {
            $sel = ($sec === $cron_interval) ? 'selected' : '';
            echo "<option value=\"{$sec}\" {$sel}>{$lbl}</option>";
        }

        echo <<<HTML
          </select>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy me-1"></i>{$this->t('Save')}
          </button>
        </div>
      </form>
    </div>
  </div>

HTML;

        // Last sync card
        if ($last_sync) {
            $statusClass = match ($last_status) {
                'success' => 'text-success',
                'error'   => 'text-danger',
                default   => 'text-secondary',
            };
            $statusIcon = match ($last_status) {
                'success' => 'ti-circle-check',
                'error'   => 'ti-alert-circle',
                default   => 'ti-clock',
            };
            echo <<<HTML
  <div class="card mb-3">
    <div class="card-header fw-bold">
      <i class="ti ti-history me-1"></i>{$this->t('Last synchronization')}
    </div>
    <div class="card-body">
      <p class="mb-1">
        <i class="ti {$statusIcon} {$statusClass} me-1"></i>
        <strong>{$last_sync}</strong> &nbsp;—&nbsp;
        <span class="{$statusClass}">{$last_status}</span>
      </p>
      <p class="mb-0 text-muted small">{$last_msg}</p>
    </div>
  </div>
HTML;
        }

        echo '</div>'; // container

        $rootdoc_js  = addslashes($rootdoc);
        $t_testing   = __('Testing…', 'unifiintegration');
        $t_ok        = __('Connection OK', 'unifiintegration');
        $t_fail      = __('Connection failed', 'unifiintegration');

        Html::scriptBlock(<<<JS
(function(){
  // Toggle API key visibility
  document.getElementById('toggleKey').addEventListener('click', function(){
    const inp  = document.getElementById('api_key');
    const icon = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
      inp.type = 'text'; icon.className = 'ti ti-eye-off';
    } else {
      inp.type = 'password'; icon.className = 'ti ti-eye';
    }
  });

  // Test connection
  document.getElementById('btn-test-api').addEventListener('click', function(){
    const key    = document.getElementById('api_key').value.trim();
    const result = document.getElementById('test-result');
    if (!key) { result.textContent = '— no key —'; return; }
    result.textContent = '{$t_testing}';
    result.className = 'ms-2 small text-secondary';

    fetch('{$rootdoc_js}/plugins/unifiintegration/front/ajax.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=test_connection&api_key=' + encodeURIComponent(key)
            + '&_glpi_csrf_token=' + encodeURIComponent(
                document.querySelector('input[name="_glpi_csrf_token"]').value)
    })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        result.textContent = '{$t_ok}' + (d.hosts ? ' (' + d.hosts + ' hosts)' : '');
        result.className = 'ms-2 small text-success fw-bold';
      } else {
        result.textContent = '{$t_fail}' + ': ' + (d.error || '');
        result.className = 'ms-2 small text-danger fw-bold';
      }
    })
    .catch(() => {
      result.textContent = '{$t_fail}';
      result.className = 'ms-2 small text-danger';
    });
  });
})();
JS);
    }

    // simple translation helper
    private function t(string $str): string
    {
        return __($str, 'unifiintegration');
    }
}
