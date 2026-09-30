import { Application } from '@hotwired/stimulus'
import DataTable from 'datatables.net-dt'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DatatableController from '../src/controller.js'

describe('datatable controller text rendering', () => {
    let application: Application
    let table: HTMLTableElement

    beforeEach(() => {
        table = document.createElement('table')
        application = Application.start()
        application.register('datatable', DatatableController)
    })

    afterEach(async () => {
        if (DataTable.isDataTable(table)) {
            new DataTable.Api(table).destroy()
        }

        table.remove()
        await vi.waitFor(() => {
            expect(application.getControllerForElementAndIdentifier(table, 'datatable')).toBeNull()
        })
        application.stop()
        document.body.innerHTML = ''
    })

    it.each([
        ['string', 'html'],
        ['string-utf8', 'html-utf8'],
    ])(
        'escapes %s cells and preserves %s markup during initialization',
        async (textType, htmlType) => {
            const untrustedText = '<img src=x onerror=alert(1)>'
            const trustedHtml = '<strong>Trusted &amp; formatted</strong>'

            table.setAttribute('data-controller', 'datatable')
            table.setAttribute(
                'data-datatable-view-value',
                JSON.stringify({
                    styleFramework: 'dt',
                    columns: [
                        { data: 'text', title: 'Text', type: textType },
                        { data: 'html', title: 'HTML', type: htmlType },
                    ],
                    data: [{ text: untrustedText, html: trustedHtml }],
                    paging: false,
                    searching: false,
                    info: false,
                })
            )
            document.body.appendChild(table)

            await vi.waitFor(() => {
                expect(table.querySelectorAll('tbody td')).toHaveLength(2)
            })

            const [textCell, htmlCell] = table.querySelectorAll('tbody td')
            expect(textCell.textContent).toBe(untrustedText)
            expect(textCell.innerHTML).toBe('&lt;img src=x onerror=alert(1)&gt;')
            expect(textCell.querySelector('img')).toBeNull()
            expect(htmlCell.innerHTML).toBe(trustedHtml)
            expect(htmlCell.querySelector('strong')?.textContent).toBe('Trusted & formatted')
        }
    )
})
