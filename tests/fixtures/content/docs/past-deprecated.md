---
title: "Past Deprecated"
description: "A doc NOT draft, NOT unlisted, with deprecated on a date that is already past, used as a control: this SHOULD be treated as deprecated today (indexable/searchable false)."
deprecated: "2020-01-01"
---
The content of a doc with `deprecated: "2020-01-01"` (a date that is already
past). `indexable()`/`searchable()` should be `false`, as they behave today:
it is a control/regression case against the future date.
