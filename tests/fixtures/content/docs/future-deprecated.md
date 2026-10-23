---
title: "Future Deprecated"
description: "A doc NOT draft, NOT unlisted, with deprecated on a future date, used to check that it should not be treated as deprecated (indexable/searchable) before that date arrives."
deprecated: "2099-01-01"
---
The content of a doc with `deprecated: "2099-01-01"` (a future date). It
should not be considered deprecated yet, so `indexable()`/`searchable()`
should stay `true` until that date arrives.
