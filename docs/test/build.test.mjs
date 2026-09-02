import assert from 'node:assert/strict'
import { readFile, readdir } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import test from 'node:test'

const dist = new URL('../dist/', import.meta.url)

async function readDistFile(path) {
  return readFile(new URL(path, dist), 'utf8')
}

/** Every documentation page, in sidebar order. */
const docRoutes = [
  'getting-started/introduction/',
  'getting-started/installation/',
  'getting-started/quick-start/',
  'getting-started/first-server-side-table/',
  'getting-started/security/',
  'getting-started/ai-assisted-development/',
  'features/overview/',
  'features/when-to-use-what/',
  'recipes/',
  'recipes/client-side-inline/',
  'recipes/doctrine-server-side/',
  'recipes/filters/',
  'recipes/actions-csrf-voter/',
  'recipes/money-column/',
  'recipes/mercure-live-update/',
  'guide/usage/',
  'guide/configuration/',
  'guide/options/',
  'guide/styling/',
  'guide/filters/',
  'guide/client-side-processing/',
  'guide/server-side-processing/',
  'columns/overview/',
  'columns/text-column/',
  'columns/number-column/',
  'columns/money-column/',
  'columns/date-column/',
  'columns/boolean-column/',
  'columns/choice-column/',
  'columns/email-column/',
  'columns/url-column/',
  'columns/image-column/',
  'columns/icon-column/',
  'columns/template-column/',
  'columns/action-column/',
  'extensions/',
  'extensions/buttons/',
  'extensions/select/',
  'extensions/column-control/',
  'extensions/responsive/',
  'extensions/keytable/',
  'extensions/scroller/',
  'extensions/fixed-columns/',
  'extensions/col-reorder/',
  'extensions/row-group/',
  'extensions/fixed-header/',
  'extensions/combining-extensions/',
  'integrations/api-platform/',
  'integrations/mercure/',
  'reference/abstract-datatable/',
  'reference/datatable/',
  'reference/datatable-request/',
  'reference/filters/',
  'reference/attributes/',
  'reference/edit-modal/',
  'reference/data-providers-row-mappers/',
  'reference/query-filters/',
  'reference/search-strategies/',
  'reference/custom-exporters/',
  'reference/enums/',
  'reference/maker/',
  'reference/profiler/',
]

/** `recipes` and `extensions` are authored hub pages, so they are not generated. */
const sectionRoutes = [
  'getting-started/',
  'features/',
  'guide/',
  'columns/',
  'integrations/',
  'reference/',
]

test('every route is built', async () => {
  assert.equal(existsSync(dist), true, 'run npm run build before npm run test:build')
  assert.equal(docRoutes.length, 62)

  for (const route of [...docRoutes, ...sectionRoutes]) {
    assert.equal(existsSync(new URL(`${route}index.html`, dist)), true, `missing ${route}`)
  }

  assert.equal(existsSync(new URL('404.html', dist)), true)
})

test('the site is addressed from its GitHub Pages sub-path', async () => {
  const index = await readDistFile('index.html')
  const sitemap = await readDistFile('sitemap-index.xml')
  const pages = await Promise.all(docRoutes.map((route) => readDistFile(`${route}index.html`)))
  const output = [index, ...pages].join('\n')

  assert.match(index, /href="\/ux-datatables\/getting-started\/quick-start\/"/)
  assert.match(sitemap, /https:\/\/pentiminax\.github\.io\/ux-datatables\//)

  // MDX authors link root-relative; rehypeBaseLinks adds the base at build time.
  assert.doesNotMatch(
    output,
    /href="\/(getting-started|features|recipes|guide|columns|extensions|integrations|reference)\//
  )
})

test('search ships and loads without a Vite preload', async () => {
  const security = await readDistFile('getting-started/security/index.html')
  const pagefindFiles = await readdir(new URL('pagefind/', dist))

  assert.ok(pagefindFiles.includes('pagefind.js'))
  assert.match(security, /<dialog class="search-dialog"/)
  assert.match(security, /data-search-input/)
  assert.match(security, /data-pagefind-body/)
  // Pagefind is imported through a runtime path, so Vite must not have rewritten it.
  assert.match(security, /location\.origin/)
  assert.doesNotMatch(security, /__VITE_PRELOAD__/)
})

test('every documentation page carries the reading chrome', async () => {
  const pages = await Promise.all(docRoutes.map((route) => readDistFile(`${route}index.html`)))

  for (const [i, page] of pages.entries()) {
    const route = docRoutes[i]

    assert.match(page, /aria-label="Breadcrumb"/, `${route} has no breadcrumb`)
    assert.match(page, /<dialog class="docs-drawer"/, `${route} has no mobile drawer`)
    assert.match(page, /aria-label="Documentation navigation"/, `${route} has no nav`)
    assert.match(page, /aria-label="Previous and next pages"/, `${route} has no pager`)
    assert.match(page, /Edit this page on GitHub/, `${route} has no edit link`)
    assert.match(page, /aria-label="Switch to (dark|light) theme"/, `${route} has no theme toggle`)
    // The frontmatter title is the only h1 — no page repeats it in its body.
    assert.equal(page.match(/<h1[\s>]/g).length, 1, `${route} does not have exactly one h1`)
  }

  const first = pages[0]
  const last = pages.at(-1)

  assert.doesNotMatch(first, /pager-prev/, 'the first page must not offer a previous link')
  assert.doesNotMatch(last, /pager-next/, 'the last page must not offer a next link')
})

test('row-group is reachable, not only built', async () => {
  // It shipped as an orphan page: present on disk, absent from the sidebar and
  // skipped by the pager. The navigation is derived from the collection now.
  const colReorder = await readDistFile('extensions/col-reorder/index.html')
  const fixedHeader = await readDistFile('extensions/fixed-header/index.html')

  assert.match(colReorder, /pager-next"[^>]*href="[^"]*extensions\/row-group\//)
  assert.match(fixedHeader, /pager-prev"[^>]*href="[^"]*extensions\/row-group\//)
  assert.match(colReorder, /docs-nav[\s\S]*extensions\/row-group\//)
})

test('headings are anchored', async () => {
  const installation = await readDistFile('getting-started/installation/index.html')

  assert.match(installation, /<a class="heading-anchor"[^>]*href="#/)
  assert.match(installation, /aria-label="Link to this section"/)
})

test('section index pages list their own pages', async () => {
  const columns = await readDistFile('columns/index.html')

  assert.match(columns, /<h1>Columns<\/h1>/)
  assert.match(columns, /href="\/ux-datatables\/columns\/money-column\/"/)
  assert.match(columns, /href="\/ux-datatables\/columns\/action-column\/"/)
})

test('the home page states what the bundle is', async () => {
  const index = await readDistFile('index.html')

  assert.match(index, /UX DataTables/)
  assert.match(index, /<h1>Declare the table\. Ship the grid\.<\/h1>/)
  assert.match(index, /composer require pentiminax\/ux-datatables/)
  // The sample table must read as a table with JavaScript disabled.
  assert.match(index, /<table class="mock-table"[\s\S]*<th scope="col">Reference<\/th>/)
  // The home page is the only entry point to the sections for a first-time reader.
  assert.match(index, /href="\/ux-datatables\/guide\/server-side-processing\/"/)
})

test('the 404 page is a real page', async () => {
  const notFound = await readDistFile('404.html')

  assert.match(notFound, /<h1>/)
  assert.match(notFound, /href="\/ux-datatables\/getting-started\/installation\/"/)
})

test('the design tokens ship and no sibling UX package leaked in', async () => {
  const assetFiles = await readdir(new URL('_astro/', dist))
  const css = (
    await Promise.all(
      assetFiles
        .filter((file) => file.endsWith('.css'))
        .map((file) => readDistFile(`_astro/${file}`))
    )
  ).join('\n')

  assert.match(css, /--accent:/)
  // Tailwind is gone: no utility layer, and colors come from tokens only.
  assert.doesNotMatch(css, /--tw-/)
  assert.doesNotMatch(css, /ux-driver|ux-sweet-alert/)
})
