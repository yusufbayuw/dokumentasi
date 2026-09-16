<div wire:ignore>
    <link rel="stylesheet" href="{{ asset('css/network-topology.css') }}?v=20260916-2">
    <script src="{{ asset('js/go.js') }}"></script>

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
                        placeholder="Cari nama perangkat, IP, asset, hostname, jenis, atau lokasi..."
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
                <button type="button" class="network-topology-button network-topology-button-square" data-network-action="zoom-out" title="Perkecil" aria-label="Perkecil">−</button>
                <button type="button" class="network-topology-button" data-network-action="zoom-reset" title="Zoom 100%">100%</button>
                <button type="button" class="network-topology-button network-topology-button-square" data-network-action="zoom-in" title="Perbesar" aria-label="Perbesar">+</button>
                <button type="button" class="network-topology-button" data-network-action="fit" title="Fit ke layar">Fit</button>
                <button type="button" class="network-topology-button" data-network-action="overview" title="Tampilkan/sembunyikan mini-map">Mini-map</button>
                <button type="button" class="network-topology-button" data-network-action="fullscreen" title="Layar penuh">Fullscreen</button>
                <button type="button" class="network-topology-button" data-network-action="reset" title="Reset filter dan view">Reset</button>
            </div>
        </div>

        <div class="network-topology-summary">
            <span class="network-topology-stat">
                Total <strong>{{ $stats['total'] }}</strong>
            </span>
            <span class="network-topology-stat">
                <span class="network-topology-dot online"></span>
                Online <strong>{{ $stats['online'] }}</strong>
            </span>
            <span class="network-topology-stat">
                <span class="network-topology-dot offline"></span>
                Offline <strong>{{ $stats['offline'] }}</strong>
            </span>
            <span class="network-topology-stat">
                <span class="network-topology-dot unknown"></span>
                Tanpa IP <strong>{{ $stats['unknown'] }}</strong>
            </span>
            <span class="network-topology-count">
                Ditampilkan: <strong id="networkVisibleCount">{{ $stats['total'] }}</strong>
            </span>
        </div>

        <div class="network-topology-stage">
            <div id="myDiagramDiv" aria-label="Diagram topologi jaringan"></div>
            <div id="myOverviewDiv" aria-label="Mini-map diagram"></div>

            <div id="networkEmptyState" class="network-topology-empty">
                <div class="network-topology-empty-card">
                    Tidak ada perangkat yang cocok dengan filter.
                </div>
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

                    <dl class="network-topology-detail-grid">
                        <dt>Kode Aset</dt>
                        <dd id="networkDetailAssetCode">—</dd>

                        <dt>Hostname</dt>
                        <dd id="networkDetailHostname">—</dd>

                        <dt>IP Address</dt>
                        <dd id="networkDetailIp">—</dd>

                        <dt>Criticality</dt>
                        <dd id="networkDetailCriticality">—</dd>

                        <dt>Lifecycle</dt>
                        <dd id="networkDetailLifecycle">—</dd>

                        <dt>Latency</dt>
                        <dd id="networkDetailLatency">—</dd>

                        <dt>Last Seen</dt>
                        <dd id="networkDetailLastSeen">—</dd>

                        <dt>Last Checked</dt>
                        <dd id="networkDetailLastChecked">—</dd>

                        <dt>Monitoring</dt>
                        <dd id="networkDetailMonitoring">—</dd>

                        <dt>Unit Lokasi</dt>
                        <dd id="networkDetailUnit">—</dd>

                        <dt>Unit Pemilik</dt>
                        <dd id="networkDetailOwnerUnit">—</dd>

                        <dt>Gedung</dt>
                        <dd id="networkDetailBuilding">—</dd>

                        <dt>Lantai</dt>
                        <dd id="networkDetailFloor">—</dd>

                        <dt>Ruangan</dt>
                        <dd id="networkDetailRoom">—</dd>

                        <dt>Turunan</dt>
                        <dd id="networkDetailChildren">—</dd>

                        <dt>Keterangan</dt>
                        <dd id="networkDetailDescription">—</dd>
                    </dl>

                    <a id="networkDetailEdit" class="network-topology-edit-link" href="#">
                        Buka detail perangkat
                    </a>
                </div>
            </aside>
        </div>
    </div>

    <script>
        window.networkTopologyNodes = {{ Illuminate\Support\Js::from($nodeDataArray) }};
    </script>
    <script src="{{ asset('js/network-topology.js') }}?v=20260916-2"></script>
</div>
