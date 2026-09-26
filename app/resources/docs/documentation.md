# Documentación de md-notes

md-notes es un espacio privado para guardar apuntes, ideas y documentación en archivos Markdown (`.md`). Está pensado para escribir rápido, organizar el contenido a tu manera y conservarlo bajo tu cuenta.

## Primeros pasos

1. Crea una cuenta desde la página principal.
2. En el panel izquierdo, crea un archivo o una carpeta con los botones superiores o haciendo clic derecho en un espacio vacío.
3. Abre una nota. Por defecto se muestra formateada; pulsa **Editar** para modificar su Markdown.
4. Pulsa **Guardar** al terminar. El cambio se guarda y crea una versión en el historial.

## Organizar notas y carpetas

- Crea carpetas y subcarpetas desde el menú contextual.
- Arrastra una nota o carpeta para moverla; soltarla sobre el espacio raíz la mueve fuera de cualquier carpeta.
- Arrastra notas y carpetas antes o después de otros elementos para cambiar el orden.
- Haz clic derecho sobre una carpeta para editar su nombre, color y si debe permanecer contraída.
- Puedes **anclar** archivos y carpetas. Aparecerán con una chincheta en el panel lateral.
- Haz clic derecho sobre un archivo para renombrarlo, descargarlo, compartirlo, consultar versiones o abrir sus propiedades.

En el móvil, abre **Notas** y pulsa los tres puntos junto al archivo o carpeta para acceder al mismo menú.

## Exportar a PDF

Selecciona **Exportar a PDF** en el menú contextual de una nota. Se descargará un PDF con el nombre de la nota, su formato Markdown y las imágenes adjuntas. Si estás editando esa nota, se guardarán primero los cambios. Exportar otra nota no cambia el documento abierto.

Las imágenes alojadas en páginas externas no se incluyen en el PDF; adjúntalas a la nota si quieres que aparezcan. Los demás archivos adjuntos se mantienen como enlaces.

## Buscar notas

Puedes buscar por título o contenido con **Buscar notas** en el panel lateral o con **Ctrl+K** (**⌘K** en Mac). Escribe al menos dos caracteres y selecciona un resultado para abrirlo. La búsqueda no incluye la papelera ni versiones anteriores.

## Escribir en Markdown

La vista de lectura interpreta el formato Markdown. El editor incluye accesos para títulos, negrita, cursiva, listas, citas, enlaces y código.

Ejemplos:

```md
# Título principal

**negrita**, *cursiva* y `código`.

[Un enlace](https://example.com)
```

Las imágenes grandes se ajustan al ancho disponible. Al colocar el cursor sobre una imagen aparece un botón para descargarla.

## Imágenes y archivos adjuntos

- Pega una imagen desde el portapapeles con `Ctrl` + `V` / `⌘` + `V` mientras editas una nota.
- También puedes arrastrar y soltar archivos sobre el editor o usar el botón de clip.
- Cada adjunto puede pesar hasta 10 MB.
- Los adjuntos referenciados por una nota cuentan dentro de la cuota de almacenamiento de tu cuenta.
- En **Propiedades** puedes ver el tamaño del Markdown, sus adjuntos y el total de la nota.

## Historial de versiones

El historial se abre desde el menú contextual de cada archivo `.md`.

- Se conserva un máximo de 50 versiones por nota durante 7 días.
- Puedes ver, descargar o restaurar cualquier versión disponible.
- Se crea una versión al pulsar **Guardar** o al cambiar a otra nota tras haber editado la actual.
- El tamaño de las versiones también consume parte de tu cuota.

## Compartir notas

Desde el menú contextual de una nota selecciona **Compartir enlace**. Puedes crear enlaces con duración de 1 hora, 24 horas, 7 días o sin caducidad.

Los enlaces nuevos tienen un identificador de seis caracteres, con números y letras mayúsculas y minúsculas, evitando caracteres confusos. Respeta las mayúsculas al copiarlo. Los enlaces anteriores siguen funcionando. Cualquier persona que tenga el enlace puede abrir la nota: utiliza una caducidad si no necesitas mantener el acceso.

Los enlaces se pueden gestionar desde **Compartidos** en el menú de perfil: allí es posible cambiar su duración o revocarlos. Una nota compartida se abre sin iniciar sesión y muestra los adjuntos que contiene.

## Papelera y almacenamiento

Al borrar un archivo o una carpeta se mueve a la papelera. Desde allí puedes restaurarlo o eliminarlo definitivamente. Los elementos de la papelera conservan su historial y continúan contando para la cuota.

Cada cuenta incluye 100 MB para notas, imágenes, adjuntos y versiones. El indicador inferior del panel lateral se actualiza periódicamente.

## Cuenta y privacidad

En **Configuración del perfil** puedes cambiar tu nombre, solicitar un código para cambiar la contraseña, descargar una exportación de tus datos y borrar la cuenta. La exportación se envía por correo mediante un enlace privado y puede solicitarse una vez al día.

Cambiar la contraseña desde el perfil cierra las sesiones de los demás dispositivos. Restablecerla desde «Contraseña olvidada» cierra todas las sesiones anteriores.

Cada cuenta tiene su propio espacio de archivos. Las notas y adjuntos de una cuenta no son accesibles por las demás. Los enlaces compartidos son la única forma de dar acceso público a una nota concreta.

## API desde terminal

Puedes publicar un Markdown sin cuenta y obtener un enlace, o usar un token para guardarlo en un espacio privado.

### Subir sin cuenta

Si tienes el archivo en el equipo, puedes subirlo a la ruta corta `POST https://mdnotes.net/api/notes`. El formulario transmite el nombre original del archivo y la respuesta contiene directamente la URL pública:

```bash
curl --fail-with-body -F 'file=@file.md' https://mdnotes.net/api/notes
```

Si envías el contenido Markdown en crudo con `--data-binary`, tendrás que indicar también el nombre en la ruta `PUT https://mdnotes.net/api/notes/{nombre}.md`, porque esa forma de envío no transmite el nombre del archivo local.

El enlace caduca a los 30 días y cualquiera que lo tenga puede leer la nota. No incluyas información privada. Estas notas no admiten adjuntos.

### Subir a una cuenta

Para guardar el archivo en tu espacio privado, crea un token personal en el perfil. El token solo se muestra una vez; guárdalo en un gestor de contraseñas.

Para subir un archivo a la raíz de tu espacio y conservar su nombre, usa la misma ruta corta con el token:

```bash
curl --fail-with-body -H "Authorization: Bearer TU_TOKEN" \
  -F 'file=@file.md' \
  https://mdnotes.net/api/notes
```

Si quieres guardarlo dentro de una carpeta, indica la ruta con `PUT`, como en este ejemplo:

```bash
curl --fail-with-body -X PUT \
  -H "Authorization: Bearer TU_TOKEN" \
  --data-binary @file.md \
  https://mdnotes.net/api/notes/Clase/file.md
```

La API acepta archivos `.md` y crea las carpetas necesarias. Puedes enviar el Markdown en crudo o como JSON con un campo `content`. El contenido también entra en el historial de versiones.

## Tema y dispositivos

Por defecto md-notes sigue el modo claro u oscuro del sistema. Puedes fijar el tema desde el menú de perfil. En móvil, usa el botón **Notas** para abrir y cerrar el panel lateral.

## Copias de seguridad

Se realizan copias de seguridad periódicas para proteger la disponibilidad de tus datos.
