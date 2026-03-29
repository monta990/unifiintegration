<?php
/**
 * UniFi Integration — front/dashboard.php
 * Author: Edwin Elias Alvarez
 * License: GPL v3+
 */
global $CFG_GLPI;

Session::checkLoginUser();
if (!Session::haveRight('config', READ)) {
    Html::forbidden();
    return;
}

Html::requireJs('charts');

Html::header(
    'UniFi — ' . __('Dashboard', 'unifiintegration'),
    '',
    'tools',
    'PluginUnifiintegrationMenu'
);

$cfg  = PluginUnifiintegrationConfig::getConfig();
$data = PluginUnifiintegrationSync::getDashboardStats();

$hasKey      = !empty($cfg['api_key']);
$rootdoc     = $CFG_GLPI['root_doc'];
$last_sync   = htmlspecialchars($cfg['last_sync']          ?? __('Never', 'unifiintegration'), ENT_QUOTES);
$last_status = $cfg['last_sync_status'] ?? '';
$last_msg    = htmlspecialchars($cfg['last_sync_message']  ?? '', ENT_QUOTES);

$totalDevices = array_sum($data['byStatus']);
$totalSites   = (int)$data['siteCnt'];

$statusColors = [
    'online'   => '#22c55e',
    'offline'  => '#ef4444',
    'updating' => '#f59e0b',
    'unknown'  => '#94a3b8',
];
$fwColors = [
    'upToDate'        => '#22c55e',
    'updateAvailable' => '#f59e0b',
    'upgrading'       => '#3b82f6',
    'unknown'         => '#94a3b8',
];

if (!$hasKey) {
    $config_url = '/plugins/unifiintegration/front/config.form.php';
    echo '<div class="container-xl mt-5">';
    echo '  <div class="card border-0 shadow-sm mx-auto" style="max-width:540px;">';
    echo '    <div class="card-body text-center py-5 px-4">';
    echo '      <i class="ti ti-wifi-off text-secondary mb-3" style="font-size:3rem;"></i>';
    echo '      <h4 class="fw-bold mb-2">' . __('UniFi not configured yet', 'unifiintegration') . '</h4>';
    echo '      <p class="text-muted mb-4">' . __('To start syncing your UniFi devices, set your API Key from UniFi Site Manager.', 'unifiintegration') . '</p>';
    echo '      <a href="' . htmlspecialchars($config_url, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary px-4">';
    echo '        <i class="ti ti-settings me-2"></i>' . __('Set up UniFi', 'unifiintegration');
    echo '      </a>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
    Html::footer();
    return;
}
?>
<div class="container-lg px-3 py-3">

  <!-- Header bar -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
      <i class="ti ti-wifi fs-3 text-primary"></i>
      <h4 class="mb-0 fw-bold"><?= __('UniFi Integration', 'unifiintegration') ?></h4>
      <span class="badge bg-primary ms-1"><?= PLUGIN_UNIFIINTEGRATION_VERSION ?></span>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="text-muted small">
        <i class="ti ti-clock me-1"></i><?= __('Last sync', 'unifiintegration') ?>:
        <strong id="last-sync-time"><?= $last_sync ?></strong>
      </span>
      <button id="btn-sync" class="btn btn-primary btn-sm">
        <span id="sync-icon"><i class="ti ti-refresh me-1"></i></span>
        <?= __('Sync now', 'unifiintegration') ?>
      </button>
      <a href="/plugins/unifiintegration/front/config.form.php" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-settings me-1"></i><?= __('Settings', 'unifiintegration') ?>
      </a>
    </div>
  </div>

  <div id="sync-toast" class="alert d-none mb-3"></div>

  <!-- KPI cards -->
  <div class="row g-3 mb-4">
<?php
$online  = $data['byStatus']['online']  ?? 0;
$offline = $data['byStatus']['offline'] ?? 0;
$cards = [
    ['ti-devices',      __('Total Devices', 'unifiintegration'), $totalDevices, 'primary'],
    ['ti-circle-check', __('Online',        'unifiintegration'), $online,       'success'],
    ['ti-circle-x',     __('Offline',       'unifiintegration'), $offline,      'danger'],
    ['ti-map-pin',      __('Sites',         'unifiintegration'), $totalSites,   'info'],
];
foreach ($cards as [$icon, $label, $val, $color]):
?>
    <div class="col-6 col-md-3">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-3 p-2 bg-<?= $color ?>-lt">
            <i class="ti <?= $icon ?> fs-3 text-<?= $color ?>"></i>
          </div>
          <div>
            <div class="fs-2 fw-bold lh-1"><?= $val ?></div>
            <div class="text-muted small"><?= $label ?></div>
          </div>
        </div>
      </div>
    </div>
<?php endforeach; ?>
  </div>

  <!-- Charts -->
  <div class="row g-3 mb-4">
    <div class="col-md-5">
      <div class="card shadow-sm h-100">
        <div class="card-header fw-bold"><i class="ti ti-chart-pie me-1"></i><?= __('Device Status', 'unifiintegration') ?></div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <div id="chart-status" style="width:100%;height:260px;"></div>
        </div>
      </div>
    </div>
    <div class="col-md-7">
      <div class="card shadow-sm h-100">
        <div class="card-header fw-bold"><i class="ti ti-chart-bar me-1"></i><?= __('Firmware Status', 'unifiintegration') ?></div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <div id="chart-firmware" style="width:100%;height:260px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Device table -->
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="fw-bold"><i class="ti ti-list me-1"></i><?= __('Devices', 'unifiintegration') ?> <span class="badge bg-secondary ms-1"><?= $totalDevices ?></span></span>
      <input type="search" id="device-search" class="form-control form-control-sm w-auto" placeholder="<?= __('Search…', 'unifiintegration') ?>">
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0" id="device-table">
        <thead class="table-dark">
          <tr>
            <th><?= __('Name','unifiintegration') ?></th>
            <th><?= __('Model','unifiintegration') ?></th>
            <th><?= __('IP','unifiintegration') ?></th>
            <th><?= __('MAC','unifiintegration') ?></th>
            <th><?= __('Site','unifiintegration') ?></th>
            <th><?= __('Status','unifiintegration') ?></th>
            <th><?= __('Firmware','unifiintegration') ?></th>
            <th><?= __('Version','unifiintegration') ?></th>
            <th><?= __('Last seen','unifiintegration') ?></th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($data['devices'] as $dev):
    $statusBadge = match($dev['status'] ?? '') {
        'online'   => '<span class="badge bg-success">online</span>',
        'offline'  => '<span class="badge bg-danger">offline</span>',
        'updating' => '<span class="badge bg-warning text-dark">updating</span>',
        default    => '<span class="badge bg-secondary">' . htmlspecialchars($dev['status'] ?? '', ENT_QUOTES) . '</span>',
    };
    $fwBadge = match($dev['firmware_status'] ?? '') {
        'upToDate'        => '<span class="badge bg-success">upToDate</span>',
        'updateAvailable' => '<span class="badge bg-warning text-dark">update available</span>',
        'upgrading'       => '<span class="badge bg-info">upgrading</span>',
        default           => '<span class="badge bg-secondary">' . htmlspecialchars($dev['firmware_status'] ?? '', ENT_QUOTES) . '</span>',
    };
?>
          <tr>
            <td class="fw-semibold">
              <?php if ((int)($dev['is_console'] ?? 0)): ?><i class="ti ti-server me-1 text-primary"></i><?php endif; ?>
              <?= htmlspecialchars($dev['name'] ?? '', ENT_QUOTES) ?>
            </td>
            <td class="text-muted small"><?= htmlspecialchars($dev['model'] ?? '', ENT_QUOTES) ?></td>
            <td class="font-monospace small"><?= htmlspecialchars($dev['ip']  ?? '', ENT_QUOTES) ?></td>
            <td class="font-monospace small"><?= htmlspecialchars($dev['mac'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($dev['site_name'] ?? '—', ENT_QUOTES) ?></td>
            <td><?= $statusBadge ?></td>
            <td><?= $fwBadge ?></td>
            <td class="small text-muted"><?= htmlspecialchars($dev['firmware_version'] ?? '', ENT_QUOTES) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($dev['last_seen'] ?? '', ENT_QUOTES) ?></td>
          </tr>
<?php endforeach; ?>
<?php if (empty($data['devices'])): ?>
          <tr><td colspan="9" class="text-center text-muted py-4"><?= __('No devices found. Run a sync to populate data.', 'unifiintegration') ?></td></tr>
<?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sync log -->
<?php if (!empty($data['logs'])): ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header fw-bold"><i class="ti ti-history me-1"></i><?= __('Recent sync history', 'unifiintegration') ?></div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-dark">
          <tr>
            <th><?= __('Date','unifiintegration') ?></th>
            <th><?= __('Status','unifiintegration') ?></th>
            <th><?= __('Devices','unifiintegration') ?></th>
            <th><?= __('Sites','unifiintegration') ?></th>
            <th><?= __('Hosts','unifiintegration') ?></th>
            <th><?= __('Message','unifiintegration') ?></th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($data['logs'] as $log): ?>
          <tr>
            <td class="small text-muted"><?= htmlspecialchars($log['date_run'] ?? '', ENT_QUOTES) ?></td>
            <td><span class="badge bg-<?= $log['status'] === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($log['status'] ?? '', ENT_QUOTES) ?></span></td>
            <td><?= (int)$log['devices_synced'] ?></td>
            <td><?= (int)$log['sites_synced'] ?></td>
            <td><?= (int)$log['hosts_synced'] ?></td>
            <td class="small text-muted"><?= htmlspecialchars($log['message'] ?? '', ENT_QUOTES) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

</div>
<?php
$byStatusJson   = json_encode($data['byStatus'],  JSON_UNESCAPED_UNICODE);
$byFirmwareJson = json_encode($data['byFirmware'], JSON_UNESCAPED_UNICODE);
$statusColorsJson = json_encode($statusColors);
$fwColorsJson     = json_encode($fwColors);
$t_syncing  = addslashes(__('Syncing…',      'unifiintegration'));
$t_sync_ok  = addslashes(__('Sync complete', 'unifiintegration'));
$t_sync_err = addslashes(__('Sync error',    'unifiintegration'));

Html::scriptBlock(<<<JS
(function(){
  const byStatus   = {$byStatusJson};
  const byFirmware = {$byFirmwareJson};
  const statusColors = {$statusColorsJson};
  const fwColors     = {$fwColorsJson};

  function resolveColor(map, key) { return map[key] || '#94a3b8'; }

  const chartStatus = echarts.init(document.getElementById('chart-status'));
  chartStatus.setOption({
    tooltip: { trigger:'item', formatter:'{b}: {c} ({d}%)' },
    legend:  { orient:'vertical', right:10, top:'center' },
    series: [{ name:'Status', type:'pie', radius:['40%','65%'], center:['40%','50%'],
      data: Object.entries(byStatus).map(([k,v]) => ({name:k, value:v, itemStyle:{color:resolveColor(statusColors,k)}})),
      label:{show:false}, emphasis:{label:{show:true, fontSize:14, fontWeight:'bold'}} }]
  });

  const chartFw = echarts.init(document.getElementById('chart-firmware'));
  const fwLabels = Object.keys(byFirmware);
  const fwValues = Object.values(byFirmware);
  chartFw.setOption({
    tooltip:{trigger:'axis', axisPointer:{type:'shadow'}},
    grid:{left:20,right:20,bottom:30,top:20,containLabel:true},
    xAxis:{type:'category', data:fwLabels, axisLabel:{rotate:fwLabels.length>3?15:0, fontSize:11}},
    yAxis:{type:'value', minInterval:1},
    series:[{type:'bar', data:fwValues.map((v,i)=>({value:v, itemStyle:{color:resolveColor(fwColors,fwLabels[i]), borderRadius:[4,4,0,0]}}))}]
  });

  window.addEventListener('resize', () => { chartStatus.resize(); chartFw.resize(); });

  document.getElementById('device-search').addEventListener('input', function(){
    const q = this.value.toLowerCase();
    document.querySelectorAll('#device-table tbody tr').forEach(tr => {
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });

  const btn   = document.getElementById('btn-sync');
  const toast = document.getElementById('sync-toast');
  if (btn) {
    btn.addEventListener('click', function(){
      btn.disabled = true;
      document.getElementById('sync-icon').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
      toast.className = 'alert alert-info mb-3';
      toast.textContent = '{$t_syncing}';
      const csrfValue = (typeof window.glpiGetNewCSRFToken === 'function')
        ? window.glpiGetNewCSRFToken()
        : (document.querySelector('meta[property="glpi:csrf_token"]') || {}).getAttribute('content') || '';
      const fd = new FormData();
      fd.append('action', 'sync');
      fd.append('_glpi_csrf_token', csrfValue);
      fetch('/plugins/unifiintegration/front/ajax.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
          toast.className = d.success ? 'alert alert-success mb-3' : 'alert alert-danger mb-3';
          toast.textContent = d.success ? '{$t_sync_ok}: ' + (d.message||'') : '{$t_sync_err}: ' + (d.message||'');
          if (d.success) setTimeout(() => location.reload(), 1500);
        })
        .catch(() => { toast.className='alert alert-danger mb-3'; toast.textContent='{$t_sync_err}'; })
        .finally(() => { btn.disabled=false; document.getElementById('sync-icon').innerHTML='<i class="ti ti-refresh me-1"></i>'; });
    });
  }
})();
JS);

Html::footer();
