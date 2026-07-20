@props([
    'url',
    'message' => null,
    'label' => 'Link',
    'hint' => null,
    'compact' => false,
])

{{--
    A link the user has to get to somebody else.

    Until now these were flashed once and lost on the next page load. With email switched
    off for beta, a link you cannot retrieve is an invitation you cannot deliver — so
    every link is shown permanently, with the two ways people here actually send things:
    copy, and WhatsApp.

    The WhatsApp text is built server-side and encoded once. wa.me handles the rest,
    opening the phone app on mobile and web.whatsapp.com on desktop.
--}}
@php
    $whatsappText = trim(($message ? $message."\n\n" : '').$url);
    $whatsappUrl = 'https://wa.me/?text='.rawurlencode($whatsappText);
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/50']) }}
     x-data="{
         copied: false,
         copy() {
             const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2000) };
             if (navigator.clipboard) {
                 navigator.clipboard.writeText(@js($url)).then(done).catch(() => this.fallback(done));
             } else {
                 this.fallback(done);
             }
         },
         fallback(done) {
             // Clipboard API needs a secure context. On plain http, which is normal for a
             // beta on a local network, select the field so the user can copy manually.
             const field = this.$refs.url;
             field.removeAttribute('readonly');
             field.select();
             try { document.execCommand('copy'); done() } catch (e) { /* the text is selected either way */ }
             field.setAttribute('readonly', 'readonly');
         },
     }">

    @unless ($compact)
        <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $label }}</p>
        @if ($hint)
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
        @endif
    @endunless

    <div class="{{ $compact ? '' : 'mt-2' }} flex flex-wrap items-center gap-2">
        <input x-ref="url" type="text" readonly value="{{ $url }}"
               aria-label="{{ $label }}"
               x-on:focus="$event.target.select()"
               class="min-w-0 flex-1 rounded-lg bg-white px-3 py-2 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">

        <button type="button" x-on:click="copy()"
                class="btn-secondary shrink-0 px-3 py-2 text-xs">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak>Copied</span>
        </button>

        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-[#25D366] px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-[#1EBE5A] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366]">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 6.988 2.896 9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.8 11.8 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.9 11.9 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.82 11.82 0 0 0 20.464 3.488"/>
            </svg>
            WhatsApp
        </a>
    </div>
</div>
