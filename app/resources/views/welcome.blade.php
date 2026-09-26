@php
    $es = app()->getLocale() === 'es';
    $authenticated = auth()->check();
    $spaceLabel = $es ? 'Acceder a mi espacio' : 'Open my space';
    $canonical = route('home');
    $pageTitle = $es ? 'md-notes · Notas y documentación en Markdown' : 'md-notes · Markdown notes and documentation';
    $description = $es
        ? 'md-notes: apuntes, notas y documentación en Markdown. Organiza tus archivos en carpetas y trabaja desde el navegador.'
        : 'md-notes: notes and documentation in Markdown. Organise your files in folders and work in your browser.';
    $socialImage = asset('android-chrome-512x512.png');
    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebSite',
                '@id' => $canonical.'#website',
                'url' => $canonical,
                'name' => 'md-notes',
                'alternateName' => 'mdnotes',
                'description' => $description,
                'inLanguage' => $es ? 'es' : 'en',
            ],
            [
                '@type' => 'WebApplication',
                '@id' => $canonical.'#application',
                'url' => $canonical,
                'name' => 'md-notes',
                'description' => $description,
                'applicationCategory' => 'UtilitiesApplication',
                'operatingSystem' => 'Any',
                'browserRequirements' => $es ? 'Requiere un navegador web moderno' : 'Requires a modern web browser',
                'isAccessibleForFree' => true,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'EUR',
                ],
                'featureList' => $es
                    ? ['Editor Markdown', 'Carpetas y subcarpetas', 'Historial de versiones', 'Archivos adjuntos', 'Enlaces compartidos', 'API']
                    : ['Markdown editor', 'Folders and subfolders', 'Version history', 'File attachments', 'Shared links', 'API'],
            ],
        ],
    ];
@endphp
<!doctype html>
<html class="landing-page" lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-session-status-url="{{ route('session.status') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafd" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#131314" media="(prefers-color-scheme: dark)">
    <meta name="description" content="{{ $description }}">
    <link rel="describedby" href="{{ url('/llms.txt') }}">
    <link rel="alternate" type="text/markdown" href="{{ route('documentation.raw') }}" title="md-notes documentation">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="md-notes">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ $es ? 'es_ES' : 'en_US' }}">
    <meta property="og:locale:alternate" content="{{ $es ? 'en_US' : 'es_ES' }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">
    <meta property="og:image:alt" content="md-notes">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <title>{{ $pageTitle }}</title>
    @include('partials.favicons')
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <link rel="stylesheet" href="{{ asset('assets/md-notes-landing.css') }}?v={{ filemtime(public_path('assets/md-notes-landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/md-notes-google.css') }}?v={{ filemtime(public_path('assets/md-notes-google.css')) }}">
    <script src="{{ asset('assets/md-notes-landing.js') }}?v={{ filemtime(public_path('assets/md-notes-landing.js')) }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#main">{{ $es ? 'Ir al contenido' : 'Skip to content' }}</a>
    <header class="wrap site-header">
        <a class="brand" href="{{ route('home') }}"><x-icon name="spark" class="brand-icon" />md-notes</a>
        <nav aria-label="{{ $es ? 'Navegación principal' : 'Main navigation' }}">
            <a class="features-link" href="#features">{{ $es ? 'Funciones' : 'Features' }}</a>
            <a class="docs-link" href="{{ route('documentation') }}">{{ $es ? 'Documentación' : 'Documentation' }}</a>
            <a class="button secondary" href="{{ $authenticated ? route('notes.index') : route('login') }}" data-session-link data-authenticated-href="{{ route('notes.index') }}" data-authenticated-label="{{ $spaceLabel }}"><span data-session-label>{{ $authenticated ? $spaceLabel : ($es ? 'Entrar' : 'Log in') }}</span><x-icon name="external-link" class="button-icon" /></a>
        </nav>
    </header>
    <main id="main">
        <section class="wrap hero" aria-labelledby="hero-title">
            <div class="hero-copy">
                <h1 id="hero-title">{{ $es ? 'Tus ideas,' : 'Your ideas,' }}<br><em>{{ $es ? 'en orden.' : 'in order.' }}</em></h1>
                <p class="lead">{{ $es ? 'Escribe notas, documentación y bases de conocimiento en Markdown. Organiza el contenido por proyectos o temas y trabaja desde el navegador, en el ordenador o en el móvil.' : 'Write notes, documentation, and knowledge bases in Markdown. Organise content by project or topic and work in your browser, on your computer or phone.' }}</p>
                <div class="hero-actions">
                    <a class="button" href="{{ $authenticated ? route('notes.index') : route('register') }}" data-session-link data-authenticated-href="{{ route('notes.index') }}" data-authenticated-label="{{ $spaceLabel }}"><span data-session-label>{{ $authenticated ? $spaceLabel : ($es ? 'Crear una cuenta' : 'Create an account') }}</span><x-icon name="arrow-right" class="button-icon" /></a>
                    <a class="text-link" href="#features">{{ $es ? 'Ver las funciones' : 'See the features' }}</a>
                </div>
            </div>
            <figure class="preview">
                <div class="app-preview" aria-label="{{ $es ? 'Ejemplo de un documento en md-notes' : 'Example of a document in md-notes' }}">
                    <div class="preview-topbar"><span><x-icon name="spark" class="brand-icon" />md-notes</span><span class="preview-state">{{ $es ? 'Modo lectura' : 'Reading mode' }}</span></div>
                    <div class="preview-body">
                        <aside class="preview-tree" aria-label="{{ $es ? 'Carpetas de ejemplo' : 'Example folders' }}">
                            <span class="tree-caption">{{ $es ? 'Mis notas' : 'My notes' }}</span>
                            <span>Ideas.md</span>
                            <span class="tree-folder"><x-icon name="chevron-down" />{{ $es ? 'Documentación' : 'Documentation' }}</span>
                            <span class="tree-indent active">{{ $es ? 'guía.md' : 'guide.md' }}</span>
                            <span class="tree-indent">{{ $es ? 'referencia.md' : 'reference.md' }}</span>
                            <span class="tree-folder"><x-icon name="chevron-right" />{{ $es ? 'Proyectos' : 'Projects' }}</span>
                        </aside>
                        <article class="preview-note">
                            <div class="preview-path">{{ $es ? 'Documentación / guía.md' : 'Documentation / guide.md' }}</div>
                            <h2>{{ $es ? 'Guía del proyecto' : 'Project guide' }}</h2>
                            <p>{{ $es ? 'Información útil para empezar a trabajar.' : 'Useful information for getting started.' }}</p>
                            <h3>{{ $es ? 'Primeros pasos' : 'Getting started' }}</h3>
                            <ul><li>{{ $es ? 'Preparar el entorno de trabajo.' : 'Prepare the working environment.' }}</li><li>{{ $es ? 'Revisar la estructura del proyecto.' : 'Review the project structure.' }}</li></ul>
                            <div class="note-reminder"><strong>{{ $es ? 'Referencia' : 'Reference' }}</strong><p>{{ $es ? 'Enlaces, decisiones y detalles importantes.' : 'Links, decisions, and important details.' }}</p></div>
                        </article>
                    </div>
                </div>
                <figcaption>{{ $es ? 'Ejemplo de un documento en modo lectura.' : 'An example document in reading mode.' }}</figcaption>
            </figure>
        </section>
        <section id="features" class="wrap features-section" aria-labelledby="features-title">
            <header class="section-heading"><h2 id="features-title">{{ $es ? 'Qué puedes hacer en md-notes' : 'What you can do in md-notes' }}</h2></header>
            <div class="features">
                <article class="feature"><h3>{{ $es ? 'Carpetas y subcarpetas' : 'Folders and subfolders' }}</h3><p>{{ $es ? 'Organiza apuntes, proyectos y documentación con la estructura que necesites. Puedes mover archivos arrastrándolos y anclar los que uses más.' : 'Organise notes, projects, and documentation with the structure you need. Drag files to move them and pin the ones you use most.' }}</p></article>
                <article class="feature"><h3>{{ $es ? 'Lectura y edición' : 'Reading and editing' }}</h3><p>{{ $es ? 'Los documentos se abren en modo lectura. Cuando quieras hacer cambios, pulsa Editar; al terminar, vuelve al lector.' : 'Documents open in reading mode. Select Edit to make changes, then switch back to reading when you are done.' }}</p></article>
                <article class="feature"><h3>{{ $es ? 'Encontrar contenido' : 'Find content' }}</h3><p>{{ $es ? 'La búsqueda revisa tanto el nombre de los archivos como su contenido. Se abre desde el panel lateral o con' : 'Search checks both file names and their contents. Open it from the sidebar or press' }} <kbd>Ctrl K</kbd>.</p></article>
                <article class="feature"><h3>{{ $es ? 'Imágenes y archivos' : 'Images and files' }}</h3><p>{{ $es ? 'Añade imágenes, PDF y otros archivos a tus documentos. Puedes arrastrarlos al editor o pegarlos desde el portapapeles. El límite es de 10 MB por archivo.' : 'Add images, PDFs, and other files to your documents by dragging them into the editor or pasting from the clipboard. Each file can be up to 10 MB.' }}</p></article>
                <article class="feature"><h3>{{ $es ? 'Compartir documentos' : 'Share documents' }}</h3><p>{{ $es ? 'Envía un enlace para que otra persona pueda leer un documento sin registrarse. Tú eliges cuándo caduca y puedes desactivarlo en cualquier momento.' : 'Send a link so someone else can read a document without an account. Choose when the link expires or disable it at any time.' }}</p></article>
                <article class="feature"><h3>{{ $es ? 'Historial y papelera' : 'History and trash' }}</h3><p>{{ $es ? 'Si necesitas deshacer un cambio, consulta el historial: guarda hasta 50 versiones durante 7 días. Los archivos que borres van a la papelera.' : 'To undo a change, check the history: it keeps up to 50 versions for 7 days. Deleted files go to the trash.' }}</p></article>
            </div>
        </section>
        <section class="wrap files-section" aria-labelledby="files-title">
            <div class="files-copy">
                <span class="file-extension" aria-hidden="true">.md</span>
                <h2 id="files-title">{{ $es ? 'Escribir en Markdown' : 'Writing in Markdown' }}</h2>
                <p>{{ $es ? 'Las notas y documentos se guardan en archivos .md. Para dar formato al texto puedes escribir la sintaxis de Markdown o usar los botones del editor: títulos, negrita, listas y enlaces.' : 'Notes and documents are saved as .md files. To format your text, type Markdown syntax or use the editor buttons for headings, bold text, lists, and links.' }}</p>
                <a class="text-link" href="{{ route('documentation') }}">{{ $es ? 'Consultar la documentación' : 'Read the documentation' }}<x-icon name="external-link" class="button-icon" /></a>
            </div>
            <dl class="details-list">
                <div><dt>{{ $es ? '100 MB por cuenta' : '100 MB per account' }}</dt><dd>{{ $es ? 'Ese espacio incluye los documentos, los adjuntos, el historial y la papelera. Puedes consultar cuánto llevas usado en el panel lateral.' : 'This includes documents, attachments, history, and trash. The sidebar shows how much space you have used.' }}</dd></div>
                <div><dt>{{ $es ? 'API disponible' : 'API available' }}</dt><dd>{{ $es ? 'Puedes publicar un archivo Markdown desde la terminal, incluso sin cuenta. La documentación explica cómo obtener el enlace.' : 'You can publish a Markdown file from the terminal, even without an account. The documentation explains how to get the link.' }}</dd></div>
                <div><dt>{{ $es ? 'Tema claro y oscuro' : 'Light and dark themes' }}</dt><dd>{{ $es ? 'Por defecto se usa el tema de tu sistema. Si prefieres otro, puedes cambiarlo desde el menú del perfil.' : 'The theme follows your system setting by default. You can choose a different one from the profile menu.' }}</dd></div>
            </dl>
        </section>
        <section class="wrap start-section" aria-labelledby="start-title">
            <div><h2 id="start-title">{{ $es ? 'Empieza a escribir' : 'Start writing' }}</h2><p>{{ $es ? 'Tu cuenta incluye un documento de bienvenida con las indicaciones para empezar.' : 'Your account includes a welcome document to help you get started.' }}</p></div>
            <a class="button" href="{{ $authenticated ? route('notes.index') : route('register') }}" data-session-link data-authenticated-href="{{ route('notes.index') }}" data-authenticated-label="{{ $spaceLabel }}"><span data-session-label>{{ $authenticated ? $spaceLabel : ($es ? 'Crear una cuenta' : 'Create an account') }}</span><x-icon name="arrow-right" class="button-icon" /></a>
        </section>
    </main>
    <footer class="wrap site-footer">
        <div><a class="brand" href="{{ route('home') }}"><x-icon name="spark" class="brand-icon" />md-notes</a><p>{{ $es ? 'Notas y documentación en Markdown.' : 'Notes and documentation in Markdown.' }}</p></div>
        <nav aria-label="{{ $es ? 'Enlaces del pie de página' : 'Footer links' }}"><a href="{{ route('documentation') }}">{{ $es ? 'Documentación' : 'Documentation' }}</a><a href="https://github.com/mateodd1/md-notes" target="_blank" rel="noopener noreferrer">GitHub <x-icon name="external-link" /></a></nav>
    </footer>
</body>
</html>
