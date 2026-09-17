<div wire:ignore>
    <link rel="stylesheet" href="{{ asset('css/network-topology.css') }}?v=20260917-2">

    <div id="networkTopologyShell" class="network-topology-shell">
        <div class="network-topology-toolbar">
            <div class="network-topology-filters">
                <div class="network-topology-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-3.5-3.5"></path>
                    </svg>
                    <input
                        id="networkSearch"
                        class="network-topology-control"
                        type="search"
                        autocomplete="off"
                        placeholder="Cari perangkat, IP, asset, hostname, atau lokasi..."
                    >
                </div>

                <select id="networkTypeFilter" class="network-topology-select" aria-label="Filter jenis perangkat">
                    <option value="">Semua jenis</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>

                <select id="networkUnitFilter" class="network-topology-select" aria-label="Filter unit">
                    <option value="">Semua unit</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit }}">{{ $unit }}</option>
                    @endforeach
                </select>

                <select id="networkStatusFilter" class="network-topology-select" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                    <option value="unknown">Tanpa IP</option>
                </select>
            </div>

            <div class="network-topology-actions">
                <div class="network-topology-segment" aria-label="Cakupan diagram">
                    <button type="button" class="network-topology-button is-active" data-network-scope="overview" title="Tampilkan topologi inti">Inti</button>
                    <button type="button" class="network-topology-button" data-network-scope="all" title="Tampilkan semua perangkat">Semua</button>
                </div>

                <span class="network-topology-action-divider"></span>

                <button type="button" class="network-topology-button" data-network-action="layout-layered" title="Layout hierarki">Hierarki</button>
                <button type="button" class="network-topology-button" data-network-action="layout-force" title="Layout relasi bebas">Relasi</button>

                <span class="network-topology-action-divider"></span>

                <button type="button" class="network-topology-button network-topology-button-square" data-network-action="zoom-out" title="Perkecil" aria-label="Perkecil">−</button>
                <button type="button" class="network-topology-button" data-network-action="fit" title="Fit ke layar">Fit</button>
                <button type="button" class="network-topology-button network-topology-button-square" data-network-action="zoom-in" title="Perbesar" aria-label="Perbesar">+</button>
                <button type="button" class="network-topology-button" data-network-action="fullscreen">Fullscreen</button>
                <button type="button" class="network-topology-button" data-network-action="reset">Reset</button>
            </div>
        </div>

        <div class="network-topology-summary">
            <span class="network-topology-stat">Total <strong>{{ $stats['total'] }}</strong></span>
            <span class="network-topology-stat">Koneksi <strong>{{ $stats['connections'] }}</strong></span>
            <span class="network-topology-stat"><span class="network-topology-dot online"></span>Online <strong>{{ $stats['online'] }}</strong></span>
            <span class="network-topology-stat"><span class="network-topology-dot offline"></span>Offline <strong>{{ $stats['offline'] }}</strong></span>
            <span class="network-topology-stat"><span class="network-topology-dot unknown"></span>Tanpa IP <strong>{{ $stats['unknown'] }}</strong></span>
            <span class="network-topology-mode">Mode: <strong id="networkScopeLabel">Topologi inti</strong></span>
            <span class="network-topology-count">Ditampilkan: <strong id="networkVisibleCount">0</strong></span>
        </div>

        <div class="network-topology-stage">
            <div id="networkGraph" aria-label="Diagram topologi jaringan"></div>

            <div id="networkEmptyState" class="network-topology-empty">
                <div class="network-topology-empty-card">Tidak ada perangkat yang cocok dengan filter.</div>
            </div>

            <div class="network-topology-legend" aria-label="Legenda">
                <span><i class="network-topology-dot online"></i>Online</span>
                <span><i class="network-topology-dot offline"></i>Offline</span>
                <span><i class="network-topology-dot unknown"></i>Unknown</span>
                <span class="network-topology-legend-hint">Inti menyembunyikan endpoint massal. Cari perangkat atau klik node untuk fokus cabang.</span>
            </div>

            <aside id="networkDetailPanel" class="network-topology-detail" aria-hidden="true">
                <div class="network-topology-detail-header">
                    <div class="network-topology-detail-title">
                        <strong id="networkDetailName">—</strong>
                        <span id="networkDetailType">—</span>
                    </div>
                    <button id="networkDetailClose" type="button" class="network-topology-close" aria-label="Tutup detail">×</button>
                </div>

                <div class="network-topology-detail-body">
                    <div class="network-topology-detail-topline">
                        <div id="networkDetailStatus" class="network-topology-status">
                            <span class="network-topology-dot unknown"></span>
                            <span>—</span>
                        </div>
                        <span id="networkDetailIp" class="network-topology-ip">—</span>
                    </div>

                    <div class="network-topology-analysis-actions">
                        <button type="button" class="network-topology-analysis-primary" data-network-analysis="focus">Fokus cabang</button>
                        <button type="button" data-network-analysis="downstream">Dampak</button>
                        <button type="button" data-network-analysis="upstream">Upstream</button>
                        <button type="button" data-network-analysis="clear">Bersihkan</button>
                    </div>

                    <dl class="network-topology-detail-grid network-topology-detail-grid-primary">
                        <dt>Lokasi</dt><dd><span id="networkDetailRoom">—</span><span class="network-topology-location-separator"> • </span><span id="networkDetailBuilding">—</span></dd>
                        <dt>Unit</dt><dd id="networkDetailUnit">—</dd>
                        <dt>Upstream</dt><dd id="networkDetailIncoming">—</dd>
                        <dt>Downstream</dt><dd id="networkDetailOutgoing">—</dd>
                        <dt>Dampak</dt><dd id="networkDetailImpact">—</dd>
                    </dl>

                    <details class="network-topology-more">
                        <summary>Detail perangkat & monitoring</summary>
                        <dl class="network-topology-detail-grid">
                            <dt>Kode Aset</dt><dd id="networkDetailAssetCode">—</dd>
                            <dt>Hostname</dt><dd id="networkDetailHostname">—</dd>
                            <dt>Criticality</dt><dd id="networkDetailCriticality">—</dd>
                            <dt>Lifecycle</dt><dd id="networkDetailLifecycle">—</dd>
                            <dt>Latency</dt><dd id="networkDetailLatency">—</dd>
                            <dt>Last Seen</dt><dd id="networkDetailLastSeen">—</dd>
                            <dt>Last Checked</dt><dd id="networkDetailLastChecked">—</dd>
                            <dt>Monitoring</dt><dd id="networkDetailMonitoring">—</dd>
                            <dt>Unit Pemilik</dt><dd id="networkDetailOwnerUnit">—</dd>
                            <dt>Lantai</dt><dd id="networkDetailFloor">—</dd>
                            <dt>Keterangan</dt><dd id="networkDetailDescription">—</dd>
                        </dl>
                    </details>

                    <a id="networkDetailEdit" class="network-topology-edit-link" href="#">Buka detail perangkat</a>
                </div>
            </aside>
        </div>
    </div>

    <script>
        window.networkTopologyData = {
            nodes: {{ Illuminate\Support\Js::from($nodes) }},
            edges: {{ Illuminate\Support\Js::from($edges) }},
        };
    </script>

    @vite('resources/js/network-topology.js')
</div>
