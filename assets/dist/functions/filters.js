const BOOTSTRAP_FRAMEWORKS = ['bs', 'bs4', 'bs5'];
function isBootstrap(framework) {
    return BOOTSTRAP_FRAMEWORKS.includes(framework);
}
function inputClass(framework) {
    return isBootstrap(framework) ? 'form-control' : 'dt-filter-input';
}
function selectClass(framework) {
    return isBootstrap(framework) ? 'form-select' : 'dt-filter-input';
}
export function hasFilters(payload) {
    return Array.isArray(payload?.filters) && payload.filters.length > 0;
}
function normalizeValue(value) {
    if (value === null)
        return null;
    if (typeof value === 'string')
        return value.trim() === '' ? null : value;
    if (Array.isArray(value))
        return value.length === 0 ? null : value;
    const from = value.from?.trim() ? value.from : undefined;
    const to = value.to?.trim() ? value.to : undefined;
    if (from === undefined && to === undefined)
        return null;
    const range = {};
    if (from !== undefined)
        range.from = from;
    if (to !== undefined)
        range.to = to;
    return range;
}
const FUNNEL_ICON = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" ' +
    'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<path d="M3 4h14l-5.5 6.5V16l-3 1.5v-7L3 4z" /></svg>';
export class FilterBar {
    constructor(payload, framework) {
        this.framework = framework;
        this.controls = [];
        this.applied = {};
        this.reload = () => { };
        this.documentClickHandler = null;
        this.definitions = payload.filters ?? [];
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'dt-filters';
        this.toggle = document.createElement('button');
        this.toggle.type = 'button';
        this.toggle.className = 'dt-filters-toggle';
        this.toggle.setAttribute('aria-expanded', 'false');
        this.toggle.setAttribute('aria-label', 'Filters');
        this.toggle.innerHTML = FUNNEL_ICON;
        this.badge = document.createElement('span');
        this.badge.className = 'dt-filters-badge';
        this.badge.textContent = '0';
        this.toggle.appendChild(this.badge);
        this.popover = document.createElement('div');
        this.popover.className = 'dt-filters-popover';
        this.popover.hidden = true;
        this.wrapper.appendChild(this.toggle);
        this.wrapper.appendChild(this.popover);
    }
    attachToPayload(payload) {
        if (!payload.ajax || typeof payload.ajax !== 'object') {
            return;
        }
        const existing = payload.ajax.data;
        payload.ajax.data = (data) => {
            if (existing && typeof existing === 'object' && !Array.isArray(existing)) {
                Object.assign(data, existing);
            }
            data.filters = this.collectValues();
            return data;
        };
    }
    collectValues() {
        return this.applied;
    }
    snapshot() {
        const out = {};
        for (const control of this.controls) {
            const value = normalizeValue(control.getValue());
            if (value !== null) {
                out[control.definition.name] = value;
            }
        }
        return out;
    }
    render(reload) {
        this.reload = reload;
        this.popover.appendChild(this.buildHeader());
        const body = document.createElement('div');
        body.className = 'dt-filters-popover__body';
        for (const definition of this.definitions) {
            const { wrapper, control } = this.buildControl(definition);
            this.controls.push(control);
            body.appendChild(wrapper);
        }
        this.popover.appendChild(body);
        this.popover.appendChild(this.buildFooter());
        this.toggle.addEventListener('click', () => this.togglePopover());
        return this.wrapper;
    }
    buildHeader() {
        const header = document.createElement('div');
        header.className = 'dt-filters-popover__header';
        const title = document.createElement('span');
        title.className = 'dt-filters-popover__title';
        title.textContent = 'Filters';
        const reset = document.createElement('button');
        reset.type = 'button';
        reset.className = 'dt-filters-reset';
        reset.textContent = 'Reset';
        reset.addEventListener('click', () => this.resetFilters());
        header.appendChild(title);
        header.appendChild(reset);
        return header;
    }
    buildFooter() {
        const footer = document.createElement('div');
        footer.className = 'dt-filters-popover__footer';
        const apply = document.createElement('button');
        apply.type = 'button';
        apply.className = 'dt-filters-apply';
        apply.textContent = 'Apply filters';
        apply.addEventListener('click', () => this.applyFilters());
        footer.appendChild(apply);
        return footer;
    }
    applyFilters() {
        this.applied = this.snapshot();
        this.updateBadge();
        this.closePopover();
        this.reload();
    }
    resetFilters() {
        for (const control of this.controls) {
            control.reset();
        }
        this.applied = {};
        this.updateBadge();
        this.reload();
    }
    updateBadge() {
        const count = Object.keys(this.applied).length;
        this.badge.textContent = String(count);
        this.toggle.classList.toggle('dt-filters-toggle--active', count > 0);
    }
    togglePopover() {
        if (this.popover.hidden) {
            this.openPopover();
        }
        else {
            this.closePopover();
        }
    }
    openPopover() {
        this.popover.hidden = false;
        this.toggle.setAttribute('aria-expanded', 'true');
        this.documentClickHandler = (event) => {
            if (!this.wrapper.contains(event.target)) {
                this.closePopover();
            }
        };
        document.addEventListener('mousedown', this.documentClickHandler);
    }
    closePopover() {
        this.popover.hidden = true;
        this.toggle.setAttribute('aria-expanded', 'false');
        if (this.documentClickHandler) {
            document.removeEventListener('mousedown', this.documentClickHandler);
            this.documentClickHandler = null;
        }
    }
    buildControl(definition) {
        const wrapper = document.createElement('div');
        wrapper.className = 'dt-filter';
        if (definition.type === 'checkbox') {
            return { wrapper, control: this.buildCheckbox(definition, wrapper) };
        }
        const label = document.createElement('label');
        label.className = isBootstrap(this.framework) ? 'form-label' : 'dt-filter-label';
        label.textContent = definition.label ?? definition.name;
        wrapper.appendChild(label);
        const control = this.buildField(definition, wrapper);
        return { wrapper, control };
    }
    buildField(definition, wrapper) {
        switch (definition.type) {
            case 'select':
                return this.buildSelect(definition, wrapper);
            case 'ternary':
                return this.buildTernary(definition, wrapper);
            case 'dateRange':
                return this.buildDateRange(definition, wrapper);
            default:
                return this.buildText(definition, wrapper);
        }
    }
    buildText(definition, wrapper) {
        const input = document.createElement('input');
        input.type = 'search';
        input.className = inputClass(this.framework);
        input.name = `filters[${definition.name}]`;
        if (definition.placeholder)
            input.placeholder = definition.placeholder;
        wrapper.appendChild(input);
        return {
            definition,
            getValue: () => input.value,
            reset: () => {
                input.value = '';
            },
        };
    }
    buildSelect(definition, wrapper) {
        const select = document.createElement('select');
        select.className = selectClass(this.framework);
        select.name = `filters[${definition.name}]`;
        select.multiple = definition.multiple === true;
        if (!select.multiple) {
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = definition.placeholder ?? 'All';
            select.appendChild(empty);
        }
        for (const [value, optLabel] of Object.entries(definition.options ?? {})) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = optLabel;
            select.appendChild(option);
        }
        wrapper.appendChild(select);
        return {
            definition,
            getValue: () => select.multiple
                ? [...select.selectedOptions].map((o) => o.value).filter((v) => v !== '')
                : select.value,
            reset: () => {
                select.selectedIndex = select.multiple ? -1 : 0;
            },
        };
    }
    buildTernary(definition, wrapper) {
        const select = document.createElement('select');
        select.className = selectClass(this.framework);
        select.name = `filters[${definition.name}]`;
        const optionsMap = [
            ['', definition.placeholder ?? 'All'],
            ['true', definition.trueLabel ?? 'Yes'],
            ['false', definition.falseLabel ?? 'No'],
        ];
        for (const [value, optLabel] of optionsMap) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = optLabel;
            select.appendChild(option);
        }
        wrapper.appendChild(select);
        return {
            definition,
            getValue: () => select.value,
            reset: () => {
                select.selectedIndex = 0;
            },
        };
    }
    buildDateRange(definition, wrapper) {
        const group = document.createElement('div');
        group.className = isBootstrap(this.framework) ? 'd-flex gap-1' : 'dt-filter-range';
        const from = document.createElement('input');
        from.type = 'date';
        from.className = inputClass(this.framework);
        from.name = `filters[${definition.name}][from]`;
        const to = document.createElement('input');
        to.type = 'date';
        to.className = inputClass(this.framework);
        to.name = `filters[${definition.name}][to]`;
        group.appendChild(from);
        group.appendChild(to);
        wrapper.appendChild(group);
        return {
            definition,
            getValue: () => ({ from: from.value, to: to.value }),
            reset: () => {
                from.value = '';
                to.value = '';
            },
        };
    }
    buildCheckbox(definition, wrapper) {
        wrapper.classList.add('dt-filter--checkbox');
        const label = document.createElement('label');
        label.className = 'dt-filter-checkbox-label';
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.className = isBootstrap(this.framework) ? 'form-check-input' : 'dt-filter-checkbox';
        input.name = `filters[${definition.name}]`;
        input.value = '1';
        const text = document.createElement('span');
        text.textContent = definition.label ?? definition.name;
        label.appendChild(input);
        label.appendChild(text);
        wrapper.appendChild(label);
        return {
            definition,
            getValue: () => (input.checked ? '1' : ''),
            reset: () => {
                input.checked = false;
            },
        };
    }
}
//# sourceMappingURL=filters.js.map