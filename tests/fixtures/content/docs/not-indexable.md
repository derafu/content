---
title: "Not Indexable"
description: "A doc NOT draft, NOT unlisted, with an explicit indexable: false, used to check that /api/content.json (and by transitivity the external indexing) excludes it, while searchable stays on its default (true), independent of indexable."
indexable: false
---
The content of a doc with an explicit `indexable: false` in the frontmatter,
without being draft or unlisted. It has to stay visible/listable on the site,
but excluded from the export `/api/content.json`.
