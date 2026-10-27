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

## Entrega 3 — Dashboard y reportes

Añadir filtros y estados de calidad; reutilizar gráficos existentes. Implementar vista imprimible y CSV autorizado del mismo conjunto de datos. Aceptación: coherencia de filtros/zonas horarias y cobertura, aislamiento, neutralización de fórmulas CSV y pruebas de interfaz. Acordar marca comercial antes de una sustitución global de identidad visual.

## Entrega 4 — Creación asistida y aprobación

Inventariar capacidades actuales antes de añadirlas. Proponer proveedor IA, costes por workspace, almacenamiento de borradores y control de cuotas. Diseñar estados editoriales sin confluir con estados de envío. Aceptación: el modelo no publica directamente; cambios tras aprobación requieren una política explícita; permisos y auditoría por workspace.

## Entrega 5 — Campañas, UTM y CRM

Elegir CRM con el propietario y definir eventos/identidades. Registrar fuente, campaña, leads, oportunidades e ingresos verificables. Ingesta idempotente, deduplicación y minimización de PII. Aceptación: evento repetido no duplica ingresos; no se mezclan monedas; atribución y ventas reales se distinguen.

## Entrega 6 — Anuncios y SaaS productivo

Evaluar Meta Ads inicialmente, permisos, límites de gasto y aprobación humana. LinkedIn Ads/Google Ads son alcance posterior propuesto. Revisar facturación existente, cuotas, backups restaurables, observabilidad y despliegue antes de clientes reales. No activar gasto publicitario como efecto de una prueba técnica.

## Registro de avance

Actualizar aquí fecha, commit, alcance, pruebas ejecutadas y limitaciones por entrega. No marcar completado un conector por existir en código ni un dashboard por tener datos simulados.

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
