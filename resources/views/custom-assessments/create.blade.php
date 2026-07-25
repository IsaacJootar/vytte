<x-app-layout title="New Custom Assessment">
    <div class="mb-5">
        <a href="{{ route('custom-assessments.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Custom assessments</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Create a custom assessment</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
            Build your own assessment for local context, a survey, or an internal review. It lives only in this
            workspace and does <span class="font-semibold">not</span> produce an official Vytte score — it is your own
            template, in your own words.
        </p>
    </div>

    <x-plan-gate feature="workspace_custom_assessments">
        <form method="POST" action="{{ route('custom-assessments.store') }}" class="max-w-3xl flex flex-col gap-6">
            @csrf

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300">
                    Please check the highlighted fields below.
                </div>
            @endif

            {{-- Basics --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">The basics</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">What this assessment is called and why it exists.</p>

                <div class="mt-4 flex flex-col gap-4">
                    <div>
                        <label for="ca-title" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Title <span class="text-red-500">*</span></label>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">A short name you will recognise later.</p>
                        <input id="ca-title" name="title" value="{{ old('title') }}" required maxlength="180"
                               placeholder="e.g. Community WASH readiness survey"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-vytte-600 focus:ring-vytte-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        @error('title') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="ca-purpose" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Purpose <span class="text-red-500">*</span></label>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">In one or two sentences, what are you trying to find out?</p>
                        <textarea id="ca-purpose" name="purpose" rows="3" required maxlength="2000"
                                  placeholder="e.g. Check whether community water points meet basic safety and maintenance standards before the rainy season."
                                  class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-vytte-600 focus:ring-vytte-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('purpose') }}</textarea>
                        @error('purpose') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- Context --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Context <span class="font-normal text-slate-400">(optional)</span></h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Who and where this assessment is about. Helps you and your team read the results in context.</p>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="ca-scope" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Scope</label>
                        <input id="ca-scope" name="scope" value="{{ old('scope') }}" maxlength="180"
                               placeholder="e.g. 12 water points in Keffi LGA"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-vytte-600 focus:ring-vytte-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label for="ca-setting" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Setting</label>
                        <input id="ca-setting" name="setting" value="{{ old('setting') }}" maxlength="180"
                               placeholder="e.g. Rural community"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-vytte-600 focus:ring-vytte-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label for="ca-population" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Target population</label>
                        <input id="ca-population" name="target_population" value="{{ old('target_population') }}" maxlength="180"
                               placeholder="e.g. Households using the water points"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-vytte-600 focus:ring-vytte-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label for="ca-respondent" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Respondent type</label>
                        <input id="ca-respondent" name="respondent_type" value="{{ old('respondent_type') }}" maxlength="180"
                               placeholder="e.g. Water committee member"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-vytte-600 focus:ring-vytte-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    </div>
                </div>
            </section>

            {{-- Content --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">The questions <span class="font-normal text-slate-400">(optional)</span></h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Draft the structure now or add it later. Put <span class="font-semibold">one item per line</span> in each box.</p>

                <div class="mt-4 flex flex-col gap-4">
                    <div>
                        <label for="ca-sections" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Sections</label>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">The headings that group your questions. One per line.</p>
                        <textarea id="ca-sections" name="sections_text" rows="4"
                                  placeholder="Water quality&#10;Maintenance&#10;Community management"
                                  class="mt-1 w-full rounded-xl border-slate-300 text-sm font-mono focus:border-vytte-600 focus:ring-vytte-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('sections_text') }}</textarea>
                    </div>
                    <div>
                        <label for="ca-questions" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Questions</label>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">The questions respondents will answer. One per line.</p>
                        <textarea id="ca-questions" name="questions_text" rows="6"
                                  placeholder="Is the water source protected from contamination?&#10;Is there a maintenance schedule in place?&#10;Who is responsible for repairs?"
                                  class="mt-1 w-full rounded-xl border-slate-300 text-sm font-mono focus:border-vytte-600 focus:ring-vytte-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('questions_text') }}</textarea>
                    </div>
                    <div>
                        <label for="ca-outputs" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Desired report outputs</label>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">What you want the finished report to show. One per line.</p>
                        <textarea id="ca-outputs" name="descriptive_outputs_text" rows="3"
                                  placeholder="Share of water points meeting safety standards&#10;List of sites needing urgent repair"
                                  class="mt-1 w-full rounded-xl border-slate-300 text-sm font-mono focus:border-vytte-600 focus:ring-vytte-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ old('descriptive_outputs_text') }}</textarea>
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('custom-assessments.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">Cancel</a>
                <button class="rounded-xl bg-vytte-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-vytte-800 transition-colors">Create draft</button>
            </div>
        </form>
    </x-plan-gate>
</x-app-layout>
