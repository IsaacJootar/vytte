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

    // `limit_error` is what the plan-limit guards flash, and `info` what the invite flow
    // uses. They are messages to the user like any other, so they surface here rather
    // than depending on each page to remember to render its own key.
    $errorMessage = session('error') ?? session('limit_error');
    $infoMessage = session('info') ?? session('warning');

    // `$errors` is shared by the session middleware, but not every render path runs it
    // (console renders and middleware-free routes do not). This banner must never be the
    // reason a page fails to render, so treat a missing bag as "no errors".
    $errorBag = $errors ?? null;
    $validationErrors = $errorBag && $errorBag->any() ? $errorBag->all() : [];
@endphp

@if ($successMessage || $errorMessage || $infoMessage || $validationErrors)
    <div class="pointer-events-none fixed inset-x-4 top-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-5 sm:top-5 sm:w-full sm:max-w-md"
         role="status" aria-live="polite">

        @if ($successMessage)
            <div x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => show = false, 5000)"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="pointer-events-auto flex w-full items-start gap-3 rounded-xl border border-vytte-600 bg-vytte-700 px-4 py-3 text-white shadow-xl shadow-slate-900/15">
                <span class="mt-0.5 text-white" aria-hidden="true">✓</span>
                <p class="flex-1 text-sm font-medium text-white">{{ $successMessage }}</p>
                <button type="button" x-on:click="show = false"
                        class="text-white/80 hover:text-white"
                        aria-label="Dismiss message">&times;</button>
            </div>
        @endif

        @if ($infoMessage)
            <div x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => show = false, 6000)"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="pointer-events-auto flex w-full items-start gap-3 rounded-xl border border-vytte-600 bg-vytte-700 px-4 py-3 text-white shadow-xl shadow-slate-900/15">
                <span class="mt-0.5 text-white" aria-hidden="true">i</span>
                <p class="flex-1 text-sm font-medium text-white">{{ $infoMessage }}</p>
                <button type="button" x-on:click="show = false"
                        class="text-white/80 hover:text-white"
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
                 class="pointer-events-auto flex w-full items-start gap-3 rounded-xl border border-vytte-600 bg-vytte-700 px-4 py-3 text-white shadow-xl shadow-slate-900/15">
                <span class="mt-0.5 text-white" aria-hidden="true">!</span>
                <div class="flex-1 text-sm text-white">
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
                        class="text-white/80 hover:text-white"
                        aria-label="Dismiss message">&times;</button>
            </div>
        @endif
    </div>
@endif
