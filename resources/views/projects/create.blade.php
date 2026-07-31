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
              mode: '{{ old('target_type_code') === 'HEALTH_FACILITY' ? 'FACILITY' : (old('target_type_code') ? 'PROGRAMME' : '') }}',
              facilityProfile: '{{ old('target_type_code') === 'HEALTH_FACILITY' ? old('facility_profile_id') : '' }}',
              programmeChoice: '{{ old('target_type_code') && old('target_type_code') !== 'HEALTH_FACILITY' ? (old('target_type_code') === 'CUSTOM' ? '__custom__' : old('facility_profile_id')) : '' }}',
              programmeSettings: {{ Illuminate\Support\Js::from($programmeProfiles->pluck('setting_type_code', 'facility_profile_id')) }},
              selectionError: {{ $errors->has('target_type_code') ? 'true' : 'false' }},
              loading: false,
              get targetTypeCode() {
                  if (this.mode === 'FACILITY') { return this.facilityProfile ? 'HEALTH_FACILITY' : ''; }
                  if (this.mode === 'PROGRAMME') {
                      if (this.programmeChoice === '__custom__') { return 'CUSTOM'; }
                      return this.programmeSettings[this.programmeChoice] ?? '';
                  }
                  return '';
              },
              get profileId() {
                  if (this.mode === 'FACILITY') { return this.facilityProfile; }
                  if (this.mode === 'PROGRAMME' && this.programmeChoice !== '__custom__') { return this.programmeChoice; }
                  return '';
              }
          }"
          x-on:submit="if (!targetTypeCode) { $event.preventDefault(); selectionError = true; $nextTick(() => document.getElementById('setting-choice').scrollIntoView({ behavior: 'smooth', block: 'center' })) } else { loading = true }">
        @csrf
        {{-- Hidden fields carry the values the two paths resolve to. --}}
        <input type="hidden" name="target_type_code" :value="targetTypeCode">
        <input type="hidden" name="facility_profile_id" :value="profileId">

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
                            class="mt-1 block w-full border border-slate-300 dark:border-slate-600 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 focus:border-vytte-500 transition"
                            placeholder="Any notes about the scope or purpose of this project…"
                        >{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>
                </div>
            </div>

            {{-- ===== SECTION 2 — Target details ===== --}}
            <div id="setting-choice"
                 class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">What are you assessing?</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Select one of the two options below, then enter its details.</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-vytte-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-vytte-700 ring-1 ring-vytte-200 dark:bg-vytte-900/30 dark:text-vytte-300 dark:ring-vytte-800">Select one</span>
                </div>

                <div class="flex flex-col gap-4">
                    {{-- Two paths, mapped to the two tools. --}}
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button type="button" @click="mode = 'FACILITY'; programmeChoice = ''; selectionError = false"
                                x-bind:aria-pressed="mode === 'FACILITY'"
                                class="group -rotate-[0.4deg] cursor-pointer rounded-xl border border-vytte-200 bg-vytte-50 p-4 text-left shadow-sm transition duration-200 hover:-translate-y-0.5 hover:rotate-0 hover:border-vytte-500 hover:shadow-md dark:border-vytte-800 dark:bg-vytte-900/25"
                                :class="mode === 'FACILITY' ? 'border-vytte-600 ring-2 ring-vytte-300 dark:border-vytte-500 dark:ring-vytte-800' : ''">
                            <span class="flex items-center justify-between gap-3">
                                <span class="text-sm font-bold text-slate-900 dark:text-white">A health facility</span>
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold"
                                      :class="mode === 'FACILITY' ? 'border-vytte-700 bg-vytte-700 text-white' : 'border-vytte-400 text-transparent group-hover:border-vytte-600 dark:border-vytte-700'">✓</span>
                            </span>
                            <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">A hospital, clinic or health centre. Runs a full facility diagnostic.</span>
                            <span class="mt-3 block text-[11px] font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400" x-text="mode === 'FACILITY' ? 'Selected' : 'Click to select'"></span>
                        </button>
                        <button type="button" @click="mode = 'PROGRAMME'; facilityProfile = ''; selectionError = false"
                                x-bind:aria-pressed="mode === 'PROGRAMME'"
                                class="group rotate-[0.4deg] cursor-pointer rounded-xl border border-amber-200 bg-amber-50 p-4 text-left shadow-sm transition duration-200 hover:-translate-y-0.5 hover:rotate-0 hover:border-vytte-500 hover:shadow-md dark:border-amber-800 dark:bg-amber-900/20"
                                :class="mode === 'PROGRAMME' ? 'border-vytte-600 ring-2 ring-vytte-300 dark:border-vytte-500 dark:ring-vytte-800' : ''">
                            <span class="flex items-center justify-between gap-3">
                                <span class="text-sm font-bold text-slate-900 dark:text-white">A programme, community or subject</span>
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold"
                                      :class="mode === 'PROGRAMME' ? 'border-vytte-700 bg-vytte-700 text-white' : 'border-amber-400 text-transparent group-hover:border-vytte-600 dark:border-amber-700'">✓</span>
                            </span>
                            <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">An NGO, research study, community or campaign. Runs focused topic assessments.</span>
                            <span class="mt-3 block text-[11px] font-bold uppercase tracking-wide text-vytte-700 dark:text-vytte-400" x-text="mode === 'PROGRAMME' ? 'Selected' : 'Click to select'"></span>
                        </button>
                    </div>
                    <p x-show="selectionError" x-cloak class="-mt-1 text-sm font-semibold text-vytte-700 dark:text-vytte-300">Choose what you are assessing.</p>

                    {{-- Facility path: pick the kind of facility. --}}
                    <div x-show="mode === 'FACILITY'" x-cloak>
                        <x-input-label for="facility_choice" value="What kind of health facility?" />
                        <select id="facility_choice" x-model="facilityProfile"
                                class="mt-1 block w-full border border-slate-300 dark:border-slate-600 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 focus:border-vytte-500 transition bg-white dark:bg-slate-700">
                            <option value="">Select facility type…</option>
                            @foreach ($facilityProfiles as $profile)
                                <option value="{{ $profile->facility_profile_id }}">{{ $profile->profile_name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Vytte uses this to load the right governed catalogue.</p>
                        <x-input-error :messages="$errors->get('facility_profile_id')" class="mt-1" />
                    </div>

                    {{-- Programme path: pick the kind of programme or subject. --}}
                    <div x-show="mode === 'PROGRAMME'" x-cloak>
                        <x-input-label for="programme_choice" value="What kind of programme or subject?" />
                        <select id="programme_choice" x-model="programmeChoice"
                                class="mt-1 block w-full border border-slate-300 dark:border-slate-600 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 focus:border-vytte-500 transition bg-white dark:bg-slate-700">
                            <option value="">Select…</option>
                            @foreach ($programmeProfiles as $profile)
                                <option value="{{ $profile->facility_profile_id }}">{{ $profile->profile_name }}</option>
                            @endforeach
                            <option value="__custom__">Other — describe it</option>
                        </select>

                        <div x-show="programmeChoice === '__custom__'" x-cloak class="mt-3">
                            <x-input-label for="custom_setting_label" value="Describe the setting" />
                            <x-text-input id="custom_setting_label" name="custom_setting_label" type="text"
                                          class="mt-1 block w-full" :value="old('custom_setting_label')"
                                          placeholder="e.g. Agricultural cooperative health scheme"
                                          x-bind:required="programmeChoice === '__custom__'" />
                            <x-input-error :messages="$errors->get('custom_setting_label')" class="mt-1" />
                            <label class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <input type="checkbox" name="uses_departments" value="1" class="rounded border-slate-300 text-vytte-600 focus:ring-vytte-500" {{ old('uses_departments') ? 'checked' : '' }}>
                                This setting genuinely uses departments
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Focused topic assessments (malaria, TB, WASH, and more) are available for any programme or subject.</p>
                    </div>

                    {{-- Target name --}}
                    <div>
                        <label for="target_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                               x-text="mode === 'FACILITY' ? 'Health facility name' : (mode === 'PROGRAMME' ? 'Programme or subject name' : 'Name')">
                            Name
                        </label>
                        <x-text-input
                            id="target_name"
                            name="target_name"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('target_name')"
                            x-bind:placeholder="mode === 'FACILITY' ? 'e.g. Ikeja Health Centre' : (mode === 'PROGRAMME' ? 'e.g. Keffi Malaria Programme' : 'Enter the official name')"
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
                                    class="mt-1 block w-full border border-slate-300 dark:border-slate-600 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-vytte-500/20 focus:border-vytte-500 transition bg-white dark:bg-slate-700"
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
