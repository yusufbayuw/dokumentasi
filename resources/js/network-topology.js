import cytoscape from 'cytoscape';
import elk from 'cytoscape-elk';

cytoscape.use(elk);

const statusColors = {
    online: '#16a34a',
    offline: '#dc2626',
    unknown: '#94a3b8',
};

const infrastructureCodes = new Set(['RT', 'FW', 'SW', 'AP', 'SV']);
const OVERVIEW_MAX_NODES = 110;
const FOCUS_MAX_NODES = 90;
const FOCUS_DEPTH = 2;

function getPalette() {
    const dark = document.documentElement.classList.contains('dark');

    return dark
        ? {
            canvas: '#0f172a',
            node: '#111827',
            nodeMuted: '#1e293b',
            infrastructure: '#172033',
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
            infrastructure: '#f8fbff',
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
                width: 184,
                height: 58,
                shape: 'round-rectangle',
                label: 'data(displayLabel)',
                color: palette.text,
                'font-family': 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                'font-size': 10.5,
                'font-weight': 600,
                'text-wrap': 'wrap',
                'text-max-width': 162,
                'text-valign': 'center',
                'text-halign': 'center',
                'min-zoomed-font-size': 9,
                'overlay-opacity': 0,
                'transition-property': 'opacity, border-width, border-color, background-color',
                'transition-duration': '140ms',
            },
        },
        {
            selector: 'node[infrastructure = 1]',
            style: {
                width: 208,
                height: 68,
                'background-color': palette.infrastructure,
                'font-size': 11.5,
                'font-weight': 700,
                'text-max-width': 184,
                'border-width': 2.5,
            },
        },
        { selector: 'node[status = "online"]', style: { 'border-color': statusColors.online } },
        { selector: 'node[status = "offline"]', style: { 'border-color': statusColors.offline } },
        { selector: 'node[status = "unknown"]', style: { 'border-color': statusColors.unknown } },
        { selector: 'node[criticality = "critical"]', style: { 'border-width': 4 } },
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
                width: 1.6,
                'line-color': palette.edge,
                'target-arrow-color': palette.edge,
                'target-arrow-shape': 'triangle',
                'curve-style': 'taxi',
                'taxi-direction': 'downward',
                'taxi-turn': 22,
                'arrow-scale': 0.7,
                opacity: 0.72,
                label: '',
                'font-size': 8,
                color: palette.muted,
                'min-zoomed-font-size': 10,
                'text-background-color': palette.canvas,
                'text-background-opacity': 0.92,
                'text-background-padding': 2,
                'text-rotation': 'autorotate',
                'overlay-opacity': 0,
                'transition-property': 'opacity, width, line-color, target-arrow-color',
                'transition-duration': '140ms',
            },
        },
        {
            selector: 'edge:selected',
            style: {
                label: 'data(displayLabel)',
                width: 3,
                'line-color': palette.primary,
                'target-arrow-color': palette.primary,
            },
        },
        { selector: 'edge[status != "active"]', style: { 'line-style': 'dashed', opacity: 0.42 } },
        { selector: 'edge[isPrimary = 0]', style: { 'line-style': 'dashed' } },
        { selector: '.analysis-muted', style: { opacity: 0.08 } },
        {
            selector: '.analysis-focus',
            style: {
                opacity: 1,
                'border-color': palette.primary,
                'line-color': palette.primary,
                'target-arrow-color': palette.primary,
                width: 3.2,
                'z-index': 999,
            },
        },
        {
            selector: 'edge.analysis-focus',
            style: {
                label: 'data(displayLabel)',
            },
        },
        { selector: '.search-context', style: { opacity: 0.42 } },
    ];
}

function nodeElement(node) {
    const compactType = node.typeCode || node.type;
    const secondary = [compactType, node.ip].filter(Boolean).join(' • ');
    const infrastructure = infrastructureCodes.has(node.typeCode) || Number(node.outgoingCount || 0) > 0;

    return {
        group: 'nodes',
        data: {
            ...node,
            infrastructure: infrastructure ? 1 : 0,
            baseLabel: secondary ? `${node.name}\n${secondary}` : node.name,
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

    if (!container || !data) return;

    if (window.networkTopologyCy) {
        window.networkTopologyCy.destroy();
        window.networkTopologyCy = null;
    }

    const palette = getPalette();
    const cy = cytoscape({
        container,
        elements: [
            ...(data.nodes || []).map(nodeElement),
            ...(data.edges || []).map(edgeElement),
        ],
        style: graphStyles(palette),
        minZoom: 0.12,
        maxZoom: 2.8,
        wheelSensitivity: 0.16,
        boxSelectionEnabled: false,
        autoungrabify: true,
        autounselectify: false,
        textureOnViewport: true,
        motionBlur: true,
        motionBlurOpacity: 0.1,
    });

    window.networkTopologyCy = cy;
    container.style.background = palette.canvas;

    const search = document.getElementById('networkSearch');
    const typeFilter = document.getElementById('networkTypeFilter');
    const unitFilter = document.getElementById('networkUnitFilter');
    const statusFilter = document.getElementById('networkStatusFilter');
    const emptyState = document.getElementById('networkEmptyState');
    const visibleCount = document.getElementById('networkVisibleCount');
    const scopeLabel = document.getElementById('networkScopeLabel');

    let activeLayout = 'layered';
    let scopeMode = 'overview';
    let selectedNode = null;
    let focusedNode = null;
    let filterTimer = null;

    function scoreNode(node) {
        const d = node.data();
        let score = node.outdegree() * 18 + node.indegree() * 2;

        if (infrastructureCodes.has(d.typeCode)) score += 50;
        if (d.criticality === 'critical') score += 70;
        if (d.criticality === 'high') score += 30;
        if (d.status === 'offline' && node.outdegree() > 0) score += 20;

        return score;
    }

    function collectionFromNodes(nodes) {
        return nodes.reduce((collection, node) => collection.union(node), cy.collection());
    }

    function rootNodes() {
        const roots = cy.nodes().filter((node) => node.indegree() === 0 && node.outdegree() > 0);
        return [...roots].sort((a, b) => scoreNode(b) - scoreNode(a));
    }

    function overviewNodes() {
        const candidates = [...cy.nodes().filter((node) => {
            const d = node.data();

            return node.outdegree() > 0
                || infrastructureCodes.has(d.typeCode)
                || d.criticality === 'critical'
                || d.criticality === 'high';
        })].sort((a, b) => scoreNode(b) - scoreNode(a));

        const selected = candidates.slice(0, OVERVIEW_MAX_NODES);
        let result = collectionFromNodes(selected);

        selected.forEach((node) => {
            result = result.union(node.predecessors('node'));
        });

        if (result.length < 8) {
            rootNodes().slice(0, 5).forEach((root) => {
                result = result.union(root);
                const children = [...root.outgoers('node')]
                    .sort((a, b) => scoreNode(b) - scoreNode(a))
                    .slice(0, 16);
                result = result.union(collectionFromNodes(children));
            });
        }

        return result;
    }

    function focusedBranchNodes(node) {
        if (!node) return overviewNodes();

        let result = node.union(node.predecessors('node'));
        let frontier = [node];
        let remaining = FOCUS_MAX_NODES - result.length;

        for (let depth = 0; depth < FOCUS_DEPTH && frontier.length > 0 && remaining > 0; depth += 1) {
            const next = [];

            frontier.forEach((parent) => {
                if (remaining <= 0) return;

                const children = [...parent.outgoers('node')]
                    .sort((a, b) => scoreNode(b) - scoreNode(a))
                    .slice(0, Math.min(24, remaining));

                children.forEach((child) => {
                    if (!result.contains(child) && remaining > 0) {
                        result = result.union(child);
                        next.push(child);
                        remaining -= 1;
                    }
                });
            });

            frontier = next;
        }

        return result;
    }

    function internalEdges(nodes) {
        return cy.edges().filter((edge) => nodes.contains(edge.source()) && nodes.contains(edge.target()));
    }

    function updateScopeButtons() {
        document.querySelectorAll('[data-network-scope]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.networkScope === scopeMode);
        });

        if (scopeLabel) {
            scopeLabel.textContent = scopeMode === 'all'
                ? 'Semua perangkat'
                : scopeMode === 'focus'
                    ? 'Fokus cabang'
                    : 'Topologi inti';
        }
    }

    function updateHiddenChildBadges(visibleNodes) {
        cy.nodes().forEach((node) => {
            const hiddenDirectChildren = node.outgoers('node').filter((child) => !visibleNodes.contains(child)).length;
            const base = node.data('baseLabel');
            node.data('displayLabel', hiddenDirectChildren > 0 ? `${base}\n+${hiddenDirectChildren} turunan` : base);
        });
    }

    function currentFilterState() {
        return {
            query: (search?.value || '').trim().toLowerCase(),
            type: typeFilter?.value || '',
            unit: unitFilter?.value || '',
            status: statusFilter?.value || '',
        };
    }

    function matchedNodes(filters) {
        return cy.nodes().filter((node) => {
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

            return (!filters.query || haystack.includes(filters.query))
                && (!filters.type || d.type === filters.type)
                && (!filters.unit || d.unit === filters.unit)
                && (!filters.status || d.status === filters.status);
        });
    }

    function runLayout(name = activeLayout, fit = true) {
        activeLayout = name;
        const visibleElements = cy.elements(':visible');

        if (visibleElements.nodes().length === 0) return;

        const layout = name === 'force'
            ? visibleElements.layout({
                name: 'cose',
                animate: false,
                fit,
                padding: 72,
                nodeRepulsion: 720000,
                idealEdgeLength: 120,
                edgeElasticity: 90,
                gravity: 0.35,
                numIter: 650,
                randomize: true,
            })
            : visibleElements.layout({
                name: 'elk',
                animate: false,
                fit,
                padding: 72,
                nodeDimensionsIncludeLabels: true,
                elk: {
                    algorithm: 'layered',
                    'elk.direction': 'DOWN',
                    'elk.edgeRouting': 'ORTHOGONAL',
                    'elk.spacing.nodeNode': '28',
                    'elk.layered.spacing.nodeNodeBetweenLayers': '72',
                    'elk.layered.nodePlacement.strategy': 'NETWORK_SIMPLEX',
                    'elk.layered.crossingMinimization.strategy': 'LAYER_SWEEP',
                    'elk.layered.considerModelOrder.strategy': 'NODES_AND_EDGES',
                },
            });

        layout.run();
    }

    function clearAnalysis() {
        cy.elements().removeClass('analysis-muted analysis-focus');
    }

    function applyView({ relayout = true, fit = true } = {}) {
        clearAnalysis();

        const filters = currentFilterState();
        const hasFilter = Boolean(filters.query || filters.type || filters.unit || filters.status);
        let visibleNodesForView;
        let contextNodes = cy.collection();
        let exactMatches = cy.collection();

        if (hasFilter) {
            exactMatches = matchedNodes(filters);
            visibleNodesForView = exactMatches;

            exactMatches.forEach((node) => {
                contextNodes = contextNodes.union(node.predecessors('node'));
            });

            visibleNodesForView = visibleNodesForView.union(contextNodes);
        } else if (scopeMode === 'all') {
            visibleNodesForView = cy.nodes();
        } else if (scopeMode === 'focus' && focusedNode) {
            visibleNodesForView = focusedBranchNodes(focusedNode);
        } else {
            visibleNodesForView = overviewNodes();
        }

        const visibleEdgesForView = internalEdges(visibleNodesForView);

        cy.batch(() => {
            cy.elements().hide().removeClass('search-context');
            visibleNodesForView.show();
            visibleEdgesForView.show();

            if (hasFilter) {
                contextNodes.difference(exactMatches).addClass('search-context');
            }
        });

        updateHiddenChildBadges(visibleNodesForView);
        updateScopeButtons();

        if (visibleCount) {
            visibleCount.textContent = hasFilter ? exactMatches.length : visibleNodesForView.length;
        }

        emptyState?.classList.toggle('is-visible', visibleNodesForView.length === 0);

        if (relayout && visibleNodesForView.length > 0) {
            runLayout(activeLayout, fit);
        } else if (fit && visibleNodesForView.length > 0) {
            cy.fit(visibleNodesForView.union(visibleEdgesForView), 84);
        }
    }

    function setDetailText(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value ?? '—';
    }

    function impactSummary(node) {
        const downstream = node.successors('node');
        const units = new Set(downstream.map((item) => item.data('unit')).filter(Boolean));

        if (downstream.length === 0) return 'Tidak ada perangkat downstream';

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

        if (editLink) {
            if (d.editUrl) {
                editLink.href = d.editUrl;
                editLink.classList.add('is-visible');
            } else {
                editLink.removeAttribute('href');
                editLink.classList.remove('is-visible');
            }
        }

        panel?.classList.add('is-open');
        panel?.setAttribute('aria-hidden', 'false');
    }

    function closeDetail({ keepSelection = false } = {}) {
        selectedNode = null;
        clearAnalysis();

        if (!keepSelection) {
            cy.$(':selected').unselect();
        }

        const panel = document.getElementById('networkDetailPanel');
        panel?.classList.remove('is-open');
        panel?.setAttribute('aria-hidden', 'true');
    }

    function highlightDirection(direction) {
        if (!selectedNode) return;

        clearAnalysis();
        const related = direction === 'upstream'
            ? selectedNode.predecessors()
            : selectedNode.successors();
        const focus = related.union(selectedNode);

        cy.elements().addClass('analysis-muted');
        focus.removeClass('analysis-muted').addClass('analysis-focus');
        cy.animate({ fit: { eles: focus, padding: 96 }, duration: 220 });
    }

    function focusSelectedBranch() {
        if (!selectedNode) return;

        focusedNode = selectedNode;
        scopeMode = 'focus';

        if (search) search.value = '';
        if (typeFilter) typeFilter.value = '';
        if (unitFilter) unitFilter.value = '';
        if (statusFilter) statusFilter.value = '';

        applyView({ relayout: true, fit: true });
        selectedNode.select();
        openDetail(selectedNode);
    }

    cy.on('tap', 'node', (event) => {
        clearAnalysis();
        openDetail(event.target);
    });

    cy.on('tap', 'edge', (event) => {
        cy.edges().unselect();
        event.target.select();
    });

    cy.on('tap', (event) => {
        if (event.target === cy) closeDetail();
    });

    [typeFilter, unitFilter, statusFilter].forEach((control) => {
        control?.addEventListener('change', () => applyView({ relayout: true, fit: true }));
    });

    search?.addEventListener('input', () => {
        window.clearTimeout(filterTimer);
        filterTimer = window.setTimeout(() => applyView({ relayout: true, fit: true }), 220);
    });

    document.querySelectorAll('[data-network-scope]').forEach((button) => {
        button.addEventListener('click', () => {
            scopeMode = button.dataset.networkScope === 'all' ? 'all' : 'overview';
            focusedNode = null;
            closeDetail();
            applyView({ relayout: true, fit: true });
        });
    });

    document.querySelectorAll('[data-network-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.networkAction;

            if (action === 'layout-layered') runLayout('layered', true);
            if (action === 'layout-force') runLayout('force', true);
            if (action === 'zoom-in') {
                cy.zoom({
                    level: Math.min(cy.maxZoom(), cy.zoom() * 1.2),
                    renderedPosition: { x: container.clientWidth / 2, y: container.clientHeight / 2 },
                });
            }
            if (action === 'zoom-out') {
                cy.zoom({
                    level: Math.max(cy.minZoom(), cy.zoom() / 1.2),
                    renderedPosition: { x: container.clientWidth / 2, y: container.clientHeight / 2 },
                });
            }
            if (action === 'fit') cy.fit(cy.elements(':visible'), 84);

            if (action === 'fullscreen') {
                const shell = document.getElementById('networkTopologyShell');
                if (!document.fullscreenElement) shell?.requestFullscreen?.();
                else document.exitFullscreen?.();
            }

            if (action === 'reset') {
                if (search) search.value = '';
                if (typeFilter) typeFilter.value = '';
                if (unitFilter) unitFilter.value = '';
                if (statusFilter) statusFilter.value = '';
                focusedNode = null;
                scopeMode = 'overview';
                closeDetail();
                applyView({ relayout: true, fit: true });
            }
        });
    });

    document.querySelectorAll('[data-network-analysis]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.networkAnalysis;
            if (action === 'focus') focusSelectedBranch();
            if (action === 'downstream') highlightDirection('downstream');
            if (action === 'upstream') highlightDirection('upstream');
            if (action === 'clear') clearAnalysis();
        });
    });

    document.getElementById('networkDetailClose')?.addEventListener('click', () => closeDetail());

    document.addEventListener('fullscreenchange', () => {
        window.setTimeout(() => {
            cy.resize();
            cy.fit(cy.elements(':visible'), 84);
        }, 80);
    });

    const themeObserver = new MutationObserver((mutations) => {
        if (!mutations.some((mutation) => mutation.attributeName === 'class')) return;
        const nextPalette = getPalette();
        container.style.background = nextPalette.canvas;
        cy.style(graphStyles(nextPalette));
    });

    window.networkTopologyThemeObserver?.disconnect?.();
    themeObserver.observe(document.documentElement, { attributes: true });
    window.networkTopologyThemeObserver = themeObserver;

    applyView({ relayout: true, fit: true });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootNetworkTopology, { once: true });
} else {
    bootNetworkTopology();
}

document.addEventListener('livewire:navigated', bootNetworkTopology);
