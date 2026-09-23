<?php

return [
    'home' => 'Ir al inicio',
    'pages' => [
        401 => ['title' => 'Inicia sesión para continuar', 'description' => 'Necesitas acceder a tu cuenta para ver esta página.'],
        403 => ['title' => 'No tienes acceso', 'description' => 'Esta página no está disponible para tu cuenta.'],
        404 => ['title' => 'Página no encontrada', 'description' => 'La dirección puede ser incorrecta o el contenido ya no está disponible.'],
        405 => ['title' => 'Acción no disponible', 'description' => 'No se puede realizar esta acción desde aquí.'],
        419 => ['title' => 'La sesión ha caducado', 'description' => 'Vuelve a abrir la página e inténtalo de nuevo.'],
        429 => ['title' => 'Demasiadas solicitudes', 'description' => 'Espera un momento antes de volver a intentarlo.'],
        500 => ['title' => 'Ha ocurrido un error', 'description' => 'No hemos podido cargar esta página. Inténtalo de nuevo dentro de unos minutos.'],
        503 => ['title' => 'Servicio no disponible', 'description' => 'Estamos realizando tareas de mantenimiento o el servicio está temporalmente interrumpido.'],
    ],
];
