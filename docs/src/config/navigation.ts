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
      {
        label: 'First Server-Side Table',
        href: '/ux-datatables/getting-started/first-server-side-table/',
      },
      { label: 'Securing Ajax Routes', href: '/ux-datatables/getting-started/security/' },
      {
        label: 'AI-Assisted Development',
        href: '/ux-datatables/getting-started/ai-assisted-development/',
      },
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
    label: 'Recipes',
    items: [
      { label: 'Overview', href: '/ux-datatables/recipes/' },
      { label: 'Client-side Inline', href: '/ux-datatables/recipes/client-side-inline/' },
      { label: 'Doctrine Server-Side', href: '/ux-datatables/recipes/doctrine-server-side/' },
      { label: 'Filters', href: '/ux-datatables/recipes/filters/' },
      { label: 'Actions + CSRF + Voter', href: '/ux-datatables/recipes/actions-csrf-voter/' },
      { label: 'MoneyColumn', href: '/ux-datatables/recipes/money-column/' },
      { label: 'Mercure Live Updates', href: '/ux-datatables/recipes/mercure-live-update/' },
    ],
  },
  {
    label: 'Guide',
    items: [
      { label: 'Usage', href: '/ux-datatables/guide/usage/' },
      { label: 'Configuration', href: '/ux-datatables/guide/configuration/' },
      { label: 'Options', href: '/ux-datatables/guide/options/' },
      { label: 'Styling', href: '/ux-datatables/guide/styling/' },
      { label: 'Filters', href: '/ux-datatables/guide/filters/' },
      {
        label: 'Client-side Processing',
        href: '/ux-datatables/guide/client-side-processing/',
      },
      {
        label: 'Server-Side Processing',
        href: '/ux-datatables/guide/server-side-processing/',
      },
    ],
  },
  {
    label: 'Columns',
    items: [
      { label: 'Overview', href: '/ux-datatables/columns/overview/' },
      { label: 'Text Column', href: '/ux-datatables/columns/text-column/' },
      { label: 'Number Column', href: '/ux-datatables/columns/number-column/' },
      { label: 'Money Column', href: '/ux-datatables/columns/money-column/' },
      { label: 'Date Column', href: '/ux-datatables/columns/date-column/' },
      { label: 'Boolean Column', href: '/ux-datatables/columns/boolean-column/' },
      { label: 'Choice Column', href: '/ux-datatables/columns/choice-column/' },
      { label: 'Email Column', href: '/ux-datatables/columns/email-column/' },
      { label: 'Image Column', href: '/ux-datatables/columns/image-column/' },
      { label: 'Icon Column', href: '/ux-datatables/columns/icon-column/' },
      { label: 'Url Column', href: '/ux-datatables/columns/url-column/' },
      { label: 'Template Column', href: '/ux-datatables/columns/template-column/' },
      { label: 'Action Column', href: '/ux-datatables/columns/action-column/' },
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
    items: [
      { label: 'API Platform', href: '/ux-datatables/integrations/api-platform/' },
      { label: 'Mercure', href: '/ux-datatables/integrations/mercure/' },
    ],
  },
  {
    label: 'Reference',
    items: [
      { label: 'AbstractDataTable', href: '/ux-datatables/reference/abstract-datatable/' },
      { label: 'DataTable', href: '/ux-datatables/reference/datatable/' },
      { label: 'DataTableRequest', href: '/ux-datatables/reference/datatable-request/' },
      { label: 'Filters', href: '/ux-datatables/reference/filters/' },
      { label: 'Attributes', href: '/ux-datatables/reference/attributes/' },
      { label: 'Edit Modal', href: '/ux-datatables/reference/edit-modal/' },
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
      { label: 'Quick Start', href: '/ux-datatables/getting-started/quick-start/' },
      { label: 'Recipes', href: '/ux-datatables/recipes/' },
    ],
  },
  {
    label: 'Key Features',
    items: [
      { label: 'Columns', href: '/ux-datatables/columns/overview/' },
      { label: 'Options', href: '/ux-datatables/guide/options/' },
      { label: 'Extensions', href: '/ux-datatables/extensions/' },
      {
        label: 'Server-Side Processing',
        href: '/ux-datatables/guide/server-side-processing/',
      },
    ],
  },
]

export function flattenSidebarLinks(): NavItem[] {
  return docsSidebarSections.flatMap((section) => section.items)
}

export function getAdjacentPages(pathname: string): {
  prev: NavItem | null
  next: NavItem | null
} {
  const items = flattenSidebarLinks()
  const index = items.findIndex((item) => item.href === pathname)

  if (index === -1) {
    return { prev: null, next: null }
  }

  return {
    prev: index > 0 ? items[index - 1] : null,
    next: index < items.length - 1 ? items[index + 1] : null,
  }
}
