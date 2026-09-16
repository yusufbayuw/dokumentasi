<div wire:ignore>
    <script src="{{ asset('js/go.js') }}"></script>

    <style>
        .network-topology-shell {
            --nt-bg: #f8fafc;
            --nt-surface: #ffffff;
            --nt-surface-soft: #f1f5f9;
            --nt-border: #e2e8f0;
            --nt-text: #0f172a;
            --nt-muted: #64748b;
            --nt-primary: #2563eb;
            --nt-online: #16a34a;
            --nt-offline: #dc2626;
            --nt-unknown: #94a3b8;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--nt-border);
            border-radius: 14px;
            background: var(--nt-surface);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        html.dark .network-topology-shell {
            --nt-bg: #0f172a;
            --nt-surface: #111827;
            --nt-surface-soft: #1e293b;
            --nt-border: #334155;
            --nt-text: #f8fafc;
            --nt-muted: #94a3b8;
            --nt-primary: #60a5fa;
        }

        .network-topology-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            padding: 14px;
            border-bottom: 1px solid var(--nt-border);
            background: var(--nt-surface);
        }

        .network-topology-search {
            position: relative;
            flex: 1 1 280px;
            min-width: 220px;
        }

        .network-topology-search svg {
            position: absolute;
            left: 11px;
            top: 50%;
            width: 17px;
            height: 17px;
            transform: translateY(-50%);
            color: var(--nt-muted);
            pointer-events: none;
        }

        .network-topology-control,
        .network-topology-select {
            height: 38px;
            border: 1px solid var(--nt-border);
            border-radius: 9px;
            background: var(--nt-surface);
            color: var(--nt-text);
            font-size: 13px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }

        .network-topology-control:focus,
        .network-topology-select:focus {
            border-color: var(--nt-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .network-topology-control {
            width: 100%;
            padding: 0 12px 0 36px;
        }

        .network-topology-select {
            min-width: 145px;
            padding: 0 30px 0 10px;
        }

        .network-topology-actions {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: auto;
        }

        .network-topology-button {
            display: inline-flex;
            height: 36px;
            min-width: 36px;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 1px solid var(--nt-border);
            border-radius: 8px;
            background: var(--nt-surface);
            color: var(--nt-text);
            padding: 0 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease;
        }

        .network-topology-button:hover {
            background: var(--nt-surface-soft);
            border-color: #cbd5e1;
        }

        html.dark .network-topology-button:hover {
            border-color: #475569;
        }

        .network-topology-summary {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border-bottom: 1px solid var(--nt-border);
            background: var(--nt-surface-soft);
            color: var(--nt-muted);
            font-size: 12px;
        }

        .network-topology-stat {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            background: var(--nt-surface);
            padding: 5px 9px;
            border: 1px solid var(--nt-border);
            color: var(--nt-text);
            font-weight: 600;
        }

        .network-topology-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--nt-unknown);
        }

        .network-topology-dot.online { background: var(--nt-online); }
        .network-topology-dot.offline { background: var(--nt-offline); }
        .network-topology-dot.unknown { background: var(--nt-unknown); }

        .network-topology-count {
            margin-left: auto;
            white-space: nowrap;
        }

        .network-topology-stage {
            position: relative;
            background: var(--nt-bg);
        }

        #myDiagramDiv {
            width: 100%;
            height: min(72vh, 780px);
            min-height: 580px;
            background: var(--nt-bg);
            outline: none;
        }

        #myOverviewDiv {
            position: absolute;
            left: 14px;
            bottom: 14px;
            width: 170px;
            height: 105px;
            overflow: hidden;
            border: 1px solid var(--nt-border);
            border-radius: 10px;
            background: var(--nt-surface);
            box-shadow: 0 8px 24px rgba(15, 23, 42, .12);
            z-index: 20;
        }

        #myOverviewDiv.is-hidden {
            display: none;
        }

        .network-topology-detail {
            position: absolute;
            top: 14px;
            right: 14px;
            width: min(360px, calc(100% - 28px));
            max-height: calc(100% - 28px);
            overflow-y: auto;
            border: 1px solid var(--nt-border);
            border-radius: 12px;
            background: var(--nt-surface);
            box-shadow: 0 12px 34px rgba(15, 23, 42, .16);
            z-index: 30;
            transform: translateX(calc(100% + 30px));
            opacity: 0;
            pointer-events: none;
            transition: transform .18s ease, opacity .18s ease;
        }

        .network-topology-detail.is-open {
            transform: translateX(0);
            opacity: 1;
            pointer-events: auto;
        }

        .network-topology-detail-header {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 16px;
            border-bottom: 1px solid var(--nt-border);
        }

        .network-topology-detail-title {
            flex: 1;
            min-width: 0;
        }

        .network-topology-detail-title strong {
            display: block;
            overflow: hidden;
            color: var(--nt-text);
            font-size: 15px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .network-topology-detail-title span {
            display: block;
            margin-top: 4px;
            color: var(--nt-muted);
            font-size: 12px;
        }

        .network-topology-close {
            border: 0;
            background: transparent;
            color: var(--nt-muted);
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }

        .network-topology-detail-body {
            padding: 14px 16px 16px;
        }

        .network-topology-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 14px;
            border-radius: 999px;
            padding: 5px 9px;
            background: var(--nt-surface-soft);
            color: var(--nt-text);
            font-size: 12px;
            font-weight: 700;
        }

        .network-topology-detail-grid {
            display: grid;
            grid-template-columns: 105px 1fr;
            gap: 10px 12px;
            font-size: 12px;
        }

        .network-topology-detail-grid dt {
            color: var(--nt-muted);
        }

        .network-topology-detail-grid dd {
            margin: 0;
            min-width: 0;
            overflow-wrap: anywhere;
            color: var(--nt-text);
            font-weight: 500;
        }

        .network-topology-edit-link {
            display: none;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-top: 16px;
            border-radius: 8px;
            background: #2563eb;
            padding: 9px 12px;
            color: #ffffff !important;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .network-topology-edit-link.is-visible {
            display: flex;
        }

        .network-topology-empty {
            position: absolute;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
            pointer-events: none;
        }

        .network-topology-empty.is-visible {
            display: flex;
        }

        .network-topology-empty-card {
            border: 1px solid var(--nt-border);
            border-radius: 10px;
            background: var(--nt-surface);
            padding: 18px 22px;
            color: var(--nt-muted);
            font-size: 13px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
        }

        .network-topology-shell:fullscreen {
            border: 0;
            border-radius: 0;
        }

        .network-topology-shell:fullscreen #myDiagramDiv {
            height: calc(100vh - 105px);
            min-height: 0;
        }

        @media (max-width: 900px) {
            .network-topology-select {
                flex: 1 1 140px;
            }

            .network-topology-actions {
                width: 100%;
                margin-left: 0;
                overflow-x: auto;
            }

            #myDiagramDiv {
                min-height: 520px;
            }

            #myOverviewDiv {
                width: 135px;
                height: 85px;
            }
        }
    </style>

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
                    placeholder="Cari nama perangkat, IP, jenis, atau lokasi..."
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

            <div class="network-topology-actions">
                <button type="button" class="network-topology-button" data-network-action="zoom-out" title="Perkecil">−</button>
                <button type="button" class="network-topology-button" data-network-action="zoom-reset" title="Zoom 100%">100%</button>
                <button type="button" class="network-topology-button" data-network-action="zoom-in" title="Perbesar">+</button>
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
                        <dt>IP Address</dt>
                        <dd id="networkDetailIp">—</dd>

                        <dt>Unit</dt>
                        <dd id="networkDetailUnit">—</dd>

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
        (() => {
            const topologyNodes = {{ Illuminate\Support\Js::from($nodeDataArray) }};

            function bootNetworkTopology() {
                const diagramDiv = document.getElementById('myDiagramDiv');

                if (!diagramDiv || typeof go === 'undefined') {
                    return;
                }

                if (window.networkTopologyDiagram?.div === diagramDiv) {
                    return;
                }

                if (window.networkTopologyDiagram?.div) {
                    window.networkTopologyDiagram.div = null;
                }

                const $ = go.GraphObject.make;
                const isDark = () => document.documentElement.classList.contains('dark');

                const colors = () => isDark()
                    ? {
                        canvas: '#0f172a',
                        card: '#111827',
                        cardSoft: '#1e293b',
                        border: '#334155',
                        text: '#f8fafc',
                        muted: '#94a3b8',
                        link: '#64748b',
                    }
                    : {
                        canvas: '#f8fafc',
                        card: '#ffffff',
                        cardSoft: '#f1f5f9',
                        border: '#cbd5e1',
                        text: '#0f172a',
                        muted: '#64748b',
                        link: '#94a3b8',
                    };

                const statusColor = (status) => {
                    if (status === 'online') return '#16a34a';
                    if (status === 'offline') return '#dc2626';
                    return '#94a3b8';
                };

                const palette = colors();

                const diagram = new go.Diagram(diagramDiv, {
                    initialAutoScale: go.AutoScale.Uniform,
                    contentAlignment: go.Spot.Center,
                    padding: new go.Margin(70, 80, 70, 80),
                    minScale: 0.18,
                    maxScale: 2.2,
                    allowMove: false,
                    allowCopy: false,
                    allowDelete: false,
                    allowInsert: false,
                    'undoManager.isEnabled': false,
                    'toolManager.mouseWheelBehavior': go.WheelMode.Zoom,
                    layout: $(go.TreeLayout, {
                        angle: 90,
                        alignment: go.TreeLayout.AlignmentCenterChildren,
                        arrangement: go.TreeLayout.ArrangementHorizontal,
                        compaction: go.TreeLayout.CompactionBlock,
                        layerSpacing: 70,
                        nodeSpacing: 24,
                        sorting: go.TreeLayout.SortingAscending,
                        comparer: (a, b) => String(a.node?.data?.name ?? '').localeCompare(String(b.node?.data?.name ?? '')),
                    }),
                    'InitialLayoutCompleted': (event) => {
                        event.diagram.zoomToFit();
                    },
                });

                window.networkTopologyDiagram = diagram;
                diagramDiv.style.background = palette.canvas;

                diagram.nodeTemplate = $(
                    go.Node,
                    'Auto',
                    {
                        selectionAdorned: false,
                        cursor: 'pointer',
                        click: (_event, node) => openDetail(node.data),
                    },
                    $(
                        go.Shape,
                        'RoundedRectangle',
                        {
                            name: 'CARD',
                            parameter1: 8,
                            fill: palette.card,
                            strokeWidth: 2,
                            stroke: palette.border,
                            shadowVisible: true,
                            shadowBlur: 10,
                            shadowOffset: new go.Point(0, 3),
                            shadowColor: isDark() ? 'rgba(0,0,0,.35)' : 'rgba(15,23,42,.12)',
                        },
                        new go.Binding('stroke', 'status', statusColor),
                    ),
                    $(
                        go.Panel,
                        'Vertical',
                        {
                            width: 242,
                            defaultStretch: go.Stretch.Horizontal,
                        },
                        $(
                            go.Panel,
                            'Table',
                            {
                                margin: new go.Margin(12, 12, 8, 12),
                                defaultAlignment: go.Spot.Left,
                            },
                            $(go.RowColumnDefinition, { column: 1, width: 158 }),
                            $(
                                go.Panel,
                                'Auto',
                                { column: 0, row: 0, rowSpan: 2, margin: new go.Margin(0, 10, 0, 0) },
                                $(go.Shape, 'RoundedRectangle', {
                                    name: 'TYPE_BADGE',
                                    parameter1: 6,
                                    width: 42,
                                    height: 42,
                                    fill: palette.cardSoft,
                                    stroke: palette.border,
                                }),
                                $(
                                    go.TextBlock,
                                    {
                                        name: 'TYPE_CODE',
                                        font: '700 11px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                                        stroke: palette.text,
                                    },
                                    new go.Binding('text', 'typeCode'),
                                ),
                            ),
                            $(
                                go.TextBlock,
                                {
                                    name: 'NAME',
                                    column: 1,
                                    row: 0,
                                    font: '700 13px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                                    stroke: palette.text,
                                    maxSize: new go.Size(158, 38),
                                    wrap: go.Wrap.Fit,
                                    overflow: go.TextOverflow.Ellipsis,
                                },
                                new go.Binding('text', 'name'),
                            ),
                            $(
                                go.Panel,
                                'Horizontal',
                                { column: 1, row: 1, margin: new go.Margin(5, 0, 0, 0) },
                                $(
                                    go.Shape,
                                    'Circle',
                                    { width: 7, height: 7, stroke: null, margin: new go.Margin(0, 6, 0, 0) },
                                    new go.Binding('fill', 'status', statusColor),
                                ),
                                $(
                                    go.TextBlock,
                                    {
                                        name: 'STATUS_TEXT',
                                        font: '500 10px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                                        stroke: palette.muted,
                                    },
                                    new go.Binding('text', 'statusLabel'),
                                ),
                            ),
                            $(
                                'TreeExpanderButton',
                                {
                                    column: 2,
                                    row: 0,
                                    rowSpan: 2,
                                    alignment: go.Spot.Right,
                                    width: 26,
                                    height: 26,
                                },
                                new go.Binding('visible', 'childrenCount', (count) => Number(count) > 0),
                            ),
                        ),
                        $(go.Shape, 'LineH', {
                            name: 'SEPARATOR',
                            height: 1,
                            strokeWidth: 1,
                            stroke: palette.border,
                        }),
                        $(
                            go.Panel,
                            'Vertical',
                            {
                                margin: new go.Margin(8, 12, 11, 12),
                                defaultAlignment: go.Spot.Left,
                            },
                            $(
                                go.TextBlock,
                                {
                                    name: 'TYPE_TEXT',
                                    font: '600 11px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                                    stroke: palette.text,
                                    maxSize: new go.Size(218, 18),
                                    overflow: go.TextOverflow.Ellipsis,
                                },
                                new go.Binding('text', '', (data) => data.ip ? `${data.type}  •  ${data.ip}` : data.type),
                            ),
                            $(
                                go.TextBlock,
                                {
                                    name: 'LOCATION_TEXT',
                                    margin: new go.Margin(5, 0, 0, 0),
                                    font: '400 10px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                                    stroke: palette.muted,
                                    maxSize: new go.Size(218, 32),
                                    wrap: go.Wrap.Fit,
                                    overflow: go.TextOverflow.Ellipsis,
                                },
                                new go.Binding('text', 'location', (value) => value || 'Lokasi belum ditentukan'),
                            ),
                            $(
                                go.TextBlock,
                                {
                                    name: 'CHILD_TEXT',
                                    margin: new go.Margin(6, 0, 0, 0),
                                    font: '600 10px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                                    stroke: palette.muted,
                                },
                                new go.Binding('visible', 'childrenCount', (count) => Number(count) > 0),
                                new go.Binding('text', 'childrenCount', (count) => `${count} perangkat turunan`),
                            ),
                        ),
                    ),
                );

                diagram.linkTemplate = $(
                    go.Link,
                    {
                        routing: go.Routing.Orthogonal,
                        corner: 8,
                        selectable: false,
                        layerName: 'Background',
                    },
                    $(go.Shape, {
                        name: 'LINK_SHAPE',
                        stroke: palette.link,
                        strokeWidth: 1.6,
                    }),
                );

                diagram.model = new go.TreeModel({
                    nodeParentKeyProperty: 'parent',
                    nodeDataArray: topologyNodes,
                });

                const overviewDiv = document.getElementById('myOverviewDiv');
                window.networkTopologyOverview = new go.Overview(overviewDiv, {
                    observed: diagram,
                    contentAlignment: go.Spot.Center,
                });

                const search = document.getElementById('networkSearch');
                const typeFilter = document.getElementById('networkTypeFilter');
                const unitFilter = document.getElementById('networkUnitFilter');
                const statusFilter = document.getElementById('networkStatusFilter');
                const emptyState = document.getElementById('networkEmptyState');
                const visibleCount = document.getElementById('networkVisibleCount');
                const parentByKey = new Map(topologyNodes.map((node) => [node.key, node.parent]));

                let filterTimer = null;

                function applyFilters({ fit = false } = {}) {
                    const query = search.value.trim().toLowerCase();
                    const type = typeFilter.value;
                    const unit = unitFilter.value;
                    const status = statusFilter.value;
                    const hasFilter = Boolean(query || type || unit || status);

                    const matched = new Set();

                    topologyNodes.forEach((node) => {
                        const haystack = [
                            node.name,
                            node.ip,
                            node.type,
                            node.unit,
                            node.location,
                            node.description,
                        ].filter(Boolean).join(' ').toLowerCase();

                        const matchesQuery = !query || haystack.includes(query);
                        const matchesType = !type || node.type === type;
                        const matchesUnit = !unit || node.unit === unit;
                        const matchesStatus = !status || node.status === status;

                        if (matchesQuery && matchesType && matchesUnit && matchesStatus) {
                            matched.add(node.key);
                        }
                    });

                    const visible = new Set(matched);

                    matched.forEach((key) => {
                        let parent = parentByKey.get(key);
                        const visited = new Set();

                        while (parent !== null && parent !== undefined && !visited.has(parent)) {
                            visited.add(parent);
                            visible.add(parent);
                            parent = parentByKey.get(parent);
                        }
                    });

                    diagram.startTransaction('filter topology');

                    diagram.nodes.each((node) => {
                        const show = !hasFilter || visible.has(node.data.key);
                        node.visible = show;
                        node.opacity = !hasFilter || matched.has(node.data.key) ? 1 : 0.45;
                    });

                    diagram.links.each((link) => {
                        link.visible = Boolean(link.fromNode?.visible && link.toNode?.visible);
                    });

                    diagram.commitTransaction('filter topology');

                    visibleCount.textContent = hasFilter ? matched.size : topologyNodes.length;
                    emptyState.classList.toggle('is-visible', hasFilter && matched.size === 0);

                    if (fit && matched.size > 0) {
                        requestAnimationFrame(() => diagram.zoomToFit());
                    }
                }

                [typeFilter, unitFilter, statusFilter].forEach((control) => {
                    control.addEventListener('change', () => applyFilters({ fit: true }));
                });

                search.addEventListener('input', () => {
                    window.clearTimeout(filterTimer);
                    filterTimer = window.setTimeout(() => applyFilters({ fit: false }), 180);
                });

                search.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        applyFilters({ fit: true });
                    }
                });

                document.querySelectorAll('[data-network-action]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const action = button.dataset.networkAction;

                        if (action === 'zoom-in') {
                            diagram.scale = Math.min(diagram.maxScale, diagram.scale * 1.2);
                        }

                        if (action === 'zoom-out') {
                            diagram.scale = Math.max(diagram.minScale, diagram.scale / 1.2);
                        }

                        if (action === 'zoom-reset') {
                            diagram.scale = 1;
                        }

                        if (action === 'fit') {
                            diagram.zoomToFit();
                        }

                        if (action === 'overview') {
                            overviewDiv.classList.toggle('is-hidden');
                        }

                        if (action === 'fullscreen') {
                            const shell = document.getElementById('networkTopologyShell');

                            if (!document.fullscreenElement) {
                                shell.requestFullscreen?.();
                            } else {
                                document.exitFullscreen?.();
                            }
                        }

                        if (action === 'reset') {
                            search.value = '';
                            typeFilter.value = '';
                            unitFilter.value = '';
                            statusFilter.value = '';

                            diagram.nodes.each((node) => {
                                if (node.findTreeChildrenNodes().count > 0) {
                                    node.isTreeExpanded = true;
                                }
                            });

                            applyFilters({ fit: true });
                            closeDetail();
                        }
                    });
                });

                document.addEventListener('fullscreenchange', () => {
                    window.setTimeout(() => {
                        diagram.requestUpdate();
                        diagram.zoomToFit();
                    }, 80);
                });

                function setDetailText(id, value) {
                    const element = document.getElementById(id);
                    if (element) element.textContent = value || '—';
                }

                function openDetail(data) {
                    const panel = document.getElementById('networkDetailPanel');
                    const status = document.getElementById('networkDetailStatus');
                    const statusDot = status.querySelector('.network-topology-dot');
                    const statusText = status.querySelector('span:last-child');
                    const editLink = document.getElementById('networkDetailEdit');

                    setDetailText('networkDetailName', data.name);
                    setDetailText('networkDetailType', data.type);
                    setDetailText('networkDetailIp', data.ip);
                    setDetailText('networkDetailUnit', data.unit);
                    setDetailText('networkDetailBuilding', data.building);
                    setDetailText('networkDetailFloor', data.floor);
                    setDetailText('networkDetailRoom', data.room);
                    setDetailText('networkDetailChildren', data.childrenCount ? `${data.childrenCount} perangkat` : 'Tidak ada');
                    setDetailText('networkDetailDescription', data.description);

                    statusDot.className = `network-topology-dot ${data.status || 'unknown'}`;
                    statusText.textContent = data.statusLabel || 'Tidak diketahui';

                    if (data.editUrl) {
                        editLink.href = data.editUrl;
                        editLink.classList.add('is-visible');
                    } else {
                        editLink.removeAttribute('href');
                        editLink.classList.remove('is-visible');
                    }

                    panel.classList.add('is-open');
                    panel.setAttribute('aria-hidden', 'false');
                }

                function closeDetail() {
                    const panel = document.getElementById('networkDetailPanel');
                    panel.classList.remove('is-open');
                    panel.setAttribute('aria-hidden', 'true');
                }

                document.getElementById('networkDetailClose').addEventListener('click', closeDetail);

                function applyTheme() {
                    const theme = colors();
                    diagramDiv.style.background = theme.canvas;
                    overviewDiv.style.background = theme.card;

                    diagram.nodes.each((node) => {
                        const card = node.findObject('CARD');
                        const badge = node.findObject('TYPE_BADGE');
                        const code = node.findObject('TYPE_CODE');
                        const name = node.findObject('NAME');
                        const statusText = node.findObject('STATUS_TEXT');
                        const separator = node.findObject('SEPARATOR');
                        const typeText = node.findObject('TYPE_TEXT');
                        const locationText = node.findObject('LOCATION_TEXT');
                        const childText = node.findObject('CHILD_TEXT');

                        if (card) {
                            card.fill = theme.card;
                            card.shadowColor = isDark() ? 'rgba(0,0,0,.35)' : 'rgba(15,23,42,.12)';
                        }
                        if (badge) {
                            badge.fill = theme.cardSoft;
                            badge.stroke = theme.border;
                        }
                        if (code) code.stroke = theme.text;
                        if (name) name.stroke = theme.text;
                        if (statusText) statusText.stroke = theme.muted;
                        if (separator) separator.stroke = theme.border;
                        if (typeText) typeText.stroke = theme.text;
                        if (locationText) locationText.stroke = theme.muted;
                        if (childText) childText.stroke = theme.muted;
                    });

                    diagram.links.each((link) => {
                        const shape = link.findObject('LINK_SHAPE');
                        if (shape) shape.stroke = theme.link;
                    });

                    diagram.requestUpdate();
                }

                const themeObserver = new MutationObserver((mutations) => {
                    if (mutations.some((mutation) => mutation.attributeName === 'class')) {
                        applyTheme();
                    }
                });

                themeObserver.observe(document.documentElement, { attributes: true });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootNetworkTopology, { once: true });
            } else {
                bootNetworkTopology();
            }

            document.addEventListener('livewire:navigated', bootNetworkTopology);
        })();
    </script>
</div>
