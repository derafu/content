---
title: "Pasado Deprecado"
description: "Doc NO draft, NO unlisted, con deprecated en una fecha ya pasada, usado como control: esto SÍ debería tratarse como deprecado hoy (indexable/searchable en false)."
deprecated: "2020-01-01"
---
Contenido de un doc con `deprecated: "2020-01-01"` (fecha ya pasada).
`indexable()`/`searchable()` deberían ser `false`, tal como ya se
comporta hoy — sirve de control/regresión frente al caso de fecha futura.
