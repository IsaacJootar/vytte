<x-admin-layout title="Facility Profile">
    <div class="mb-5">
        <a href="{{ route('admin.facility-profiles.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Facility profiles</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $profile->profile_name }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $profile->profile_code }} · {{ $profile->settingType?->setting_type_name ?? $profile->setting_type_code }} · {{ $profile->status }}</p>
    </div>

    <form method="POST" action="{{ route('admin.facility-profiles.update', $profile) }}" class="section-card p-5 dark:border-slate-700 dark:bg-slate-800">
        @csrf
        @method('PUT')
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Profile settings</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <input name="profile_name" value="{{ $profile->profile_name }}" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
            <select name="status" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <option value="DRAFT" @selected($profile->status === 'DRAFT')>Draft</option>
                <option value="PUBLISHED" @selected($profile->status === 'PUBLISHED')>Published</option>
            </select>
            <input name="display_order" type="number" value="{{ $profile->display_order }}" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
        </div>
        <textarea name="description" rows="2" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">{{ $profile->description }}</textarea>

        <h2 class="mt-6 text-sm font-bold text-slate-900 dark:text-white">Departments and services</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @foreach ($modules as $module)
                @php $current = $profile->departments->firstWhere('module_id', $module->module_id); @endphp
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                    <input type="hidden" name="departments[{{ $loop->index }}][module_id]" value="{{ $module->module_id }}">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $module->module_name }}</p>
                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <select name="departments[{{ $loop->index }}][applicability]" class="rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            @foreach (['UNAVAILABLE', 'OPTIONAL', 'DEFAULT', 'REQUIRED'] as $applicability)
                                <option value="{{ $applicability }}" @selected(($current?->pivot->applicability ?? 'UNAVAILABLE') === $applicability)>{{ $applicability }}</option>
                            @endforeach
                        </select>
                        <input name="departments[{{ $loop->index }}][display_order]" type="number" value="{{ $current?->pivot->display_order ?? $loop->index + 1 }}" class="rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                        <label class="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="departments[{{ $loop->index }}][removal_allowed]" value="1" @checked($current?->pivot->removal_allowed ?? true)> Removable</label>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-5 text-right"><button class="rounded-xl bg-vytte-700 px-5 py-2 text-sm font-bold text-white">Save profile</button></div>
    </form>
</x-admin-layout>
