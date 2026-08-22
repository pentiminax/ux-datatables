import { inputClass } from './filters.js';
export function hasTfootSearch(payload) {
    return payload.tfootSearch === true;
}
export function applyTfootColumnSearch(payload, framework, DataTable) {
    const columns = Array.isArray(payload.columns) ? payload.columns : [];
    const prior = payload.initComplete;
    payload.initComplete = function (settings, data) {
        if (prior) {
            prior.call(this, settings, data);
        }
        const api = new DataTable.Api(settings);
        const tableEl = api.table().node();
        let tfoot = tableEl.querySelector('tfoot');
        if (!tfoot) {
            tfoot = document.createElement('tfoot');
            tableEl.appendChild(tfoot);
        }
        const row = document.createElement('tr');
        columns.forEach((column, index) => {
            const th = document.createElement('th');
            if (column.searchable) {
                const input = document.createElement('input');
                input.type = 'search';
                input.className = inputClass(framework);
                input.placeholder = typeof column.title === 'string' ? column.title : '';
                input.setAttribute('aria-label', input.placeholder);
                input.addEventListener('input', () => {
                    api.column(index, { search: 'applied' }).search(input.value).draw(false);
                });
                th.appendChild(input);
            }
            row.appendChild(th);
        });
        tfoot.appendChild(row);
    };
}
//# sourceMappingURL=columnSearch.js.map