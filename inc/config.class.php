<?php
/**
 * UniFi Integration — PluginUnifiintegrationConfig
 *
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginUnifiintegrationConfig extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_unifiintegration_configs';
    }

    public static function getTypeName($nb = 0): string
    {
        return __('UniFi Configuration', 'unifiintegration');
    }

    // ── Load ──────────────────────────────────────────────────────────────
    public static function getConfig(): array
    {
        global $DB;
        $iter = $DB->request(['FROM' => 'glpi_plugin_unifiintegration_configs', 'WHERE' => ['id' => 1], 'LIMIT' => 1]);
        if (!$iter->count()) {
            return [];
        }
        $c = $iter->current();
        if (!empty($c['api_key'])) {
            $c['api_key'] = PluginUnifiintegrationUtils::decrypt($c['api_key']);
        }
        return $c;
    }

    // ── Save ──────────────────────────────────────────────────────────────
    public static function saveConfig(array $input): bool
    {
        global $DB;

        // Encrypt API key — if empty, preserve existing
        if (!empty($input['api_key'])) {
            $input['api_key'] = PluginUnifiintegrationUtils::encrypt($input['api_key']);
        } else {
            unset($input['api_key']);
        }

        if (isset($input['cron_interval'])) {
            $input['cron_interval'] = max(300, (int)$input['cron_interval']);
        }

        $existing = $DB->request(['FROM' => 'glpi_plugin_unifiintegration_configs', 'WHERE' => ['id' => 1], 'LIMIT' => 1]);
        if ($existing->count()) {
            return $DB->update('glpi_plugin_unifiintegration_configs', $input, ['id' => 1]);
        }
        $input['id'] = 1;
        return $DB->insert('glpi_plugin_unifiintegration_configs', $input);
    }

    // ── Config form ───────────────────────────────────────────────────────
    public function showConfigForm(): void
    {
        global $CFG_GLPI;

        $cfg             = self::getConfig();
        $has_key         = !empty($cfg['api_key']);
        $debug           = (int)($cfg['debug_logging']    ?? 0);
        $sync_devices    = (int)($cfg['sync_devices']     ?? 1);
        $sync_sites      = (int)($cfg['sync_sites']       ?? 1);
        $sync_hosts      = (int)($cfg['sync_hosts']       ?? 1);
        $interval        = (int)($cfg['cron_interval']    ?? 600);
        $refresh_interval= max(60, (int)($cfg['refresh_interval'] ?? 600));

        // helper: password/key field with show/hide
        $pwField = function(string $name, string $placeholder, bool $saved) {
            $ph  = htmlspecialchars($saved
                ? __('Saved — leave empty to keep', 'unifiintegration')
                : $placeholder, ENT_QUOTES, 'UTF-8');
            $uid = 'unifi_pw_' . $name;
            return "<div class='input-group'>"
                 . "<input type='password' class='form-control unifi-pw font-monospace' name='{$name}' id='{$uid}'"
                 . " autocomplete='new-password' placeholder='{$ph}'>"
                 . "<button type='button' class='btn btn-outline-secondary unifi-pw-toggle'"
                 . " data-target='{$uid}' tabindex='-1'>"
                 . "<i class='ti ti-eye'></i></button>"
                 . "</div>";
        };

        $intervals = [
            300  => __('5 minutes', 'unifiintegration'),
            600  => __('10 minutes', 'unifiintegration'),
            900  => __('15 minutes', 'unifiintegration'),
            1800 => __('30 minutes', 'unifiintegration'),
            3600 => __('1 hour', 'unifiintegration'),
        ];
        ?>
<div class="container-fluid px-4 mt-3">
<form method="post" action="">

  <!-- API Key card -->
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <i class="ti ti-key"></i>
      <h5 class="mb-0"><?= __('UniFi Site Manager — API Key', 'unifiintegration') ?></h5>
      <?php if ($has_key): ?>
        <span class="badge bg-success ms-auto"><i class="ti ti-check-circle me-1"></i><?= __('Configured', 'unifiintegration') ?></span>
      <?php else: ?>
        <span class="badge bg-warning text-dark ms-auto"><i class="ti ti-alert-triangle me-1"></i><?= __('Not configured', 'unifiintegration') ?></span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="alert alert-info d-flex gap-2 align-items-start mb-3">
        <i class="ti ti-info-circle mt-1 flex-shrink-0"></i>
        <span>
          <?= __('Generate your API Key at', 'unifiintegration') ?>
          <a href="https://unifi.ui.com" target="_blank" rel="noopener">unifi.ui.com</a>
          → API → Create API Key.
          <?= __('The key is read-only (100 req/min). It will only be shown once — store it securely.', 'unifiintegration') ?>
        </span>
      </div>
      <div class="row g-3 align-items-end">
        <div class="col-md-6">
          <label class="form-label fw-semibold"><?= __('API Key', 'unifiintegration') ?></label>
          <?= $pwField('api_key', __('Paste your UniFi Site Manager API Key', 'unifiintegration'), $has_key) ?>
          <small class="text-muted d-block mt-1"><?= __('Stored encrypted in the database.', 'unifiintegration') ?></small>
        </div>
        <div class="col-md-6 d-flex align-items-center gap-3">
          <button type="button" class="btn btn-outline-primary" id="btn-test-api">
            <i class="ti ti-plug me-1"></i><?= __('Test connection', 'unifiintegration') ?>
          </button>
          <span id="test-result" class="small fw-bold"></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Sync options card -->
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <i class="ti ti-refresh"></i>
      <h5 class="mb-0"><?= __('Synchronization options', 'unifiintegration') ?></h5>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="sync_devices" id="sync_devices" value="1" <?= $sync_devices ? 'checked' : '' ?>>
            <label class="form-check-label" for="sync_devices"><?= __('Sync devices (APs, switches, routers) → NetworkEquipment', 'unifiintegration') ?></label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="sync_sites" id="sync_sites" value="1" <?= $sync_sites ? 'checked' : '' ?>>
            <label class="form-check-label" for="sync_sites"><?= __('Sync sites → Location', 'unifiintegration') ?></label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="sync_hosts" id="sync_hosts" value="1" <?= $sync_hosts ? 'checked' : '' ?>>
            <label class="form-check-label" for="sync_hosts"><?= __('Sync hosts/consoles (UDM, UCG…) → NetworkEquipment', 'unifiintegration') ?></label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Advanced card -->
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <i class="ti ti-adjustments"></i>
      <h5 class="mb-0"><?= __('Advanced', 'unifiintegration') ?></h5>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold"><?= __('Sync interval (cron)', 'unifiintegration') ?></label>
          <select name="cron_interval" class="form-select">
            <?php foreach ($intervals as $sec => $lbl): ?>
              <option value="<?= $sec ?>" <?= $sec === $interval ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted d-block mt-1"><?= __('How often the cron job synchronizes devices. Minimum 5 min.', 'unifiintegration') ?></small>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold"><?= __('Tablero — auto-actualización (segundos)', 'unifiintegration') ?></label>
          <input type="number" class="form-control" name="refresh_interval" min="60" max="3600" value="<?= $refresh_interval ?>">
          <small class="text-muted d-block mt-1"><?= __('How often the dashboard auto-syncs. Minimum 60s, default 600s (10 min).', 'unifiintegration') ?></small>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold d-block"><?= __('Debug logging', 'unifiintegration') ?></label>
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="debug_logging" id="debug_logging" value="1" <?= $debug ? 'checked' : '' ?>>
            <label class="form-check-label" for="debug_logging"><?= __('Verbose logging (debug mode)', 'unifiintegration') ?></label>
          </div>
          <small class="text-muted d-block mt-1"><?= __('Writes detailed API requests and responses to files/log/unifiintegration.log. Use only for troubleshooting.', 'unifiintegration') ?></small>
        </div>
      </div>
    </div>
  </div>

  <div class="mb-4 d-flex align-items-center gap-3 flex-wrap">
    <button type="submit" name="save_config" class="btn btn-primary px-4">
      <i class="ti ti-device-floppy me-1"></i><?= __('Save', 'unifiintegration') ?>
    </button>
    <?php if ($has_key): ?>
    <a href="/plugins/unifiintegration/front/dashboard.php" class="btn btn-outline-secondary px-4">
      <i class="ti ti-layout-dashboard me-2"></i><?= __('Go to Dashboard', 'unifiintegration') ?>
    </a>
    <?php endif; ?>
  </div>

  <?= Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) ?>
</form>
</div>
<script>
// Show/hide password fields
document.querySelectorAll('.unifi-pw-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var input = document.getElementById(this.dataset.target);
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        this.querySelector('i').className = input.type === 'password' ? 'ti ti-eye' : 'ti ti-eye-slash';
    });
});
// Test connection
document.getElementById('btn-test-api').addEventListener('click', function() {
    var keyInput = document.querySelector('input[name="api_key"]');
    var result   = document.getElementById('test-result');
    var key      = keyInput ? keyInput.value.trim() : '';
    if (!key) {
        result.textContent = '<?= addslashes(__('Enter an API Key first', 'unifiintegration')) ?>';
        result.className = 'ms-3 small fw-bold text-warning';
        return;
    }
    result.textContent = '<?= addslashes(__('Testing…', 'unifiintegration')) ?>';
    result.className = 'ms-3 small text-muted';
    var fd = new FormData();
    fd.append('action', 'test_connection');
    fd.append('api_key', key);
    var csrf = (typeof window.glpiGetNewCSRFToken === 'function')
        ? window.glpiGetNewCSRFToken()
        : (document.querySelector('meta[property="glpi:csrf_token"]') || {}).getAttribute('content') || '';
    fd.append('_glpi_csrf_token', csrf);
    fetch('/plugins/unifiintegration/front/ajax.php', {method: 'POST', credentials: 'same-origin', body: fd})
        .then(function(r){ return r.json(); })
        .then(function(d) {
            if (d.success) {
                result.textContent = '<?= addslashes(__('Connection OK', 'unifiintegration')) ?>' + (d.hosts ? ' (' + d.hosts + ' hosts)' : '');
                result.className = 'ms-3 small fw-bold text-success';
            } else {
                result.textContent = '<?= addslashes(__('Connection failed', 'unifiintegration')) ?>: ' + (d.error || '');
                result.className = 'ms-3 small fw-bold text-danger';
            }
        })
        .catch(function() {
            result.textContent = '<?= addslashes(__('Connection failed', 'unifiintegration')) ?>';
            result.className = 'ms-3 small fw-bold text-danger';
        });
});
</script>
        <?php
    }
}
