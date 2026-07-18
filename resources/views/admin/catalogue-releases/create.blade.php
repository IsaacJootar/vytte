<x-admin-layout title="New Catalogue Release">
    <div class="mb-5">
        <a href="{{ route('admin.catalogue-releases.index') }}" class="text-xs font-semibold text-vytte-700 dark:text-vytte-300">← Catalogue releases</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Create catalogue release</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Create a draft entry point, then pin published framework versions into it.</p>
    </div>
    <form method="POST" action="{{ route('admin.catalogue-releases.store') }}" class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <input name="release_code" placeholder="Release code" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
            <input name="release_name" placeholder="Release name" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
        </div>
        <textarea name="description" rows="2" placeholder="Description" class="mt-4 w-full rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <select name="creation_path" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" required>
                <option value="COMPREHENSIVE">Comprehensive</option>
                <option value="FOCUSED">Focused</option>
            </select>
            <select name="facility_profile_id" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <option value="">Facility profile</option>
                @foreach ($facilityProfiles as $profile)
                    <option value="{{ $profile->facility_profile_id }}">{{ $profile->profile_name }}</option>
                @endforeach
            </select>
            <select name="health_domain_id" class="rounded-xl border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <option value="">Focused domain</option>
                @foreach ($healthDomains as $domain)
                    <option value="{{ $domain->health_domain_id }}">{{ $domain->domain_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mt-5 text-right"><button class="rounded-xl bg-vytte-700 px-5 py-2 text-sm font-bold text-white">Create draft</button></div>
    </form>
</x-admin-layout>
