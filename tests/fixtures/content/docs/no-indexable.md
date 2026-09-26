---
title: "No Indexable"
description: "Doc NO draft, NO unlisted, con indexable: false explícito, usado para validar que /api/content.json (y por transitividad la indexación externa) lo excluye, mientras que searchable queda en su default (true), independiente de indexable."
indexable: false
---
Contenido de un doc con `indexable: false` explícito en el frontmatter,
sin estar en draft ni unlisted. Debe seguir siendo visible/listable en el
sitio, pero excluido del export `/api/content.json`.
