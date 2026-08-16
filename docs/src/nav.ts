import { getCollection } from 'astro:content'

export interface NavSection {
  id: string
  title: string
  description: string
}

export interface NavItem {
  slug: string
  title: string
  navTitle: string
  description: string
  section: NavSection
}

/**
 * Reading order of the whole site. A page's directory decides its section, so a
 * new `.mdx` appears in the sidebar and the pager without touching this file.
 */
export const sections: NavSection[] = [
  {
    id: 'getting-started',
    title: 'Getting started',
    description: 'Install the bundle, render a first table, and secure the Ajax routes it exposes.',
  },
  {
    id: 'features',
    title: 'Features',
    description: 'What the bundle does, and which processing mode a given table should use.',
  },
  {
    id: 'recipes',
    title: 'Recipes',
    description: 'Complete tables you can paste into a project — entity, table class, controller, template.',
  },
  {
    id: 'guide',
    title: 'Guide',
    description: 'Configuration, options, styling, filters, and the two processing modes in depth.',
  },
  {
    id: 'columns',
    title: 'Columns',
    description: 'Every column type, its options, and what it renders on the client.',
  },
  {
    id: 'extensions',
    title: 'Extensions',
    description: 'The official DataTables.net extensions the bundle wires up for you.',
  },
  {
    id: 'integrations',
    title: 'Integrations',
    description: 'API Platform column discovery and live updates over Mercure.',
  },
  {
    id: 'reference',
    title: 'Reference',
    description: 'Classes, attributes, enums, and the Ajax contract, described exactly.',
  },
]

const sectionsById = new Map(sections.map((section) => [section.id, section]))

/**
 * A page outside a declared section would be unreachable from the sidebar, so
 * this throws at build time rather than shipping an orphan.
 */
export function sectionOf(slug: string): NavSection {
  const id = slug.includes('/') ? slug.slice(0, slug.indexOf('/')) : slug
  const section = sectionsById.get(id)

  if (!section) {
    throw new Error(
      `Page "${slug}" is not in a known section directory (${sections.map((item) => item.id).join(', ')}).`,
    )
  }

  return section
}

/** Every page in sidebar and pager order. */
export async function getDocsNav(): Promise<NavItem[]> {
  const entries = await getCollection('docs')

  return entries
    .map((entry) => ({
      slug: entry.id,
      title: entry.data.title,
      navTitle: entry.data.navTitle ?? entry.data.title,
      description: entry.data.description,
      section: sectionOf(entry.id),
      order: entry.data.order ?? Number.MAX_SAFE_INTEGER,
    }))
    .sort(
      (a, b) =>
        // `order` is scoped to a section, so the section rank has to come first —
        // otherwise the pager walks sideways between sections sharing a number.
        sections.indexOf(a.section) - sections.indexOf(b.section) ||
        a.order - b.order ||
        a.slug.localeCompare(b.slug),
    )
}

export async function getDocsNavSections(): Promise<{ section: NavSection; items: NavItem[] }[]> {
  const nav = await getDocsNav()

  return sections
    .map((section) => ({
      section,
      items: nav.filter((item) => item.section.id === section.id),
    }))
    .filter((group) => group.items.length > 0)
}

/** The site is served from a GitHub Pages sub-path; no link may hardcode it. */
export function withBase(path = ''): string {
  return `${import.meta.env.BASE_URL}/${path}`.replace(/\/{2,}/g, '/')
}
