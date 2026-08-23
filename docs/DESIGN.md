---
name: UX DataTables Documentation
description: The documentation site for a Symfony bundle that renders DataTables.net grids from a PHP class.
colors:
  indigo-accent: '#4a46c4'
  indigo-accent-hover: '#3c39a3'
  indigo-surface: '#eff0fb'
  indigo-border: '#c2c2ef'
  amber-focus: '#f4d35e'
  amber-warm: '#a17c11'
  symfony-crimson: '#c62734'
  teal-tip: '#0a6350'
  slate-page: '#f7f9fc'
  slate-surface: '#ffffff'
  slate-rule: '#e3e9f2'
  slate-text: '#1a2233'
  slate-muted: '#5a6880'
  slate-subtle: '#7b8aa5'
  slate-code: '#0b1020'
typography:
  display:
    fontFamily: "'Familjen Grotesk', ui-sans-serif, system-ui, sans-serif"
    fontSize: 'clamp(2.4rem, 1.7rem + 3.4vw, 4rem)'
    fontWeight: 700
    lineHeight: 1.08
    letterSpacing: '-0.022em'
  headline:
    fontFamily: "'Familjen Grotesk', ui-sans-serif, system-ui, sans-serif"
    fontSize: 'clamp(1.5rem, 1.34rem + 0.8vw, 1.9rem)'
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: '-0.012em'
  title:
    fontFamily: "'Familjen Grotesk', ui-sans-serif, system-ui, sans-serif"
    fontSize: 'clamp(1.25rem, 1.19rem + 0.3vw, 1.4rem)'
    fontWeight: 700
    lineHeight: 1.3
  body:
    fontFamily: "'Source Sans 3', ui-sans-serif, system-ui, sans-serif"
    fontSize: '1.0625rem'
    fontWeight: 400
    lineHeight: 1.72
  label:
    fontFamily: "'Familjen Grotesk', ui-sans-serif, system-ui, sans-serif"
    fontSize: '0.6875rem'
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: '0.06em'
  code:
    fontFamily: "'JetBrains Mono', ui-monospace, 'SF Mono', monospace"
    fontSize: '0.875rem'
    fontWeight: 400
    lineHeight: 1.5
rounded:
  sm: '0.375rem'
  md: '0.625rem'
  lg: '1rem'
  full: '999px'
spacing:
  '1': '0.25rem'
  '2': '0.5rem'
  '3': '0.75rem'
  '4': '1rem'
  '5': '1.25rem'
  '6': '1.5rem'
  '8': '2rem'
  '10': '2.5rem'
  '12': '3rem'
  '16': '4rem'
  '20': '5rem'
  '24': '6rem'
components:
  button-primary:
    backgroundColor: '{colors.indigo-accent}'
    textColor: '#ffffff'
    rounded: '{rounded.md}'
    padding: '0 1.25rem'
    height: '2.75rem'
    typography: '{typography.body}'
  button-primary-hover:
    backgroundColor: '{colors.indigo-accent-hover}'
    textColor: '#ffffff'
  button-secondary:
    backgroundColor: '{colors.slate-surface}'
    textColor: '{colors.slate-text}'
    rounded: '{rounded.md}'
    padding: '0 1.25rem'
    height: '2.75rem'
  nav-item:
    backgroundColor: 'transparent'
    textColor: '{colors.slate-muted}'
    rounded: '{rounded.sm}'
    padding: '0.5rem 0.75rem'
    typography: '{typography.body}'
  nav-item-current:
    backgroundColor: '{colors.indigo-surface}'
    textColor: '{colors.indigo-accent}'
  search-result-selected:
    backgroundColor: '{colors.indigo-surface}'
    textColor: '{colors.slate-text}'
    rounded: '{rounded.md}'
    padding: '0.75rem'
  aside-note:
    backgroundColor: '{colors.indigo-surface}'
    textColor: '{colors.slate-text}'
    rounded: '{rounded.md}'
    padding: '1rem 1.25rem'
---

# Design System: UX DataTables Documentation

## Overview

**Creative North Star: "The Well-Composed Table"**

The site borrows the vocabulary of the thing it documents. A DataTable is a strict grid with a
frozen header, one active row, and a visible sort direction; the documentation is built from the
same parts. Columns are separated by 1px rules rather than by cards, the header stays frozen at the
top of the viewport, the page you are reading is marked the way a selected row is marked, and the
one piece of motion on the site is rows settling after a sort. A reader is already inside a
DataTable while reading about one.

Density carries the identity. Nothing floats: the shell is three columns — navigation, prose, table
of contents — divided by hairlines, and surfaces are distinguished by tone and rule rather than by
elevation. Color is scarce on purpose. Indigo is the only accent that appears at rest; amber exists
only in the focus halo; Symfony crimson appears only where Symfony itself is named. The result reads
as reference material, not as a marketing page that happens to contain code.

The site refuses the two defaults of its category: a card grid of feature tiles standing in for
structure, and a screenshot standing in for the product. The home page's sample table is a real
sortable table, and the sections are entered through a list of their own pages.

**Key Characteristics:**

- Rules, not cards: structure is drawn with 1px hairlines.
- One accent (indigo), one reserved highlight (amber), one contextual mark (crimson).
- Two type families with sharply distinct jobs: Familjen Grotesk for structure, Source Sans 3 for
  reading.
- Hover-only zebra; state is shown at the moment of interaction, never permanently.
- Everything readable, navigable, and themable with JavaScript disabled.

## Colors

A cool, near-neutral slate field with one saturated accent; every hue in the system has exactly one
job.

### Primary

- **Signal Indigo** (`#4a46c4`): every interactive commitment — primary button, current navigation
  row, link, selected search result, focus ring at `#5b5bd6`. It is the only saturated color a page
  shows at rest.
- **Indigo Wash** (`#eff0fb`): the surface behind the current row, the selected search result, and
  the hovered table row. It marks position, never importance.

### Secondary

- **Reserved Amber** (`#f4d35e`): the focus halo only — `color-mix(in srgb, var(--amber-500) 55%,
  transparent)` behind the indigo outline. **Warm Amber** (`#a17c11`) carries warning asides.
- **Symfony Crimson** (`#c62734`): used where Symfony itself is meant, and for danger asides. It is
  a citation, not a palette color.
- **Guide Teal** (`#0a6350`): tip asides only.

### Neutral

- **Page Slate** (`#f7f9fc`): the page field. Surfaces sit above it in pure white (`#ffffff`).
- **Rule Slate** (`#e3e9f2` strong, `#e3e9f2`/`#eff3f9` hairline): every divider — column
  separators, section rules above `h2`, table borders.
- **Ink** (`#1a2233` text, `#5a6880` muted, `#7b8aa5` subtle): a three-step text ramp; subtle is
  reserved for eyebrow labels and inactive glyphs.
- **Terminal Navy** (`#0b1020`): the code block field in both themes, so a snippet reads the same
  whatever the page is doing.

### Named Rules

**The One Accent Rule.** Indigo is the only saturated color on a page at rest. Amber may not paint a
surface, a border, or text — it exists as the focus halo. Crimson appears only where Symfony is the
subject.

**The Two-Theme Rule.** Dark is declared twice: once as `@media (prefers-color-scheme: dark)
:root:not([data-theme='light'])` for visitors before the theme script runs or with JavaScript off,
once as `:root[data-theme='dark']` so the toggle wins in both directions. A color defined in only
one of the two is a bug.

**The Primitive Containment Rule.** Ramps (`--indigo-500`, `--slate-800`, …) are referenced only
inside `tokens.css`. Every other file reads semantic tokens (`--accent`, `--rule`, `--row-hover`),
so a theme change is a one-file change.

## Typography

**Display Font:** Familjen Grotesk (fallback `ui-sans-serif, system-ui, sans-serif`)
**Body Font:** Source Sans 3 (fallback `ui-sans-serif, system-ui, sans-serif`)
**Label/Mono Font:** JetBrains Mono (fallback `ui-monospace, 'SF Mono', monospace`)

**Character:** Familjen Grotesk is geometric and slightly condensed — it holds a heading tight and
gives uppercase micro-labels the look of column headers. Source Sans 3 is a workhorse humanist face
that stays legible at 1.72 line-height over long technical paragraphs. The pairing is deliberately
unglamorous: structure is the expressive layer, type is the calm one.

### Hierarchy

- **Display** (700, `clamp(2.4rem, 1.7rem + 3.4vw, 4rem)`, 1.08, `-0.022em`): the home page
  headline only.
- **Headline** (700, `clamp(1.5rem, 1.34rem + 0.8vw, 1.9rem)`, 1.3): page `h1` and section `h2`.
  Every `h2` opens with a 1px rule above it and 4rem of air.
- **Title** (700, `clamp(1.25rem, 1.19rem + 0.3vw, 1.4rem)`, 1.3): `h3`.
- **Body** (400, `1.0625rem`, 1.72): prose, capped at a 46rem measure (`--prose-max`).
- **Label** (700, `0.6875rem`, `0.06em`, uppercase): sidebar section headings, aside titles, table
  headers, the section badge in search results.
- **Code** (400, `0.875rem`, 1.5): inline code and blocks; identifiers keep their own casing even
  inside an uppercase label.

### Named Rules

**The Single H1 Rule.** The `h1` and the one-line summary come from frontmatter and are rendered
once by the layout. No page body writes its own title; the build test asserts exactly one `h1` per
page.

**The Column-Header Rule.** Uppercase, wide-tracked micro-type is how the site says "this labels a
group" — sidebar sections, aside kinds, table headers. It is never used for a sentence.

## Layout

A three-column shell (`--sidebar-w: 16rem` / prose / `--toc-w: 14rem`) inside a `82rem` container,
separated by 1px rules rather than gutters or cards. The header is `4rem` tall and sticky — the
frozen header row of the page — and headings carry `scroll-margin-top: calc(var(--header-h) +
var(--space-6))` so an anchored jump never lands under it.

Spacing is a 4px scale (`0.25rem` → `6rem`). Prose rhythm: `1rem` between paragraphs, `4rem` above
an `h2` (with its rule), `2.5rem` above an `h3`. Prose is capped at `46rem`; the home page and
section indexes opt into the full `82rem` width.

Responsive behavior is a progressive collapse, not a separate design: below 1180px the table of
contents becomes a `<details>` panel under the header; below 1000px the sidebar becomes a `<dialog>`
drawer opened from a sticky section bar that shows the current section and page; below 600px the
sample table drops its least important column. There are no breakpoint-specific components — the
same markup narrows.

## Elevation & Depth

The system is flat by doctrine. Depth comes from tone and rule: the page field is one step darker
than surfaces, dividers are hairlines, and a "raised" surface is simply a lighter tone with a
border. Shadows exist for exactly one thing — elements that leave the document flow.

### Shadow Vocabulary

- **`--shadow-1`** (`0 1px 2px rgb(15 21 36 / 0.06), 0 1px 3px rgb(15 21 36 / 0.05)`): resting lift
  on the home page's sample table.
- **`--shadow-2`** (`0 2px 4px rgb(15 21 36 / 0.05), 0 8px 18px -6px rgb(15 21 36 / 0.12)`): the
  mobile drawer.
- **`--shadow-3`** (`0 4px 8px rgb(15 21 36 / 0.06), 0 22px 48px -12px rgb(15 21 36 / 0.22)`): the
  search dialog, the only element that floats over the whole page.

### Named Rules

**The Rule-Over-Shadow Rule.** Structure is drawn with `1px solid var(--rule)`. A shadow is only
allowed on something that has actually left the flow — dialog, drawer, or the one demonstrative
table. A card with a shadow standing in for a section is not this system.

## Shapes

Corners are quiet: `0.375rem` on small controls and nav rows, `0.625rem` on buttons, asides, code
blocks, and search results, `1rem` on the two large floating surfaces, and `999px` only on the
section badge inside a search result. Borders do the work corners would otherwise do: 1px at
`--border`, escalating to `--border-strong` where a boundary must read as structural (table header
underline, secondary button).

The recurring silhouette is a bordered rectangle divided by hairlines. Nothing is clipped, tilted,
or given a decorative frame.

## Components

### Buttons

- **Shape:** softly rounded rectangle (`0.625rem`), `2.75rem` minimum height, `1.25rem` inline
  padding.
- **Primary:** Signal Indigo field, white label, 600 weight, transparent 1px border so it aligns
  optically with secondary buttons.
- **Hover / Focus:** background deepens to `#3c39a3` over 120ms; `:active` nudges 1px down; focus is
  the shared 2px indigo outline with a 2px offset over an amber halo.
- **Secondary:** white surface, `--border-strong` outline, ink label; on hover the surface sinks a
  step and the border takes the accent tint.

### Cards / Containers

Cards are used only where a card is literally the content (mode summaries and section entry points
on the home page). Corner `0.625rem`, surface white, 1px `--border`, `1.25rem`–`1.5rem` internal
padding, no shadow at rest. They never stand in for page structure.

### Inputs / Fields

The search field is a borderless input on the dialog's surface, separated from the results by a
single 1px rule and preceded by a `1.0625rem` search glyph. Its own outline is suppressed because
the dialog itself carries focus; every other control uses the global `2px solid var(--focus)` ring
with a `2px` offset.

### Navigation

Sidebar rows are `0.875rem` muted text on `0.5rem`/`0.75rem` padding, grouped under uppercase
section labels. Hover fills with the sunken surface. The current page is the active row: indigo
wash, 600 weight, indigo text, and `box-shadow: inset 2px 0 0 var(--accent)` as a solid marker at
the start of the line. On mobile the same component renders inside a `<dialog>` drawer, opened from
a sticky bar that reads `Section / Page`.

### Search Dialog

The signature component. A native `<dialog>` (⌘K / Ctrl+K / `/`) with an ARIA combobox: the input
owns `aria-expanded` and `aria-activedescendant`, results are `role="option"` rows carrying a title,
an uppercase section badge, and a two-line clamped excerpt, and the footer states the keys. The
selected row uses the indigo wash — the same marker as the current sidebar row, so "where you are"
looks the same everywhere. Pagefind is imported through a runtime path so the build cannot preload a
bundle that only exists after it.

### Sample Table (home)

The one demonstrative element and the only authored motion. Header cells are uppercase micro-labels
on the frozen-header tone with a `--border-strong` underline; each carries a caret that is neutral
until its column is chosen and rotates to the sort direction. Rows zebra on hover only. After a
sort, rows re-enter with a 45ms-per-row stagger (`row-settle`), suppressed under
`prefers-reduced-motion`. The sort buttons are injected by script, so with JavaScript off the reader
gets a plain, legible table rather than four dead controls.

### Asides

Four tones (note / tip / warning / danger) built from a shared triple of foreground, background, and
border tokens: indigo, teal, amber, crimson. The title is an uppercase micro-label; an identifier
inside it keeps its own casing.

## Do's and Don'ts

### Do:

- **Do** draw structure with `1px solid var(--rule)` and tone changes, the way the site's own
  three-column shell is drawn.
- **Do** read semantic tokens (`--accent`, `--text-muted`, `--rule`) in every file except
  `tokens.css`.
- **Do** declare a new themed color in all three places: light `:root`, the
  `prefers-color-scheme: dark` block, and `:root[data-theme='dark']`.
- **Do** ship behavior that degrades: content, navigation, theme, and tables must work with
  JavaScript disabled, and enhancement controls are injected by script rather than authored dead in
  the markup.
- **Do** keep the accent for interaction and position; a decorative use of indigo weakens every
  functional one.
- **Do** gate motion behind `prefers-reduced-motion: no-preference`.

### Don't:

- **Don't** add a shadow to something that has not left the document flow.
- **Don't** use a grid of same-size icon-and-heading cards as page structure; sections are entered
  through lists of their real pages.
- **Don't** paint with amber. It is the focus halo and warning tone, nothing else.
- **Don't** use crimson except where Symfony itself is the subject, or for a danger aside.
- **Don't** hardcode the site's base path in a link; `withBase()` owns it, and authored MDX links
  stay root-relative.
- **Don't** write an `h1` in page content — the layout renders the frontmatter title once.
- **Don't** introduce permanent zebra striping or a second accent hue to add "variety" to a dense
  page; density is carried by rules and spacing.
