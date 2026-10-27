# Decisiones de arquitectura

## ADR-001 — Ampliar Shoutrrr

Estado: adoptado para esta adaptación. Mantener Laravel/Inertia/React, Bun y Recharts. Las versiones precisas se resuelven desde composer.lock y bun.lock. No añadir FastAPI, otro frontend, otra cola o un segundo sistema de usuarios sin una necesidad demostrada.

## ADR-002 — Cliente y permisos

Estado: propuesta de producto para validar con el primer cliente. Un workspace delimita inicialmente un cliente. Las marcas se pueden representar con las capacidades existentes de cuentas y conjuntos, después de comprobar que cubren permisos y reportes. No crear tablas organization/brand paralelas por anticipado.

Conservar owner/admin/member. Antes de añadir analista o revisor, definir acciones permitidas y pruebas de acceso. Las consultas, exportaciones y tareas asíncronas deben acotar el workspace explícitamente o garantizar su contexto; un identificador recibido del navegador no concede acceso.

## ADR-003 — Publicación e intermediarios

Estado: decisión de proveedor pendiente. Priorizar la validación de los conectores nativos existentes para Facebook, Instagram y LinkedIn. Mantener los contratos y registros de conectores de la base; no acoplar la UI a un SDK externo.

Comparar Upload-Post y Ayrshare con el mismo guion solo cuando exista una brecha concreta: formatos, renovación de credenciales, fiabilidad, cobertura o coste operativo. La evaluación debe confirmar uso SaaS/comercial y reventa/marca blanca cuando corresponda, titularidad y exportación de datos, tarifas vigentes, sobrecostes, límites por perfil, webhooks, retención y salida del proveedor. Registrar fuentes y fecha; no convertir precios históricos en presupuesto.

Prueba: cuenta de prueba autorizada, texto/imagen/vídeo según capacidad, LinkedIn perfil y página por separado, programación y zona horaria, expiración OAuth, límites, timeout tras envío y reconciliación sin duplicados, recuperación de métricas y desconexión. Resultado: matriz con evidencias, costes por escenario y decisión documentada.

## ADR-004 — Inteligencia comercial

Estado: dirección de producto adoptada. Ampliar la analítica nativa con filtros, calidad de datos, reportes y posteriormente CRM. Power BI Embedded no es una dependencia inicial. Recharts ya está instalado; no añadir librerías gráficas sin justificación.

Separar datos orgánicos, gasto publicitario y eventos CRM. No presentar engagement como ventas ni atribución de una red como ingresos confirmados. El contrato está en [CONTRATO_BI.md](CONTRATO_BI.md).

## ADR-005 — Despliegue y datos

Estado: conservar entorno actual en la fase inicial. La configuración de desarrollo soporta SQLite; PostgreSQL y Redis son opciones a evaluar para producción, no una migración autorizada de datos ahora.

No ejecutar migrate:fresh, reset, seeders demo, regeneración de APP_KEY ni eliminación de volúmenes en la instalación del usuario. composer dev incluye migraciones y seeders: inspeccionarlo antes de usarlo. Para pruebas usar una base aislada y comprobar configuración de tests. Nunca versionar .env, tokens, exports con datos personales ni bases reales.

## ADR-006 — Licencia y continuidad

Conservar Apache-2.0 y avisos aplicables de upstream. Separar marca comercial propia de las atribuciones. Revisar licencias de nuevas dependencias y condiciones de APIs al introducirlas. Registrar las adaptaciones y mantener trazabilidad con el upstream; no prometer derechos de terceros basándose solo en la licencia del repositorio.
