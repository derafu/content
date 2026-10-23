---
title: "Lesson with a remote test"
description: "An isolated fixture: it does not live under tests/fixtures/content/academy/ on purpose, so that no other test that walks that tree triggers the HTTP fetch of this remote 'test'."
test: "http://127.0.0.1:19802/?scenario=academy_test_ok"
---
The content of an isolated lesson used only by
`AcademyLessonRemoteTestSourceTest`, built directly (without a
plugin/registry) so that this scenario is not mixed with the content tree
shared by the rest of the Academy suite.
