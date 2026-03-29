# Changelog — UniFi Integration

## [1.0.0] — 2026-03-29

### Added
- Initial release
- UniFi Site Manager Cloud API client (`api.ui.com`, `X-API-KEY` auth)
- Sync UniFi sites → GLPI Locations
- Sync UniFi hosts/consoles (UDM, UCG…) → GLPI NetworkEquipment
- Sync UniFi devices (APs, switches, routers) → GLPI NetworkEquipment
- ECharts 5 dashboard: device-status pie chart, firmware-status bar chart
- Device table with inline search, status badges, firmware badges, site column
- Sync log panel (last 10 runs)
- Manual sync button with live spinner (AJAX)
- AJAX endpoint with CSRF protection
- Configurable cron task (5 min · 10 min · 15 min · 30 min · 1 hour)
- Test connection button in configuration form
- Locales: es\_MX (Full), fr\_FR (Full), de\_DE (Full), en\_US (Base), en\_GB (Base)
- GLPI 11 conventions: `$CFG_GLPI['root_doc']`, `Html::requireJs('charts')`, `DBConnection::getDefault*`, `Session::checkRight()`
