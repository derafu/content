---
title: "Curso De Módulos Mixtos"
description: "Curso normal, no draft, con un módulo hijo en draft y otro visible, usado para validar la cascada de allowed() y el filtrado de modules()/lessons()."
---
Curso de prueba usado solo por los tests automatizados de
`derafu/content`, para validar que un módulo en `draft: true` (y su
lección hija, que no lo está) queden excluidos de `modules()`,
`lessons()`, `videos()`, `attachments()`, `time()` y del nav del curso.
