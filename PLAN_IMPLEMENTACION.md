# Plan de implementación

## Entrega 0 — Contexto técnico (integrada)

Se integró `inicio-adaptacion.patch` sobre el commit existente, sin reconstruir el
historial ni reemplazar las instrucciones originales de `AGENTS.md` y
`CLAUDE.md`. La documentación de producto, auditoría, arquitectura, contrato BI,
plan y continuación de trabajo está en el repositorio. Laravel, Inertia, React,
Bun y Recharts se mantienen.

## Entrega 1 — Métricas fiables (implementación integrada; despliegue local recuperado)

- Seguidores: `null` cuando no hay lecturas válidas; cero se conserva como
  medición. La cobertura cuenta cuentas no Discord medidas respecto de todas
  las cuentas no Discord consultadas en el workspace activo. El total se
  describe como suma por cuenta, no como personas únicas. El delta agregado es
  `null` si alguna cuenta del denominador no tiene dos lecturas comparables.
- Engagement: solo destinos `ok` aportan contadores al resumen, ranking y
  baseline anterior. `failed`, `unsupported` y `rate_limited` conservan sus
  datos almacenados, pero no se exponen como mediciones actuales. La cobertura
  es destinos medidos respecto de todos los destinos de posts publicados o
  parcialmente publicados en el intervalo.
- Publicaciones: el conteo completo de posts `published`/`partial` conserva el
  cero conocido. Las tarjetas distinguen ausencia de datos y cero, muestran
  cobertura y precisan que engagement son los últimos contadores exitosos de
  posts publicados en el intervalo, no interacciones necesariamente ocurridas
  durante él.
- No se añadieron migraciones ni se cambió el aislamiento existente por
  workspace. Se actualizaron juntos controlador, tipos, componentes y pruebas.

### Verificación efectuada

- La validación registrada previamente pasó Pest (`20 tests, 294 assertions`).
  No se repitió: el controlador PHP y sus pruebas no cambiaron desde esa
  ejecución.
- Revalidación del 27-10-2026, en contenedores efímeros y sin usar la base
  persistente:
  - Oxfmt en los cuatro archivos frontend modificados: aprobado.
  - Vitest focalizado en analytics: `9 tests passed`.
  - Oxlint en los cuatro archivos frontend modificados: `0 warnings, 0 errors`.
  - TypeScript: `tsc --noEmit` aprobado después de generar Wayfinder dentro del
    contenedor efímero, porque esos archivos generados no se incluyen en la
    imagen runtime.
  - Pint en el controlador y la prueba PHP modificados: aprobado.
  - Larastan/PHPStan, nivel 7 (`composer types:check`): `[OK] No errors`.
- La imagen desplegada `shoutrrr:dev` contiene hashes idénticos a los archivos
  modificados de aplicación y pruebas. El Compose de desarrollo no monta el
  código fuente (solo `storage` y `database/sqlite`); no hizo falta reconstruir
  ni copiar archivos al contenedor. Se conserva el mismo servicio, proyecto y
  volúmenes.
- Docker Engine responde. `shoutrrr-app-1` está `running` y `healthy`; la ruta
  de analítica respondió HTTP 200 y `/` redirige a `/login` cuando no se usa
  una sesión autenticada.
- La pantalla se inspeccionó en el navegador integrado: muestra las tarjetas
  Followers, Engagement y Posts published, sus definiciones y coberturas
  parciales (6/8 cuentas para seguidores y 72/87 destinos para engagement).
  La instancia contiene datos de demostración existentes (por ejemplo, Acme y
  una publicación `dummy-analytics`), así que la inspección valida el render y
  no resultados comerciales reales. No se añadieron datos persistentes.
  Playwright no registró errores de consola ni excepciones de página durante
  la carga. Los estados de sin medición, cero válido y cobertura parcial están
  cubiertos por las pruebas aisladas; la vista actual no expone un caso sin
  datos.
- El control inicial de formato y el primer intento de Oxlint no pudieron
  arrancar porque los lanzadores esperaban `node`, ausente en la imagen runtime.
  Se resolvió sin cambios al proyecto: Oxfmt se ejecutó con Bun y Oxlint con un
  enlace `node` temporal al Bun dentro del contenedor efímero.
- El primer `tsc` encontró 175 imports Wayfinder no generados, no errores de
  tipos de la aplicación. Tras generarlos en el entorno efímero, pasó.
- El contenedor histórico `rsmanager-analytics-test-...` finalizó con código
  137, `OOMKilled=false`, sin logs y con comando `sleep infinity`; no demuestra
  que se bloqueara una validación ni que hubiera falta de memoria. No hay
  evidencia disponible para determinar la causa exacta de ese cierre anterior.
  En la comprobación actual no había procesos de validación activos; el host
  informó 1.3 GiB de memoria física libre y la app usaba 648 MiB de un límite de
  3.8 GiB. `/tmp/rsmanager-delivery1-test` no existe en la app ni en el
  contenedor de inspección, por lo que no hubo que borrar archivos.

### Estado de cierre de la entrega

- Implementación, controles requeridos y despliegue local verificados. El
  trabajo permanece en la rama actual; no se han modificado credenciales,
  APP_KEY, configuración de base de datos ni volúmenes.
- No se avanza a las entregas posteriores. El commit de la entrega
  `14325d73f9b5ca9a11c041d5c13858700b46e4b3` ya se publicó mediante push
  normal a `origin/main`, con coincidencia del SHA local y remoto.

## Entrega 2 — Conexiones reales (en curso; no completada)

Validar Facebook, Instagram y LinkedIn con cuentas de prueba autorizadas. Separar perfil y página LinkedIn y comprobar capacidades del producto OAuth contratado/aprobado. Registrar publicación y lectura de métricas, permisos, errores y renovación. No enviar contenido real de clientes sin autorización editorial. Determinar si se necesita la prueba Upload-Post/Ayrshare descrita en arquitectura.

El inventario de capacidades, rutas OAuth, configuración observada, pruebas
aisladas, referencias de código y bloqueos está en
[MATRIZ_CONEXIONES.md](./MATRIZ_CONEXIONES.md). El conector Meta añadió
validación explícita de `state` OAuth porque el flujo Socialite stateless no
estaba verificando ese valor; la prueba focalizada cubre el envío y el rechazo
de un estado incorrecto.

La configuración local efectiva no contiene credenciales de aplicaciones
Meta ni LinkedIn. Los registros de cuentas existentes no se consideran
conexiones reales ni evidencia de acceso. Por tanto, ninguna lectura de API ni
publicación real fue autorizada o ejecutada. La matriz registra la validación
real como pendiente y Entrega 2 permanece abierta.

## Entrega 3 — Dashboard y reportes (implementada; revisión visual pendiente)

La pantalla de analítica incorpora filtros por periodo, plataforma y cuenta,
restringidos al workspace autorizado. Resumen, gráficos y ranking usan el mismo
conjunto filtrado y la zona horaria del workspace. Se conservan `null` frente a
cero válido y las reglas de cobertura de la Entrega 1. Se añadieron la vista
imprimible y la exportación CSV autorizada, con filtros, cliente/workspace,
periodo, zona horaria, fecha de generación, cobertura y definiciones. La fecha
de generación se distingue de la última actualización de métricas y esta solo
se muestra cuando hay evidencia. Los endpoints vuelven a autorizar el acceso,
rechazan cuentas ajenas al workspace, escapan CSV y neutralizan fórmulas. No
hay enlaces públicos de reportes ni datos de prueba persistidos.

Los controles del candidato pasaron: Pest focalizado (`70 tests, 571
assertions`), Vitest focalizado (7 pruebas de analytics y 11 de composer),
Oxfmt, Oxlint (0 warnings, 0 errors), TypeScript, Pint y Larastan/PHPStan nivel
7. `build:ssr` y la construcción Docker también pasaron. Los estados de sin
datos, cero válido, cobertura parcial, límites de fecha/zona horaria,
autorización de workspace y seguridad CSV se verificaron con pruebas aisladas.

El candidato está desplegado en el servicio local `shoutrrr-app-1` desde la
imagen `shoutrrr:dev` con digest
`sha256:3aadbfd3d4b59d6f3ccdcc4e5588ff06764ab7155fdc69833df231d22b1dc466`;
el contenedor está `healthy` y `/analytics` redirige a `/login` sin sesión.
La revisión visual de analítica, impresión, CSV y pantallas de conexiones no
está completa porque el navegador integrado no tiene una sesión autenticada.
No se usaron los valores autofill de la pantalla ni se añadieron datos. Para
evitar efectos externos durante la inspección, los procesos internos de cola y
scheduler están temporalmente deshabilitados mediante variables de entorno de
la ejecución Compose; no se modificaron `.env`, Compose ni volúmenes.

## Entrega 4 — Creación asistida y aprobación

Inventariar capacidades actuales antes de añadirlas. Proponer proveedor IA, costes por workspace, almacenamiento de borradores y control de cuotas. Diseñar estados editoriales sin confluir con estados de envío. Aceptación: el modelo no publica directamente; cambios tras aprobación requieren una política explícita; permisos y auditoría por workspace.

## Entrega 5 — Campañas, UTM y CRM

Elegir CRM con el propietario y definir eventos/identidades. Registrar fuente, campaña, leads, oportunidades e ingresos verificables. Ingesta idempotente, deduplicación y minimización de PII. Aceptación: evento repetido no duplica ingresos; no se mezclan monedas; atribución y ventas reales se distinguen.

## Entrega 6 — Anuncios y SaaS productivo

Evaluar Meta Ads inicialmente, permisos, límites de gasto y aprobación humana. LinkedIn Ads/Google Ads son alcance posterior propuesto. Revisar facturación existente, cuotas, backups restaurables, observabilidad y despliegue antes de clientes reales. No activar gasto publicitario como efecto de una prueba técnica.

## Registro de avance

Actualizar aquí fecha, commit, alcance, pruebas ejecutadas y limitaciones por entrega. No marcar completado un conector por existir en código ni un dashboard por tener datos simulados.

28-10-2026: Se cerró la causa del fallo de build sin retirar la directiva
`# syntax` ni desactivar BuildKit. El último build previo había fallado al
resolver `docker/dockerfile:1` (`DeadlineExceeded`) y luego con
`Unknown frontend grpc server closed unexpectedly`. Los builders `default` y
`desktop-linux` apuntaban al mismo Engine ID
`4271d2ca-8dae-4c43-b8dc-018b97c07eea` y worker BuildKit
`5840ed33-33c5-476b-818c-36bafe9cd15e`; `desktop-linux` era el contexto
activo y `DOCKER_CONTEXT`, `DOCKER_HOST` y `BUILDX_BUILDER` no estaban
definidas. La imagen del frontend ya estaba cacheada; se construyó una vez usando
`BUILDKIT_SYNTAX=docker/dockerfile:1@sha256:4edf897a3ffa55b89f906fc8cc78afdb3f1834cc9c7083565e611a8a7d5fe99e`
con el comando exacto `docker compose -f docker-compose.development.yaml build
--build-arg BUILDKIT_SYNTAX=docker/dockerfile:1@sha256:4edf897a3ffa55b89f906fc8cc78afdb3f1834cc9c7083565e611a8a7d5fe99e app`
(código 0). La imagen resultante tiene el digest registrado en Entrega 3.
Los registros retenidos no conservan la línea de comando ni el código de
salida de cada intento fallido; sí conservan los errores causales anteriores y
el comando exacto y el código 0 del reintento correctivo.

La primera recreación produjo timeouts de `frankenphp build-info` a los 60 s,
junto con procesos solapados de scheduler, `posts:dispatch-due` y healthcheck;
se observó hasta 9892 % de CPU. El contenedor usaba alrededor de 595 MiB de
3.8 GiB y no hubo evidencia de OOM; el host sí informó 0.44 GiB de memoria
física libre. Se recreó solo `app`, sin build y sin tocar los volúmenes, con
`QUEUE_WORKER_ENABLED=false` y `SCHEDULER_ENABLED=false` como overrides
temporales de proceso para impedir envíos durante la inspección. Tras completar
el arranque de debug, Octane inició, la app quedó `healthy`, el uso de CPU
descendió y `/analytics` respondió con redirección HTTP 302 a `/login`. Los
overrides no modificaron `.env` ni `docker-compose.development.yaml`.

La aplicación y la imagen se verificaron localmente, pero la revisión visual
autenticada sigue pendiente: el navegador integrado mostró el login y no se
dispone de una sesión iniciada para verificar analítica, impresión, CSV,
compositor y conexiones. No se iniciaron OAuth, publicaciones, ni se
insertaron datos. Entrega 2 sigue abierta por validación real externa; Entrega
3 no se declara completada hasta terminar la inspección visual.

26-10-2026: Entregas 0 y 1 integradas localmente sobre `main` (base
`28bbc7f`). Pest focalizado pasó (20 tests, 294 assertions, SQLite en memoria).
En ese momento el cierre estaba bloqueado porque Docker Engine dejó de
responder y los reinicios CLI agotaron el tiempo de espera.

27-10-2026: Docker Engine recuperado y servicio local saludable; analítica HTTP
200 inspeccionada visualmente en el navegador integrado. Oxfmt, Vitest (9),
Oxlint, TypeScript, Pint y Larastan nivel 7 pasaron en contenedores efímeros.
Pest no se repitió porque el código PHP y sus pruebas no cambiaron desde la
ejecución aprobada (20 tests, 294 assertions). La imagen ya contenía el código
actual, así que no se reconstruyó. La pantalla usa datos de demostración ya
existentes; no se insertaron datos persistentes. Se conserva la limitación de
no poder establecer la causa exacta del contenedor histórico con exit code 137.
El commit de la entrega `14325d73f9b5ca9a11c041d5c13858700b46e4b3` se publicó
mediante push normal a `origin/main`; SHA local y remoto coincidieron en la
verificación posterior. Entregas 2–6 siguen pendientes.

27-10-2026: Entrega 2 continúa abierta. Inventario de Facebook Pages,
Instagram Business/Creator y LinkedIn personal/organización registrado en
[MATRIZ_CONEXIONES.md](./MATRIZ_CONEXIONES.md). En pruebas aisladas con SQLite
en memoria pasaron `ConnectMetaTest` (13 tests, 93 assertions), el grupo de
conexión Meta/LinkedIn (31 tests, 174 assertions) y las pruebas focalizadas de
publicación y métricas de los tres proveedores (70 tests, 196 assertions);
Pint y Larastan nivel 7 también pasaron. Estos resultados verifican flujos con
respuestas simuladas, no acceso a proveedores reales. No se modificó ni
reconstruyó el servicio local. El contenedor temporal de pruebas y sus
volúmenes anónimos ya se retiraron; el punto de montaje vacío `vendor` también
se eliminó. Pendiente: credenciales, permisos y autorización OAuth reales,
lecturas de métricas con cuentas autorizadas y validación del caso de
publicación antes de cualquier envío.
