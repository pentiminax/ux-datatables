const DEFAULT_LABELS = {
    sum: 'Total',
    avg: 'Average',
    min: 'Minimum',
    max: 'Maximum',
    count: 'Count',
};
export function hasSummaries(payload) {
    return (Array.isArray(payload?.columns) &&
        payload.columns.some((column) => (column?.summaries?.length ?? 0) > 0));
}
export function aggregate(type, values) {
    const present = values.filter((value) => value !== null && value !== undefined);
    if (type === 'count') {
        return present.length;
    }
    const numbers = present
        .filter((value) => typeof value === 'number' || (typeof value === 'string' && value.trim() !== ''))
        .map(Number)
        .filter(Number.isFinite);
    if (numbers.length === 0) {
        return null;
    }
    const sum = numbers.reduce((total, n) => total + n, 0);
    switch (type) {
        case 'sum':
            return sum;
        case 'avg':
            return sum / numbers.length;
        case 'min':
            return numbers.reduce((a, b) => Math.min(a, b));
        default:
            return numbers.reduce((a, b) => Math.max(a, b));
    }
}
function format(column, type, value) {
    if (value === null || value === undefined) {
        return '';
    }
    if (column.customOptions?.isMoney && type !== 'count' && typeof column.render === 'function') {
        return String(column.render(value, 'display'));
    }
    return typeof value === 'number'
        ? new Intl.NumberFormat(navigator.language).format(value)
        : value;
}
function fill(cell, column, values, labels) {
    if (!cell) {
        return;
    }
    cell.replaceChildren();
    for (const { type, label } of column.summaries ?? []) {
        const line = document.createElement('div');
        line.className = 'dt-summary';
        const name = document.createElement('span');
        name.className = 'dt-summary__label';
        name.textContent = label ?? labels[type] ?? DEFAULT_LABELS[type];
        const value = document.createElement('span');
        value.className = 'dt-summary__value';
        value.textContent = format(column, type, values[type] ?? null);
        line.append(name, ' ', value);
        cell.append(line);
    }
}
export function ensureSummaryFooter(tableElement, payload) {
    const columnCount = payload.columns.length;
    const existing = tableElement.querySelector('tfoot');
    if (existing) {
        const cells = existing.querySelectorAll('tr:first-child > *').length;
        if (cells !== columnCount) {
            console.warn(`Column summaries need one footer cell per column (${columnCount}), but the existing <tfoot> has ${cells}; summaries are not shown.`);
            return false;
        }
        return true;
    }
    const row = document.createElement('tr');
    for (let index = 0; index < columnCount; index++) {
        const cell = document.createElement('th');
        cell.scope = 'col';
        row.append(cell);
    }
    const footer = document.createElement('tfoot');
    footer.append(row);
    tableElement.append(footer);
    return true;
}
export function installColumnSummaries(table, payload) {
    const columns = payload.columns;
    const labels = payload.summaryLabels ?? {};
    columns.forEach((column, index) => {
        if (column.summaries?.length) {
            fill(table.column(index).footer(), column, {}, labels);
        }
    });
    if (payload.serverSide) {
        table.on('xhr.dt', (_event, _settings, json) => {
            columns.forEach((column, index) => {
                if (column.summaries?.length) {
                    fill(table.column(index).footer(), column, json?.summaries?.[column.name ?? ''] ?? {}, labels);
                }
            });
        });
        return;
    }
    const update = () => {
        columns.forEach((column, index) => {
            if (!column.summaries?.length) {
                return;
            }
            const rows = table.column(index, { search: 'applied' }).data().toArray();
            const values = {};
            for (const { type } of column.summaries) {
                values[type] = aggregate(type, rows);
            }
            fill(table.column(index).footer(), column, values, labels);
        });
    };
    table.on('draw.dt', update);
    update();
}
//# sourceMappingURL=columnSummaries.js.map