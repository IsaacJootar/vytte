<x-admin-layout title="Facility Profiles">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Facility Profiles</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Official setting profiles that define valid departments/services for comprehensive assessments.</p>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
        <select name="status" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All status</option>
            <option value="DRAFT" @selected(request('status') === 'DRAFT')>Draft</option>
            <option value="PUBLISHED" @selected(request('status') === 'PUBLISHED')>Published</option>
        </select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">Filter</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr><th class="px-4 py-3">Profile</th><th class="px-4 py-3">Setting</th><th class="px-4 py-3">Departments</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($profiles as $profile)
                <tr>
                    <td class="px-4 py-3"><p class="font-semibold text-slate-900 dark:text-white">{{ $profile->profile_name }}</p><p class="text-xs text-slate-500">{{ $profile->profile_code }}</p></td>
                    <td class="px-4 py-3 text-slate-500">{{ $profile->settingType?->setting_type_name ?? $profile->setting_type_code }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $profile->departments_count }}</td>
                    <td class="px-4 py-3 text-xs font-bold text-slate-500">{{ $profile->status }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.facility-profiles.show', $profile) }}" class="text-sm font-semibold text-vytte-700 dark:text-vytte-300">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No facility profiles found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $profiles->links() }}</div>
</x-admin-layout>
