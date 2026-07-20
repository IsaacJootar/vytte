@props(['title', 'description' => null, 'last' => false])

{{--
    A titled group of related fields.

    A form should never read as one long white page. Each group states what it is and why
    it is being asked, then holds only the fields that belong to it, separated from the
    next group by a hairline rather than by whitespace alone.
--}}
<section {{ $attributes->merge(['class' => $last ? '' : 'border-b border-slate-100 pb-6 dark:border-slate-700']) }}>
    <div class="mb-4">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $title }}</h2>
        @if ($description)
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
        @endif
    </div>

    <div class="space-y-5">
        {{ $slot }}
    </div>
</section>
