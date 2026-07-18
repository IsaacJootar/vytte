<x-admin-layout title="Facility Profile">
    <div class="mb-5">
        <a href="{{ route('admin.facility-profiles.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Facility profiles</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $profile->profile_name }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $profile->profile_code }} · {{ $profile->settingType?->setting_type_name ?? $profile->setting_type_code }} · {{ $profile->status }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Departments and services</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $profile->description }}</p>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @foreach ($profile->departments as $department)
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $department->module_name }}</p>
                        <span class="text-xs font-bold text-slate-500">{{ $department->pivot->applicability }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Removal allowed: {{ $department->pivot->removal_allowed ? 'Yes' : 'No' }} · Framework versions: {{ $department->frameworkVersions->count() }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-admin-layout>
