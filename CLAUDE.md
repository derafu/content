# derafu/content — Plan de internacionalización (i18n)

> Registra los hallazgos de la auditoría y el plan de trabajo para hacer
> `derafu/content` completamente traducible, siguiendo la misma
> arquitectura que ya usan `derafu/translation`, `derafu/data-processor` y
> `derafu/form`. La lista de tareas queda **siempre sujeta a revisión justo
> antes de implementar** — este documento es la memoria entre sesiones, no
> una autorización para ejecutar sin mirar de nuevo.

## Contexto necesario (ya resuelto en otros repos, no rederivar)

- `derafu/translation`: `TranslatableExceptionTrait`, `TranslatableMessage`,
  `TranslatorFactory::create($locale, $fallbackLocales, $resourceProviders)`,
  `TranslationResourceRegistrar`, `TranslationResourceProviderInterface`.
  Archivos `resources/translations/{domain}(+intl-icu)?.{locale}.{format}`.
  El sufijo `+intl-icu` en el dominio es obligatorio para que placeholders
  tipo `{value}` se expandan (si no, cae a sustitución `%name%` estilo
  Symfony legado, y puede corromper el string vía `strtr()` si de todas
  formas usas sintaxis `{name}` — ya nos pasó dos veces en otras sesiones).
- El id de traducción es el texto real en inglés (no keys tipo
  `content.not_found`), por decisión ya tomada para librerías compartidas
  (así lo documenta Symfony y lo seguimos en `data-processor`/`form`).
- Patrón "excepción con `sprintf()` en el mensaje" = bug: el id queda
  distinto en cada llamada (incluye el valor dinámico), nunca calza con el
  catálogo. Se arregla pasando a forma array-ICU:
  `['...{param}...', 'param' => $valor]`.
- `_instanceof`/`_defaults` en YAML de `symfony/dependency-injection` **no
  se propagan entre archivos importados** — cada archivo solo aplica a lo
  que él mismo declara. Por eso los providers de recursos se tagean
  explícito (`tags: ['derafu_translation.resource_provider']`), nunca vía
  `_instanceof` de un archivo ajeno.
- El wiring de un `Translator` real (locale, fallback, providers) es
  responsabilidad de la app consumidora, nunca de la librería.

## Hallazgos de la auditoría

### 1. Excepciones (trabajo mecánico, chico)

Dos clases, ambas ya bien diseñadas (extienden `TranslatableRuntimeException`,
constructor propio `(message, ?previous)` delegando a
`parent::__construct($message, 0, $previous)`, implementan
`HttpExceptionInterface`). El problema es que casi todos los sitios que las
lanzan usan `sprintf()` para armar el mensaje, lo que rompe la
traducibilidad (ver bug de arriba). Van al dominio `errors+intl-icu` (el
default del trait, mismo criterio que `ValidationException`).

**`src/Exception/ContentNotFoundException.php`** — 10 sitios:

| Archivo:línea | Mensaje actual (sprintf) | Id ICU propuesto |
|---|---|---|
| `Abstract/AbstractContentRegistry.php:105` | `'Content item with URI "%s" (slug: %s) is not allowed.'` | `'Content item with URI "{uri}" (slug: {slug}) is not allowed.'` |
| `Abstract/AbstractContentRegistry.php:116` | `'Content item with URI "%s" not found.'` | `'Content item with URI "{uri}" not found.'` |
| `Abstract/AbstractContentRegistry.php:122` | `'Content item with URI "%s" (slug: %s) not found.'` | `'Content item with URI "{uri}" (slug: {slug}) not found.'` |
| `Plugin/Storage/StorageController.php:60` | `'Content not found.'` | Ya está bien (string estático), sin cambios de código. |
| `Plugin/Storage/StorageController.php:68` | `'Attachment for the content not found.'` | Ya está bien, sin cambios de código. |
| `Plugin/Storage/StorageController.php:77` | `'Attachment not found.'` | Ya está bien, sin cambios de código. |
| `Plugin/Blog/BlogArchive.php:81` | `'Invalid archive "%s": expected it to start with a "YYYYMM" prefix.'` | `'Invalid archive "{date}": expected it to start with a "YYYYMM" prefix.'` |
| `Plugin/Academy/AcademyController.php:114` | `'Module "%s" not found.'` | `'Module "{module}" not found.'` |
| `Plugin/Academy/AcademyController.php:172` | `'Module "%s" not found.'` | Mismo id que el anterior (duplicado, está bien). |
| `Plugin/Academy/AcademyController.php:178` | `'Lesson "%s" not found.'` | `'Lesson "{lesson}" not found.'` |

**`src/Plugin/Search/Exception/SearchUpstreamException.php`** — 6 sitios:

| Archivo:línea | Mensaje actual (sprintf) | Id ICU propuesto |
|---|---|---|
| `Plugin/Search/SearchEngine.php:91` | `'The search engine at "%s" could not be reached: %s.'` (con `$e->getMessage()`) | `'The search engine at "{url}" could not be reached: {reason}.'` |
| `Plugin/Search/SearchEngine.php:~103` | `'The search engine at "%s" returned HTTP %d%s.'` con sufijo condicional `: %s` | `'The search engine at "{url}" returned HTTP {status}{detail}.'` — calcular `$detail` en PHP igual que hoy (`''` o `': ' . $detail`) y pasarlo como parámetro, sin lógica ICU adicional. |
| `Plugin/Search/SearchEngine.php:~114` | `'...returned a response without a "results" field.'` | `'The search engine at "{url}" returned a response without a "results" field.'` |
| `Plugin/Search/OpenAiCompatibleLlmClient.php:103` | `'The LLM backend at "%s" could not be reached: %s.'` | `'The LLM backend at "{url}" could not be reached: {reason}.'` |
| `Plugin/Search/OpenAiCompatibleLlmClient.php:~115` | `'The LLM backend at "%s" returned HTTP %d%s.'` mismo patrón condicional | `'The LLM backend at "{url}" returned HTTP {status}{detail}.'` |
| `Plugin/Search/OpenAiCompatibleLlmClient.php:~129` | `'...returned a response without a "choices[0].message.content" field.'` | `'The LLM backend at "{url}" returned a response without a "choices[0].message.content" field.'` |

**Bug aparte, no relacionado al sprintf:** `composer.json` **no declara
`derafu/translation`** pese a que ambas excepciones dependen de él en
duro. Hoy "funciona" solo porque `derafu/http` lo arrastra transitivamente,
apuntando al commit viejo pre-rediseño (`d2d79cef`) en el lock actual. Hay
que agregarlo explícito a `require` de todas formas, más allá de la
traducción.

> ✅ **Fase 1 completada.** Ver sección "Fase 1" más abajo para el detalle
> de lo implementado y verificado.

### 2. Plantillas Twig (el trabajo grande)

43 archivos, **cero infraestructura de i18n**: ni un `trans()`, ni
extensión Twig, ni concepto de locale en todo el repo. `content-services.yaml`
no toca el renderer en absoluto — armar el `RendererInterface` (engines,
extensions, paths) es trabajo de la app consumidora (confirmado contra el
`services.yaml` real de `tools.libredte.cl`).

Categorías encontradas:
- UI corta y repetida entre archivos (~30-50 strings únicas): `Search`,
  `Copy`, `Next`, `Tags:`, `No results found`, badges por plugin.
- Oraciones con parámetros ICU (~20-30): `Found {count} results for
  "{query}"`, `Last updated on {date}`, `by {authors}`.
- Prosa larga tipo marketing (~10-20 bloques, ej. el hero de academy es un
  bloque HTML multi-párrafo completo) — **decisión tomada: sin trato
  especial, todo en inglés y traducible como cualquier otro string.**
  Cuidado al implementar: si algún bloque contiene `{`/`}` literales (CSS o
  JSON embebido), hay que escaparlo o restructurarlo para no romper el
  parser ICU — revisar caso a caso al migrar cada bloque.
- **Español ya hardcodeado, sin avisar**, en 7 archivos (`blog/index`,
  `blog/tag`, `blog/archive`, `layouts/blog`, `docs/tag`, `faq/tag`,
  `academy/tag`): frases tipo "Por {authors} el {date} • {time} min de
  lectura", "Leer más". No es "traducir el inglés" — es sacar el español
  ad-hoc, poner un id en inglés, y mover ese texto a la traducción `es`
  real.
- **Fuera de alcance, confirmado:** `layouts/*.html.twig` extienden
  `layouts/default.html.twig`, que no existe en este repo — el chrome del
  sitio (nav/footer) vive en `derafu/twig` o en la app. No es de este repo.
- Al margen (no es de traducción, solo para no perderlo): `search/index.html.twig`
  línea 59 tiene un `<div id="llm-response">HELLO</div>` que parece
  placeholder de debug olvidado.

### 3. JS (client-side)

Dos categorías bien distintas, que no deben tratarse igual:

- **`<script>` inline en archivos `.twig`** (`_downloads.html.twig`,
  sección "AI Assistant" de `search/index.html.twig`, ~8 strings): estos
  YA pasan por Twig en cada render, igual que cualquier otro texto de la
  plantilla. **No son un caso especial.** `_downloads.html.twig` —
  ✅ **corregido** (`Copied!`, `Could not copy`, `Error`, vía
  `{{ '...'|trans|e('js') }}`, mismo patrón que ya usa `{{ query|e('js') }}`
  en `search/index.html.twig`; verificado ejecutando el JS resultante con
  node, no solo mirando el string). Queda pendiente aplicar lo mismo a la
  sección "AI Assistant" de `search/index.html.twig` — es mecánico, mismo
  patrón, ninguna decisión de por medio.

- **`resources/js/blog.js`, `academy.js`** (~15 strings: mensaje de error
  genérico duplicado en ambos, texto de la card de blog, "True/False"
  hardcodeado bilingüe como parche, "Review answers"/"Correct"/"Incorrect"/
  "Result: ..." en `academy.js`). Estos son módulos ES **que ningún
  `.twig` de este repo carga ni invoca** (confirmado: cero referencias a
  `initBlogCards`/`initTestForm` en `resources/templates/`) — están
  pensados para que la app consumidora los importe en su propio bundle y
  los invoque pasándoles un contenedor.

  ⚠️ **Se descartó una idea (atributos `data-*` con el string en inglés
  como default, traducido por la app al armarlo)** por una razón de
  fondo, no de detalle: eso traslada a cada app consumidora una
  responsabilidad — traducir texto que pertenece a esta librería — que el
  resto de `derafu/content` ya no tiene (todo lo demás se traduce solo,
  vía el catálogo de este paquete). `docs.js` no tiene este problema (no
  tiene texto visible). Sigue sin resolverse cómo una librería le entrega
  a un módulo JS *desconectado de Twig* un default ya traducido —
  requiere diseño real (¿renderizar estos `.js` como `.js.twig` por
  request, perdiendo el cacheo de asset estático? ¿otro mecanismo?), no
  algo para decidir apurado. Queda **explícitamente abierto**, sin fecha.

## Decisiones ya tomadas

- **Dominio único `content+intl-icu`** para todos los strings de UI de
  Twig/JS de este paquete. No hay motivo real para dividir por plugin: los
  plugins no son paquetes Composer independientes, viven todos en el mismo
  repo — a diferencia de `data-processor`/`form`, que sí necesitaban
  dominios separados por ser paquetes distintos entre sí. Si el archivo de
  traducción crece demasiado, se puede dividir en **archivos** por
  plugin/carpeta (todos apuntando al mismo dominio, ya que
  `TranslationResourceRegistrar` fusiona múltiples recursos del mismo
  dominio/locale sin problema) — eso es organización de archivos, no una
  razón para más dominios.
- **Excepciones se quedan en `errors+intl-icu`** (default del trait), no en
  `content+intl-icu` — son una categoría distinta (errores, no copy de UI),
  mismo criterio que `ValidationException` en `data-processor`.
- **Prosa larga: sin trato especial.** Todo texto visible al usuario final
  va en inglés y es traducible por el mismo mecanismo `trans()`/ICU.
- **No se inventa una extensión Twig en `derafu/content`.** Pero tampoco se
  usa `symfony/twig-bridge` tal cual: se probó con un vendor real instalado
  y trae 55 archivos (~500KB) con extensiones de Csrf, Form, HttpKernel,
  Security, Serializer, Workflow, etc. que no usamos — peso muerto en el
  autoload solo para obtener el filtro `|trans`. En su lugar, la extensión
  vive en `derafu/twig` (`Derafu\Twig\Extension\TranslationExtension`,
  **ya implementada y verificada** — ver sección "Fase 2"), que reusa
  `Derafu\Translation\TranslatableMessage` de este mismo ecosistema en vez
  de las clases de Symfony. Mismo filtro `|trans` y función `t()` que
  Symfony, misma estructura, sin la dependencia pesada, y de paso
  resuelve el matiz de comportamiento sin traductor (ver sección "Fase 2").
- **Orden de trabajo:** primero lo mecánico (excepciones + composer.json),
  después la extensión Twig + migración de plantillas.

## Decisión pendiente externa (no resolver acá)

Corrección sobre la primera pasada de este plan: **no todo el JS de este
repo depende de una decisión externa.** Los `<script>` inline en archivos
`.twig` ya pasan por Twig — no tienen nada especial, se tradujeron como
cualquier otro texto (ver "3. JS" arriba, `_downloads.html.twig` ya
corregido).

Lo que sigue genuinamente pendiente es más angosto de lo que se pensó:
cómo una librería (`derafu/content`, y a futuro otros paquetes como
`derafu/form` vía `derafu-js`) le entrega a un **módulo JS desconectado de
Twig** (`resources/js/*.js`, importado por el bundle de la app
consumidora, nunca renderizado por PHP) un texto ya traducido por
defecto, sin que cada app tenga que traducirlo de nuevo por su cuenta. No
es "dónde vive un `window.i18n`" — es más de fondo: ¿esos `.js` pasan a
ser `.js.twig` renderizados por request (con el costo de perder el cacheo
de asset estático)? ¿otro mecanismo? **No implementar nada ad-hoc acá.**
Mientras no haya una decisión, `blog.js`/`academy.js` quedan **fuera de
alcance** — no bloquean nada más, porque ningún `.twig` de este repo los
carga.

## Plan de trabajo (fases)

### Fase 1 — Mecánico (excepciones) — ✅ COMPLETADA

Implementado y verificado (tests, phpstan, php-cs-fixer, y un `Translator`
real vía path-repo, con y sin DI):

1. `derafu/translation` agregado a `require` en `composer.json`
   (`composer update derafu/translation` corrido; quedó en la versión
   nueva del componente, ya no en el commit viejo transitivo).
2. Los 13 sitios `sprintf()` de las tablas de arriba pasados a forma
   array-ICU (los 3 de `StorageController.php` no se tocaron, ya estaban
   bien). Total real: **15 strings únicas** entre ambas excepciones (no
   ~11 como se estimó antes de leer el código exacto).
3. `resources/translations/errors+intl-icu.es.php` creado con las 15
   traducciones.
4. `Derafu\Content\Translation\ContentTranslationResourceProvider` creado,
   apunta a `resources/translations/` (misma carpeta que va a usar también
   el dominio `content+intl-icu` de la fase 2 — un solo provider para
   ambos dominios).
5. Tageado en `content-services.yaml` (`tags: ['derafu_translation.resource_provider']`,
   explícito).
6. Verificado de punta a punta: `ContentNotFoundException`/`SearchUpstreamException`
   traducidas correctamente con un `Translator` real construido vía
   `TranslatorFactory`, y también vía un `ContainerBuilder` real con el tag
   de DI (sin tocar el registrar a mano). 181 tests existentes siguen en
   verde, phpstan y php-cs-fixer sin novedad.

### Fase 2 — Traducción de plantillas Twig

#### `Derafu\Twig\Extension\TranslationExtension` — ✅ ya implementada y verificada (en `derafu/twig`)

Se evaluó usar `symfony/twig-bridge` tal cual y se descartó: probado con un
vendor real instalado, sus hard requires son livianos
(`symfony/translation-contracts` + `twig/twig`), pero el paquete completo
trae 55 archivos (~500KB) con extensiones de Csrf, Form, HttpKernel,
Security, Serializer, Workflow, WebLink, Emoji, etc. — nada de eso se usa,
es peso muerto en el autoload solo para tener el filtro `|trans`.

En su lugar se escribió `Derafu\Twig\Extension\TranslationExtension` en el
repo `derafu/twig` (junto a `RoutingExtension`/`MarkdownExtension`, que ya
cumplen ese mismo rol de "bridge" liviano para este ecosistema — ni
`derafu/renderer` ni `derafu/translation` son el lugar correcto: el primero
es agnóstico de motor de plantillas, el segundo debe seguir siendo
agnóstico de Twig, misma razón por la que Symfony separó `twig-bridge` de
`symfony/translation`). `derafu/twig` pasó a tener `derafu/translation`
como `require` real (no opcional), porque la extensión usa
`TranslatableMessage` incondicionalmente.

Misma estructura que Symfony, implementación propia y liviana:

- Filtro **`|trans`**: `{{ 'The field {field} is required.'|trans({'field': 'email'}) }}`,
  o con dominio explícito como cuarto uso: `{{ '...'|trans({'field': x}, 'content+intl-icu') }}`.
- Función **`t()`**: arma un `Derafu\Translation\TranslatableMessage` (la de
  este ecosistema, no la de Symfony) sin traducirlo todavía — útil para
  pasar el mensaje a algo que lo traduce después, con otro locale. Costo
  de implementación nulo (`return new TranslatableMessage(...)`), se
  incluyó por paridad de estructura aunque hoy no hay un caso de uso
  concreto en `derafu/content`.
- **`{% trans_default_domain 'content+intl-icu' %}` sí está implementado.**
  Corrección sobre la primera pasada de este plan: se había descartado por
  el costo de acoplarse a los internals del compilador de Twig (un
  `TokenParser` + `Node` marcador + `NodeVisitor` que camina el AST), pero
  eso subestimaba que una biblioteca compartida (`derafu/twig`) **no puede
  asumir un dominio por defecto** — cada app/paquete que la usa necesita
  poder fijar el suyo por plantilla, sin pisarse entre sí. Se reimplementó
  el tag, tomando como base la implementación real de Symfony (MIT, mismo
  algoritmo) pero **simplificada** para apuntar solo a la versión de Twig
  actual (3.28: sin la rama de compatibilidad con clases internas viejas
  que tiene el código de Symfony) y solo al filtro `|trans` (sin el tag de
  bloque `{% trans %}...{% endtrans %}`, que esta extensión no tiene). Con
  eso quedó en ~150 líneas en vez de ~200, y sin la doble rama de
  compatibilidad. **También admite una expresión dinámica** como dominio
  (`{% trans_default_domain some_var %}`), no solo un string literal — una
  biblioteca compartida no puede exigirle a sus consumidores que el dominio
  sea siempre una constante conocida en tiempo de compilación. Se evalúa
  una sola vez (verificado con test: una expresión con efecto secundario
  contado se ejecuta 1 vez, no una por cada `|trans` que la usa) guardando
  el resultado en una variable de plantilla sintética, referenciada luego
  por cada `trans` afectado — mismo mecanismo que usa Symfony, sin rama de
  compatibilidad porque solo apuntamos a la API actual de Twig. Verificado
  con Twig real: aplica al `|trans` sin dominio explícito, un dominio
  explícito lo sigue pisando, el scope respeta bloques anidados (un
  `{% trans_default_domain %}` dentro de un `{% block %}` no se filtra
  hacia afuera), y el caso dinámico funciona igual. El dominio del
  constructor de la extensión (`new TranslationExtension($translator, $domain)`)
  sigue existiendo como *fallback* opcional para quien no use el tag, nunca
  como algo que la extensión asuma por su cuenta. 8 tests nuevos en
  `derafu/twig` cubriendo todo esto.
- **`{% trans %}...{% endtrans %}` (tag de bloque) — ✅ implementado.**
  Corrección sobre la pasada anterior de este plan: se había descartado,
  pero no por costo real (es comparable en tamaño al tag de dominio: en
  Symfony son ~220 líneas entre `TransTokenParser` y `TransNode`). Se
  reimplementó, deliberadamente distinto a Symfony: **nunca auto-extrae
  variables del texto** (Symfony escanea el cuerpo con una regex buscando
  `%name%`, convención legacy incompatible con ICU); acá los parámetros
  siempre van explícitos vía `with`, y los placeholders en el texto son
  `{name}` (ICU), igual que en todo el resto del ecosistema:
  ```twig
  {% trans with {'name': user.name} from 'content+intl-icu' %}Hello {name}!{% endtrans %}
  ```
  También soporta `into 'locale'`, y hereda `{% trans_default_domain %}`
  cuando no se pasa `from`. El cuerpo debe ser texto plano — si contiene
  una expresión Twig (`{{ ... }}`), lanza un `SyntaxError` claro en
  compilación (el id de traducción no puede depender de una variable en
  tiempo de ejecución: nunca calzaría con el catálogo, mismo motivo por el
  que se prohibió `sprintf()` en mensajes de excepción).
  **Hallazgo real durante la verificación, documentado en el código:** este
  tag (ni ningún otro mecanismo que ponga texto plano en el cuerpo de una
  plantilla Twig) puede usarse con un mensaje ICU que tenga un plural/select
  (`{count, plural, one {# item} other {# items}}`): el *lexer* de Twig
  trata cualquier `{#` en el texto de la plantilla como inicio de un
  comentario `{# ... #}`, **antes** de que el código de esta extensión
  llegue a ejecutarse — rompe la plantilla completa (o, peor, se "come"
  contenido en silencio si el mensaje contiene después un `#}` de casualidad).
  No es un bug de esta extensión ni arreglable desde una extensión de Twig:
  es una limitación del lexer para cualquier texto plano, no relacionada con
  traducción. En un `Environment` de Twig "pelado" (solo extensiones de
  Twig, sin componentes) el filtro `\|trans`/`t()` **no** tiene este
  problema, porque ahí el mensaje vive dentro de un string literal de
  Twig, que se lexea literal sin buscar `{{`/`{%`/`{#`.
  ⚠️ **Corrección posterior (ver Fase 2 abajo): esa garantía no se sostiene
  siempre en este repo**, porque acá SÍ se usa `symfony/ux-twig-component`
  (`<twig:block-hero>` etc.) — se encontró un caso real donde el mismo
  `{#`, dentro de un string pasado a `\|trans`, rompió una plantilla que
  también usaba `{% extends %}` y un componente `<twig:...>`, con un error
  que no menciona nada de comentarios. Regla real y verificada: **evitar
  el substring `{#` en cualquier mensaje ICU de este repo, venga de
  `{% trans %}`, `\|trans` o `t()`** — no alcanza con "usar el filtro en
  vez del tag". 6 tests nuevos en `derafu/twig` (14 en total para toda la
  extensión) cubren el tag/filtro en un `Environment` sin componentes; el
  caso con `ux-twig-component` se verificó por separado, directamente
  sobre este repo (ver Fase 2).
- **El matiz del fallback sin traductor queda resuelto**, no solo
  documentado: a diferencia de `symfony/twig-bridge` (que cae a un
  traductor identidad basado en `TranslatorTrait`/`strtr()` plano, sin
  soporte ICU, cuando no hay `Translator`), esta extensión reusa
  `TranslatableMessage::__toString()` como fallback — el mismo mecanismo
  ICU-siempre-funciona usado en `InputActionResolver` de `derafu/form`.
  Verificado con Twig real: `{{ 'Hello {name}!'|trans({'name': 'Juan'}) }}`
  sin ningún traductor configurado da `Hello Juan!` (correcto), no
  corrompe el placeholder.
- Verificado de punta a punta con un `Environment` de Twig real (no solo en
  PHP puro): con traductor real (es) + dominio `+intl-icu` traduce e
  interpola bien: `Hola Juan!`; sin traductor cae al fallback ICU descrito
  arriba; `t()` impreso directo también formatea bien vía `__toString()`.
  phpstan y php-cs-fixer del repo `derafu/twig` sin novedad.

#### Tareas en `derafu/content` — ✅ COMPLETADAS

1. `derafu/twig` agregado como `suggest` en `composer.json` (no `require`
   — `derafu/content` no referencia ninguna clase de Twig en PHP, es la
   app consumidora quien registra la extensión en su renderer, igual que
   ya hace con `FormTwigExtension`).
2. Documentado en el `suggest` de `composer.json` que la app debe agregar
   `new Derafu\Twig\Extension\TranslationExtension($translator, 'content+intl-icu')`
   a la lista de `extensions` de su `RendererInterface` (una guía
   "Translations" dedicada, análoga a las de `data-processor`/`form`,
   queda pendiente si se estima necesaria más adelante — no bloqueante).
3. Las 43 plantillas migradas: texto hardcodeado reemplazado por
   `{{ '...'|trans }}` / `{{ '...'|trans({param: value}) }}` /
   `{% trans %}...{% endtrans %}`, con `{% trans_default_domain 'content+intl-icu' %}`
   al principio de cada archivo con texto traducible (evita repetir el
   dominio en cada `|trans`). **90 strings únicas** en total (bastante
   menos que "43 archivos × N strings cada uno" por la repetición
   encontrada). 3 archivos de fragmentos de navegación (`*/_nav_item.html.twig`)
   no necesitaron cambios: son 100% data-driven, sin texto hardcodeado.
4. Los 7 archivos con español hardcodeado quedaron limpios: el texto en
   español salió del código, quedó un id en inglés, y la traducción real
   se movió a `content+intl-icu.es.php`. De paso se tradujeron a inglés
   comentarios HTML sueltos en español (`<!-- Barra lateral -->` →
   `<!-- Sidebar -->`, etc.) encontrados en los mismos archivos al
   tocarlos.
5. `resources/translations/content+intl-icu.es.php` creado con las 90
   traducciones.
6. El `HELLO` de debug en `search/index.html.twig:59` se eliminó (el div
   se deja vacío; el JS ya lo puebla en `DOMContentLoaded` si hay query).
7. Verificado de punta a punta: compilación real de las 43 plantillas sin
   errores de sintaxis; renders reales con datos falsos cubriendo los
   patrones más delicados (atributo HTML embebido resuelto vía `{% set %}`
   antes de interpolarlo, bloque de héroe con HTML multi-párrafo vía
   `{% set %}`, plural ICU + HTML + `|raw`, breadcrumbs con parámetro ICU,
   dominio de PDF traducido junto con el passthrough de placeholders de
   mPDF `{PAGENO}`/`{nb}`, herencia de dominio dentro de macros). Los 181
   tests existentes del repo (que ejercitan renders reales de varias de
   estas plantillas) siguen en verde, agregando `TranslationExtension`
   (con traductor `null`, fallback ICU) a los 4 puntos donde los tests
   arman su propio `TwigService`/`RendererInterface`
   (`tests/src/Support/RendererFixture.php` y 3 tests que arman el suyo
   propio). phpstan y php-cs-fixer sin novedad.

**Hallazgo real, no documentado antes de esta pasada:** el sufijo `{#`
dentro de un string de Twig pasado a `|trans` (ej. la sintaxis ICU corta
de plural `{count, plural, one {# item} other {# items}}`) puede romper
la plantilla igual que en el tag `{% trans %}` — **no solo ahí**. Se
encontró en `academy/index.html.twig`: con `symfony/ux-twig-component`
registrado (el motor real de `<twig:block-hero>` etc., no presente en un
`Environment` de Twig "pelado"), ese mismo `{#` en un string literal
—dentro de un archivo que además usa `{% extends %}`— produce
`Twig\Error\SyntaxError: A template that extends another one cannot
include content outside Twig blocks`, un error que no menciona nada de
comentarios y que hace parecer que el problema es otra cosa. No se logró
aislar la causa exacta dentro de `ux-twig-component` (que no es código de
este repo ni de `derafu/twig`), pero se confirmó con certeza, por
bisección exhaustiva sobre el archivo real, que el disparador es
exactamente el substring `{#`.

**Ojo con el "arreglo" ingenuo:** reemplazar `#` por una referencia
literal `{count}` al MISMO argumento usado como selector de plural NO es
seguro tampoco — ICU real (`MessageFormatter`, tanto en el fallback como,
vía Symfony, con un traductor real) lanza
`U_ARGUMENT_TYPE_MISMATCH` cuando el mismo nombre de argumento se usa a
la vez como selector de plural y como referencia literal dentro de una
rama. `#` existe justo para evitar ese conflicto — no es intercambiable
por `{count}`. Se detectó porque las pruebas de este repo usan traductor
`null` (fallback ICU), que no exhibe este error — solo apareció al probar
con un `Translator` real y el catálogo `.es.php` real. El arreglo correcto,
verificado con traductor real y con fallback: pasar el mismo valor bajo
**dos nombres de argumento distintos**, uno solo para seleccionar el
plural y otro solo para mostrarlo:

```twig
{% set lessonsCount = course.lessons|length %}
{{ '{count, plural, one {{n} lesson} other {{n} lessons}}'|trans({'count': lessonsCount, 'n': lessonsCount}) }}
```

**Regla ampliada:** evitar el substring `{#` en cualquier mensaje ICU
usado en plantillas de este repo, no solo dentro de `{% trans %}` —
incluye a `|trans`/`t()` también, al menos mientras se use `<twig:...>`
(componentes de `symfony/ux-twig-component`) en el mismo archivo. Ningún
otro string del catálogo tenía el patrón `{#` (se revisó); si se agregan
más plurales ICU a futuro, usar el patrón de doble alias de arriba, nunca
`#` seguido de `{` ni una referencia `{count}` duplicada al selector.

### Fase 3 — JS

- `<script>` inline en `.twig` — ✅ `_downloads.html.twig` corregido.
  Pendiente, mecánico (sin decisión de por medio): la sección "AI
  Assistant" de `search/index.html.twig` (~8 strings), mismo patrón
  `{{ '...'|trans|e('js') }}`.
- `resources/js/blog.js`/`academy.js` — bloqueados, ver "Decisión
  pendiente externa" arriba (ahora acotada: no es dónde vive un
  `window.i18n`, es cómo una librería traduce por defecto un módulo JS que
  Twig nunca renderiza).

## Antes de implementar cualquier fase

Releer esta lista y confirmar que sigue vigente — pueden haber cambiado
cosas en `derafu/translation`, `derafu/data-processor` o `derafu/form`
entre sesiones.
