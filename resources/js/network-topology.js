import cytoscape from 'cytoscape';
import elk from 'cytoscape-elk';

cytoscape.use(elk);

const statusColors = {
    online: '#16a34a',
    offline: '#dc2626',
    unknown: '#94a3b8',
};

function getPalette() {
    const dark = document.documentElement.classList.contains('dark');

    return dark
        ? {
            canvas: '#0f172a',
            node: '#111827',
            nodeMuted: '#1e293b',
            border: '#334155',
            text: '#f8fafc',
            muted: '#94a3b8',
            edge: '#64748b',
            primary: '#60a5fa',
        }
        : {
            canvas: '#f8fafc',
            node: '#ffffff',
            nodeMuted: '#f1f5f9',
            border: '#cbd5e1',
            text: '#0f172a',
            muted: '#64748b',
            edge: '#94a3b8',
            primary: '#2563eb',
        };
}

function graphStyles(palette) {
    return [
        {
            selector: 'node',
            style: {
                'background-color': palette.node,
                'border-width': 2,
                'border-color': palette.border,
                'width': 220,
                'height': 78,
                'shape': 'round-rectangle',
                'label': 'data(displayLabel)',
                'color': palette.text,
                'font-family': 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                'font-size': 11,
                'font-weight': 600,
                'text-wrap': 'wrap',
                'text-max-width': 190,
                'text-valign': 'center',
                'text-halign': 'center',
                'overlay-opacity': 0,
                'transition-property': 'opacity, border-width, border-color, background-color',
                'transition-duration': '140ms',
            },
        },
        {
            selector: 'node[status = "online"]',
            style: { 'border-color': statusColors.online },
        },
        {
            selector: 'node[status = "offline"]',
            style: { 'border-color': statusColors.offline },
        },
        {
            selector: 'node[status = "unknown"]',
            style: { 'border-color': statusColors.unknown },
        },
        {
            selector: 'node[criticality = "critical"]',
            style: { 'border-width': 4 },
        },
        {
            selector: 'node:selected',
            style: {
                'border-width': 5,
                'border-color': palette.primary,
                'background-color': palette.nodeMuted,
            },
        },
        {
            selector: 'edge',
            style: {
                'width': 1.8,
                'line-color': palette.edge,
                'target-arrow-color': palette.edge,
                'target-arrow-shape': 'triangle',
                'curve-style': 'taxi',
                'taxi-direction': 'downward',
                'taxi-turn': 24,
                'arrow-scale': 0.8,
                'opacity': 0.85,
                'label': 'data(displayLabel)',
                'font-size': 8,
                'color': palette.muted,
                'text-background-color': palette.canvas,
                'text-background-opacity': 0.9,
                'text-background-padding': 2,
                'text-rotation': 'autorotate',
                'overlay-opacity': 0,
                'transition-property': 'opacity, width, line-color, target-arrow-color',
                'transition-duration': '140ms',
            },
        },
        {
            selector: 'edge[status != "active"]',
            style: {
                'line-style': 'dashed',
                'opacity': 0.45,
            },
        },
        {
            selector: 'edge[isPrimary = 0]',
            style: {
                'line-style': 'dashed',
            },
        },
        {
            selector: '.analysis-muted',
            style: { 'opacity': 0.1 },
        },
        {
            selector: '.analysis-focus',
            style: {
                'opacity': 1,
                'border-color': palette.primary,
                'line-color': palette.primary,
                'target-arrow-color': palette.primary,
                'width': 3.5,
                'z-index': 999,
            },
        },
        {
            selector: '.search-context',
            style: { 'opacity': 0.45 },
        },
    ];
}

function nodeElement(node) {
    const secondary = [node.type, node.ip].filter(Boolean).join(' • ');

    return {
        group: 'nodes',
        data: {
            ...node,
            displayLabel: secondary ? `${node.name}\n${secondary}` : node.name,
        },
    };
}

function edgeElement(edge) {
    const ports = [edge.sourcePort, edge.targetPort].filter(Boolean).join(' → ');
    const meta = [edge.label, edge.speed, edge.vlan ? `VLAN ${edge.vlan}` : null, ports]
        .filter(Boolean)
        .join(' • ');

    return {
        group: 'edges',
        data: {
            ...edge,
            isPrimary: edge.isPrimary ? 1 : 0,
            displayLabel: meta,
        },
    };
}

function bootNetworkTopology() {
    const container = document.getElementById('networkGraph');
    const data = window.networkTopologyData;

    if (! container || ! data) {
        return;
    }

    if (window.networkTopologyCy) {
        window.networkTopologyCy.destroy();
        window.networkTopologyCy = null;
    }

    const palette = getPalette();
    const elements = [
        ...(data.nodes || []).map(nodeElement),
        ...(data.edges || []).map(edgeElement),
    ];

    const cy = cytoscape({
        container,
        elements,
        style: graphStyles(palette),
        minZoom: 0.08,
        maxZoom: 2.6,
        wheelSensitivity: 0.18,
        boxSelectionEnabled: false,
        autoungrabify: true,
        autounselectify: false,
        textureOnViewport: true,
        motionBlur: true,
        motionBlurOpacity: 0.12,
    });

    window.networkTopologyCy = cy;
    container.style.background = palette.canvas;

    const search = document.getElementById('networkSearch');
    const typeFilter = document.getElementById('networkTypeFilter');
    const unitFilter = document.getElementById('networkUnitFilter');
    const statusFilter = document.getElementById('networkStatusFilter');
    const emptyState = document.getElementById('networkEmptyState');
    const visibleCount = document.getElementById('networkVisibleCount');

    let activeLayout = 'layered';
    let selectedNode = null;
    let filterTimer = null;

    function runLayout(name = activeLayout, fit = true) {
        activeLayout = name;

        const visibleElements = cy.elements(':visible');
        const layout = name === 'force'
            ? visibleElements.layout({
                name: 'cose',
                animate: false,
                fit,
                padding: 70,
                nodeRepulsion: 900000,
                idealEdgeLength: 130,
                edgeElasticity: 80,
                gravity: 0.3,
                numIter: 700,
                randomize: true,
            })
            : visibleElements.layout({
                name: 'elk',
                animate: false,
                fit,
                padding: 70,
                nodeDimensionsIncludeLabels: true,
                elk: {
                    algorithm: 'layered',
                    'elk.direction': 'DOWN',
                    'elk.edgeRouting': 'ORTHOGONAL',
                    'elk.spacing.nodeNode': '36',
                    'elk.layered.spacing.nodeNodeBetweenLayers': '85',
                    'elk.layered.nodePlacement.strategy': 'NETWORK_SIMPLEX',
                    'elk.layered.crossingMinimization.strategy': 'LAYER_SWEEP',
                },
            });

        layout.run();
    }

    function clearAnalysis() {
        cy.elements().removeClass('analysis-muted analysis-focus');
    }

    function applyFilters({ relayout = false } = {}) {
        clearAnalysis();

        const query = (search?.value || '').trim().toLowerCase();
        const type = typeFilter?.value || '';
        const unit = unitFilter?.value || '';
        const status = statusFilter?.value || '';
        const hasFilter = Boolean(query || type || unit || status);

        cy.batch(() => {
            cy.elements().show().removeClass('search-context');

            if (! hasFilter) {
                return;
            }

            const matched = cy.nodes().filter((node) => {
                const d = node.data();
                const haystack = [
                    d.name,
                    d.ip,
                    d.assetCode,
                    d.hostname,
                    d.type,
                    d.unit,
                    d.ownerUnit,
                    d.location,
                    d.description,
                ].filter(Boolean).join(' ').toLowerCase();

                return (! query || haystack.includes(query))
                    && (! type || d.type === type)
                    && (! unit || d.unit === unit)
                    && (! status || d.status === status);
            });

            let context = matched;
            matched.forEach((node) => {
                context = context.union(node.predecessors());
            });

            cy.elements().hide();
            context.show();
            context.edges().show();
            context.nodes().difference(matched).addClass('search-context');
        });

        const shownNodes = cy.nodes(':visible');
        visibleCount.textContent = hasFilter ? shownNodes.filter(':not(.search-context)').length : cy.nodes().length;
        emptyState?.classList.toggle('is-visible', hasFilter && shownNodes.length === 0);

        if (relayout && shownNodes.length > 0) {
            runLayout(activeLayout, true);
        }
    }

    function setDetailText(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value ?? '—';
    }

    function impactSummary(node) {
        const downstream = node.successors('node');
        const units = new Set(
            downstream
                .map((item) => item.data('unit'))
                .filter(Boolean),
        );

        if (downstream.length === 0) {
            return 'Tidak ada perangkat downstream';
        }

        return `${downstream.length} perangkat${units.size ? ` / ${units.size} unit` : ''}`;
    }

    function openDetail(node) {
        selectedNode = node;
        const d = node.data();
        const panel = document.getElementById('networkDetailPanel');
        const statusElement = document.getElementById('networkDetailStatus');
        const statusDot = statusElement?.querySelector('.network-topology-dot');
        const statusText = statusElement?.querySelector('span:last-child');
        const editLink = document.getElementById('networkDetailEdit');

        setDetailText('networkDetailName', d.name);
        setDetailText('networkDetailType', d.type);
        setDetailText('networkDetailAssetCode', d.assetCode);
        setDetailText('networkDetailHostname', d.hostname);
        setDetailText('networkDetailIp', d.ip);
        setDetailText('networkDetailCriticality', d.criticalityLabel);
        setDetailText('networkDetailLifecycle', d.lifecycleLabel);
        setDetailText('networkDetailLatency', d.latency !== null && d.latency !== undefined ? `${d.latency} ms` : '—');
        setDetailText('networkDetailLastSeen', d.lastSeenAt);
        setDetailText('networkDetailLastChecked', d.lastCheckedAt);
        setDetailText('networkDetailMonitoring', d.monitoringEnabled ? 'Aktif' : 'Nonaktif');
        setDetailText('networkDetailUnit', d.unit);
        setDetailText('networkDetailOwnerUnit', d.ownerUnit);
        setDetailText('networkDetailBuilding', d.building);
        setDetailText('networkDetailFloor', d.floor);
        setDetailText('networkDetailRoom', d.room);
        setDetailText('networkDetailIncoming', `${node.indegree()} koneksi`);
        setDetailText('networkDetailOutgoing', `${node.outdegree()} koneksi`);
        setDetailText('networkDetailImpact', impactSummary(node));
        setDetailText('networkDetailDescription', d.description);

        if (statusDot) statusDot.className = `network-topology-dot ${d.status || 'unknown'}`;
        if (statusText) statusText.textContent = d.statusLabel || 'Tidak diketahui';

        if (d.editUrl) {
            editLink.href = d.editUrl;
            editLink.classList.add('is-visible');
        } else {
            editLink.removeAttribute('href');
            editLink.classList.remove('is-visible');
        }

        panel?.classList.add('is-open');
        panel?.setAttribute('aria-hidden', 'false');
    }

    function closeDetail() {
        selectedNode = null;
        clearAnalysis();
        cy.$(':selected').unselect();

        const panel = document.getElementById('networkDetailPanel');
        panel?.classList.remove('is-open');
        panel?.setAttribute('aria-hidden', 'true');
    }

    function highlightDirection(direction) {
        if (! selectedNode) return;

        clearAnalysis();
        const related = direction === 'upstream'
            ? selectedNode.predecessors()
            : selectedNode.successors();
        const focus = related.union(selectedNode);

        cy.elements().addClass('analysis-muted');
        focus.removeClass('analysis-muted').addClass('analysis-focus');
        cy.animate({ fit: { eles: focus, padding: 90 }, duration: 220 });
    }

    cy.on('tap', 'node', (event) => {
        clearAnalysis();
        openDetail(event.target);
    });

    cy.on('tap', (event) => {
        if (event.target === cy) {
            closeDetail();
        }
    });

    [typeFilter, unitFilter, statusFilter].forEach((control) => {
        control?.addEventListener('change', () => applyFilters({ relayout: true }));
    });

    search?.addEventListener('input', () => {
        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(() => applyFilters({ relayout: false }), 180);
    });

    search?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            applyFilters({ relayout: true });
        }
    });

    document.querySelectorAll('[data-network-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.networkAction;

            if (action === 'layout-layered') runLayout('layered', true);
            if (action === 'layout-force') runLayout('force', true);
            if (action === 'zoom-in') cy.zoom({ level: Math.min(cy.maxZoom(), cy.zoom() * 1.2), renderedPosition: { x: container.clientWidth / 2, y: container.clientHeight / 2 } });
            if (action === 'zoom-out') cy.zoom({ level: Math.max(cy.minZoom(), cy.zoom() / 1.2), renderedPosition: { x: container.clientWidth / 2, y: container.clientHeight / 2 } });
            if (action === 'fit') cy.fit(cy.elements(':visible'), 70);

            if (action === 'fullscreen') {
                const shell = document.getElementById('networkTopologyShell');

                if (! document.fullscreenElement) shell?.requestFullscreen?.();
                else document.exitFullscreen?.();
            }

            if (action === 'reset') {
                if (search) search.value = '';
                if (typeFilter) typeFilter.value = '';
                if (unitFilter) unitFilter.value = '';
                if (statusFilter) statusFilter.value = '';
                closeDetail();
                applyFilters({ relayout: true });
            }
        });
    });

    document.querySelectorAll('[data-network-analysis]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.networkAnalysis;

            if (action === 'downstream') highlightDirection('downstream');
            if (action === 'upstream') highlightDirection('upstream');
            if (action === 'clear') clearAnalysis();
        });
    });

    document.getElementById('networkDetailClose')?.addEventListener('click', closeDetail);

    document.addEventListener('fullscreenchange', () => {
        window.setTimeout(() => {
            cy.resize();
            cy.fit(cy.elements(':visible'), 70);
        }, 80);
    });

    const themeObserver = new MutationObserver((mutations) => {
        if (! mutations.some((mutation) => mutation.attributeName === 'class')) return;

        const nextPalette = getPalette();
        container.style.background = nextPalette.canvas;
        cy.style(graphStyles(nextPalette));
    });

    themeObserver.observe(document.documentElement, { attributes: true });
    window.networkTopologyThemeObserver?.disconnect?.();
    window.networkTopologyThemeObserver = themeObserver;

    runLayout('layered', true);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootNetworkTopology, { once: true });
} else {
    bootNetworkTopology();
}

document.addEventListener('livewire:navigated', bootNetworkTopology);
