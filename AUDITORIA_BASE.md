# Auditoría inicial de Shoutrrr

Inspección estática de `28bbc7f9e1ff225ffda264185e45d756a46b82b4`. No se ejecutó la suite ni se conectaron cuentas externas en esta entrega.

| Área | Evidencia local | Consecuencia para la adaptación |
| --- | --- | --- |
| Backend | `composer.json`: PHP ^8.5, Laravel ^13.7, Inertia ^3.0 | Mantener stack y dependencias bloqueadas |
| Frontend | `package.json`: React 19, TypeScript, Tailwind 4, Recharts; Bun | Ampliar componentes existentes |
| Clientes/equipos | `app/Models/Workspace.php`, `app/Enums/WorkspaceRole.php` | Reutilizar workspaces y roles owner/admin/member |
| Aislamiento | `app/Concerns/HasWorkspaceScope.php`, modelos Post y ConnectedAccount | Revisar también jobs, exportaciones y contexto ausente |
| LinkedIn | `app/Services/Publishing/Connectors/LinkedInConnector.php`, `app/Http/Controllers/ConnectedAccounts/LinkedInPageConnectionController.php` | Ya hay publicación y conexión de páginas; validar con OAuth real |
| Métricas LinkedIn | `app/Services/Metrics/Connectors/LinkedInMetricsConnector.php` | El conector diferencia páginas y perfiles; marca analítica personal unsupported |
| Analítica | `app/Http/Controllers/AnalyticsController.php`, `resources/js/pages/analytics/index.tsx` | Ya hay resumen, series y ranking; extender esta pantalla |
| Histórico | `app/Models/AccountMetric.php`, `app/Models/PostTargetMetric.php` | Revisar granularidad antes de calcular variaciones |
| Calendario | `resources/js/pages/posts/calendar/index.tsx` | Aprovechar calendario existente |
| Pruebas | `tests/Feature/Metrics/AnalyticsPageTest.php`, `resources/js/pages/analytics/index.test.ts` | Extender pruebas existentes |
| Entorno local | `docker-compose.development.yaml` | SQLite y servicios supervisados disponibles; no cambiar persistencia ahora |

## Hallazgos prioritarios de analítica

1. `buildSummary()` convierte seguidores no medidos en cero al sumar. Esto impide distinguir ausencia total de lecturas y un cero observado.
2. El ranking selecciona publicaciones que tengan al menos un destino con estado `Ok`, pero suma las interacciones de todos sus destinos. Debe definirse qué hacer con destinos fallidos, limitados o no soportados y probar un caso mixto.
3. El resumen usa publicaciones por su fecha de publicación y sus contadores disponibles. No equivale necesariamente a interacciones ocurridas dentro del periodo. La etiqueta y el reporte deben declarar esa semántica.
4. La suma de seguidores de cuentas distintas no representa personas únicas. Debe presentarse como suma de seguidores por cuenta.
5. `HasWorkspaceScope` solo aplica el filtro cuando existe contexto. Esto requiere revisar middleware y jobs antes de afirmar aislamiento completo; por sí solo no prueba una vulnerabilidad explotable.

## Brechas por confirmar

La inspección inicial no acredita un CRM, ventas, constructor de reportes, flujo de aprobaciones editoriales o generación IA completos. No presentarlos como funciones listas. Levantar inventario más profundo antes de añadir módulos duplicados.

Los conectores encontrados no prueban que la aplicación tenga productos OAuth aprobados, permisos vigentes o cobertura real de formatos. Las pruebas de cuentas deben registrar plataforma, tipo de cuenta, scopes, formatos, límites, resultado y fecha.

## Validación realizada en esta entrega

Checkout del SHA indicado; lectura de instrucciones y archivos referenciados; verificación de enlaces locales del paquete y de aplicabilidad del parche. No se ejecutaron pruebas PHP/JS porque los cambios son exclusivamente documentales. No se modificaron datos, variables de entorno ni servicios.
