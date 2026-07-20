{{--
    One place that confirms an action worked, or explains why it did not.

    Controllers already flash `success` and `error`, and validation puts messages in
    `$errors`. Before this component those messages had to be hand-rendered on every
    page, so any page that forgot them left the user with no confirmation at all.
    Rendering here means every action in the app reports its outcome.

    Success auto-dismisses because it is reassurance. Errors stay until dismissed
    because the user has to read and act on them.
--}}
@php
    $successMessage = session('success');
    $errorMessage = session('error');

    // `$errors` is shared by the session middleware, but not every render path runs it
    // (console renders and middleware-free routes do not). This banner must never be the
    // reason a page fails to render, so treat a missing bag as "no errors".
    $errorBag = $errors ?? null;
    $validationErrors = $errorBag && $errorBag->any() ? $errorBag->all() : [];
@endphp

@if ($successMessage || $errorMessage || $validationErrors)
    <div class="pointer-events-none fixed inset-x-0 top-3 z-50 flex flex-col items-center gap-2 px-4 sm:top-5"
         role="status" aria-live="polite">

        @if ($successMessage)
            <div x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => show = false, 5000)"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-lg dark:border-emerald-800 dark:bg-emerald-950">
                <span class="mt-0.5 text-emerald-600 dark:text-emerald-400" aria-hidden="true">✓</span>
                <p class="flex-1 text-sm font-medium text-emerald-900 dark:text-emerald-100">{{ $successMessage }}</p>
                <button type="button" x-on:click="show = false"
                        class="text-emerald-700 hover:text-emerald-900 dark:text-emerald-300"
                        aria-label="Dismiss message">&times;</button>
            </div>
        @endif

        @if ($errorMessage || $validationErrors)
            <div x-data="{ show: true }"
                 x-show="show"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 shadow-lg dark:border-red-800 dark:bg-red-950">
                <span class="mt-0.5 text-red-600 dark:text-red-400" aria-hidden="true">!</span>
                <div class="flex-1 text-sm text-red-900 dark:text-red-100">
                    @if ($errorMessage)
                        <p class="font-medium">{{ $errorMessage }}</p>
                    @endif
                    @if (count($validationErrors) === 1)
                        <p class="font-medium">{{ $validationErrors[0] }}</p>
                    @elseif (count($validationErrors) > 1)
                        <ul class="list-disc space-y-1 pl-4">
                            @foreach ($validationErrors as $validationError)
                                <li>{{ $validationError }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <button type="button" x-on:click="show = false"
                        class="text-red-700 hover:text-red-900 dark:text-red-300"
                        aria-label="Dismiss message">&times;</button>
            </div>
        @endif
    </div>
@endif
