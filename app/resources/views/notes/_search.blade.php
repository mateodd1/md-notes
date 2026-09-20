<dialog id="note-search" class="note-search-dialog" aria-labelledby="note-search-title">
    <header><h2 id="note-search-title">{{ __('ui.search_notes') }}</h2><button id="close-note-search" type="button" aria-label="{{ __('ui.close') }}">×</button></header>
    <label class="sr-only" for="note-search-input">{{ __('ui.search_notes') }}</label>
    <input id="note-search-input" type="search" maxlength="100" autocomplete="off" placeholder="{{ __('ui.search_placeholder') }}">
    <p id="note-search-status" role="status">{{ __('ui.search_hint') }}</p>
    <div id="note-search-results"></div>
</dialog>
