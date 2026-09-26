---
title: "Futuro Deprecado"
description: "Doc NO draft, NO unlisted, con deprecated en una fecha futura, usado para validar que no debería tratarse como deprecado (indexable/searchable) antes de que llegue esa fecha."
deprecated: "2099-01-01"
---
Contenido de un doc con `deprecated: "2099-01-01"` (fecha futura). No
debería considerarse deprecado todavía, así que `indexable()`/
`searchable()` deberían seguir en `true` hasta que llegue esa fecha.
