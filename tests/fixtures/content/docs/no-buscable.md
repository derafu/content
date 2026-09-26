---
title: "No Buscable"
description: "Doc NO draft, NO unlisted, con searchable: false explícito, usado para validar que ese flag es independiente de indexable (que debe quedar en su default: true)."
searchable: false
---
Contenido de un doc con `searchable: false` explícito en el frontmatter.
Debe seguir siendo indexable (para /api/content.json) e igual de
visible/listable, solo se excluye de la búsqueda propia del sitio.
