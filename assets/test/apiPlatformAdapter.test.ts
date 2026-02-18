import {ApiPlatformAdapter} from '../src/functions/apiPlatformAdapter';
import {describe, expect, it} from 'vitest';

describe('ApiPlatformAdapter', () => {
    describe('buildRequestParams', () => {
        it('uses field with dot-notation over name for nested relations', () => {
            const adapter = new ApiPlatformAdapter([
                {name: 'id'},
                {name: 'author', field: 'author.firstName'},
            ]);

            const converted = adapter.buildRequestParams({
                draw: 1,
                start: 0,
                length: 10,
                order: [{column: 1, dir: 'asc'}],
                columns: [
                    {name: 'id', search: {value: ''}},
                    {name: 'author', search: {value: 'John'}},
                ],
            });

            expect(converted).toEqual({
                page: '1',
                itemsPerPage: '10',
                'order[author.firstName]': 'asc',
                'author.firstName': 'John',
            });
        });

        it('converts DataTables parameters to API Platform parameters using field mapping', () => {
            const adapter = new ApiPlatformAdapter([
                {name: 'id'},
                {name: 'publishedAt', field: 'createdAt'},
            ]);

            const converted = adapter.buildRequestParams({
                draw: 3,
                start: 20,
                length: 10,
                order: [{column: 1, dir: 'asc'}],
                columns: [
                    {name: 'id', search: {value: ''}},
                    {name: 'publishedAt', search: {value: '31/01/2025'}},
                ],
            });

            expect(converted).toEqual({
                page: '3',
                itemsPerPage: '10',
                'order[createdAt]': 'asc',
                createdAt: '31/01/2025',
            });
        });
    });

    describe('buildResponse', () => {
        it('converts API Platform collection responses to DataTables format without value transformation', () => {
            const adapter = new ApiPlatformAdapter([]);

            const converted = adapter.buildResponse(
                {
                    'hydra:member': [{id: 1, createdAt: '2025-01-31'}],
                    'hydra:totalItems': 42,
                },
                5
            );

            expect(converted).toEqual({
                draw: 5,
                recordsTotal: 42,
                recordsFiltered: 42,
                data: [{id: 1, createdAt: '2025-01-31'}],
            });
        });
    });

    describe('configure', () => {
        it('wires ajax data/dataFilter hooks for API Platform mode', () => {
            const payload: Record<string, any> = {
                columns: [
                    {name: 'id'},
                    {name: 'publishedAt', field: 'createdAt'},
                ],
                serverSide: false,
                ajax: {
                    type: 'GET',
                    url: '/api/books',
                },
            };

            new ApiPlatformAdapter(payload.columns).configure(payload);

            expect(typeof payload.ajax.data).toBe('function');
            expect(typeof payload.ajax.dataFilter).toBe('function');
            expect(payload.serverSide).toBe(true);

            const query = payload.ajax.data({
                draw: 4,
                start: 10,
                length: 10,
                order: [{column: 1, dir: 'desc'}],
                columns: [
                    {name: 'id', search: {value: ''}},
                    {name: 'publishedAt', search: {value: '31/01/2025'}},
                ],
            });

            expect(query).toEqual({
                page: '2',
                itemsPerPage: '10',
                'order[createdAt]': 'desc',
                createdAt: '31/01/2025',
            });

            const response = JSON.parse(payload.ajax.dataFilter(JSON.stringify({
                'hydra:member': [{id: 1, createdAt: '2025-01-31'}],
                'hydra:totalItems': 1,
            }), 'json'));

            expect(response).toEqual({
                draw: 4,
                recordsTotal: 1,
                recordsFiltered: 1,
                data: [{id: 1, createdAt: '2025-01-31'}],
            });
        });
    });
});
