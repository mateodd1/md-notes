# Memoria del proyecto: md-notes

Documento de referencia para mantener y ampliar la aplicación. No contiene contraseñas, claves SMTP ni credenciales de Backblaze: esas variables viven únicamente en el entorno del servidor.

## Propósito

`md-notes` es el espacio privado de apuntes de Mateo, disponible en `https://md.mateo.ovh`. Guarda notas Markdown reales (`.md`) y las organiza en carpetas, con una interfaz de edición y previsualización simultánea.

## Arquitectura y despliegue

- Código y Compose: `/root/docker/md-notes`.
- Aplicación Laravel: `/root/docker/md-notes/app`.
- Servicios Compose: `md-notes-app` (PHP 8.4 + Apache/Laravel 13) y `md-notes-db` (MySQL 8.4). La app espera a que MySQL esté sano antes de arrancar.
- El proxy inverso se conecta mediante la red Docker externa `nginx-pm_default`.
- El volumen `./data` se monta como `storage/app/private/spaces`; la aplicación no guarda los Markdown en la base de datos.
- Base de datos de producción: MySQL, con volumen persistente `mysql/`. Las credenciales viven en `db.env` (modo 600) y en el `.env` no versionado de Laravel; nunca se deben incluir en documentación ni salidas de terminal.
- La antigua `app/database/database.sqlite` se conserva solo como origen/recuperación de la migración. Hay una copia previa a MySQL en `migration-backups/`.
- Cada espacio de notas está físicamente aislado en `data/{id-de-usuario}/`.

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
- Las contraseñas se validan con un mínimo de 12 caracteres y se guardan mediante hash.
- Restablecimiento de contraseña por correo, usando la configuración SMTP de `.env`.
- No hay panel de administración de usuarios ni Filament: la dependencia, proveedor, recursos y enlace de interfaz se han eliminado. La antigua columna `is_admin` puede permanecer en bases ya migradas por compatibilidad de esquema, pero no se usa.
- `NoteSpace` valida y normaliza rutas para impedir que un usuario salga de su propia carpeta.
- Las rutas de notas y las operaciones de crear, leer, editar, mover, renombrar y borrar siempre reciben el usuario autenticado, por lo que ninguna cuenta puede leer las notas de otra.
- El HTML Markdown se renderiza filtrando entrada HTML y enlaces inseguros.

## Notas, carpetas y navegación

- Las notas usan rutas directas, por ejemplo `https://md.mateo.ovh/Clase/tema-1.md`; no hay prefijo `/nota`.
- Las rutas de la aplicación están en inglés y no se mantienen las antiguas: `/login`, `/singup`, `/forgot-password`, `/reset-password`, `/settings`, `/shared`, `/history`, `/versions`, `/download`, `/media`, `/folders`, `/notes`, `/organize` e `/item`.
- Panel lateral con árbol de carpetas y ficheros `.md`.
- En cada nivel del árbol, los archivos `.md` aparecen antes que las carpetas; ambos grupos se ordenan alfabéticamente.
- En móvil, el árbol se abre desde el botón “☰ Notas” como un cajón lateral; se puede cerrar tocando fuera, con Escape o al abrir una nota.
- Creación de carpetas, subcarpetas y notas tanto desde los botones como con clic derecho sobre una carpeta o sobre un hueco vacío del árbol.
- El campo “Dentro de” de los modales de creación es un selector propio con árbol desplegable, no un `<select>` nativo. Sus parciales son `notes._parent-picker` y `notes._parent-options`.
- Menú contextual para renombrar, borrar y compartir una nota. Las confirmaciones de borrado usan modales de la propia interfaz.
- Arrastrar y soltar permite reorganizar notas y carpetas, incluida la raíz. Al arrastrar una nota sobre la mitad superior o inferior de otra se conserva un orden manual antes/después por carpeta y usuario, persistido en el archivo privado `.md-notes-order.json`. El árbol se actualiza sin recargar la página completa.
- Abrir otra nota usa navegación dinámica: guarda primero la nota actual y cambia editor, vista previa, título, URL y árbol sin el destello de una recarga total.
- Autoguardado periódico y guardado manual. El contenido admite hasta 5 MiB de texto, para apuntes grandes.
- Cada creación o guardado con cambios conserva una versión privada de la nota. Se retienen como máximo 50 por nota y las que superan 7 días se eliminan automáticamente (la limpieza global se ejecuta cada hora).
- El historial se abre con clic derecho sobre una nota desde el árbol en una ventana flotante, sin abandonar la nota. Cada versión permite verla renderizada en la misma ventana, restaurarla (conservando antes el estado actual) y descargarla como `.md`.
- El botón “Descargar” de la barra de una nota descarga su contenido Markdown actual.
- Al mover o renombrar una nota o carpeta se actualizan las rutas de los enlaces compartidos afectados. Al borrar se revocan sus enlaces.
- Los movimientos, renombres y borrados actualizan o eliminan también el historial correspondiente.

## Apariencia e interfaz

- Las notas se abren en modo lectura, con el Markdown renderizado a ancho completo. “Editar” abre el editor y la vista previa; “Lectura” guarda y vuelve al documento formateado.
- Diseño responsive con editor y previsualización Markdown.
- El editor ofrece controles Markdown para títulos H1/H2/H3, negrita, cursiva, citas, listas, enlaces y código. Se aplican sobre el texto seleccionado.
- Al pegar una imagen desde el portapapeles dentro del editor (`Ctrl+V` o pegar), la imagen se sube al espacio privado del usuario y se inserta su referencia Markdown. Se aceptan JPEG, PNG, GIF y WebP hasta 5 MiB.
- Los adjuntos se guardan ocultos en `.md-notes-media` dentro del espacio de cada usuario y se sirven mediante una ruta autenticada `/media/{filename}`; no aparecen como carpetas en el árbol.
- Las imágenes renderizadas se limitan al ancho disponible y muestran un botón de descarga al pasar el cursor (siempre visible en pantallas táctiles). Los enlaces Markdown se muestran con color, peso y subrayado diferenciados.
- Al guardar una nota se eliminan los adjuntos que ya no estén referenciados por ninguna nota ni versión retenida del mismo usuario; al borrar notas o carpetas también se elimina cualquier adjunto que quede huérfano. Los `.md` se eliminan físicamente mediante `unlink` y las carpetas de forma recursiva y contenida en el espacio del usuario. Una imagen necesaria para restaurar una versión se conserva solo durante la retención de ese historial; la limpieza global horaria libera las que dejan de estar referenciadas al caducar dicha versión.
- En el perfil se puede elegir “Según el sistema”, modo claro o modo oscuro.
- “Según el sistema” es la opción predeterminada: sigue `prefers-color-scheme` y responde a cambios del sistema mientras la página está abierta.
- La preferencia se guarda localmente en el navegador bajo `md-notes-theme`.
- Las notificaciones de la zona de notas aparecen como una ventana flotante inferior y se ocultan tras tres segundos.
- Las páginas públicas compartidas incorporan también el selector de apariencia (sistema, claro u oscuro).
- El idioma se elige automáticamente por el idioma preferido que envía el navegador/SO: `es` muestra español y cualquier otra preferencia muestra inglés. `SetLocale` se aplica al grupo web y también configura Carbon; las cadenas de interfaz viven en `lang/es/ui.php` y `lang/en/ui.php`.

## Enlaces compartidos

- Desde el menú contextual de una nota, “Compartir enlace” aparece antes del separador y no obliga a abrir la nota que se comparte.
- Crear un enlace vuelve a la página que ya estaba abierta y presenta el enlace generado en un modal, con botón para copiar.
- Las duraciones disponibles son 1 hora, 24 horas, 7 días o sin caducidad; los selectores usan controles visuales propios, no el desplegable nativo del navegador.
- Las URLs públicas tienen la forma `https://md.mateo.ovh/share/ABCDE`.
- Cada token tiene cinco caracteres y usa `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`, evitando `0/O` e `1/I`.
- Las rutas públicas son de solo lectura, están limitadas por tasa y verifican la caducidad antes de mostrar el Markdown.
- Las imágenes de una nota compartida se reescriben a `/share/{token}/media/{filename}`. Esa ruta comprueba que el enlace siga activo y que el adjunto esté referenciado por la nota compartida, por lo que no requiere sesión ni expone otros adjuntos privados del propietario.
- El perfil incluye “Compartidos”, donde cada usuario ve exclusivamente sus enlaces, puede copiarlo, cambiar su duración y revocarlo mediante una confirmación visual.
- La tabla `shared_notes` contiene usuario propietario, ruta, token, caducidad y marcas de tiempo. La migración correspondiente es `2026_09_16_000002_create_shared_notes_table.php`.

## Perfil y correo

- El menú de perfil incluye “Configuración”, desde donde cada usuario puede cambiar su nombre, contraseña o borrar permanentemente su cuenta. El correo no se puede cambiar desde la plataforma.
- Para cambiar contraseña se solicita primero un código de seis cifras por correo. El código solo contiene un hash en base de datos, caduca a los 15 minutos y no requiere la contraseña anterior.
- `profile_verification_codes` es la tabla de estos códigos y la migración es `2026_09_16_000004_create_profile_verification_codes_table.php`. Las rutas son `POST /settings/password/code` y `PATCH /settings/password`.
- El borrado exige la contraseña actual y elimina el espacio Markdown, el usuario, sus versiones y sus enlaces compartidos.
- El perfil incluye “Acceso API” (excepto en demo) para crear y revocar tokens personales. El secreto solo se muestra al crearlo y en la base de datos solo se guarda su hash.
- La API usa `Authorization: Bearer mdn_...` y `PUT /api/notes/{ruta}.md` (máximo 5 MiB). El cuerpo se envía como Markdown crudo, crea las carpetas que falten, crea o actualiza el archivo y registra su historial. Ejemplo: `curl --fail-with-body -X PUT -H "Authorization: Bearer TU_TOKEN" --data-binary @apuntes.md https://md.mateo.ovh/api/notes/Clase/apuntes.md`.
- Después de registrarse se envía un correo de bienvenida mediante el SMTP configurado y, si la cuenta nace sin notas importadas, se crea `Bienvenida.md` o `Welcome.md` con un resumen de la plataforma. Si el envío falla, la cuenta y su nota se crean igualmente y el error queda registrado.

## Cuenta de demostración

- Credenciales públicas de prueba: `demo@demo` / `demo`.
- El comando `md-notes:reset-demo` recrea su estado inicial, elimina sus enlaces y versiones y vuelve a crear dos notas de ejemplo.
- El temporizador de sistema `md-notes-demo-reset.timer` está habilitado. Ejecuta `md-notes-demo-reset.service` cada hora y tras el arranque, que invoca dicho comando dentro del contenedor.
- La cuenta demo nunca es administradora. Sus cambios están pensados para ser efímeros.
- La opción de configuración aparece atenuada y queda bloqueada también en el servidor para la cuenta demo; se mantienen disponibles las funciones de prueba como crear notas, historial, compartir y cerrar sesión.

## Copias de seguridad

- El temporizador de sistema `silverbullet-backup.timer` está habilitado y se ejecuta cada minuto.
- Su servicio asociado es `silverbullet-backup.service`, que invoca `/usr/local/sbin/backup-silverbullet` para las copias cifradas hacia Backblaze B2.
- El script conserva las notas por usuario y genera antes un volcado lógico consistente de MySQL (`md-notes/database.sql`) con `mysqldump`; ya no copia SQLite como base de datos activa. El volcado temporal se limpia al acabar. Usa `--skip-dump-date` y `rclone --checksum`, por lo que la comprobación por minuto no sube otra copia de MySQL cuando su contenido no ha cambiado.
- Si Backblaze devuelve un límite de transacciones o una cuota agotada, las copias remotas no podrán completar hasta resolverlo en Backblaze. La aplicación y sus datos locales siguen operativos.
- Antes de tocar datos productivos, hacer una copia recuperable de MySQL (volcado lógico) y de `data/`. Mantener también la SQLite heredada mientras sea útil para recuperación.

## Verificación y desarrollo

- Pruebas: `docker compose run --rm app php artisan test --compact`.
- `phpunit.xml` usa SQLite y cachés en `/tmp` para que las pruebas no escriban en la base de datos de producción.
- Revisar siempre que el contenedor de pruebas no modifique `app/database/database.sqlite` ni `data/`.
- Tras editar rutas, vistas o configuración, reconstruir las cachés de Laravel con los comandos indicados arriba.
- No guardar secretos en este documento, en el repositorio ni en salidas de terminal.
- `note_versions` es la tabla de historial, creada por la migración `2026_09_16_000003_create_note_versions_table.php`.

## Puntos de extensión recomendados

- Si se necesitan adjuntos, guardarlos por usuario bajo el mismo espacio privado y protegerlos con rutas autenticadas; no exponer directamente el volumen `data`.
- Para colaboración en tiempo real entre navegadores o usuarios haría falta introducir eventos (por ejemplo, broadcasting/WebSockets); la actualización dinámica actual evita recargas completas en las acciones realizadas desde la propia pestaña.
- Si el número de notas o el tamaño de los adjuntos crece mucho, revisar cuota de disco, límites de PHP/Apache y la política de B2.
