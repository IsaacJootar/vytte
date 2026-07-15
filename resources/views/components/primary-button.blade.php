@props(['loading' => false])

<button
    {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-2 px-4 py-2 bg-vytte-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-vytte-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-700 focus-visible:ring-offset-2 active:bg-vytte-900 disabled:opacity-65 disabled:cursor-not-allowed transition-colors duration-150']) }}
    x-data="{ loading: false }"
    @click="if (!$el.disabled) loading = true"
    :disabled="loading"
>
    <svg x-show="loading" class="w-3.5 h-3.5 btn-spinner" viewBox="0 0 14 14" fill="none" aria-hidden="true">
        <circle cx="7" cy="7" r="5" stroke="rgba(255,255,255,0.35)" stroke-width="2"/>
        <path d="M7 2a5 5 0 015 5" stroke="white" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span>{{ $slot }}</span>
</button>
