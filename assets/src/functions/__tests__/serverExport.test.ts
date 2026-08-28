import { afterEach, describe, expect, it, vi } from 'vitest'
import { buttonActions } from '../buttonActionRegistry.js'
import {
    applyServerExportUrls,
    flattenFormValues,
    runServerExport,
    SERVER_EXPORT_ACTION,
} from '../serverExport.js'

describe('serverExport', () => {
    afterEach(() => {
        document.body.innerHTML = ''
        vi.restoreAllMocks()
    })

    it('registers the built-in server export action', () => {
        expect(buttonActions.get(SERVER_EXPORT_ACTION)).toBe(runServerExport)
    })

    it('flattens nested ajax params into form fields', () => {
        expect(
            flattenFormValues({
                draw: 2,
                start: 0,
                search: { value: 'ada', regex: false },
                columns: [{ data: 'email' }],
            })
        ).toEqual([
            { name: 'draw', value: '2' },
            { name: 'start', value: '0' },
            { name: 'search[value]', value: 'ada' },
            { name: 'search[regex]', value: 'false' },
            { name: 'columns[0][data]', value: 'email' },
        ])
    })

    it('stamps the export url onto server export buttons and strips it from the payload', () => {
        const payload = {
            exportUrl: '/datatables/ajax/export?table=abc',
            layout: {
                topStart: {
                    buttons: [{ action: SERVER_EXPORT_ACTION, exportKey: 'csv' }, { extend: 'excel' }],
                },
            },
        }

        applyServerExportUrls(payload)

        const buttons = payload.layout.topStart.buttons as Record<string, unknown>[]
        expect(buttons[0].url).toBe('/datatables/ajax/export?table=abc')
        expect(buttons[1].url).toBeUndefined()
        expect(payload.exportUrl).toBeUndefined()
    })

    it('posts start 0, length 0, exportKey and format via a hidden form', () => {
        const submitted: { action: string; method: string; fields: Record<string, string> } = {
            action: '',
            method: '',
            fields: {},
        }

        const originalCreate = document.createElement.bind(document)
        vi.spyOn(document, 'createElement').mockImplementation((tag: string) => {
            const el = originalCreate(tag)
            if (tag === 'form') {
                el.submit = () => {
                    submitted.action = (el as HTMLFormElement).action
                    submitted.method = (el as HTMLFormElement).method
                    submitted.fields = Object.fromEntries(
                        [...el.querySelectorAll('input')].map((input) => [input.name, input.value])
                    )
                }
            }

            return el
        })

        runServerExport(
            new Event('click'),
            {
                ajax: {
                    params: () => ({
                        draw: 3,
                        start: 10,
                        length: 25,
                        search: { value: 'ada' },
                    }),
                },
            },
            document.createElement('button'),
            {
                url: 'https://example.test/datatables/ajax/export?table=abc',
                exportKey: 'xlsx',
                format: 'xlsx',
            }
        )

        expect(submitted.method.toLowerCase()).toBe('post')
        expect(submitted.action).toContain('/datatables/ajax/export')
        expect(submitted.fields.draw).toBe('3')
        expect(submitted.fields.start).toBe('0')
        expect(submitted.fields.length).toBe('0')
        expect(submitted.fields.exportKey).toBe('xlsx')
        expect(submitted.fields.format).toBe('xlsx')
        expect(submitted.fields['search[value]']).toBe('ada')
    })
})
