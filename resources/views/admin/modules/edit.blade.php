<x-admin-layout :title="'Edit ' . $module->module_name">

    <div class="mb-5">
        <a href="{{ route('admin.modules.show', $module) }}"
           class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-2 transition-colors">
            ← {{ $module->module_name }}
        </a>
        <h1 class="text-xl font-bold text-slate-900">Edit Module</h1>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-5 max-w-xl">
        <form method="POST" action="{{ route('admin.modules.update', $module) }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Module Name</label>
                    <input type="text" name="module_name" value="{{ old('module_name', $module->module_name) }}" required
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-500">
                    @error('module_name')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Primary Respondent</label>
                    <input type="text" name="primary_respondent" value="{{ old('primary_respondent', $module->primary_respondent) }}"
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-500"
                           placeholder="e.g. Facility Manager">
                    @error('primary_respondent')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Estimated Duration (minutes)</label>
                    <input type="number" name="estimated_duration_minutes" min="1" max="480"
                           value="{{ old('estimated_duration_minutes', $module->estimated_duration_minutes) }}"
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-500"
                           placeholder="e.g. 45">
                    @error('estimated_duration_minutes')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Data Collection Methods</label>
                    <input type="text" name="data_collection_methods"
                           value="{{ old('data_collection_methods', $module->data_collection_methods) }}"
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-vytte-500"
                           placeholder="e.g. Interview, Observation">
                    @error('data_collection_methods')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-slate-100 flex items-center gap-3">
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold bg-vytte-700 text-white rounded-lg hover:bg-vytte-800 transition-colors">
                    Save changes
                </button>
                <a href="{{ route('admin.modules.show', $module) }}"
                   class="px-4 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</x-admin-layout>
