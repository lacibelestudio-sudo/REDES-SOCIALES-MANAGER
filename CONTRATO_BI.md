# Contrato de métricas y reportes

Estado: contrato de métricas de la Entrega 1 y filtros/reportes de la Entrega 3
implementados. La inspección autenticada detectó un defecto en el diseño de
impresión; su corrección está versionada, pero sin validar ni desplegar. El
servicio local volvió a responder saludable con la imagen anterior; la Entrega
3 sigue abierta hasta completar controles, build y revisión visual/CSV. Las
ampliaciones de BI posteriores siguen siendo propuestas.

## Datos mínimos

Cada indicador debe informar valor nullable, estado, origen, cuenta/workspace, intervalo o fecha de captura, granularidad y cobertura. Reutilizar `MetricsStatus` (ok, unsupported, rate_limited, failed) en los conectores. La presentación puede añadir sin datos y desactualizado sin modificar por defecto el enum persistido.

- `null`: no existe un valor válido disponible.
- `0`: existe una medición válida de cero.
- Parcial: hay mediciones válidas para parte del conjunto; mostrar cobertura y no aparentar un total completo.
- Desactualizado: conservar la última lectura con fecha y advertencia de frescura definida por cadencia, sin presentarla como actual.

## Indicadores

| Indicador | Regla | Restricción |
| --- | --- | --- |
| Publicaciones | Conteo del conjunto publicado en el intervalo | Declarar inclusión de estado partial y evitar duplicar por destino |
| Seguidores | Última lectura válida por cuenta y suma declarada | No equivale a personas únicas; ausencia total produce null |
| Crecimiento | Última menos primera lectura comparable por cuenta | Necesita dos lecturas; mostrar cobertura |
| Interacciones | Likes + comentarios + reposts de destinos válidos | Etiquetar acumulado de publicaciones del periodo si esa es la fuente |
| Alcance | Métrica según proveedor y granularidad | No sumar para afirmar alcance único entre cuentas o redes |
| Engagement rate | Interacciones / denominador declarado × 100 | Denominador cero o ausente produce null; no mezclar fórmulas |
| Leads | Registros deduplicados del CRM o formularios definidos | Distinguir persona, evento y oportunidad |
| CPL | Gasto / leads atribuibles en alcance comparable | Moneda, periodo y atribución explícitos; sin leads, null |
| ROAS | Ingresos atribuidos / gasto | Diferenciar atribución de plataforma y ventas CRM; sin gasto, null |

No sumar snapshots acumulativos del mismo post. Para interacciones ocurridas durante un intervalo se requiere una fuente temporal adecuada; la diferencia de snapshots exige controlar falta de cobertura y correcciones del proveedor. No comparar monedas sin conversión explícita y fecha de tasa.

## Reglas aplicadas a la analítica (Entrega 1)

- Seguidores: se suman las últimas lecturas del intervalo para cuentas no
  Discord del workspace activo. `coverage.measured` cuenta las cuentas con
  lectura y `coverage.total` todas las cuentas no Discord incluidas por la
  consulta, también las que no tienen lecturas. El total es una suma por cuenta,
  no personas únicas. El valor es `null` si no hay lecturas válidas; cero se
  conserva como cero.
- Variación de seguidores: cada cuenta necesita al menos dos lecturas del
  intervalo. El delta agregado solo se publica si todas las cuentas del
  denominador tienen una variación comparable; en caso contrario es `null`,
  para no presentar un subtotal como variación completa.
- Publicaciones: se cuentan una vez los posts `published` y `partial` por
  `published_at` en el intervalo y workspace activo. Es un conteo completo del
  conjunto local; cero es un dato conocido y puede compararse con cero posts
  en el intervalo anterior.
- Engagement: se suman likes, comentarios y reposts solo de destinos cuyo
  `metrics_status` es `ok`. La cobertura es destinos con estado `ok` respecto
  de todos los destinos de posts `published`/`partial` del intervalo; cada fila
  del ranking publica su propia cobertura con el mismo denominador local.
  `failed`, `unsupported` y `rate_limited` no aportan sus contadores retenidos,
  ni al valor actual, ni al ranking, ni a la base del delta anterior.
- El valor de engagement describe los últimos contadores capturados con éxito
  para posts publicados en el intervalo; no demuestra que esas interacciones
  ocurrieran durante él. El delta solo se calcula cuando hay al menos un
  destino medido con éxito tanto en el periodo actual como en el anterior.

## Dashboard (Entrega 3)

La pantalla aplica filtros por intervalo, red y cuenta dentro del workspace
autorizado. Resumen, gráficos, ranking y cobertura se calculan sobre el mismo
conjunto filtrado; las fechas se interpretan en la zona horaria del workspace y
se convierten a límites temporales coherentes. Se conserva `null` frente a cero
válido, la definición de cobertura y la limitación de que seguidores sumados
por cuenta no representan personas únicas.

## Reportes para clientes

La vista imprimible y el CSV presentan el mismo conjunto filtrado que el
dashboard. Incluyen cliente/workspace, intervalo, filtros, zona horaria, fecha
de generación, definiciones y cobertura. La generación del reporte no se
presenta como actualización de métricas: la fecha de actualización solo se
muestra cuando existe evidencia. Cada endpoint revalida el workspace y la
pertenencia de las cuentas. El CSV escapa campos con comillas y saltos de línea
y neutraliza fórmulas en texto exportado. No incluye secretos, respuestas API
crudas ni PII innecesaria; no se publican enlaces abiertos.

## Criterios de aceptación

Sin métricas válidas se muestra «Sin datos»; una lectura válida cero muestra 0. Un destino fallido no aporta contadores como medición actual. Un conjunto parcial declara su cobertura. Un usuario de otro workspace no puede obtener datos ni exportarlos cambiando IDs. Un reporte reproduce filtros y reglas de la pantalla. Los datos de demostración jamás se presentan como resultados reales.

## Evidencia de implementación

Las pruebas focalizadas del candidato anterior pasaron: Pest (`70 tests, 571
assertions`), Vitest (7 pruebas de analytics), TypeScript, Oxfmt, Oxlint (0
warnings, 0 errors), Pint y Larastan/PHPStan nivel 7. Las pruebas aisladas
cubren filtros combinados, límites de fecha y zona horaria, acceso entre
workspaces, estados sin datos/cero/cobertura parcial y CSV con fórmulas
potenciales, comillas y saltos de línea. La inspección autenticada confirmó
filtros y metadatos en analítica/reporte, pero reveló que el encabezado y la
barra lateral aparecen en impresión. Solo se confirmó que el enlace CSV
conserva los filtros; no se comprobó su respuesta ni contenido. La corrección
local del layout aún no tiene controles de frontend aprobados y no se desplegó:
el build se bloqueó al exportar capas y Docker dejó temporalmente de responder.
Una comprobación posterior confirmó que el servicio está saludable en la imagen
anterior; el candidato corregido no está desplegado. Por tanto, los resultados
previos no certifican el candidato corregido ni su despliegue.
