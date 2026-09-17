# md-notes

Espacio privado de notas Markdown desarrollado con Laravel y Docker.

## Características

- Almacenamiento directo en archivos Markdown (`.md`) organizados en carpetas por usuario.
- Interfaz web con editor en tiempo real, vista previa y modo lectura.
- Soporte para adjuntar imágenes (drag & drop y pegado desde el portapapeles).
- Historial de versiones con capacidad de restauración y descarga.
- Enlaces compartidos públicos con tiempo de caducidad configurable.
- Selector de tema: claro, oscuro o sincronizado con el sistema.
- Multilingüe: español e inglés según las preferencias del navegador.
- API REST para creación y sincronización de notas mediante Bearer token.
- Despliegue mediante Docker Compose (PHP 8.4 + Apache + MySQL 8.4).

## Despliegue con Docker Compose

### 1. Variables de entorno

Copia las plantillas de configuración y define las credenciales:

```bash
cp db.env.example db.env
cp app/.env.example app/.env
```

Genera la clave de la aplicación en `app/.env`:
```bash
docker compose run --rm app php artisan key:generate
```

### 2. Iniciar contenedores

```bash
docker compose up -d --build
```

### 3. Ejecutar migraciones

```bash
docker compose exec -T app php artisan migrate --force
```

## Documentación

Para consultar la memoria completa del proyecto, arquitectura y comandos de mantenimiento, revisa [PROJECT_MEMORY.md](PROJECT_MEMORY.md).
