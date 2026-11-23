@props([
    'name',
    'size' => 18,
    'strokeWidth' => 2,
])

<svg {{ $attributes }} aria-hidden="true" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect>
            @break
        @case('members')
            <circle cx="9" cy="8" r="4"></circle><path d="M2 21c0-4 3-6 7-6s7 2 7 6"></path><path d="M16 4a4 4 0 0 1 0 8"></path><path d="M22 21c0-3-2-5-4-5.6"></path>
            @break
        @case('org')
            <rect x="9" y="2" width="6" height="5" rx="1"></rect><rect x="2" y="17" width="6" height="5" rx="1"></rect><rect x="16" y="17" width="6" height="5" rx="1"></rect><path d="M12 7v5M5 17v-5h14v5"></path>
            @break
        @case('activities')
            <rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"></path>
            @break
        @case('birthdays')
            <path d="M4 21h16v-8H4zM4 17h16M12 13V9M12 6a1.5 1.5 0 0 1-1.5-1.5C10.5 3 12 2 12 2s1.5 1 1.5 2.5A1.5 1.5 0 0 1 12 6z"></path><path d="M7 13v-2h10v2"></path>
            @break
        @case('tasks')
            <rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M8 12l3 3 5-6"></path>
            @break
        @case('documents')
            <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            @break
        @case('reports')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"></path>
            @break
        @case('more')
            <path d="M4 6h16M4 12h16M4 18h16"></path>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"></path>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"></path>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path>
            @break
        @case('alert')
            <circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 16v.5"></path>
            @break
        @case('pencil')
            <path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"></path>
            @break
        @case('swap')
            <path d="M7 16V4M3 8l4-4 4 4M17 8v12M21 16l-4 4-4-4"></path>
            @break
        @case('upload')
            <path d="M12 15V3M7 8l5-5 5 5M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"></path>
            @break
        @case('sign-out')
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"></path>
            @break
        @case('chevron-left')
            <path d="M15 6l-6 6 6 6"></path>
            @break
        @case('chevron-right')
            <path d="M9 6l6 6-6 6"></path>
            @break
    @endswitch
</svg>
