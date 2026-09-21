@if ($attachments !== [])
    <section class="note-attachments" aria-labelledby="note-attachments-title">
        <header class="note-attachments-header">
            <h2 id="note-attachments-title">{{ __('ui.attached_files') }}</h2>
            <span>{{ trans_choice('ui.attached_files_count', count($attachments), ['count' => count($attachments)]) }}</span>
        </header>
        <div class="note-attachments-grid">
            @foreach ($attachments as $attachment)
                <article class="note-attachment">
                    <span class="note-attachment-type" aria-hidden="true">{{ mb_strtoupper($attachment['extension']) }}</span>
                    <span class="note-attachment-details">
                        <strong>{{ $attachment['name'] }}</strong>
                        <small>.{{ $attachment['extension'] }}</small>
                    </span>
                    <span class="note-attachment-actions">
                        @if ($attachment['preview_url'])
                            <button class="note-attachment-view" type="button" data-pdf-preview-url="{{ $attachment['preview_url'] }}" data-pdf-download-url="{{ $attachment['url'] }}" data-pdf-name="{{ $attachment['name'] }}">{{ __('ui.view_pdf') }}</button>
                        @endif
                        <a class="note-attachment-download" href="{{ $attachment['url'] }}" download="{{ $attachment['name'] }}" title="{{ __('ui.download_attachment', ['name' => $attachment['name']]) }}" aria-label="{{ __('ui.download_attachment', ['name' => $attachment['name']]) }}">
                            <svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
                                <path d="M12 3v11"></path>
                                <path d="m7.5 10 4.5 4.5 4.5-4.5"></path>
                                <path d="M5 18v2h14v-2"></path>
                            </svg>
                        </a>
                    </span>
                </article>
            @endforeach
        </div>
    </section>
@endif
