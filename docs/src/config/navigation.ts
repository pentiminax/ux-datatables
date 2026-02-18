export interface NavItem {
  label: string
  href: string
}

export interface NavSection {
  label: string
  items: NavItem[]
}

export const docsSidebarSections: NavSection[] = [
  {
    label: 'Getting Started',
    items: [
      { label: 'Introduction', href: '/ux-datatables/getting-started/introduction/' },
      { label: 'Installation', href: '/ux-datatables/getting-started/installation/' },
      { label: 'Quick Start', href: '/ux-datatables/getting-started/quick-start/' },
    ],
  },
  {
    label: 'Features',
    items: [
      { label: 'Overview', href: '/ux-datatables/features/overview/' },
      { label: 'When to use what', href: '/ux-datatables/features/when-to-use-what/' },
    ],
  },
  {
    label: 'Guide',
    items: [
      { label: 'Usage', href: '/ux-datatables/guide/usage/' },
      { label: 'Configuration', href: '/ux-datatables/guide/configuration/' },
      { label: 'Columns', href: '/ux-datatables/guide/columns/' },
      { label: 'Options', href: '/ux-datatables/guide/options/' },
      { label: 'Data Loading', href: '/ux-datatables/guide/data-loading/' },
      {
        label: 'Server-Side Processing',
        href: '/ux-datatables/guide/server-side-processing/',
      },
    ],
  },
  {
    label: 'Extensions',
    items: [
      { label: 'Overview', href: '/ux-datatables/extensions/' },
      { label: 'Buttons', href: '/ux-datatables/extensions/buttons/' },
      { label: 'Select', href: '/ux-datatables/extensions/select/' },
      { label: 'Column Control', href: '/ux-datatables/extensions/column-control/' },
      { label: 'Responsive', href: '/ux-datatables/extensions/responsive/' },
      { label: 'KeyTable', href: '/ux-datatables/extensions/keytable/' },
      { label: 'Scroller', href: '/ux-datatables/extensions/scroller/' },
      { label: 'Fixed Columns', href: '/ux-datatables/extensions/fixed-columns/' },
      { label: 'ColReorder', href: '/ux-datatables/extensions/col-reorder/' },
      {
        label: 'Combining Extensions',
        href: '/ux-datatables/extensions/combining-extensions/',
      },
    ],
  },
  {
    label: 'Integrations',
    items: [{ label: 'API Platform', href: '/ux-datatables/integrations/api-platform/' }],
  },
  {
    label: 'Reference',
    items: [
      { label: 'AbstractDataTable', href: '/ux-datatables/reference/abstract-datatable/' },
      { label: 'DataTable', href: '/ux-datatables/reference/datatable/' },
      { label: 'DataTableRequest', href: '/ux-datatables/reference/datatable-request/' },
      { label: 'Attributes', href: '/ux-datatables/reference/attributes/' },
      { label: 'Action Columns', href: '/ux-datatables/reference/action-columns/' },
      {
        label: 'Data Providers & Row Mappers',
        href: '/ux-datatables/reference/data-providers-row-mappers/',
      },
      { label: 'Enums', href: '/ux-datatables/reference/enums/' },
      { label: 'Maker Command', href: '/ux-datatables/reference/maker/' },
    ],
  },
]

export const docsFooterSections: NavSection[] = [
  {
    label: 'Documentation',
    items: [
      { label: 'Introduction', href: '/ux-datatables/getting-started/introduction/' },
      { label: 'Installation', href: '/ux-datatables/getting-started/installation/' },
      { label: 'Usage', href: '/ux-datatables/guide/usage/' },
      { label: 'Configuration', href: '/ux-datatables/guide/configuration/' },
    ],
  },
  {
    label: 'Key Features',
    items: [
      { label: 'Columns', href: '/ux-datatables/guide/columns/' },
      { label: 'Options', href: '/ux-datatables/guide/options/' },
      { label: 'Extensions', href: '/ux-datatables/extensions/' },
      { label: 'Data Loading', href: '/ux-datatables/guide/data-loading/' },
    ],
  },
]
