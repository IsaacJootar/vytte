<x-app-layout title="New Project">

    <div class="max-w-2xl mx-auto">

    {{-- Back link + page header --}}
    <div class="mb-6">
        <a href="{{ route('projects.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors mb-3">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
            </svg>
            Projects
        </a>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">New Project</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Name the project and the setting being assessed.</p>
    </div>

    <form method="POST" action="{{ route('projects.store') }}"
          x-data="{
              targetType: '{{ old('target_type_code', '') }}',
              loading: false
          }">
        @csrf

        <div class="flex flex-col gap-5">

            {{-- ===== SECTION 1 — Project details ===== --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-4">Project details</h2>

                <div class="flex flex-col gap-4">
                    {{-- Name --}}
                    <div>
                        <x-input-label for="name" value="Project name" />
                        <x-text-input
                            id="name"
                            name="name"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('name')"
                            placeholder="e.g. Lagos Clinic — Q2 2026 Assessment"
                            required
                            autofocus
                        />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    {{-- Description --}}
                    <div>
                        <x-input-label for="description" value="Description (optional)" />
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            class="mt-1 block w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-vytte-700 focus:border-transparent transition"
                            placeholder="Any notes about the scope or purpose of this project…"
                        >{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>
                </div>
            </div>

            {{-- ===== SECTION 2 — Target details ===== --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">What are you assessing?</h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">Choose the kind of setting, then enter its name.</p>

                <div class="flex flex-col gap-4">
                    {{-- Target type --}}
                    <div>
                        <x-input-label for="target_type_code" value="Type" />
                        <select
                            id="target_type_code"
                            name="target_type_code"
                            class="mt-1 block w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-700 focus:border-transparent transition bg-white dark:bg-slate-700"
                            x-model="targetType"
                            required
                        >
                            <option value="" disabled>Select type…</option>
                            @foreach ($targetTypes as $type)
                                <option value="{{ $type->target_type_code }}"
                                    {{ old('target_type_code') === $type->target_type_code ? 'selected' : '' }}>
                                    {{ $type->target_type_name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('target_type_code')" class="mt-1" />
                    </div>

                    <div x-show="targetType === 'CUSTOM'" x-cloak>
                        <x-input-label for="custom_setting_label" value="What kind of setting is this?" />
                        <x-text-input
                            id="custom_setting_label"
                            name="custom_setting_label"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('custom_setting_label')"
                            placeholder="e.g. Agricultural cooperative"
                            x-bind:required="targetType === 'CUSTOM'"
                        />
                        <x-input-error :messages="$errors->get('custom_setting_label')" class="mt-1" />

                        <label class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="uses_departments" value="1" class="rounded border-slate-300 text-vytte-600 focus:ring-vytte-500" {{ old('uses_departments') ? 'checked' : '' }}>
                            This setting genuinely uses departments
                        </label>
                    </div>

                    <div x-show="targetType === 'HEALTH_FACILITY'" x-cloak>
                        <x-input-label for="facility_profile_id" value="Health facility profile" />
                        <select
                            id="facility_profile_id"
                            name="facility_profile_id"
                            class="mt-1 block w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-700 focus:border-transparent transition bg-white dark:bg-slate-700"
                            x-bind:required="targetType === 'HEALTH_FACILITY'"
                        >
                            <option value="" disabled {{ old('facility_profile_id') ? '' : 'selected' }}>Select facility profile...</option>
                            @foreach ($facilityProfiles as $profile)
                                <option value="{{ $profile->facility_profile_id }}" {{ old('facility_profile_id') === $profile->facility_profile_id ? 'selected' : '' }}>
                                    {{ $profile->profile_name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Vytte uses this to load the right governed catalogue release.</p>
                        <x-input-error :messages="$errors->get('facility_profile_id')" class="mt-1" />
                    </div>

                    {{-- Target name --}}
                    <div>
                        <label for="target_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                               x-text="targetType === 'SCHOOL' ? 'School name' : (targetType === 'HEALTH_FACILITY' ? 'Health facility name' : 'Setting name')">
                            Setting name
                        </label>
                        <x-text-input
                            id="target_name"
                            name="target_name"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('target_name')"
                            x-bind:placeholder="targetType === 'SCHOOL' ? 'e.g. Sunrise Academy' : (targetType === 'HEALTH_FACILITY' ? 'e.g. Ikeja Health Centre' : 'Enter the official name')"
                            required
                        />
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Use the official or commonly recognized name.</p>
                        <x-input-error :messages="$errors->get('target_name')" class="mt-1" />
                    </div>

                    {{-- Location section --}}
                    <div class="pt-1">
                        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">Where is this assessment being carried out?</h3>
                        <div class="flex flex-col gap-4">
                            {{-- Country --}}
                            <div>
                                <x-input-label for="country" value="Country" />
                                <select
                                    id="country"
                                    name="country"
                                    class="mt-1 block w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-700 focus:border-transparent transition bg-white dark:bg-slate-700"
                                    required
                                >
                                    <option value="" disabled {{ old('country') ? '' : 'selected' }}>Select country…</option>
                                    @foreach ($countries as $group => $groupCountries)
                                        <optgroup label="{{ $group }}">
                                            @foreach ($groupCountries as $country)
                                                <option value="{{ $country }}" {{ old('country') === $country ? 'selected' : '' }}>{{ $country }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('country')" class="mt-1" />
                            </div>

                            {{-- Region + Sub-region --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <x-input-label for="region" value="Region (optional)" />
                                    <x-text-input
                                        id="region"
                                        name="region"
                                        type="text"
                                        class="mt-1 block w-full"
                                        :value="old('region')"
                                        placeholder="State / Province / County"
                                    />
                                    <x-input-error :messages="$errors->get('region')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="sub_region" value="Sub-region (optional)" />
                                    <x-text-input
                                        id="sub_region"
                                        name="sub_region"
                                        type="text"
                                        class="mt-1 block w-full"
                                        :value="old('sub_region')"
                                        placeholder="LGA / District / Municipality"
                                    />
                                    <x-input-error :messages="$errors->get('sub_region')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form actions --}}
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-vytte-700 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-vytte-800 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-700 focus-visible:ring-offset-2"
                    x-on:click="if ($el.closest('form').checkValidity()) $nextTick(() => loading = true)"
                    :disabled="loading"
                >
                    <svg class="w-3.5 h-3.5 btn-spinner hidden" x-show="loading" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" d="M12 2C6.477 2 2 6.477 2 12"/>
                    </svg>
                    <span x-text="loading ? 'Creating…' : 'Create Project'"></span>
                </button>
                <a href="{{ route('projects.index') }}"
                   class="text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">Cancel</a>
            </div>

        </div>
    </form>

    </div>{{-- /max-w-2xl mx-auto --}}

</x-app-layout>
