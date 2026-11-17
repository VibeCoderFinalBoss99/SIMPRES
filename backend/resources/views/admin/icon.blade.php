{{-- Ikon garis sederhana (gaya sama dengan desain Figma). Pemakaian: @include('admin.icon', ['name' => 'pin']) --}}
@php
    $paths = [
        'shield' => '<path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'review' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 13l2 2 4-4"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 4.5a3.5 3.5 0 010 7M21 20c0-2.6-1.6-4.8-4-5.6"/>',
        'pin' => '<path d="M12 21s7-6.2 7-12a7 7 0 10-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5"/><path d="M4 19h16"/>',
        'history' => '<path d="M3 12a9 9 0 103-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'sliders' => '<path d="M5 3v18M12 3v18M19 3v18"/><circle cx="5" cy="15" r="2" fill="currentColor"/><circle cx="12" cy="8" r="2" fill="currentColor"/><circle cx="19" cy="14" r="2" fill="currentColor"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>',
        'bell' => '<path d="M6 16V11a6 6 0 1112 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 004 0"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'filter' => '<path d="M4 5h16l-6 8v5l-4 2v-7z"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'power' => '<path d="M12 3v8"/><path d="M6.5 7a8 8 0 1011 0"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
        'chevron-left' => '<path d="M15 5l-7 7 7 7"/>',
        'chevron-right' => '<path d="M9 5l7 7-7 7"/>',
    ];
@endphp
<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? '' !!}</svg>
