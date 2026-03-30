import { escapeHtml } from '../functions/htmlUtils.js'
import { ColumnRenderer, EmailCustomOptions } from './types.js'

function maskEmail(email: string): string {
    const atIndex = email.indexOf('@')
    if (atIndex <= 0) {
        return email
    }

    return email[0] + '***' + email.slice(atIndex)
}

function obfuscateMailto(email: string): string {
    return email.replace(/@/g, '&#64;').replace(/\./g, '&#46;')
}

export const emailColumnRenderer: ColumnRenderer = {
    matches(column: Record<string, any>): boolean {
        return true === column?.customOptions?.isEmail
    },

    configure(column: Record<string, any>): void {
        const customOptions = (column.customOptions ?? {}) as EmailCustomOptions
        const shouldObfuscate = true === customOptions.obfuscate
        const shouldMask = true === customOptions.mask
        const displayValue =
            typeof customOptions.displayValue === 'string' ? customOptions.displayValue : null

        column.render = (data: any, type: string, _row: Record<string, any>): any => {
            if (type !== 'display') {
                return data
            }

            const email = typeof data === 'string' ? data : ''

            if (!email) {
                return ''
            }

            const href = shouldObfuscate
                ? `mailto:${escapeHtml(obfuscateMailto(email))}`
                : `mailto:${escapeHtml(email)}`

            let text: string
            if (displayValue !== null) {
                text = escapeHtml(displayValue)
            } else if (shouldMask) {
                text = escapeHtml(maskEmail(email))
            } else {
                text = escapeHtml(email)
            }

            return `<a href="${href}">${text}</a>`
        }
    },
}
