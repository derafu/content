---
title: "Lección con test remoto"
description: "Fixture aislado: no vive bajo tests/fixtures/content/academy/ a propósito, para que ningún otro test que recorra ese árbol dispare el fetch HTTP de este 'test' remoto."
test: "http://127.0.0.1:19802/?scenario=academy_test_ok"
---
Contenido de una lección aislada usada solo por
`AcademyLessonRemoteTestSourceTest`, construida directamente (sin
plugin/registry) para que este escenario no se mezcle con el árbol de
contenido compartido por el resto de la suite de Academy.
