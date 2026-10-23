---
title: "Lesson with an unreachable remote test"
description: "An isolated fixture: 'test' points to a port where nothing is listening, to check that icon() does not trigger the HTTP fetch just to choose the icon."
test: "http://127.0.0.1:19899/"
---
The content of an isolated lesson used only by
`AcademyLessonRemoteTestSourceTest` to check `icon()` when the remote "test"
is unreachable.
