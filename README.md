# ✦ md-notes

Un espacio de apuntes privado, rápido y centrado en archivos Markdown reales.

**md-notes** combina una interfaz de escritura cómoda con la libertad de conservar las notas como archivos `.md`, organizados en carpetas y aislados por cuenta. Está pensado para clase, proyectos personales y cualquier colección de notas que quieras conservar bajo tu control.

<p align="center">
  <a href="https://mdnotes.net">Abrir md-notes</a> · <a href="https://github.com/mateodd1/md-notes">Código fuente</a>
</p>

> La raíz presenta el servicio y el espacio de trabajo privado está en `/app`.

## Lo que ofrece

| | Función | Detalle |
| --- | --- | --- |
| ✍️ | Escritura Markdown | Modo lectura por defecto, editor bajo demanda, vista previa y barra para títulos, listas, negrita, cursiva, citas, enlaces y código. |
| 🗂️ | Organización | Carpetas, subcarpetas, menú contextual y arrastrar y soltar. Notas y carpetas se pueden ordenar manualmente en cada nivel y mover a la raíz. |
| 📎 | Adjuntos | Arrastra varios archivos, selecciónalos o pégalos desde el portapapeles. La subida muestra progreso, admite hasta 10 MB por archivo y mantiene todo privado. |
| 🕘 | Historial | Hasta 50 versiones por nota y 7 días de retención. Consulta, restaura o descarga cualquier versión. |
| 🔗 | Enlaces compartidos | Comparte una nota en modo lectura durante 1 h, 24 h, 7 días o indefinidamente. Los adjuntos del enlace permanecen protegidos por ese mismo enlace. |
| 🔐 | Privacidad | Las notas son privadas por defecto; solo se accede a ellas mediante enlaces compartidos que puedes limitar o revocar. |
| 💾 | Cuota | Cada cuenta dispone de 100 MB para notas y adjuntos, con indicador de uso en el panel y el perfil. |
| 📦 | Exportación | Desde Perfil puedes pedir un ZIP privado con tus notas, adjuntos, historial y datos de cuenta mediante un enlace enviado por correo. |
| 🌗 | Apariencia e idioma | Tema claro, oscuro o según el sistema. Español para navegadores en español e inglés para el resto. |
| ⚡ | Experiencia fluida | Navegación entre notas, guardado y actualización del árbol sin recargar toda la página. |

## Cómo se organiza

Las notas no se esconden en una base de datos: cada cuenta tiene un directorio privado con sus carpetas y `.md`. La base de datos guarda únicamente la información de la aplicación —usuarios, sesiones, enlaces compartidos, historial y tokens de API—.

```text
espacio privado de cada usuario/
├── Matemáticas/
│   ├── Límites.md
│   └── Derivadas.md
├── Proyecto final.md
└── .md-notes-media/       # adjuntos privados, ocultos del árbol
```

Los archivos y carpetas se pueden crear, renombrar, mover o eliminar con clic derecho. El orden manual de ambos se conserva por usuario y carpeta.

## Compartir sin abrir tus notas

Desde el menú contextual de cualquier `.md` se puede crear una URL corta como:

```text
https://mdnotes.net/share/ABCDE
```

El receptor solo ve una versión renderizada de esa nota. Puedes cambiar la duración o revocar el enlace desde **Perfil → Compartidos**. Las imágenes y archivos adjuntos de una nota compartida se sirven únicamente mientras su enlace siga activo.

## API para terminal y automatizaciones

En **Perfil → Acceso API** puedes crear un token personal, que se muestra una única vez y se puede revocar cuando quieras. Con él puedes crear o actualizar notas desde un terminal:

```bash
curl --fail-with-body -X PUT \
  -H "Authorization: Bearer TU_TOKEN" \
  --data-binary @apuntes.md \
  https://mdnotes.net/api/notes/Clase/apuntes.md
```

- Endpoint: `PUT /api/notes/{ruta}.md`
- Autenticación: `Authorization: Bearer mdn_...`
- Tamaño máximo: 5 MiB por archivo
- Las carpetas que no existan se crean automáticamente.
- Cada subida también crea una versión en el historial.

## Privacidad y seguridad

- Contraseñas con hash y un mínimo de 8 caracteres; el registro también exige nombre de 3 caracteres y correo válido.
- Recuperación de contraseña y confirmación por código para cambiarla.
- Espacios de archivos, adjuntos, versiones y enlaces compartidos asociados siempre a su propietario.
- El HTML incluido en Markdown se filtra y los enlaces inseguros no se renderizan.
- Los tokens de API se guardan únicamente como hash.
- Las exportaciones de cuenta se guardan fuera del directorio público, su enlace usa un token aleatorio guardado como hash y caduca en 24 horas.
- Los adjuntos sin referencias se limpian al guardar o borrar; los necesarios para restaurar versiones se conservan solo durante el periodo de historial.

## Ejecutarlo con Docker Compose

### Requisitos

- Docker Engine con Docker Compose v2.
- Una red Docker externa para el proxy inverso. Si no existe aún:

```bash
docker network create nginx-pm_default
```

### 1. Preparar la configuración

```bash
cp db.env.example db.env
cp app/.env.example app/.env
```

Define contraseñas robustas en `db.env`. Después configura Laravel para MySQL en `app/.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.example

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=md_notes
DB_USERNAME=md_notes
DB_PASSWORD=usa-la-misma-contraseña-de-db.env
```

Genera la clave de Laravel una vez:

```bash
docker compose run --rm app php artisan key:generate
```

Configura también el correo SMTP en `app/.env` si quieres habilitar la bienvenida, recuperación de contraseña y códigos de confirmación.

### 2. Arrancar y migrar

```bash
docker compose up -d --build
docker compose exec -T app php artisan migrate --force
```

El servicio `app` se conecta a la red del proxy `nginx-pm_default`; configura allí tu dominio y TLS. MySQL no publica ningún puerto al exterior.

### Dominio canónico y migraciones

El dominio canónico de producción es `https://mdnotes.net`. Configura `APP_URL` con ese valor y conserva los dominios anteriores en `MD_NOTES_LEGACY_HOSTS`, separados por comas. La aplicación responde con una redirección permanente `308`, manteniendo método, ruta y parámetros; así funcionan enlaces compartidos, adjuntos, API y URLs antiguas.

```dotenv
APP_URL=https://mdnotes.net
MD_NOTES_LEGACY_HOSTS=md.mateo.ovh
```

En el proxy crea un host HTTPS independiente para el nuevo dominio que apunte a `md-notes-app:80` y mantén activo el host del dominio anterior. No combines ambos hosts bajo un certificado antiguo: emite un certificado TLS válido para el dominio nuevo antes de activar la redirección.

## Persistencia y copias

- `data/`: notas Markdown y adjuntos privados por usuario.
- `mysql/`: datos de MySQL.
- `db.env` y `app/.env`: configuración local y credenciales.

Estos directorios y ficheros están excluidos de Git. En producción, `md-notes` crea una copia local incremental cada 5 minutos, conserva instantáneas con enlaces físicos durante 30 días y sube el estado local cifrado a Backblaze B2 a `:30` de cada hora. El proceso se detiene con seguridad si quedan menos de 5 GiB libres. Antes de actualizar el servidor, confirma que la última instantánea local se ha creado correctamente.

## Desarrollo y mantenimiento

Tras cambiar rutas, vistas o configuración, reconstruye las cachés:

```bash
docker compose exec -T app php artisan route:clear
docker compose exec -T app php artisan view:clear
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
```

Para una referencia técnica completa —arquitectura, mantenimiento, historial, demo y copias— consulta [PROJECT_MEMORY.md](PROJECT_MEMORY.md).

---

Hecho con Laravel, MySQL y Docker, y diseñado para que tus apuntes sigan siendo tuyos.
