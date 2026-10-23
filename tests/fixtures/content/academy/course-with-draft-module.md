---
title: "Course With Mixed Modules"
description: "A normal course, not draft, with a draft child module and a visible one, used to check the cascade of allowed() and the filtering of modules()/lessons()."
---
A test course used only by the automated tests of `derafu/content`, to check
that a module with `draft: true` (and its child lesson, which is not draft)
are excluded from `modules()`, `lessons()`, `videos()`, `attachments()`,
`time()` and from the nav of the course.
