<div wire:ignore>
    <link rel="stylesheet" href="{{ asset('css/network-topology.css') }}?v=20260917-1">

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
                <button type="button" class="network-topology-button" data-network-action="layout-layered">Layered</button>
                <button type="button" class="network-topology-button" data-network-action="layout-force">Force</button>
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
            <span class="network-topology-count">Ditampilkan: <strong id="networkVisibleCount">{{ $stats['total'] }}</strong></span>
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
                <span class="network-topology-legend-hint">Klik node untuk analisis dependency</span>
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
                    <div id="networkDetailStatus" class="network-topology-status">
                        <span class="network-topology-dot unknown"></span>
                        <span>—</span>
                    </div>

                    <div class="network-topology-analysis-actions">
                        <button type="button" data-network-analysis="downstream">Dampak downstream</button>
                        <button type="button" data-network-analysis="upstream">Jalur upstream</button>
                        <button type="button" data-network-analysis="clear">Bersihkan highlight</button>
                    </div>

                    <dl class="network-topology-detail-grid">
                        <dt>Kode Aset</dt><dd id="networkDetailAssetCode">—</dd>
                        <dt>Hostname</dt><dd id="networkDetailHostname">—</dd>
                        <dt>IP Address</dt><dd id="networkDetailIp">—</dd>
                        <dt>Criticality</dt><dd id="networkDetailCriticality">—</dd>
                        <dt>Lifecycle</dt><dd id="networkDetailLifecycle">—</dd>
                        <dt>Latency</dt><dd id="networkDetailLatency">—</dd>
                        <dt>Last Seen</dt><dd id="networkDetailLastSeen">—</dd>
                        <dt>Last Checked</dt><dd id="networkDetailLastChecked">—</dd>
                        <dt>Monitoring</dt><dd id="networkDetailMonitoring">—</dd>
                        <dt>Unit Lokasi</dt><dd id="networkDetailUnit">—</dd>
                        <dt>Unit Pemilik</dt><dd id="networkDetailOwnerUnit">—</dd>
                        <dt>Gedung</dt><dd id="networkDetailBuilding">—</dd>
                        <dt>Lantai</dt><dd id="networkDetailFloor">—</dd>
                        <dt>Ruangan</dt><dd id="networkDetailRoom">—</dd>
                        <dt>Upstream</dt><dd id="networkDetailIncoming">—</dd>
                        <dt>Downstream</dt><dd id="networkDetailOutgoing">—</dd>
                        <dt>Potensi Dampak</dt><dd id="networkDetailImpact">—</dd>
                        <dt>Keterangan</dt><dd id="networkDetailDescription">—</dd>
                    </dl>

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
