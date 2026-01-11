import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
  integrations: [
    starlight({
      title: 'UX DataTables',
      description: 'Symfony bundle integrating DataTables in Symfony applications',
      logo: {
        src: './src/assets/logo.svg',
        replacesTitle: false,
      },
      social: {
        github: 'https://github.com/pentiminax/ux-datatables',
      },
      editLink: {
        baseUrl: 'https://github.com/pentiminax/ux-datatables/edit/main/docs/',
      },
      customCss: [
        './src/styles/custom.css',
      ],
      head: [
        {
          tag: 'meta',
          attrs: {
            property: 'og:image',
            content: '/og-image.png',
          },
        },
      ],
      sidebar: [
        {
          label: 'Getting Started',
          items: [
            { label: 'Introduction', slug: 'getting-started/introduction' },
            { label: 'Installation', slug: 'getting-started/installation' },
            { label: 'Quick Start', slug: 'getting-started/quick-start' },
          ],
        },
        {
          label: 'Guide',
          items: [
            { label: 'Usage', slug: 'guide/usage' },
            { label: 'Configuration', slug: 'guide/configuration' },
            { label: 'Columns', slug: 'guide/columns' },
            { label: 'Options', slug: 'guide/options' },
            { label: 'Extensions', slug: 'guide/extensions' },
            { label: 'Ajax', slug: 'guide/ajax' },
          ],
        },
        {
          label: 'Reference',
          items: [
            { label: 'AbstractDataTable', slug: 'reference/abstract-datatable' },
            { label: 'Action Columns', slug: 'reference/action-columns' },
            { label: 'Maker Command', slug: 'reference/maker' },
          ],
        },
      ],
      defaultLocale: 'root',
      locales: {
        root: {
          label: 'English',
          lang: 'en',
        },
      },
    }),
  ],
});
