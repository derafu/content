---
title: "Not Searchable"
description: "A doc NOT draft, NOT unlisted, with an explicit searchable: false, used to check that this flag is independent of indexable (which has to stay on its default: true)."
searchable: false
---
The content of a doc with an explicit `searchable: false` in the frontmatter.
It has to stay indexable (for /api/content.json) and just as visible/listable,
it is only excluded from the own search of the site.
