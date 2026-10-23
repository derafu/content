---
title: "Draft Module"
description: "A module marked as draft, with a child lesson that is not."
draft: true
---
A module marked `draft: true`, used to check that its child lesson (which is
not marked as draft) is blocked all the same by inheritance, and that
`AcademyCourse::modules()` does not include it.
