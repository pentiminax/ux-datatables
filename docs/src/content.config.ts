import { defineCollection } from 'astro:content'
import { z } from 'astro/zod'
import { glob } from 'astro/loaders'

const docs = defineCollection({
  loader: glob({ pattern: '**/*.{md,mdx}', base: './src/content/docs' }),
  schema: z.object({
    title: z.string(),
    description: z.string(),
    /** Rank in the sidebar and the pager. Unranked pages sort last. */
    order: z.number().optional(),
    /** Shorter label for the sidebar when the page title is long. */
    navTitle: z.string().optional(),
  }),
})

export const collections = { docs }
