@php($es = app()->getLocale() === 'es')
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $es ? 'md-notes: apuntes, notas y documentación en Markdown. Organiza tus archivos en carpetas y trabaja desde el navegador.' : 'md-notes: notes and documentation in Markdown. Organise your files in folders and work in your browser.' }}">
    <title>{{ $es ? 'md-notes · Apuntes en Markdown' : 'md-notes · Markdown notes' }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/md-notes-landing.css') }}?v={{ filemtime(public_path('assets/md-notes-landing.css')) }}">
</head>
<body>
    <a class="skip-link" href="#main">{{ $es ? 'Ir al contenido' : 'Skip to content' }}</a>
    <header class="wrap site-header">
        <a class="brand" href="{{ route('home') }}"><span class="spark" aria-hidden="true">✦</span> md-notes</a>
        <nav aria-label="{{ $es ? 'Navegación principal' : 'Main navigation' }}">
            <a class="features-link" href="#features">{{ $es ? 'Funciones' : 'Features' }}</a>
            <a class="docs-link" href="{{ route('documentation') }}">{{ $es ? 'Documentación' : 'Documentation' }}</a>
            @auth
                <a class="button secondary" href="{{ route('notes.index') }}">{{ $es ? 'Mis notas' : 'My notes' }} <span aria-hidden="true">↗</span></a>
            @else
                <a class="button secondary" href="{{ route('login') }}">{{ $es ? 'Entrar' : 'Log in' }} <span aria-hidden="true">↗</span></a>
            @endauth
        </nav>
    </header>
    <main id="main">
        <section class="wrap hero" aria-labelledby="hero-title">
            <div class="hero-copy">
                <h1 id="hero-title">{{ $es ? 'Tus ideas,' : 'Your ideas,' }}<br><em>{{ $es ? 'en orden.' : 'in order.' }}</em></h1>
                <p class="lead">{{ $es ? 'Toma apuntes en Markdown y organízalos por asignatura o proyecto. Puedes leerlos y editarlos desde el navegador, en el ordenador o en el móvil.' : 'Take notes in Markdown and organise them by subject or project. Read and edit them in your browser, on your computer or phone.' }}</p>
                <div class="hero-actions">
                    @auth
                        <a class="button" href="{{ route('notes.index') }}">{{ $es ? 'Abrir mis notas' : 'Open my notes' }} <span aria-hidden="true">→</span></a>
                    @else
                        <a class="button" href="{{ route('register') }}">{{ $es ? 'Crear una cuenta' : 'Create an account' }} <span aria-hidden="true">→</span></a>
                    @endauth
                    <a class="text-link" href="#features">{{ $es ? 'Ver las funciones' : 'See the features' }}</a>
                </div>
            </div>
            <figure class="preview">
                <div class="app-preview" aria-label="{{ $es ? 'Ejemplo de una nota en md-notes' : 'Example of a note in md-notes' }}">
                    <div class="preview-topbar"><span><span class="spark" aria-hidden="true">✦</span> md-notes</span><span class="preview-state">{{ $es ? 'Modo lectura' : 'Reading mode' }}</span></div>
                    <div class="preview-body">
                        <aside class="preview-tree" aria-label="{{ $es ? 'Carpetas de ejemplo' : 'Example folders' }}">
                            <span class="tree-caption">{{ $es ? 'Mis notas' : 'My notes' }}</span>
                            <span>Ideas.md</span>
                            <span class="tree-folder"><span aria-hidden="true">⌄</span> {{ $es ? 'Clases' : 'Classes' }}</span>
                            <span class="tree-indent active">{{ $es ? 'apuntes.md' : 'notes.md' }}</span>
                            <span class="tree-indent">{{ $es ? 'resumen.md' : 'summary.md' }}</span>
                            <span class="tree-folder"><span aria-hidden="true">›</span> {{ $es ? 'Proyectos' : 'Projects' }}</span>
                        </aside>
                        <article class="preview-note">
                            <div class="preview-path">{{ $es ? 'Clases / apuntes.md' : 'Classes / notes.md' }}</div>
                            <h2>{{ $es ? 'Apuntes de clase' : 'Class notes' }}</h2>
                            <p>{{ $es ? 'Un resumen de lo que hemos visto hoy.' : 'A summary of what we covered today.' }}</p>
                            <h3>{{ $es ? 'Ideas principales' : 'Key ideas' }}</h3>
                            <ul><li>{{ $es ? 'Conceptos que conviene recordar.' : 'Concepts to remember.' }}</li><li>{{ $es ? 'Ejemplos y dudas de la clase.' : 'Examples and questions from class.' }}</li></ul>
                            <div class="note-reminder"><strong>{{ $es ? 'Para repasar' : 'To review' }}</strong><p>{{ $es ? 'Completar el resumen antes de la próxima clase.' : 'Finish the summary before the next class.' }}</p></div>
                        </article>
                    </div>
                </div>
                <figcaption>{{ $es ? 'Ejemplo de una nota en modo lectura.' : 'An example note in reading mode.' }}</figcaption>
            </figure>
        </section>
        <section id="features" class="wrap features-section" aria-labelledby="features-title">
            <header class="section-heading"><h2 id="features-title">{{ $es ? 'Qué puedes hacer en md-notes' : 'What you can do in md-notes' }}</h2></header>
            <div class="features">
                <article class="feature"><h3>{{ $es ? 'Carpetas y subcarpetas' : 'Folders and subfolders' }}</h3><p>{{ $es ? 'Crea la estructura de carpetas que necesites. Puedes mover archivos arrastrándolos y anclar las notas que uses más.' : 'Set up folders however you need them. Drag files to move them, and pin the notes you use most.' }}</p></article>
                <article class="feature"><h3>{{ $es ? 'Lectura y edición' : 'Reading and editing' }}</h3><p>{{ $es ? 'Las notas se abren en modo lectura. Cuando quieras hacer cambios, pulsa Editar; al terminar, vuelve al lector.' : 'Notes open in reading mode. Select Edit to make changes, then switch back to reading when you are done.' }}</p></article>
                <article class="feature"><h3>{{ $es ? 'Buscar una nota' : 'Find a note' }}</h3><p>{{ $es ? 'La búsqueda revisa tanto el nombre del archivo como su contenido. Se abre desde el panel lateral o con' : 'Search checks both file names and note contents. Open it from the sidebar or press' }} <kbd>Ctrl K</kbd>.</p></article>
                <article class="feature"><h3>{{ $es ? 'Imágenes y archivos' : 'Images and files' }}</h3><p>{{ $es ? 'Añade imágenes, PDF y otros archivos a tus apuntes. Puedes arrastrarlos al editor o pegarlos desde el portapapeles. El límite es de 10 MB por archivo.' : 'Add images, PDFs and other files to your notes by dragging them into the editor or pasting from the clipboard. Each file can be up to 10 MB.' }}</p></article>
                <article class="feature"><h3>{{ $es ? 'Compartir una nota' : 'Share a note' }}</h3><p>{{ $es ? 'Envía un enlace para que otra persona pueda leer la nota sin registrarse. Tú eliges cuándo caduca y puedes desactivarlo en cualquier momento.' : 'Send a link so someone else can read a note without an account. Choose when the link expires or disable it at any time.' }}</p></article>
                <article class="feature"><h3>{{ $es ? 'Historial y papelera' : 'History and trash' }}</h3><p>{{ $es ? 'Si necesitas deshacer un cambio, consulta el historial: guarda hasta 50 versiones durante 7 días. Las notas que borres van a la papelera.' : 'To undo a change, check the history: it keeps up to 50 versions for 7 days. Deleted notes go to the trash.' }}</p></article>
            </div>
        </section>
        <section class="wrap files-section" aria-labelledby="files-title">
            <div class="files-copy">
                <span class="file-extension" aria-hidden="true">.md</span>
                <h2 id="files-title">{{ $es ? 'Escribir en Markdown' : 'Writing in Markdown' }}</h2>
                <p>{{ $es ? 'Las notas se guardan en archivos .md. Para dar formato al texto puedes escribir la sintaxis de Markdown o usar los botones del editor: títulos, negrita, listas y enlaces.' : 'Notes are saved as .md files. To format your text, type Markdown syntax or use the editor buttons for headings, bold text, lists and links.' }}</p>
                <a class="text-link" href="{{ route('documentation') }}">{{ $es ? 'Consultar la documentación' : 'Read the documentation' }} <span aria-hidden="true">↗</span></a>
            </div>
            <dl class="details-list">
                <div><dt>{{ $es ? '100 MB por cuenta' : '100 MB per account' }}</dt><dd>{{ $es ? 'Ese espacio incluye las notas, los adjuntos, el historial y la papelera. Puedes consultar cuánto llevas usado en el panel lateral.' : 'This includes notes, attachments, history and trash. The sidebar shows how much space you have used.' }}</dd></div>
                <div><dt>{{ $es ? 'API disponible' : 'API available' }}</dt><dd>{{ $es ? 'También puedes subir notas desde la terminal con curl. Crea un token en tu perfil y consulta los ejemplos de la documentación.' : 'You can also upload notes from the terminal with curl. Create a token in your profile and follow the examples in the documentation.' }}</dd></div>
                <div><dt>{{ $es ? 'Tema claro y oscuro' : 'Light and dark themes' }}</dt><dd>{{ $es ? 'Por defecto se usa el tema de tu sistema. Si prefieres otro, puedes cambiarlo desde el menú del perfil.' : 'The theme follows your system setting by default. You can choose a different one from the profile menu.' }}</dd></div>
            </dl>
        </section>
        <section class="wrap start-section" aria-labelledby="start-title">
            <div><h2 id="start-title">{{ $es ? 'Empieza a tomar apuntes' : 'Start taking notes' }}</h2><p>{{ $es ? 'Tu cuenta incluye una nota de bienvenida con las indicaciones para empezar.' : 'Your account includes a welcome note to help you get started.' }}</p></div>
            @auth<a class="button" href="{{ route('notes.index') }}">{{ $es ? 'Abrir mis notas' : 'Open my notes' }} <span aria-hidden="true">→</span></a>
            @else<a class="button" href="{{ route('register') }}">{{ $es ? 'Crear una cuenta' : 'Create an account' }} <span aria-hidden="true">→</span></a>@endauth
        </section>
    </main>
    <footer class="wrap site-footer">
        <div><a class="brand" href="{{ route('home') }}"><span class="spark" aria-hidden="true">✦</span> md-notes</a><p>{{ $es ? 'Notas y apuntes en Markdown.' : 'Notes and study notes in Markdown.' }}</p></div>
        <nav aria-label="{{ $es ? 'Enlaces del pie de página' : 'Footer links' }}"><a href="{{ route('documentation') }}">{{ $es ? 'Documentación' : 'Documentation' }}</a><a href="https://github.com/mateodd1/md-notes" target="_blank" rel="noopener noreferrer">GitHub <span aria-hidden="true">↗</span></a></nav>
    </footer>
</body>
</html>
