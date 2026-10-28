# Matriz de conexiones — Entrega 2

**Estado:** inventario y pruebas de código completados; validación con cuentas
reales pendiente. Los tests citados usan respuestas HTTP simuladas y no prueban
permisos, acceso ni límites de una cuenta real.

## Capacidades y evidencia

| Plataforma / tipo de cuenta | Capacidad implementada | Prueba ejecutada / resultado | Evidencia en código | Validación real / bloqueo pendiente |
|---|---|---|---|---|
| Facebook Pages | Facebook Login compartido con Instagram; enumera Pages y usa el token de Page. Publica feed/texto, foto, carrusel, vídeo, Reels y Stories. Métricas de publicaciones (reacciones, comentarios, compartidos, impresiones) y Page (seguidores/fan count). No conecta perfiles personales. | `ConnectMetaTest` y pruebas de conexión Meta: aprobadas. Pruebas focalizadas de publicación y métricas de Facebook: aprobadas en el grupo de 70 tests. Solo respuestas simuladas. | [MetaConnectionController](app/Http/Controllers/ConnectedAccounts/MetaConnectionController.php), [MetaAssetEnumerator](app/Services/ConnectedAccounts/Meta/MetaAssetEnumerator.php), [FacebookConnector](app/Services/Publishing/Connectors/FacebookConnector.php), [FacebookMetricsConnector](app/Services/Metrics/Connectors/FacebookMetricsConnector.php), [scopes de Platform](app/Enums/Platform.php). | Pendiente configurar `FACEBOOK_CLIENT_ID` y `FACEBOOK_CLIENT_SECRET`, registrar el callback exacto y conseguir acceso aprobado para los permisos requeridos. Un usuario autorizado debe administrar la Page. No se leyó ninguna Page real. |
| Instagram Business | El flujo Meta descubre cuentas profesionales vinculadas a Pages; publica imagen, carrusel, Reels y Stories únicamente si la capacidad Business está confirmada. La clasificación conservada usa `capabilities.instagram_account_type`; el flujo actual no solicita un campo `account_type` que no está documentado para el endpoint Facebook Login usado, por lo que cuentas sin evidencia permanecen `unknown` y Stories se rechaza. Métricas disponibles de publicaciones (likes, comentarios, guardados, compartidos y reach/views) y cuenta (seguidores y cantidad de medios). | Pruebas aisladas para Business, Creator y tipo desconocido; el endpoint de edición, el job y el conector rechazan Stories no confirmadas. La normalización de respuesta y la UI se prueban con datos simulados; no prueban una cuenta real ni desbloquean cuentas desconocidas. | [InstagramAccountType](app/Enums/InstagramAccountType.php), [MetaConnectionController](app/Http/Controllers/ConnectedAccounts/MetaConnectionController.php), [MetaAssetEnumerator](app/Services/ConnectedAccounts/Meta/MetaAssetEnumerator.php), [UpdatePostRequest](app/Http/Requests/Post/UpdatePostRequest.php), [PublishPostTarget](app/Jobs/PublishPostTarget.php), [InstagramConnector](app/Services/Publishing/Connectors/InstagramConnector.php), [ComposerToolbar](resources/js/components/compose/composer-toolbar.tsx). | Mismos requisitos de credenciales, callback, permisos de Meta y acceso a la Page asociada. La referencia vigente del endpoint usado no documenta `account_type`; no hay clasificación real confirmada ni lectura autorizada. Stories permanece bloqueado para cuentas desconocidas hasta que exista una fuente documentada y fiable. |
| Instagram Creator | El flujo admite cuentas profesionales ligadas a una Page y comparte los formatos de publicación Feed, carrusel e imagen, y Reels; Stories se rechaza porque la capacidad Business no está confirmada. El tipo Creator se conserva si lo entrega la respuesta y no se infiere por el nombre. Las cuentas personales de consumidor no forman parte del flujo implementado. | Pruebas simuladas para Creator y rechazo de Stories en UI, endpoint, job y conector. No se validó publicación real. | [InstagramAccountType](app/Enums/InstagramAccountType.php), [MetaAssetEnumerator](app/Services/ConnectedAccounts/Meta/MetaAssetEnumerator.php), [UpdatePostRequest](app/Http/Requests/Post/UpdatePostRequest.php), [InstagramConnector](app/Services/Publishing/Connectors/InstagramConnector.php), [ComposerToolbar](resources/js/components/compose/composer-toolbar.tsx). | Pendientes credenciales, permisos y lectura real. El flujo Facebook Login actual no solicita `account_type` porque el campo no está documentado para esa ruta; una cuenta sin dato sigue `unknown`. |
| LinkedIn — perfil personal | OAuth de miembro con identidad OIDC y `w_member_social`; publica texto, imagen, multi-image y vídeo, y admite vista previa de artículo/enlace. Repost es un flujo separado. **No hay métricas personales de publicaciones ni métricas de cuenta:** el conector devuelve `unsupported` para ambas. | Pruebas de OAuth, publicación/vídeo y respuesta `unsupported` de métricas aprobadas con respuestas simuladas. No se conectó un perfil real. | [OAuthConnectionController](app/Http/Controllers/ConnectedAccounts/OAuthConnectionController.php), [scopes de Platform](app/Enums/Platform.php), [LinkedInConnector](app/Services/Publishing/Connectors/LinkedInConnector.php), [LinkedInMetricsConnector](app/Services/Metrics/Connectors/LinkedInMetricsConnector.php). | Pendientes `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET`, callback HTTPS exacto y aprobación/acceso del producto necesario para publicar. Ninguna lectura de perfil ni métrica fue probada con LinkedIn real. |
| LinkedIn — página de organización | Si se activa Community Management, el selector consulta las organizaciones donde el miembro tiene rol `ADMINISTRATOR` y permite conectarlas por separado del perfil. Publica en nombre de la organización con permisos de Community Management; consulta métricas de página/organización y seguidores. | Descubrimiento, selección y publicación/métricas de organizaciones aprobados en tests simulados. No se validó una Page real ni el acceso de la aplicación al producto restringido. | [OAuthConnectionController](app/Http/Controllers/ConnectedAccounts/OAuthConnectionController.php), [LinkedInOrganizationDiscovery](app/Services/ConnectedAccounts/LinkedIn/LinkedInOrganizationDiscovery.php), [ConnectLinkedInPagesTest](tests/Feature/ConnectedAccounts/ConnectLinkedInPagesTest.php), [LinkedInConnector](app/Services/Publishing/Connectors/LinkedInConnector.php), [LinkedInMetricsConnector](app/Services/Metrics/Connectors/LinkedInMetricsConnector.php). | Además de las credenciales y callback HTTPS, el operador debe contar con acceso aprobado a Community Management y rol `ADMINISTRATOR` en la organización. La opción `INSTANCE_LINKEDIN_COMMUNITY_MANAGEMENT_ENABLED` tiene valor predeterminado `false` y debe activarse deliberadamente una vez aprobados los scopes restringidos. Validación real pendiente. |

## Rutas, OAuth y configuración

- Facebook e Instagram comparten el inicio de sesión Meta y el callback
  `/accounts/callback/meta`. LinkedIn utiliza
  `/accounts/callback/linkedin`. Las rutas están en
  [routes/accounts.php](routes/accounts.php).
- Meta conserva Socialite `stateless()` para evitar la carrera de callbacks
  duplicados, pero ahora genera y valida un `state` aleatorio ligado a la
  sesión antes de intercambiar el código. Los tests comprueban el valor saliente,
  el rechazo de un valor incorrecto y el callback duplicado.
- Los controladores derivan `redirect_uri` de la ruta del request actual. Aunque
  `FACEBOOK_REDIRECT_URI` y `LINKEDIN_REDIRECT_URI` están disponibles en
  configuración, las URLs registradas en cada portal deben coincidir con el
  esquema, host, puerto y ruta que genera la aplicación. En despliegues detrás
  de proxy hay que configurar correctamente `TRUSTED_PROXIES` y
  `OCTANE_HTTPS`. LinkedIn requiere que el callback real registrado sea HTTPS;
  el callback local HTTP no basta para completar una conexión real.
- `Platform::isConfigured()` exige client ID y client secret. La comprobación
  local encontró Facebook/Instagram y LinkedIn sin credenciales efectivas;
  las plataformas están habilitadas en la instancia. Configura esos valores en
  `.env` o en el gestor de secretos del despliegue y registra las URLs de
  callback en el portal de cada aplicación. No se inspeccionaron ni copiaron
  valores secretos.
- En Meta, los scopes de Facebook son `pages_show_list`,
  `pages_read_engagement`, `pages_manage_posts`, `pages_read_user_content`,
  `pages_manage_engagement`, `read_insights` y `business_management`. Instagram
  solicita `instagram_basic`, `instagram_content_publish`,
  `instagram_manage_comments`, `instagram_manage_insights`,
  `instagram_manage_engagement`, `pages_show_list` y `business_management`.
  Como el flujo está compartido, el redirect combina los scopes de las
  plataformas Meta lanzadas; los permisos de mensajería son condicionales y no
  son necesarios para esta entrega. El usuario debe tener acceso suficiente a
  la Page y al activo profesional vinculado.
- En LinkedIn, los scopes de perfil son `openid`, `profile`, `email` y
  `w_member_social`. Community Management añade
  `r_member_social_feed`, `w_member_social_feed`, `r_organization_social`,
  `w_organization_social` y `rw_organization_admin`; son scopes restringidos y
  requieren acceso aprobado al producto de LinkedIn. Para publicar en nombre
  de una organización, el miembro debe mantener el rol requerido en esa Page.
- La base local contiene cinco filas OAuth marcadas activas (dos Facebook, dos
  Instagram y una LinkedIn sin tipo especificado). No se consideran cuentas
  reales: existen datos de demostración en el proyecto y no se hizo una llamada
  de lectura al proveedor para atribuirlas o verificar su vigencia.

## Programación, tokens, métricas y fallos

- **Programación y zona horaria:** la programación es interna a la aplicación,
  no una función de publicación programada del proveedor. Los horarios son
  horas locales de pared en la zona del workspace; se resuelven con tratamiento
  de DST y se convierten a instantes UTC. `posts:dispatch-due` despacha los
  vencidos dentro de la ventana permitida y marca los demasiado atrasados como
  `missed`; no los publica tardíamente. Evidencia: [NextSlotResolver](app/Services/Posts/NextSlotResolver.php),
  [DispatchDuePosts](app/Console/Commands/DispatchDuePosts.php).
- **Renovación y reconexión:** Meta intercambia el token inicial por uno de
  larga duración al conectar, pero no usa el refresco OAuth genérico para sus
  tokens de Page; si el acceso expira o se revoca, se requiere reconectar.
  LinkedIn refresca solo si recibió refresh token válido; el barrido
  `accounts:refresh-tokens` procesa tokens próximos a vencer. Si no hay refresh
  token, LinkedIn lo revoca o falla la renovación, se requiere reconectar y la
  cuenta puede quedar en `needs_attention`. La documentación oficial consultada
  indica una vida habitual de 60 días para access tokens y hasta un año para
  refresh tokens programáticos sujetos a aprobación. Evidencia: [MetaAssetEnumerator](app/Services/ConnectedAccounts/Meta/MetaAssetEnumerator.php),
  [TokenManager](app/Services/Publishing/TokenManager.php),
  [RefreshExpiringTokens](app/Console/Commands/RefreshExpiringTokens.php).
- **Errores y límites:** errores HTTP se clasifican; 401 indica autenticación
  expirada, 429 rate limit, 400/422 validación y 5xx fallo de servidor. Los
  conectores pueden refinar casos 403 a `unsupported` o duplicado. En
  publicación se respeta `Retry-After` cuando es numérico; de lo contrario se
  aplica backoff exponencial con jitter, hasta cinco intentos. El procesamiento
  de vídeo tiene sondeos acotados y conserva estado de carga para reanudar.
  Evidencia: [MapsHttpErrors](app/Services/Publishing/Connectors/Concerns/MapsHttpErrors.php),
  [BackoffSchedule](app/Services/Publishing/BackoffSchedule.php),
  [PublishPostTarget](app/Jobs/PublishPostTarget.php).
- La captura de métricas aplica límites por plataforma; los datos no disponibles
  o no soportados se representan como estados tipados, no como ceros válidos.
  Las métricas efectivas dependen de permisos y elegibilidad del objeto en el
  proveedor. LinkedIn no ofrece aquí métricas de cuenta personal. Evidencia:
  [AppServiceProvider](app/Providers/AppServiceProvider.php) y los tres
  conectores de métricas enlazados en la matriz.
- `LinkedInOrganizationDiscovery` limita la paginación de ACL y los timeouts, y
  devuelve estados distintos para resultado vacío confirmado, autenticación,
  permisos, límite, conexión y errores del proveedor. Si el descubrimiento
  falla, el selector conserva disponible el perfil personal y muestra un aviso
  seguro; solo una respuesta exitosa sin organizaciones se presenta como “sin
  páginas”. Las pruebas usan respuestas simuladas.

## Controles ejecutados y alcance de la evidencia

En un contenedor de pruebas independiente, con SQLite en memoria:

- Validación focalizada final de los flujos de conexión, publicación,
  Instagram Stories y analítica: `70 tests, 571 assertions` — aprobada.
- Pint en los archivos PHP afectados — aprobado.
- Larastan/PHPStan, nivel 7 — aprobado, sin errores.

Las pruebas usaron respuestas simuladas y SQLite aislado; no verifican
permisos, acceso ni límites de cuentas reales. La aplicación local fue
construida y desplegada desde la imagen `shoutrrr:dev` con digest
`sha256:3aadbfd3d4b59d6f3ccdcc4e5588ff06764ab7155fdc69833df231d22b1dc466`.
El contenedor está `healthy`, pero la revisión de pantallas autenticadas queda
pendiente hasta disponer de una sesión local. Durante esta inspección,
`QUEUE_WORKER_ENABLED=false` y `SCHEDULER_ENABLED=false` se aplicaron como
overrides temporales del proceso Compose; no se modificaron el archivo Compose,
`.env` ni los volúmenes.

La validación real no se intentó: faltan credenciales efectivas y autorización
OAuth de los proveedores. No se publicaron, programaron ni enviaron mensajes,
ni se activaron anuncios. Para probar una publicación será necesario acordar
previamente cuenta destino, formato, texto y archivo (si aplica), y obtener
autorización explícita para ese caso concreto.

## Documentación oficial consultada

- [Meta Login: manual OAuth flow](https://developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow/)
- [Instagram API with Facebook Login](https://developers.facebook.com/documentation/instagram-platform/instagram-api-with-facebook-login)
- [Facebook Pages API — Posts](https://developers.facebook.com/documentation/pages-api/posts)
- [LinkedIn Authorization Code Flow](https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow)
- [LinkedIn Programmatic Refresh Tokens](https://learn.microsoft.com/en-us/linkedin/shared/authentication/programmatic-refresh-tokens)
- [LinkedIn Posts API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?view=li-lms-2026-04)

La existencia de un conector nativo y la aprobación de tests aislados no prueban
que un intermediario sea necesario. No hay evidencia de una brecha real que
justifique comparar Upload-Post o Ayrshare; reevaluar solo si una cuenta
autorizada muestra una limitación concreta de permisos, formatos, métricas o
estabilidad que el conector nativo no pueda cubrir.
