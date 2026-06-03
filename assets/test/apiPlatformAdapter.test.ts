import {ApiPlatformAdapter} from '../src/functions/apiPlatformAdapter';
import {afterEach, describe, expect, it, vi} from 'vitest';

describe('ApiPlatformAdapter', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

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

        it('adds an empty defaultContent fallback for missing or nullable API fields', () => {
            const payload: Record<string, any> = {
                columns: [
                    {name: 'avatar', data: 'avatar', field: 'avatar'},
                    {
                        name: 'lastLoginAt',
                        data: 'lastLoginAt',
                        field: 'lastLoginAt',
                        defaultContent: 'Never',
                    },
                ],
                serverSide: false,
                ajax: {
                    type: 'GET',
                    url: '/api/users',
                },
            };

            new ApiPlatformAdapter(payload.columns).configure(payload);

            expect(payload.columns).toEqual([
                {name: 'avatar', data: 'avatar', field: 'avatar', defaultContent: ''},
                {
                    name: 'lastLoginAt',
                    data: 'lastLoginAt',
                    field: 'lastLoginAt',
                    defaultContent: 'Never',
                },
            ]);

            const response = JSON.parse(payload.ajax.dataFilter(JSON.stringify({
                'hydra:member': [{id: 1, avatar: null}, {id: 2}],
                'hydra:totalItems': 2,
            }), 'json'));

            expect(response.data).toEqual([{id: 1, avatar: null}, {id: 2}]);
        });

        it('fetches API Platform data then renders template columns through the backend', async () => {
            const fetchMock = vi.fn()
                .mockResolvedValueOnce(new Response(JSON.stringify({
                    'hydra:member': [{id: 1, avatar: 'https://example.test/avatar.png'}],
                    'hydra:totalItems': 1,
                })))
                .mockResolvedValueOnce(new Response(JSON.stringify({
                    data: [{id: 1, avatar: '<img src="https://example.test/avatar.png" alt="">'}],
                })));

            vi.stubGlobal('fetch', fetchMock);

            const payload: Record<string, any> = {
                apiPlatformTemplateRendering: {
                    table: 'signed-token',
                    url: '/datatables/ajax/templates',
                },
                columns: [
                    {name: 'avatar', data: 'avatar', field: 'avatar'},
                    {name: 'email', data: 'email', field: 'email'},
                ],
                serverSide: false,
                ajax: {
                    type: 'GET',
                    url: '/api/users',
                },
            };

            new ApiPlatformAdapter(payload.columns).configure(payload);

            const response = await new Promise((resolve) => {
                payload.ajax({
                    draw: 6,
                    start: 0,
                    length: 25,
                    order: [{column: 1, dir: 'asc'}],
                    columns: [
                        {name: 'avatar', search: {value: ''}},
                        {name: 'email', search: {value: 'user@example.com'}},
                    ],
                }, resolve);
            });

            expect(response).toEqual({
                draw: 6,
                recordsTotal: 1,
                recordsFiltered: 1,
                data: [{id: 1, avatar: '<img src="https://example.test/avatar.png" alt="">'}],
            });
            expect(fetchMock).toHaveBeenCalledTimes(2);
            expect(fetchMock.mock.calls[0][0]).toContain('/api/users?');
            expect(fetchMock.mock.calls[0][0]).toContain('itemsPerPage=25');
            expect(fetchMock.mock.calls[0][0]).toContain('order%5Bemail%5D=asc');
            expect(fetchMock.mock.calls[0][0]).toContain('email=user%40example.com');
            expect(fetchMock.mock.calls[1][0]).toBe('/datatables/ajax/templates');
            expect(fetchMock.mock.calls[1][1]).toMatchObject({
                body: JSON.stringify({
                    table: 'signed-token',
                    rows: [{id: 1, avatar: 'https://example.test/avatar.png'}],
                }),
                credentials: 'same-origin',
                method: 'POST',
            });
        });
    });
});
