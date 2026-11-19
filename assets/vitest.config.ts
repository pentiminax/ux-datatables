import { fileURLToPath } from 'node:url'

export default {
    resolve: {
        alias: {
            '@pentiminax/ux-datatables/dist/core.js': fileURLToPath(
                new URL('./src/core.ts', import.meta.url)
            ),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['test/**/*.test.ts', 'src/**/__tests__/**/*.test.ts'],
        server: {
            deps: {
                inline: [/^datatables\.net/],
            },
        },
        setupFiles: ['test/setup.ts'],
    },
}
