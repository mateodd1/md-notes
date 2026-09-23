@props(['name'])
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    @switch($name)
        @case('spark')<path class="icon-fill" d="M12 2.4c.7 5.3 3.1 8.1 8.2 9.6-5.1 1.5-7.5 4.3-8.2 9.6-.7-5.3-3.1-8.1-8.2-9.6 5.1-1.5 7.5-4.3 8.2-9.6Z"/>@break
        @case('settings')<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.09A1.7 1.7 0 0 0 8.94 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.57 15 1.7 1.7 0 0 0 3 14H3v-4h.09A1.7 1.7 0 0 0 4.6 8.94a1.7 1.7 0 0 0-.34-1.88L4.2 7l2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.57 1.7 1.7 0 0 0 10 3h4v.09A1.7 1.7 0 0 0 15.06 4.6a1.7 1.7 0 0 0 1.88-.34L17 4.2 19.83 7l-.06.06A1.7 1.7 0 0 0 19.43 9 1.7 1.7 0 0 0 21 10v4h-.09A1.7 1.7 0 0 0 19.4 15Z"/>@break
        @case('external-link')<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/>@break
        @case('trash')<path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/>@break
        @case('info')<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>@break
        @case('logout')<path d="M10 5H5v14h5M14 8l4 4-4 4M18 12H9"/>@break
        @case('quote')<path d="M5 10h5v6H4v-5c0-3 1.5-5 4-6M15 10h5v6h-6v-5c0-3 1.5-5 4-6"/>@break
        @case('list')<path d="M9 6h11M9 12h11M9 18h11"/><circle class="icon-fill" cx="4.5" cy="6" r="1"/><circle class="icon-fill" cx="4.5" cy="12" r="1"/><circle class="icon-fill" cx="4.5" cy="18" r="1"/>@break
        @case('paperclip')<path d="m9 12.5 5.7-5.7a3 3 0 1 1 4.2 4.2l-7.8 7.8a5 5 0 0 1-7.1-7.1l7.4-7.4a3.5 3.5 0 0 1 5 5L9.2 16.5a2 2 0 0 1-2.8-2.8l6.4-6.4"/>@break
        @case('chevron-down')<path d="m6 9 6 6 6-6"/>@break
        @case('chevron-right')<path d="m9 6 6 6-6 6"/>@break
        @case('home')<path d="m3.5 11.5 8.5-7 8.5 7"/><path d="M5.5 10v10h13V10M9.5 20v-6h5v6"/>@break
        @case('close')<path d="m6 6 12 12M18 6 6 18"/>@break
        @case('arrow-right')<path d="M5 12h14M14 7l5 5-5 5"/>@break
        @case('arrow-left')<path d="M19 12H5M10 7l-5 5 5 5"/>@break
        @case('copy-plus')<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2M14 11v6M11 14h6"/>@break
        @case('download')<path d="M12 3v12M7 10l5 5 5-5M4 20h16"/>@break
        @case('folder')<path d="M3.5 7a2.5 2.5 0 0 1 2.5-2.5h3.8l2.1 2.6H18A2.5 2.5 0 0 1 20.5 9.6V17a2.5 2.5 0 0 1-2.5 2.5H6A2.5 2.5 0 0 1 3.5 17Z"/>@break
        @case('folder-plus')<path d="M3.5 7a2.5 2.5 0 0 1 2.5-2.5h3.8l2.1 2.6H18A2.5 2.5 0 0 1 20.5 9.6V17a2.5 2.5 0 0 1-2.5 2.5H6A2.5 2.5 0 0 1 3.5 17Z"/><path d="M12 11v5M9.5 13.5h5"/>@break
        @case('file')<path d="M6 3h8l4 4v14H6Z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>@break
        @case('file-plus')<path d="M6 3h8l4 4v14H6Z"/><path d="M14 3v4h4M12 11v6M9 14h6"/>@break
        @case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4.5 4.5"/>@break
        @case('menu')<path d="M4 7h16M4 12h16M4 17h16"/>@break
        @case('pin')<path d="M12 17v5M5 17h14M6 3h12l-2 7 3 3v1H5v-1l3-3Z"/>@break
        @case('more')<circle class="icon-fill" cx="12" cy="5" r="1.5"/><circle class="icon-fill" cx="12" cy="12" r="1.5"/><circle class="icon-fill" cx="12" cy="19" r="1.5"/>@break
        @case('monitor')<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>@break
        @case('sun')<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/>@break
        @case('moon')<path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z"/>@break
    @endswitch
</svg>
