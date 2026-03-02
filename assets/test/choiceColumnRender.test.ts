import {describe, expect, it, vi} from 'vitest';

vi.mock('@hotwired/stimulus', () => ({
    Controller: class Controller {},
}));

vi.mock('../src/functions/getLoadedDataTablesStyleSheet.js', () => ({
    getLoadedDataTablesStyleSheet: () => null,
}));

vi.mock('../src/functions/loadButtonsLibrary.js', () => ({
    loadButtonsLibrary: vi.fn(),
}));

vi.mock('../src/functions/loadDataTableLibrary.js', () => ({
    loadDataTableLibrary: vi.fn(),
}));

vi.mock('../src/functions/loadSelectLibrary.js', () => ({
    loadSelectLibrary: vi.fn(),
}));

vi.mock('../src/functions/loadResponsiveLibrary.js', () => ({
    loadResponsiveLibrary: vi.fn(),
}));

vi.mock('../src/functions/loadColReorderLibrary.js', () => ({
    loadColReorderLibrary: vi.fn(),
}));

vi.mock('../src/functions/loadColumnControlLibrary.js', () => ({
    loadColumnControlLibrary: vi.fn(),
}));

vi.mock('../src/functions/loadFixedColumnsLibrary.js', () => ({
    loadFixedColumnsLibrary: vi.fn(),
}));

vi.mock('../src/functions/loadKeyTableLibrary.js', () => ({
    loadKeyTableLibrary: vi.fn(),
}));

vi.mock('../src/functions/loadScrollerLibrary.js', () => ({
    loadScrollerLibrary: vi.fn(),
}));

vi.mock('../src/functions/deleteRow.js', () => ({
    deleteRow: vi.fn(),
}));

vi.mock('../src/functions/toggleBooleanValue.js', () => ({
    toggleBooleanValue: vi.fn(),
}));

vi.mock('../src/functions/apiPlatformAdapter.js', () => ({
    ApiPlatformAdapter: class ApiPlatformAdapter {
        configure(): void {}
    },
}));

const {default: DataTableController} = await import('../src/controller');

describe('ChoiceColumn render', () => {
    const createController = (): any => Object.create(DataTableController.prototype);

    it('renders the escaped label when badges are disabled', () => {
        const controller = createController();
        const column: Record<string, any> = {
            choices: {
                active: 'Active <b>',
            },
        };

        controller.configureChoiceColumnRender(column);

        expect(column.render('active', 'display')).toBe('Active &lt;b&gt;');
    });

    it('renders a badge with the mapped variant for display mode', () => {
        const controller = createController();
        const column: Record<string, any> = {
            choices: {
                active: 'Active',
            },
            renderAsBadges: {
                active: 'success',
            },
            defaultBadgeVariant: 'warning',
        };

        controller.configureChoiceColumnRender(column);

        expect(column.render('active', 'display')).toBe('<span class="badge text-bg-success">Active</span>');
    });

    it('falls back to the default badge variant for unmapped values', () => {
        const controller = createController();
        const column: Record<string, any> = {
            choices: {
                pending: 'Pending',
            },
            renderAsBadges: {},
            defaultBadgeVariant: 'warning',
        };

        controller.configureChoiceColumnRender(column);

        expect(column.render('pending', 'display')).toBe('<span class="badge text-bg-warning">Pending</span>');
    });

    it('returns the plain label outside display mode', () => {
        const controller = createController();
        const column: Record<string, any> = {
            choices: {
                active: 'Active',
            },
            renderAsBadges: {
                active: 'success',
            },
        };

        controller.configureChoiceColumnRender(column);

        expect(column.render('active', 'filter')).toBe('Active');
    });
});
