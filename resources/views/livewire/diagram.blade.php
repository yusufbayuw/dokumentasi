<div wire:ignore>
    <link rel="stylesheet" href="{{ asset('css/network-topology.css') }}?v=20260917-3">

    <div id="networkTopologyShell" class="network-topology-shell">
        <div class="network-topology-toolbar">
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
                    placeholder="Cari nama perangkat, IP, asset, hostname..."
                >
                <div id="networkSearchResults" class="network-topology-search-results" hidden></div>
            </div>

            <div class="network-topology-primary-controls">
                <select id="networkViewMode" class="network-topology-select network-topology-view-select" aria-label="Mode diagram">
                    <option value="overview">Ringkasan Infrastruktur</option>
                    <option value="backbone">Backbone</option>
                    <option value="physical">Lokasi Fisik</option>
                    <option value="health">Health</option>
                    <option value="dependency">Dependency</option>
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
                <button type="button" class="network-topology-button" data-network-action="fit" title="Sesuaikan diagram ke layar">Fit</button>
                <button type="button" class="network-topology-button" data-network-action="fullscreen">Fullscreen</button>

                <details class="network-topology-menu">
                    <summary class="network-topology-button network-topology-button-square" aria-label="Opsi diagram">•••</summary>
                    <div class="network-topology-menu-popover">
                        <span class="network-topology-menu-label">Layout</span>
                        <button type="button" data-network-action="layout-layered">Hierarki</button>
                        <button type="button" data-network-action="layout-force">Relasi bebas</button>
                        <div class="network-topology-menu-divider"></div>
                        <button type="button" data-network-action="zoom-in">Perbesar</button>
                        <button type="button" data-network-action="zoom-out">Perkecil</button>
                        <button type="button" data-network-action="reset">Reset tampilan</button>
                    </div>
                </details>
            </div>
        </div>

        <div class="network-topology-summary">
            <div class="network-topology-stats">
                <span class="network-topology-stat">Total <strong>{{ $stats['total'] }}</strong></span>
                <span class="network-topology-stat"><span class="network-topology-dot online"></span>Online <strong>{{ $stats['online'] }}</strong></span>
                <span class="network-topology-stat"><span class="network-topology-dot offline"></span>Offline <strong>{{ $stats['offline'] }}</strong></span>
                <span class="network-topology-stat"><span class="network-topology-dot unknown"></span>Tanpa IP <strong>{{ $stats['unknown'] }}</strong></span>
            </div>
            <div class="network-topology-context">
                <span id="networkViewDescription">Ringkasan kondisi infrastruktur per gedung</span>
                <span>•</span>
                <span>Ditampilkan <strong id="networkVisibleCount">0</strong></span>
            </div>
        </div>

        <nav id="networkBreadcrumb" class="network-topology-breadcrumb" aria-label="Posisi diagram">
            <button type="button" data-network-breadcrumb="0">Infrastructure</button>
        </nav>

        <div id="networkTopologyStage" class="network-topology-stage">
            <div class="network-topology-canvas-wrap">
                <div id="networkGraph" aria-label="Diagram topologi jaringan"></div>

                <div id="networkEmptyState" class="network-topology-empty">
                    <div class="network-topology-empty-card">
                        <strong>Tidak ada data untuk tampilan ini.</strong>
                        <span>Coba ubah filter atau kembali ke Ringkasan Infrastruktur.</span>
                    </div>
                </div>

                <div class="network-topology-legend" aria-label="Legenda">
                    <span><i class="network-topology-dot online"></i>Online</span>
                    <span><i class="network-topology-dot offline"></i>Offline</span>
                    <span><i class="network-topology-dot unknown"></i>Unknown</span>
                    <span class="network-topology-legend-hint">Double-click group untuk masuk • scroll untuk semantic zoom</span>
                </div>
            </div>

            <aside id="networkDetailPanel" class="network-topology-detail" aria-hidden="true">
                <div class="network-topology-detail-header">
                    <div class="network-topology-detail-title">
                        <span id="networkDetailEyebrow">PERANGKAT</span>
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

                    <div id="networkDetailMetrics" class="network-topology-metrics">
                        <div><span>Upstream</span><strong id="networkDetailIncoming">—</strong></div>
                        <div><span>Downstream</span><strong id="networkDetailOutgoing">—</strong></div>
                        <div><span>Dampak</span><strong id="networkDetailImpact">—</strong></div>
                    </div>

                    <div class="network-topology-analysis-actions">
                        <button type="button" class="network-topology-analysis-primary" data-network-analysis="focus">Fokus dependency</button>
                        <button type="button" data-network-analysis="path">Jalur ke upstream</button>
                    </div>

                    <dl class="network-topology-detail-grid network-topology-detail-grid-primary">
                        <dt>Lokasi</dt><dd id="networkDetailLocation">—</dd>
                        <dt>Unit</dt><dd id="networkDetailUnit">—</dd>
                        <dt>Criticality</dt><dd id="networkDetailCriticality">—</dd>
                        <dt>Last Seen</dt><dd id="networkDetailLastSeen">—</dd>
                    </dl>

                    <details class="network-topology-more">
                        <summary>Detail perangkat & monitoring</summary>
                        <dl class="network-topology-detail-grid">
                            <dt>Kode Aset</dt><dd id="networkDetailAssetCode">—</dd>
                            <dt>Hostname</dt><dd id="networkDetailHostname">—</dd>
                            <dt>Lifecycle</dt><dd id="networkDetailLifecycle">—</dd>
                            <dt>Latency</dt><dd id="networkDetailLatency">—</dd>
                            <dt>Last Checked</dt><dd id="networkDetailLastChecked">—</dd>
                            <dt>Monitoring</dt><dd id="networkDetailMonitoring">—</dd>
                            <dt>Unit Pemilik</dt><dd id="networkDetailOwnerUnit">—</dd>
                            <dt>Gedung</dt><dd id="networkDetailBuilding">—</dd>
                            <dt>Lantai</dt><dd id="networkDetailFloor">—</dd>
                            <dt>Ruangan</dt><dd id="networkDetailRoom">—</dd>
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
