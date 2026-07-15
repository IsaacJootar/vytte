<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-2 px-4 py-2 bg-danger-100 text-danger-700 text-sm font-semibold rounded-lg border border-danger-100 hover:bg-danger-100/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 focus-visible:ring-offset-2 disabled:opacity-65 disabled:cursor-not-allowed transition-colors duration-150']) }}>
    {{ $slot }}
</button>
