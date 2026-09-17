import cytoscape from 'cytoscape';
import elk from 'cytoscape-elk';

cytoscape.use(elk);

const INFRA_CODES = new Set(['RT', 'FW', 'SW', 'SV']);
const BACKBONE_MAX = 90;
const HEALTH_MAX = 90;
const DEPENDENCY_MAX = 90;

const STATUS_COLORS = {
    online: '#16a34a',
    offline: '#dc2626',
    unknown: '#94a3b8',
};

function palette() {
    const dark = document.documentElement.classList.contains('dark');

    return dark
        ? {
            canvas: '#0b1220',
            node: '#111827',
            nodeSoft: '#172033',
            group: '#132039',
            groupBorder: '#3b82f6',
            border: '#334155',
            text: '#f8fafc',
            muted: '#94a3b8',
            edge: '#64748b',
            primary: '#60a5fa',
        }
        : {
            canvas: '#f8fafc',
            node: '#ffffff',
            nodeSoft: '#f1f5f9',
            group: '#eff6ff',
            groupBorder: '#3b82f6',
            border: '#cbd5e1',
            text: '#0f172a',
            muted: '#64748b',
            edge: '#94a3b8',
            primary: '#2563eb',
        };
}

function styles(p) {
    return [
        {
            selector: 'node.device',
            style: {
                'background-color': p.node,
                'border-width': 2,
                'border-color': p.border,
                width: 184,
                height: 60,
                shape: 'round-rectangle',
                label: 'data(displayLabel)',
                color: p.text,
                'font-family': 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                'font-size': 10.5,
                'font-weight': 600,
                'text-wrap': 'wrap',
                'text-max-width': 160,
                'text-valign': 'center',
                'text-halign': 'center',
                'min-zoomed-font-size': 7,
                'overlay-opacity': 0,
            },
        },
        {
            selector: 'node.device.infrastructure',
            style: {
                width: 208,
                height: 70,
                'background-color': p.nodeSoft,
                'font-size': 11,
                'font-weight': 700,
                'text-max-width': 184,
                'border-width': 2.5,
            },
        },
        {
            selector: 'node.group',
            style: {
                'background-color': p.group,
                'border-width': 2,
                'border-color': p.groupBorder,
                width: 230,
                height: 94,
                shape: 'round-rectangle',
                label: 'data(displayLabel)',
                color: p.text,
                'font-family': 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                'font-size': 12,
                'font-weight': 700,
                'text-wrap': 'wrap',
                'text-max-width': 200,
                'text-valign': 'center',
                'text-halign': 'center',
                'overlay-opacity': 0,
            },
        },
        {
            selector: 'node.scope-root',
            style: {
                width: 250,
                height: 86,
                'background-color': p.nodeSoft,
                'border-width': 3,
                'border-color': p.primary,
                'font-size': 13,
            },
        },
        { selector: 'node[status = "online"]', style: { 'border-color': STATUS_COLORS.online } },
        { selector: 'node[status = "offline"]', style: { 'border-color': STATUS_COLORS.offline } },
        { selector: 'node[status = "unknown"]', style: { 'border-color': STATUS_COLORS.unknown } },
        { selector: 'node[criticality = "critical"]', style: { 'border-width': 4 } },
        {
            selector: 'node:selected',
            style: {
                'border-width': 5,
                'border-color': p.primary,
                'background-color': p.nodeSoft,
            },
        },
        {
            selector: 'edge',
            style: {
                width: 1.7,
                'line-color': p.edge,
                'target-arrow-color': p.edge,
                'target-arrow-shape': 'triangle',
                'curve-style': 'taxi',
                'taxi-direction': 'downward',
                'taxi-turn': 22,
                'arrow-scale': 0.72,
                opacity: 0.72,
                label: '',
                'font-size': 8,
                color: p.muted,
                'text-background-color': p.canvas,
                'text-background-opacity': 0.94,
                'text-background-padding': 2,
                'text-rotation': 'autorotate',
                'overlay-opacity': 0,
            },
        },
        {
            selector: 'edge.group-edge',
            style: {
                width: 2,
                opacity: 0.58,
                'target-arrow-shape': 'none',
            },
        },
        {
            selector: 'edge:selected, edge.path-focus',
            style: {
                width: 3.5,
                'line-color': p.primary,
                'target-arrow-color': p.primary,
                label: 'data(displayLabel)',
                opacity: 1,
            },
        },
        { selector: 'edge[status != "active"]', style: { 'line-style': 'dashed', opacity: 0.45 } },
        { selector: 'edge[isPrimary = 0]', style: { 'line-style': 'dashed' } },
        { selector: '.analysis-muted', style: { opacity: 0.08 } },
        {
            selector: '.analysis-focus',
            style: {
                opacity: 1,
                'border-color': p.primary,
                'line-color': p.primary,
                'target-arrow-color': p.primary,
                'z-index': 999,
            },
        },
    ];
}

function safe(value, fallback = '—') {
    return value === null || value === undefined || value === '' ? fallback : value;
}

function bootNetworkTopology() {
    const container = document.getElementById('networkGraph');
    const raw = window.networkTopologyData;

    if (!container || !raw) return;

    window.networkTopologyCy?.destroy?.();

    const rawNodes = raw.nodes || [];
    const rawEdges = raw.edges || [];
    const nodeById = new Map(rawNodes.map((node) => [node.id, node]));
    const outgoing = new Map();
    const incoming = new Map();

    rawNodes.forEach((node) => {
        outgoing.set(node.id, []);
        incoming.set(node.id, []);
    });

    rawEdges.forEach((edge) => {
        if (outgoing.has(edge.source)) outgoing.get(edge.source).push(edge.target);
        if (incoming.has(edge.target)) incoming.get(edge.target).push(edge.source);
    });

    const p = palette();
    const cy = cytoscape({
        container,
        elements: [],
        style: styles(p),
        minZoom: 0.18,
        maxZoom: 3.2,
        wheelSensitivity: 0.16,
        boxSelectionEnabled: false,
        autoungrabify: true,
        textureOnViewport: true,
        motionBlur: true,
        motionBlurOpacity: 0.08,
    });

    window.networkTopologyCy = cy;
    container.style.background = p.canvas;

    const search = document.getElementById('networkSearch');
    const searchResults = document.getElementById('networkSearchResults');
    const viewMode = document.getElementById('networkViewMode');
    const unitFilter = document.getElementById('networkUnitFilter');
    const statusFilter = document.getElementById('networkStatusFilter');
    const visibleCount = document.getElementById('networkVisibleCount');
    const description = document.getElementById('networkViewDescription');
    const emptyState = document.getElementById('networkEmptyState');
    const breadcrumb = document.getElementById('networkBreadcrumb');
    const stage = document.getElementById('networkTopologyStage');

    let mode = 'overview';
    let activeLayout = 'layered';
    let physicalPath = [];
    let focusedDeviceId = null;
    let selectedDeviceId = null;
    let semanticLevel = 'mid';
    let searchTimer = null;

    function baseFilteredNodes() {
        const unit = unitFilter?.value || '';
        const status = statusFilter?.value || '';

        return rawNodes.filter((node) => {
            const unitMatch = !unit || node.unit === unit || node.ownerUnit === unit;
            const statusMatch = !status || node.status === status;
            return unitMatch && statusMatch;
        });
    }

    function score(node) {
        let value = Number(node.outgoingCount || 0) * 20 + Number(node.incomingCount || 0) * 3;
        if (INFRA_CODES.has(node.typeCode)) value += 70;
        if (node.criticality === 'critical') value += 90;
        if (node.criticality === 'high') value += 35;
        if (node.status === 'offline' && Number(node.outgoingCount || 0) > 0) value += 25;
        return value;
    }

    function traverse(startId, direction, { depth = 10, max = 100 } = {}) {
        const adjacency = direction === 'up' ? incoming : outgoing;
        const visited = new Set([startId]);
        let frontier = [startId];

        for (let currentDepth = 0; currentDepth < depth && frontier.length && visited.size < max; currentDepth += 1) {
            const next = [];

            frontier.forEach((id) => {
                for (const neighbor of adjacency.get(id) || []) {
                    if (visited.size >= max) break;
                    if (!visited.has(neighbor)) {
                        visited.add(neighbor);
                        next.push(neighbor);
                    }
                }
            });

            frontier = next;
        }

        return visited;
    }

    function deviceElement(node) {
        const infra = INFRA_CODES.has(node.typeCode) || Number(node.outgoingCount || 0) > 0;
        const location = [node.room, node.floor, node.building].filter(Boolean).join(' • ');
        const mid = [node.typeCode || node.type, node.ip].filter(Boolean).join(' • ');

        return {
            group: 'nodes',
            classes: `device ${infra ? 'infrastructure' : 'endpoint'}`,
            data: {
                ...node,
                nodeKind: 'device',
                labelLow: node.name,
                labelMid: mid ? `${node.name}\n${mid}` : node.name,
                labelHigh: [node.name, mid, location].filter(Boolean).join('\n'),
                displayLabel: mid ? `${node.name}\n${mid}` : node.name,
            },
        };
    }

    function deviceEdgeElement(edge) {
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

    function groupHealth(nodes) {
        const offline = nodes.filter((node) => node.status === 'offline').length;
        const unknown = nodes.filter((node) => node.status === 'unknown').length;
        const criticalOffline = nodes.filter((node) => node.status === 'offline' && node.criticality === 'critical').length;

        if (criticalOffline > 0 || offline >= 5) return 'offline';
        if (offline > 0 || unknown > 0) return 'unknown';
        return 'online';
    }

    function groupElement(id, name, nodes, groupLevel, value, parentId = null) {
        const online = nodes.filter((node) => node.status === 'online').length;
        const offline = nodes.filter((node) => node.status === 'offline').length;
        const unknown = nodes.filter((node) => node.status === 'unknown').length;
        const healthLine = offline > 0 ? `${offline} offline` : unknown > 0 ? `${unknown} unknown` : 'Semua terpantau baik';

        return {
            group: 'nodes',
            classes: 'group',
            data: {
                id,
                nodeKind: 'group',
                groupLevel,
                groupValue: value,
                name,
                count: nodes.length,
                online,
                offline,
                unknown,
                status: groupHealth(nodes),
                labelLow: `${name}\n${nodes.length} perangkat`,
                labelMid: `${name}\n${nodes.length} perangkat • ${healthLine}`,
                labelHigh: `${name}\n${nodes.length} perangkat\n${online} online • ${offline} offline • ${unknown} unknown`,
                displayLabel: `${name}\n${nodes.length} perangkat • ${healthLine}`,
                parentGroupId: parentId,
            },
        };
    }

    function rootElement(label, subtitle) {
        return {
            group: 'nodes',
            classes: 'group scope-root',
            data: {
                id: 'scope-root',
                nodeKind: 'scope',
                name: label,
                labelLow: label,
                labelMid: `${label}\n${subtitle}`,
                labelHigh: `${label}\n${subtitle}`,
                displayLabel: `${label}\n${subtitle}`,
            },
        };
    }

    function syntheticEdge(id, source, target) {
        return {
            group: 'edges',
            classes: 'group-edge',
            data: { id, source, target, status: 'active', isPrimary: 1, displayLabel: '' },
        };
    }

    function groupBy(items, selector) {
        const map = new Map();
        items.forEach((item) => {
            const key = selector(item) || 'Tanpa Data';
            if (!map.has(key)) map.set(key, []);
            map.get(key).push(item);
        });
        return map;
    }

    function overviewElements(nodes) {
        const elements = [rootElement('ICT YTB', `${nodes.length} perangkat terdata`)];
        const buildings = [...groupBy(nodes, (node) => node.building || 'Tanpa Gedung').entries()]
            .sort((a, b) => b[1].length - a[1].length);

        buildings.forEach(([building, members], index) => {
            const id = `group-building-${index}`;
            elements.push(groupElement(id, building, members, 'building', building));
            elements.push(syntheticEdge(`group-edge-${index}`, 'scope-root', id));
        });

        return elements;
    }

    function physicalElements(nodes) {
        if (physicalPath.length === 0) return overviewElements(nodes);

        const building = physicalPath[0]?.value;
        const buildingNodes = nodes.filter((node) => (node.building || 'Tanpa Gedung') === building);

        if (physicalPath.length === 1) {
            const elements = [rootElement(building, `${buildingNodes.length} perangkat`)];
            [...groupBy(buildingNodes, (node) => node.floor || 'Tanpa Lantai').entries()]
                .sort((a, b) => b[1].length - a[1].length)
                .forEach(([floor, members], index) => {
                    const id = `group-floor-${index}`;
                    elements.push(groupElement(id, floor, members, 'floor', floor));
                    elements.push(syntheticEdge(`floor-edge-${index}`, 'scope-root', id));
                });
            return elements;
        }

        const floor = physicalPath[1]?.value;
        const floorNodes = buildingNodes.filter((node) => (node.floor || 'Tanpa Lantai') === floor);

        if (physicalPath.length === 2) {
            const elements = [rootElement(`${building} / ${floor}`, `${floorNodes.length} perangkat`)];
            [...groupBy(floorNodes, (node) => node.room || 'Tanpa Ruangan').entries()]
                .sort((a, b) => b[1].length - a[1].length)
                .forEach(([room, members], index) => {
                    const id = `group-room-${index}`;
                    elements.push(groupElement(id, room, members, 'room', room));
                    elements.push(syntheticEdge(`room-edge-${index}`, 'scope-root', id));
                });
            return elements;
        }

        const room = physicalPath[2]?.value;
        const roomNodes = floorNodes.filter((node) => (node.room || 'Tanpa Ruangan') === room);
        const elements = [rootElement(room, `${roomNodes.length} perangkat`), ...roomNodes.map(deviceElement)];
        roomNodes.forEach((node, index) => elements.push(syntheticEdge(`device-edge-${index}`, 'scope-root', node.id)));
        return elements;
    }

    function backboneElements(nodes) {
        const candidates = nodes
            .filter((node) => INFRA_CODES.has(node.typeCode) || Number(node.outgoingCount || 0) > 0 || ['critical', 'high'].includes(node.criticality))
            .sort((a, b) => score(b) - score(a))
            .slice(0, BACKBONE_MAX);
        const ids = new Set(candidates.map((node) => node.id));
        const edges = rawEdges.filter((edge) => ids.has(edge.source) && ids.has(edge.target));

        return [...candidates.map(deviceElement), ...edges.map(deviceEdgeElement)];
    }

    function healthElements(nodes) {
        const problematic = nodes
            .filter((node) => node.status === 'offline' || (node.status === 'unknown' && ['critical', 'high'].includes(node.criticality)))
            .sort((a, b) => score(b) - score(a))
            .slice(0, 45);

        const ids = new Set();
        problematic.forEach((node) => {
            traverse(node.id, 'up', { depth: 4, max: 20 }).forEach((id) => ids.add(id));
            ids.add(node.id);
        });

        const selectedIds = [...ids].slice(0, HEALTH_MAX);
        const selectedSet = new Set(selectedIds);
        const selectedNodes = selectedIds.map((id) => nodeById.get(id)).filter(Boolean);
        const edges = rawEdges.filter((edge) => selectedSet.has(edge.source) && selectedSet.has(edge.target));

        return [...selectedNodes.map(deviceElement), ...edges.map(deviceEdgeElement)];
    }

    function dependencyElements() {
        if (!focusedDeviceId || !nodeById.has(focusedDeviceId)) {
            const fallback = [...rawNodes].sort((a, b) => score(b) - score(a))[0];
            focusedDeviceId = fallback?.id || null;
        }

        if (!focusedDeviceId) return [];

        const ids = new Set();
        traverse(focusedDeviceId, 'up', { depth: 12, max: 30 }).forEach((id) => ids.add(id));
        traverse(focusedDeviceId, 'down', { depth: 3, max: DEPENDENCY_MAX }).forEach((id) => ids.add(id));
        ids.add(focusedDeviceId);

        const selectedIds = [...ids].slice(0, DEPENDENCY_MAX);
        const selectedSet = new Set(selectedIds);
        const nodes = selectedIds.map((id) => nodeById.get(id)).filter(Boolean);
        const edges = rawEdges.filter((edge) => selectedSet.has(edge.source) && selectedSet.has(edge.target));

        return [...nodes.map(deviceElement), ...edges.map(deviceEdgeElement)];
    }

    function buildElements() {
        const nodes = baseFilteredNodes();

        if (mode === 'backbone') return backboneElements(nodes);
        if (mode === 'physical') return physicalElements(nodes);
        if (mode === 'health') return healthElements(nodes);
        if (mode === 'dependency') return dependencyElements();
        return overviewElements(nodes);
    }

    function modeDescription() {
        if (mode === 'backbone') return 'Jalur perangkat inti dan uplink jaringan';
        if (mode === 'physical') return 'Drill-down Gedung → Lantai → Ruangan → Perangkat';
        if (mode === 'health') return 'Perangkat bermasalah beserta konteks upstream';
        if (mode === 'dependency') return 'Jalur upstream dan downstream perangkat terpilih';
        return 'Ringkasan kondisi infrastruktur per gedung';
    }

    function breadcrumbItems() {
        const items = [{ label: 'Infrastructure', mode: 'overview' }];

        if (mode === 'physical') {
            items.push({ label: 'Lokasi Fisik', mode: 'physical', depth: 0 });
            physicalPath.forEach((item, index) => items.push({ label: item.label, mode: 'physical', depth: index + 1 }));
        } else if (mode === 'backbone') {
            items.push({ label: 'Backbone', mode: 'backbone' });
        } else if (mode === 'health') {
            items.push({ label: 'Health', mode: 'health' });
        } else if (mode === 'dependency') {
            items.push({ label: 'Dependency', mode: 'dependency' });
            if (focusedDeviceId && nodeById.has(focusedDeviceId)) items.push({ label: nodeById.get(focusedDeviceId).name, mode: 'dependency' });
        }

        return items;
    }

    function renderBreadcrumb() {
        if (!breadcrumb) return;
        breadcrumb.replaceChildren();

        breadcrumbItems().forEach((item, index, items) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = item.label;
            button.dataset.crumbIndex = String(index);
            button.disabled = index === items.length - 1;
            breadcrumb.appendChild(button);

            if (index < items.length - 1) {
                const separator = document.createElement('span');
                separator.textContent = '›';
                separator.setAttribute('aria-hidden', 'true');
                breadcrumb.appendChild(separator);
            }
        });
    }

    function applySemanticZoom() {
        const zoom = cy.zoom();
        const nextLevel = zoom < 0.58 ? 'low' : zoom < 1.05 ? 'mid' : 'high';
        if (nextLevel === semanticLevel && cy.nodes().every((node) => node.data('displayLabel'))) return;
        semanticLevel = nextLevel;

        cy.nodes().forEach((node) => {
            const key = nextLevel === 'low' ? 'labelLow' : nextLevel === 'high' ? 'labelHigh' : 'labelMid';
            node.data('displayLabel', node.data(key) || node.data('name'));
        });
    }

    function runLayout({ fit = true } = {}) {
        const nodes = cy.nodes();
        if (nodes.length === 0) return;

        let layout;
        if (mode === 'overview' || mode === 'physical') {
            layout = cy.layout({
                name: 'breadthfirst',
                directed: true,
                roots: cy.$('#scope-root'),
                spacingFactor: 1.35,
                padding: 80,
                fit,
                animate: false,
            });
        } else if (activeLayout === 'force') {
            layout = cy.layout({
                name: 'cose',
                animate: false,
                fit,
                padding: 80,
                nodeRepulsion: 760000,
                idealEdgeLength: 140,
                edgeElasticity: 90,
                gravity: 0.3,
                numIter: 700,
                randomize: true,
            });
        } else {
            layout = cy.layout({
                name: 'elk',
                animate: false,
                fit,
                padding: 80,
                nodeDimensionsIncludeLabels: true,
                elk: {
                    algorithm: 'layered',
                    'elk.direction': 'DOWN',
                    'elk.edgeRouting': 'ORTHOGONAL',
                    'elk.spacing.nodeNode': '42',
                    'elk.layered.spacing.nodeNodeBetweenLayers': '92',
                    'elk.layered.nodePlacement.strategy': 'NETWORK_SIMPLEX',
                    'elk.layered.crossingMinimization.strategy': 'LAYER_SWEEP',
                },
            });
        }

        layout.run();
        window.setTimeout(applySemanticZoom, 50);
    }

    function renderGraph({ fit = true } = {}) {
        selectedDeviceId = null;
        closeInspector();
        cy.elements().remove();
        const elements = buildElements();
        if (elements.length) cy.add(elements);

        if (description) description.textContent = modeDescription();
        if (visibleCount) visibleCount.textContent = String(cy.nodes().filter('[nodeKind = "device"]').length || cy.nodes().filter('[nodeKind = "group"]').length);
        emptyState?.classList.toggle('is-visible', cy.nodes().length === 0);
        renderBreadcrumb();
        if (viewMode) viewMode.value = mode;
        runLayout({ fit });
    }

    function impactCount(deviceId) {
        return Math.max(0, traverse(deviceId, 'down', { depth: 20, max: 5000 }).size - 1);
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = safe(value);
    }

    function openInspector(deviceId) {
        const node = nodeById.get(deviceId);
        if (!node) return;

        selectedDeviceId = deviceId;
        const statusBox = document.getElementById('networkDetailStatus');
        const dot = statusBox?.querySelector('.network-topology-dot');
        const text = statusBox?.querySelector('span:last-child');
        const editLink = document.getElementById('networkDetailEdit');

        setText('networkDetailEyebrow', node.criticality === 'critical' ? 'CRITICAL DEVICE' : 'PERANGKAT');
        setText('networkDetailName', node.name);
        setText('networkDetailType', node.type);
        setText('networkDetailIp', node.ip);
        setText('networkDetailIncoming', incoming.get(deviceId)?.length || 0);
        setText('networkDetailOutgoing', outgoing.get(deviceId)?.length || 0);
        setText('networkDetailImpact', impactCount(deviceId));
        setText('networkDetailLocation', [node.room, node.floor, node.building].filter(Boolean).join(' / '));
        setText('networkDetailUnit', node.unit);
        setText('networkDetailCriticality', node.criticalityLabel);
        setText('networkDetailLastSeen', node.lastSeenAt);
        setText('networkDetailAssetCode', node.assetCode);
        setText('networkDetailHostname', node.hostname);
        setText('networkDetailLifecycle', node.lifecycleLabel);
        setText('networkDetailLatency', node.latency !== null && node.latency !== undefined ? `${node.latency} ms` : null);
        setText('networkDetailLastChecked', node.lastCheckedAt);
        setText('networkDetailMonitoring', node.monitoringEnabled ? 'Aktif' : 'Nonaktif');
        setText('networkDetailOwnerUnit', node.ownerUnit);
        setText('networkDetailBuilding', node.building);
        setText('networkDetailFloor', node.floor);
        setText('networkDetailRoom', node.room);
        setText('networkDetailDescription', node.description);

        if (dot) dot.className = `network-topology-dot ${node.status || 'unknown'}`;
        if (text) text.textContent = node.statusLabel || 'Tidak diketahui';

        if (node.editUrl) {
            editLink.href = node.editUrl;
            editLink.classList.add('is-visible');
        } else {
            editLink.removeAttribute('href');
            editLink.classList.remove('is-visible');
        }

        stage?.classList.add('inspector-open');
        document.getElementById('networkDetailPanel')?.setAttribute('aria-hidden', 'false');
        window.setTimeout(() => {
            cy.resize();
            const selected = cy.$id(deviceId);
            if (selected.length) cy.animate({ fit: { eles: selected.union(selected.neighborhood()), padding: 120 }, duration: 180 });
        }, 180);
    }

    function closeInspector() {
        selectedDeviceId = null;
        stage?.classList.remove('inspector-open');
        document.getElementById('networkDetailPanel')?.setAttribute('aria-hidden', 'true');
        window.setTimeout(() => cy.resize(), 160);
    }

    function drillGroup(data) {
        mode = 'physical';

        if (data.groupLevel === 'building') {
            physicalPath = [{ level: 'building', value: data.groupValue, label: data.name }];
        } else if (data.groupLevel === 'floor') {
            physicalPath = [...physicalPath.slice(0, 1), { level: 'floor', value: data.groupValue, label: data.name }];
        } else if (data.groupLevel === 'room') {
            physicalPath = [...physicalPath.slice(0, 2), { level: 'room', value: data.groupValue, label: data.name }];
        }

        renderGraph();
    }

    function focusDependency(deviceId) {
        focusedDeviceId = deviceId;
        mode = 'dependency';
        physicalPath = [];
        renderGraph();
        window.setTimeout(() => {
            const node = cy.$id(deviceId);
            if (node.length) {
                node.select();
                openInspector(deviceId);
            }
        }, 120);
    }

    function highlightUpstream(deviceId) {
        if (mode !== 'dependency' || focusedDeviceId !== deviceId) focusDependency(deviceId);

        window.setTimeout(() => {
            cy.elements().removeClass('analysis-muted analysis-focus path-focus');
            const upstreamIds = traverse(deviceId, 'up', { depth: 20, max: 100 });
            const focusNodes = cy.nodes().filter((node) => upstreamIds.has(node.id()));
            const focusEdges = cy.edges().filter((edge) => upstreamIds.has(edge.source().id()) && upstreamIds.has(edge.target().id()));
            cy.elements().addClass('analysis-muted');
            focusNodes.removeClass('analysis-muted').addClass('analysis-focus');
            focusEdges.removeClass('analysis-muted').addClass('analysis-focus path-focus');
            if (focusNodes.length) cy.animate({ fit: { eles: focusNodes.union(focusEdges), padding: 110 }, duration: 220 });
        }, 150);
    }

    function searchMatches(query) {
        const needle = query.trim().toLowerCase();
        if (needle.length < 2) return [];

        return rawNodes
            .map((node) => {
                const name = (node.name || '').toLowerCase();
                const ip = (node.ip || '').toLowerCase();
                const hostname = (node.hostname || '').toLowerCase();
                const asset = (node.assetCode || '').toLowerCase();
                const haystack = [name, ip, hostname, asset, node.type, node.location].filter(Boolean).join(' ').toLowerCase();
                let rank = 100;
                if (name.startsWith(needle)) rank = 0;
                else if (ip.startsWith(needle)) rank = 1;
                else if (hostname.startsWith(needle)) rank = 2;
                else if (asset.startsWith(needle)) rank = 3;
                else if (haystack.includes(needle)) rank = 10;
                return { node, rank };
            })
            .filter((item) => item.rank < 100)
            .sort((a, b) => a.rank - b.rank || a.node.name.localeCompare(b.node.name))
            .slice(0, 8)
            .map((item) => item.node);
    }

    function hideSearchResults() {
        if (!searchResults) return;
        searchResults.hidden = true;
        searchResults.replaceChildren();
    }

    function showSearchResults(matches) {
        if (!searchResults) return;
        searchResults.replaceChildren();

        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'network-topology-search-empty';
            empty.textContent = 'Perangkat tidak ditemukan';
            searchResults.appendChild(empty);
        } else {
            matches.forEach((node) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.deviceId = node.id;

                const title = document.createElement('strong');
                title.textContent = node.name;
                const meta = document.createElement('span');
                meta.textContent = [node.typeCode || node.type, node.ip, node.building].filter(Boolean).join(' • ');

                button.append(title, meta);
                searchResults.appendChild(button);
            });
        }

        searchResults.hidden = false;
    }

    cy.on('tap', 'node.group', (event) => drillGroup(event.target.data()));
    cy.on('tap', 'node.device', (event) => openInspector(event.target.id()));
    cy.on('tap', (event) => {
        if (event.target === cy) closeInspector();
    });
    cy.on('zoom', () => {
        window.requestAnimationFrame(applySemanticZoom);
    });

    viewMode?.addEventListener('change', () => {
        mode = viewMode.value;
        if (mode !== 'physical') physicalPath = [];
        if (mode === 'dependency' && !focusedDeviceId && selectedDeviceId) focusedDeviceId = selectedDeviceId;
        renderGraph();
    });

    [unitFilter, statusFilter].forEach((control) => {
        control?.addEventListener('change', () => renderGraph());
    });

    search?.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            const matches = searchMatches(search.value);
            if (search.value.trim().length < 2) hideSearchResults();
            else showSearchResults(matches);
        }, 120);
    });

    search?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        const first = searchMatches(search.value)[0];
        if (first) {
            hideSearchResults();
            focusDependency(first.id);
        }
    });

    searchResults?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-device-id]');
        if (!button) return;
        hideSearchResults();
        if (search) search.value = nodeById.get(button.dataset.deviceId)?.name || search.value;
        focusDependency(button.dataset.deviceId);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.network-topology-search')) hideSearchResults();
    });

    breadcrumb?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-crumb-index]');
        if (!button) return;
        const index = Number(button.dataset.crumbIndex);
        const items = breadcrumbItems();
        const item = items[index];
        if (!item) return;

        if (index === 0) {
            mode = 'overview';
            physicalPath = [];
        } else if (item.mode === 'physical') {
            mode = 'physical';
            physicalPath = physicalPath.slice(0, item.depth || 0);
        } else {
            mode = item.mode;
        }
        renderGraph();
    });

    document.querySelectorAll('[data-network-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.networkAction;
            if (action === 'fit') cy.fit(cy.elements(), 80);
            if (action === 'zoom-in') cy.zoom({ level: Math.min(cy.maxZoom(), cy.zoom() * 1.2), renderedPosition: { x: container.clientWidth / 2, y: container.clientHeight / 2 } });
            if (action === 'zoom-out') cy.zoom({ level: Math.max(cy.minZoom(), cy.zoom() / 1.2), renderedPosition: { x: container.clientWidth / 2, y: container.clientHeight / 2 } });
            if (action === 'layout-layered') { activeLayout = 'layered'; runLayout(); }
            if (action === 'layout-force') { activeLayout = 'force'; runLayout(); }
            if (action === 'fullscreen') {
                const shell = document.getElementById('networkTopologyShell');
                if (!document.fullscreenElement) shell?.requestFullscreen?.();
                else document.exitFullscreen?.();
            }
            if (action === 'reset') {
                mode = 'overview';
                activeLayout = 'layered';
                physicalPath = [];
                focusedDeviceId = null;
                if (search) search.value = '';
                if (unitFilter) unitFilter.value = '';
                if (statusFilter) statusFilter.value = '';
                hideSearchResults();
                renderGraph();
            }
            button.closest('details')?.removeAttribute('open');
        });
    });

    document.querySelectorAll('[data-network-analysis]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!selectedDeviceId) return;
            if (button.dataset.networkAnalysis === 'focus') focusDependency(selectedDeviceId);
            if (button.dataset.networkAnalysis === 'path') highlightUpstream(selectedDeviceId);
        });
    });

    document.getElementById('networkDetailClose')?.addEventListener('click', closeInspector);

    document.addEventListener('fullscreenchange', () => {
        window.setTimeout(() => {
            cy.resize();
            cy.fit(cy.elements(), 80);
        }, 90);
    });

    const themeObserver = new MutationObserver((mutations) => {
        if (!mutations.some((mutation) => mutation.attributeName === 'class')) return;
        const next = palette();
        container.style.background = next.canvas;
        cy.style(styles(next));
        applySemanticZoom();
    });

    window.networkTopologyThemeObserver?.disconnect?.();
    themeObserver.observe(document.documentElement, { attributes: true });
    window.networkTopologyThemeObserver = themeObserver;

    renderGraph();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootNetworkTopology, { once: true });
} else {
    bootNetworkTopology();
}

document.addEventListener('livewire:navigated', bootNetworkTopology);
