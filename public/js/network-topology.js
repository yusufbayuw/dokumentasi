(() => {
    function bootNetworkTopology() {
        const diagramDiv = document.getElementById('myDiagramDiv');
        const topologyNodes = window.networkTopologyNodes || [];

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
            initialAutoScale: go.Diagram.Uniform,
            contentAlignment: go.Spot.Center,
            padding: new go.Margin(70, 80, 70, 80),
            minScale: 0.18,
            maxScale: 2.2,
            allowMove: false,
            allowCopy: false,
            allowDelete: false,
            allowInsert: false,
            'undoManager.isEnabled': false,
            'toolManager.mouseWheelBehavior': go.ToolManager.WheelZoom,
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
                isShadowed: true,
                shadowBlur: 10,
                shadowOffset: new go.Point(0, 3),
                shadowColor: isDark() ? 'rgba(0,0,0,.35)' : 'rgba(15,23,42,.12)',
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
                },
                new go.Binding('stroke', 'status', statusColor),
            ),
            $(
                go.Panel,
                'Vertical',
                {
                    width: 242,
                    defaultStretch: go.GraphObject.Horizontal,
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
                            wrap: go.TextBlock.WrapFit,
                            overflow: go.TextBlock.OverflowEllipsis,
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
                            overflow: go.TextBlock.OverflowEllipsis,
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
                            wrap: go.TextBlock.WrapFit,
                            overflow: go.TextBlock.OverflowEllipsis,
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
                routing: go.Link.Orthogonal,
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
                    node.assetCode,
                    node.hostname,
                    node.ip,
                    node.type,
                    node.unit,
                    node.ownerUnit,
                    node.location,
                    node.criticalityLabel,
                    node.lifecycleLabel,
                    node.description,
                ].filter(Boolean).join(' ').toLowerCase();

                if (
                    (!query || haystack.includes(query))
                    && (!type || node.type === type)
                    && (!unit || node.unit === unit)
                    && (!status || node.status === status)
                ) {
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

                if (action === 'zoom-in') diagram.scale = Math.min(diagram.maxScale, diagram.scale * 1.2);
                if (action === 'zoom-out') diagram.scale = Math.max(diagram.minScale, diagram.scale / 1.2);
                if (action === 'zoom-reset') diagram.scale = 1;
                if (action === 'fit') diagram.zoomToFit();
                if (action === 'overview') overviewDiv.classList.toggle('is-hidden');

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
            setDetailText('networkDetailAssetCode', data.assetCode);
            setDetailText('networkDetailHostname', data.hostname);
            setDetailText('networkDetailIp', data.ip);
            setDetailText('networkDetailCriticality', data.criticalityLabel);
            setDetailText('networkDetailLifecycle', data.lifecycleLabel);
            setDetailText('networkDetailLatency', data.latency !== null && data.latency !== undefined ? `${data.latency} ms` : null);
            setDetailText('networkDetailLastSeen', data.lastSeenAt);
            setDetailText('networkDetailLastChecked', data.lastCheckedAt);
            setDetailText('networkDetailMonitoring', data.monitoringEnabled ? 'Aktif' : 'Nonaktif');
            setDetailText('networkDetailUnit', data.unit);
            setDetailText('networkDetailOwnerUnit', data.ownerUnit);
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

        document.getElementById('networkDetailClose')?.addEventListener('click', closeDetail);

        function applyTheme() {
            const theme = colors();
            diagramDiv.style.background = theme.canvas;
            overviewDiv.style.background = theme.card;

            diagram.nodes.each((node) => {
                node.shadowColor = isDark() ? 'rgba(0,0,0,.35)' : 'rgba(15,23,42,.12)';

                const card = node.findObject('CARD');
                const badge = node.findObject('TYPE_BADGE');
                const code = node.findObject('TYPE_CODE');
                const name = node.findObject('NAME');
                const statusText = node.findObject('STATUS_TEXT');
                const separator = node.findObject('SEPARATOR');
                const typeText = node.findObject('TYPE_TEXT');
                const locationText = node.findObject('LOCATION_TEXT');
                const childText = node.findObject('CHILD_TEXT');

                if (card) card.fill = theme.card;
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
