# Memoria del proyecto: md-notes

Documento de referencia para mantener y ampliar la aplicación. No contiene contraseñas, claves SMTP ni credenciales de Backblaze: esas variables viven únicamente en el entorno del servidor.

## Propósito

`md-notes` es el espacio privado de apuntes de Mateo, disponible en `https://mdnotes.net`. Guarda notas Markdown reales (`.md`) y las organiza en carpetas, con una interfaz de edición y previsualización simultánea. `md.mateo.ovh` se conserva como dominio legado y redirige permanentemente al mismo path y parámetros en el dominio nuevo.

La raíz pública (/) es una landing bilingüe con presentación y llamadas a crear cuenta o iniciar sesión; el espacio de trabajo autenticado vive bajo /app.

## Arquitectura y despliegue

- Código y Compose: `/root/docker/md-notes`.
- Aplicación Laravel: `/root/docker/md-notes/app`.
- Servicios Compose: `md-notes-app` (PHP 8.4 + Apache/Laravel 13) y `md-notes-db` (MySQL 8.4). La app espera a que MySQL esté sano antes de arrancar.
- El despliegue es autónomo: no monta espacios externos ni necesita otro servicio de notas. Las cuentas nuevas reciben su nota de bienvenida y no importan datos de instalaciones anteriores.
- El proxy inverso se conecta mediante la red Docker externa `nginx-pm_default`.
- `mdnotes.net` es el dominio canónico (`APP_URL`). `RedirectLegacyDomain` devuelve un `308` desde cada host de `MD_NOTES_LEGACY_HOSTS` —actualmente `md.mateo.ovh`— hacia la misma ruta y consulta del dominio canónico. Los dos dominios tienen hosts HTTPS independientes en Nginx Proxy Manager, ambos hacia `md-notes-app:80`.
- El volumen `./data` se monta como `storage/app/private/spaces`; los Markdown de las cuentas no se guardan en la base de datos. Las notas públicas temporales subidas sin cuenta sí se guardan en `shared_notes.content`.
- Base de datos de producción: MySQL, con volumen persistente `mysql/`. Las credenciales viven en `db.env` (modo 600) y en el `.env` no versionado de Laravel; nunca se deben incluir en documentación ni salidas de terminal.
- La antigua `app/database/database.sqlite` se conserva solo como origen/recuperación de la migración. Hay una copia previa a MySQL en `migration-backups/`.
- Cada espacio de notas está físicamente aislado en `data/{id-de-usuario}/`.
- Cada usuario tiene `storage_quota_bytes`, con valor por defecto de 100 MiB. La migración es `2026_09_17_000006_add_storage_quota_to_users_table.php`.

Para aplicar cambios de PHP, Blade o rutas:

```bash
cd /root/docker/md-notes
docker compose exec -T app php artisan route:clear
docker compose exec -T app php artisan view:clear
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
```

Las migraciones se ejecutan con `docker compose exec -T app php artisan migrate --force`. Para una migración manual desde SQLite ya existe `md-notes:migrate-sqlite /ruta/a/database.sqlite`; realiza inserciones idempotentes en MySQL para `users`, restablecimientos, enlaces y versiones.

## Cuentas y seguridad

- Registro, inicio y cierre de sesión propios de Laravel.
- El registro exige nombre de al menos 3 caracteres, correo con formato RFC válido y contraseña de al menos 8 caracteres; las contraseñas se guardan mediante hash. El restablecimiento y el cambio desde Perfil comparten el mínimo de 8 caracteres.
- Restablecimiento de contraseña por correo, usando la configuración SMTP de `.env`.
- No hay panel de administración de usuarios ni Filament: la dependencia, proveedor, recursos y enlace de interfaz se han eliminado. La antigua columna `is_admin` puede permanecer en bases ya migradas por compatibilidad de esquema, pero no se usa.
- `NoteSpace` valida y normaliza rutas para impedir que un usuario salga de su propia carpeta.
- Las rutas de notas y las operaciones de crear, leer, editar, mover, renombrar y borrar siempre reciben el usuario autenticado, por lo que ninguna cuenta puede leer las notas de otra.
- El HTML Markdown se renderiza filtrando entrada HTML y enlaces inseguros.

## Notas, carpetas y navegación

- Las notas usan rutas directas dentro del espacio de trabajo, por ejemplo `https://mdnotes.net/app/Clase/tema-1.md`; no hay prefijo `/nota`.
- Las rutas públicas de autenticación están en inglés: `/login`, `/singup`, `/forgot-password` y `/reset-password`. Las funciones autenticadas del espacio de trabajo (notas, carpetas, ajustes, adjuntos, historial, descargas y enlaces gestionados) se agrupan bajo `/app`.
- Panel lateral con árbol de carpetas y ficheros `.md`.
- En cada nivel del árbol, los archivos `.md` aparecen antes que las carpetas. Ambos grupos admiten orden manual independiente por arrastre y conservan ese orden por usuario y carpeta.
- En móvil, el árbol se abre desde el botón “☰ Notas” como un cajón lateral; se puede cerrar tocando fuera, con Escape o al abrir una nota.
- Creación de carpetas, subcarpetas y notas tanto desde los botones como con clic derecho sobre una carpeta o sobre un hueco vacío del árbol.
- El campo “Dentro de” de los modales de creación es un selector propio con árbol desplegable, no un `<select>` nativo. Sus parciales son `notes._parent-picker` y `notes._parent-options`.
- Menú contextual para renombrar, borrar y compartir una nota. Las confirmaciones de borrado usan modales de la propia interfaz.
- Arrastrar y soltar permite reorganizar notas y carpetas, incluida la raíz: el destino visible “Raíz” y cualquier espacio vacío del panel lateral permiten sacar elementos de una subcarpeta. Al arrastrar una nota o carpeta sobre la mitad superior o inferior de otra del mismo tipo se conserva un orden manual antes/después por carpeta y usuario, persistido respectivamente en los archivos privados `.md-notes-order.json` y `.md-notes-folder-order.json`. El árbol se actualiza sin recargar la página completa.
- Abrir otra nota usa navegación dinámica: guarda primero la nota actual y cambia editor, vista previa, título, URL y árbol sin el destello de una recarga total.
- Autoguardado periódico y guardado manual. El contenido admite hasta 5 MiB de texto, para apuntes grandes.
- Cada creación o guardado con cambios conserva una versión privada de la nota. Se retienen como máximo 50 por nota y las que superan 7 días se eliminan automáticamente (la limpieza global se ejecuta cada hora).
- El historial se abre con clic derecho sobre una nota desde el árbol en una ventana flotante, sin abandonar la nota. Cada versión permite verla renderizada en la misma ventana, restaurarla (conservando antes el estado actual) y descargarla como `.md`.
- El botón “Descargar” de la barra de una nota descarga su contenido Markdown actual.
- Al mover o renombrar una nota o carpeta se actualizan las rutas de los enlaces compartidos afectados. Al borrar se revocan sus enlaces.
- Los movimientos, renombres y borrados actualizan o eliminan también el historial correspondiente.

## Apariencia e interfaz

- Ajustes móviles (2026-09-20): `md-notes-responsive.css` conserva los botones de edición en dos filas, amplía los controles táctiles, permite desplazar modales/menús altos y evita desbordamientos por tablas, URLs y acciones de la papelera. `md-notes-viewport.js` adapta la altura visible al teclado sin interferir con el zoom. Se cargan desde el layout común.
- “Exportar a PDF” está en el menú contextual de cada nota, también en móvil. `GET /{path}.md/pdf` en el dominio del espacio requiere sesión y limita a seis solicitudes por minuto. El sufijo evita reservar nuevos nombres de carpeta. `NotePdf` genera A4 con Dompdf y GD, incrusta solo imágenes del propietario y elimina temporales; no permite red, PHP ni JavaScript. Las imágenes externas/ausentes se sustituyen por un aviso. Al exportar la nota abierta se guardan primero los cambios; exportar otra no altera el editor. Las traducciones y documentación están en español e inglés.
- Las notas se abren en modo lectura, con el Markdown renderizado a ancho completo. “Editar” abre el editor y la vista previa; “Lectura” guarda y vuelve al documento formateado.
- Diseño responsive con editor y previsualización Markdown.
- El editor ofrece controles Markdown para títulos H1/H2/H3, negrita, cursiva, citas, listas, enlaces y código. Se aplican sobre el texto seleccionado.
- Al pegar archivos desde el portapapeles dentro del editor (incluidas imágenes con `Ctrl+V`) o soltarlos sobre este, se suben al espacio privado y se insertan como Markdown. El botón de clip permite seleccionar varios adjuntos; la subida se procesa en cola y muestra una notificación inferior con progreso. Cada adjunto admite hasta 10 MiB.
- Los adjuntos se guardan ocultos en `.md-notes-media` dentro del espacio de cada usuario y se sirven mediante la ruta autenticada actual `/app/media/{filename}`. La ruta autenticada histórica `/media/{filename}` se conserva para que las notas ya existentes no pierdan sus imágenes; no aparecen como carpetas en el árbol. Las imágenes se muestran en línea; el resto se descarga con un enlace Markdown.
- `StorageQuota` calcula el espacio físico de Markdown y adjuntos del usuario (sin contar los metadatos de orden) antes de guardar una nota o mover un archivo adjunto. Si se superan 100 MiB, el cambio se rechaza. La cuota y el uso actual se muestran en la barra lateral y en Perfil.
- El medidor de almacenamiento de la barra lateral se refresca por AJAX cada minuto mientras la pestaña está visible, y también al volver a ella, mediante `GET /app/quota`; no recarga la nota abierta.
- Las imágenes renderizadas se limitan al ancho disponible y muestran un botón de descarga al pasar el cursor (siempre visible en pantallas táctiles). Los enlaces Markdown se muestran con color, peso y subrayado diferenciados.
- Al guardar una nota se eliminan los adjuntos que ya no estén referenciados por ninguna nota ni versión retenida del mismo usuario; al borrar notas o carpetas también se elimina cualquier adjunto que quede huérfano. Los `.md` se eliminan físicamente mediante `unlink` y las carpetas de forma recursiva y contenida en el espacio del usuario. Una imagen necesaria para restaurar una versión se conserva solo durante la retención de ese historial; la limpieza global horaria libera las que dejan de estar referenciadas al caducar dicha versión.
- En el perfil se puede elegir “Según el sistema”, modo claro o modo oscuro.
- La interfaz usa una paleta de grises fríos y azul cian inspirada en `xdp.es`, con fondos claros `#f8fafc` / oscuros `#0e0f12`, tarjetas sobrias y un favicon vectorial de la estrella de md-notes (`public/favicon.svg`).
- “Según el sistema” es la opción predeterminada: sigue `prefers-color-scheme` y responde a cambios del sistema mientras la página está abierta.
- La preferencia se guarda localmente en el navegador bajo `md-notes-theme`.
- Las notificaciones de la zona de notas aparecen como una ventana flotante inferior y se ocultan tras tres segundos.
- Las páginas públicas compartidas incorporan también el selector de apariencia (sistema, claro u oscuro).
- El idioma se elige automáticamente por el idioma preferido que envía el navegador/SO: `es` muestra español y cualquier otra preferencia muestra inglés. `SetLocale` se aplica al grupo web y también configura Carbon; las cadenas de interfaz viven en `lang/es/ui.php` y `lang/en/ui.php`.

## Enlaces compartidos

- Desde el menú contextual de una nota, “Compartir enlace” aparece antes del separador y no obliga a abrir la nota que se comparte.
- Crear un enlace vuelve a la página que ya estaba abierta y presenta el enlace generado en un modal, con botón para copiar.
- Las duraciones disponibles son 1 hora, 24 horas, 7 días o sin caducidad; los selectores usan controles visuales propios, no el desplegable nativo del navegador.
- Las URLs que se entregan al usuario tienen la forma corta `https://mdnotes.net/share/aB2cD3` y redirigen al lector en `https://app.mdnotes.net/share/aB2cD3`.
- Cada token nuevo tiene seis caracteres de `23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz`. Se excluyen `0`, `1`, `I`, `O`, `i`, `l` y `o`; distingue mayúsculas/minúsculas mediante `ascii_bin` en MySQL. Se conservan las rutas antiguas de cinco y siete caracteres.
- Las rutas públicas son de solo lectura, están limitadas por tasa y verifican la caducidad antes de mostrar el Markdown.
- Las imágenes y archivos de una nota compartida se reescriben a `/share/{token}/media/{filename}` tanto si el Markdown usa la ruta histórica `/media/...` como la actual `/app/media/...`. Esa ruta comprueba que el enlace siga activo y que el adjunto esté referenciado por la nota compartida, por lo que no requiere sesión ni expone otros adjuntos privados del propietario.
- El perfil incluye “Compartidos”, donde cada usuario ve exclusivamente sus enlaces, puede copiarlo, cambiar su duración y revocarlo mediante una confirmación visual.
- La tabla `shared_notes` contiene propietario opcional, ruta, token, caducidad y marcas de tiempo. Los enlaces de cuenta leen el Markdown del espacio privado; los anónimos guardan el contenido en la propia tabla. Migraciones: `2026_09_16_000002_create_shared_notes_table.php` y `2026_09_26_000001_allow_anonymous_shared_notes.php`.

## Perfil y correo

- El menú de perfil incluye “Configuración”, desde donde cada usuario puede cambiar su nombre, contraseña o borrar permanentemente su cuenta. El correo no se puede cambiar desde la plataforma.
- Para cambiar contraseña se solicita primero un código de seis cifras por correo. El código solo contiene un hash en base de datos, caduca a los 15 minutos y no requiere la contraseña anterior.
- `profile_verification_codes` es la tabla de estos códigos y la migración es `2026_09_16_000004_create_profile_verification_codes_table.php`. Las rutas son `POST /app/settings/password/code` y `PATCH /app/settings/password`.
- El borrado exige la contraseña actual y elimina el espacio Markdown, el usuario, sus versiones y sus enlaces compartidos.
- El perfil incluye “Acceso API” (excepto en demo) para crear y revocar tokens personales. El secreto solo se muestra al crearlo y en la base de datos solo se guarda su hash.
- Perfil incluye “Descargar mis datos”. Genera un ZIP privado con los Markdown, adjuntos, metadatos de orden, historial, enlaces compartidos, perfil y metadatos de tokens API (nunca secretos ni hashes de contraseña). Se solicita como máximo una vez por usuario y día; solo al segundo intento se muestra el aviso. El correo contiene un enlace con token aleatorio de 64 caracteres, guardado únicamente como hash, válido 24 horas. Los ZIP se guardan fuera de la web en `storage/app/private/account-exports`, se eliminan al caducar y al borrar la cuenta. La tabla es `account_exports` y la migración `2026_09_17_000007_create_account_exports_table.php`.
- La API usa `Authorization: Bearer mdn_...` y `PUT /api/notes/{ruta}.md` (máximo 5 MiB). Acepta el cuerpo como Markdown crudo o JSON con el campo `content`, crea las carpetas que falten, crea o actualiza el archivo y registra su historial. Ejemplo: `curl --fail-with-body -X PUT -H "Authorization: Bearer TU_TOKEN" --data-binary @file.md https://mdnotes.net/api/notes/Clase/file.md`.
- Sin cabecera `Authorization`, `PUT /api/notes/{ruta}.md` o `POST /api/notes` crean una nota pública anónima con caducidad de 30 días; el POST acepta multipart `file=@archivo.md`, toma el nombre original y no necesita ruta destino. Con token válido, ese mismo POST guarda el archivo en la raíz privada del usuario; usar PUT con ruta para subcarpetas. Las subidas anónimas devuelven URL pública en texto plano por defecto (o JSON con `Accept: application/json`); token inválido devuelve 401. Límite por IP: 3/min y 20/día; capacidad total configurable con `MD_NOTES_ANONYMOUS_NOTES_MAX_BYTES` (512 MiB por defecto). Un servicio `scheduler` ejecuta `shares:prune-anonymous` cada hora para borrar el contenido caducado; la lectura también lo elimina si detecta caducidad.
- Después de registrarse se envía un correo de bienvenida mediante el SMTP configurado y se crea `Bienvenida.md` o `Welcome.md` con un resumen de la plataforma. Si el envío falla, la cuenta y su nota se crean igualmente y el error queda registrado.

## Cuenta de demostración

- Credenciales públicas de prueba: `demo@demo` / `demo`.
- El comando `md-notes:reset-demo` recrea su estado inicial, elimina sus enlaces y versiones y vuelve a crear dos notas de ejemplo.
- El temporizador de sistema `md-notes-demo-reset.timer` está habilitado. Ejecuta `md-notes-demo-reset.service` cada hora y tras el arranque, que invoca dicho comando dentro del contenedor.
- La cuenta demo nunca es administradora. Sus cambios están pensados para ser efímeros.
- La opción de configuración aparece atenuada y queda bloqueada también en el servidor para la cuenta demo; se mantienen disponibles las funciones de prueba como crear notas, historial, compartir y cerrar sesión.

## Copias de seguridad

- `md-notes-local-backup.timer` está habilitado y ejecuta `/usr/local/sbin/backup-md-notes-local` cada cinco minutos. Genera un estado local actual en `/var/backups/md-notes/current` y una instantánea solo cuando cambian contenido, permisos, altas o bajas; las marcas de tiempo efímeras del volcado no crean instantáneas en `/var/backups/md-notes/snapshots`.
- Las instantáneas locales usan enlaces físicos para reutilizar archivos sin cambios e incluyen únicamente el espacio de notas y adjuntos de md-notes, el código de Laravel (sin dependencias ni datos de ejecución), `db.env`, `app/.env`, `compose.yml`, `Dockerfile`, la configuración Docker y un volcado lógico consistente de MySQL (`md-notes/database.sql`). Se conservan 30 días y todo el árbol está restringido a `root` (directorios 700, ficheros 600). El script aborta sin modificar la copia si quedan menos de 5 GiB libres.
- El temporizador `md-notes-backblaze-backup.timer` se ejecuta cada 15 minutos, con hasta dos minutos de dispersión. Su servicio invoca `/usr/local/sbin/backup-md-notes-backblaze` y solo contacta B2 si existe una instantánea local nueva desde la última subida; sincroniza ese estado hacia el remoto cifrado `crypt-md-notes`, dejando sustituciones en `crypt-md-notes:history`.
- El volcado MySQL usa `--single-transaction`, `--routines`, `--events`, `--no-tablespaces`, `--skip-dump-date` y `--set-gtid-purged=OFF`. La subida usa `--fast-list`, un único request por segundo y no ejecuta un segundo recorrido completo con `rclone check`. Si B2 devuelve `transaction_cap_exceeded`, se pausa automáticamente hasta las 00:05 UTC.
- Si Backblaze devuelve un límite de transacciones o una cuota agotada, las copias remotas no podrán completar hasta resolverlo en Backblaze. La aplicación y sus datos locales siguen operativos.
- Antes de tocar datos productivos, hacer una copia recuperable de MySQL (volcado lógico) y de `data/`. Mantener también la SQLite heredada mientras sea útil para recuperación.

## Verificación y desarrollo

- Pruebas: `docker compose run --rm app php artisan test --compact`.
- `phpunit.xml` usa SQLite y cachés en `/tmp` para que las pruebas no escriban en la base de datos de producción.
- Revisar siempre que el contenedor de pruebas no modifique `app/database/database.sqlite` ni `data/`.
- Tras editar rutas, vistas o configuración, reconstruir las cachés de Laravel con los comandos indicados arriba.
- No guardar secretos en este documento, en el repositorio ni en salidas de terminal.
- `note_versions` es la tabla de historial, creada por la migración `2026_09_16_000003_create_note_versions_table.php`.
- El historial de versiones cuenta para la cuota de 100 MiB de cada usuario. Al guardar una instantánea se calcula el uso resultante considerando la retención (50 versiones por nota y 7 días); superar el límite responde con un error de validación, nunca con un 500.
- `ShareTokens` centraliza el alfabeto y las rutas compatibles. La creación reintenta colisiones usando la excepción de unicidad tanto en MySQL como en SQLite.
- El menú contextual de cada `.md` incluye “Propiedades”. Abre una ventana con su fecha de creación, tamaño del Markdown, tamaño y número de imágenes/adjuntos referenciados y el total. La fecha procede de la primera versión guardada y, para notas antiguas sin historial, de la fecha disponible del archivo.
- La documentación pública en español está en `app/resources/docs/documentation.md` y la inglesa en `app/resources/docs/documentation.en.md`. Se renderiza para personas en `/documentation.md` y está disponible como Markdown sin renderizar en `/documentation.raw.md`; ambas rutas son públicas y usan el idioma del navegador. `/llms.txt` explica la API de publicación a agentes y enlaza la documentación. La landing enlaza `llms.txt` con `rel="describedby"`.

## Refuerzo de seguridad (2026-09-20)

- `TrustProxies` solo acepta las IP exactas de `MD_NOTES_TRUSTED_PROXIES`. No confía en `X-Forwarded-Host` ni `X-Forwarded-Port`. NPM fuerza el esquema real para los dominios de md-notes mediante `ops/md-notes-proxy.conf`, instalado como un include específico desde su `server_proxy.conf`.
- Login: 6 peticiones/minuto/IP y 20/15 minutos/cuenta; solicitud de recuperación: 3/minuto/IP y 3/15 minutos/cuenta; envío de nueva contraseña: 3/minuto/IP y 5/15 minutos/cuenta. Las claves de correo se normalizan y se guardan como hashes en el limitador.
- Enlaces compartidos: lectura 60/minuto, adjuntos 120/minuto y copia 20/minuto, limitados por IP y también por cuenta si hay sesión. Cabeceras de las respuestas compartidas: `no-store`, `noindex, nofollow, noarchive` y `no-referrer`.
- `users.auth_version`, `PasswordSecurity` y `ValidateSessionVersion` invalidan las sesiones anteriores al cambiar o restablecer la contraseña. Un cambio desde el perfil conserva únicamente la sesión actual, regenera su ID y rota `remember_token`. El restablecimiento invalida todas las anteriores, incluidas las sesiones anteriores a esta actualización. Los tokens API mantienen su ciclo de revocación independiente.
- En producción, las sesiones se guardan cifradas en MySQL. La cookie `__Host-md-notes-session` es `Secure`, `HttpOnly`, `SameSite=Lax`, usa `Path=/` y no define `Domain`; el prefijo `__Host-` impide relajar ese alcance. La landing solo consulta un booleano de sesión mediante CORS restringido exactamente al dominio público y una respuesta `no-store`. El directorio de sesiones de archivo queda como fallback con permisos `700` y archivos `600`.
- Migración: `2026_09_20_195905_harden_sessions_and_share_tokens.php`. Añade la versión de autenticación y hace binaria la comparación de tokens en MySQL. No revertir la comparación binaria: ya pueden existir enlaces que solo difieran en mayúsculas/minúsculas.
- Regresión: `SecurityHardeningTest` reproduce la falsificación de cabeceras, límites entre IP/cuentas, sesiones reales en disco, cookies persistentes, enlaces antiguos/nuevos, adjuntos y colisiones. Suite completa validada en SQLite y MySQL 8.4.

## Puntos de extensión recomendados

- Los adjuntos deben seguir guardándose por usuario bajo el mismo espacio privado y protegidos por rutas autenticadas; no exponer directamente el volumen `data`. Cualquier nueva vía de escritura debe pasar por `StorageQuota`.
- Para colaboración en tiempo real entre navegadores o usuarios haría falta introducir eventos (por ejemplo, broadcasting/WebSockets); la actualización dinámica actual evita recargas completas en las acciones realizadas desde la propia pestaña.
- Si el número de notas o el tamaño de los adjuntos crece mucho, revisar cuota de disco, límites de PHP/Apache y la política de B2.
