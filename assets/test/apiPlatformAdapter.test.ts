import {
    configureApiPlatformAjax,
    convertApiPlatformToDataTable,
    convertDataTableToApiPlatform,
} from '../src/functions/apiPlatformAdapter';

describe('apiPlatformAdapter', () => {
    it('converts DataTables parameters to API Platform parameters', () => {
        const converted = convertDataTableToApiPlatform(
            {
                draw: 3,
                start: 20,
                length: 10,
                order: [{column: 1, dir: 'asc'}],
                columns: [
                    {name: 'id', search: {value: ''}},
                    {name: 'title', search: {value: 'harry'}},
                ],
            },
            [
                {name: 'id'},
                {name: 'title'},
            ]
        );

        expect(converted).toEqual({
            page: '3',
            itemsPerPage: '10',
            'order[title]': 'asc',
            title: 'harry',
        });
    });

    it('converts API Platform collection responses to DataTables format', () => {
        const converted = convertApiPlatformToDataTable(
            {
                'hydra:member': [{id: 1}],
                'hydra:totalItems': 42,
            },
            5
        );

        expect(converted).toEqual({
            draw: 5,
            recordsTotal: 42,
            recordsFiltered: 42,
            data: [{id: 1}],
        });
    });

    it('wires ajax data/dataFilter hooks for API Platform mode', () => {
        const payload: Record<string, any> = {
            columns: [
                {name: 'id'},
                {name: 'title'},
            ],
            ajax: {
                type: 'GET',
                url: '/api/books',
            },
        };

        configureApiPlatformAjax(payload);

        expect(typeof payload.ajax.data).toBe('function');
        expect(typeof payload.ajax.dataFilter).toBe('function');

        const query = payload.ajax.data({
            draw: 4,
            start: 10,
            length: 10,
            order: [{column: 1, dir: 'desc'}],
            columns: [
                {name: 'id', search: {value: ''}},
                {name: 'title', search: {value: 'tolkien'}},
            ],
        });

        expect(query).toEqual({
            page: '2',
            itemsPerPage: '10',
            'order[title]': 'desc',
            title: 'tolkien',
        });

        const response = JSON.parse(payload.ajax.dataFilter(JSON.stringify({
            'hydra:member': [{id: 1, title: 'Book'}],
            'hydra:totalItems': 1,
        }), 'json'));

        expect(response).toEqual({
            draw: 4,
            recordsTotal: 1,
            recordsFiltered: 1,
            data: [{id: 1, title: 'Book'}],
        });
    });
});
