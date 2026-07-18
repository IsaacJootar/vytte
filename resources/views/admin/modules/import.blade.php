<x-admin-layout title="Import Module">

    <div class="mb-5">
        <a href="{{ route('admin.modules.index') }}"
           class="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 mb-2 transition-colors">
            ← Modules
        </a>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Import Assessment Module</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Upload a JSON file to add a new module with question groups and questions.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Upload form --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
            <form method="POST" action="{{ route('admin.modules.import.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">JSON File</label>
                    <input type="file" name="json_file" accept=".json,.txt" required
                           class="block w-full text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-vytte-50 file:text-vytte-700 hover:file:bg-vytte-100 dark:file:bg-vytte-900/30 dark:file:text-vytte-400 transition-colors">
                    @error('json_file')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold bg-vytte-700 text-white rounded-lg hover:bg-vytte-800 transition-colors">
                    Import
                </button>
            </form>
        </div>

        {{-- JSON format guide --}}
        <div class="bg-slate-50 dark:bg-slate-700/50 rounded-2xl border border-slate-200 dark:border-slate-700 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Expected JSON format</h2>
            <pre class="text-[11px] text-slate-600 dark:text-slate-300 overflow-x-auto leading-relaxed"><code>{
  "module_code": "NEWMOD",
  "module_name": "Module Name",
  "target_type_code": "HEALTH_FACILITY",
  "primary_respondent": "Facility Head",
  "estimated_duration_minutes": 45,
  "question_groups": [
    {
      "domain_number": 1,
      "domain_label": "Question Group Label",
      "questions": [
        {
          "question_code": "Q001",
          "question_text": "Question text here?",
          "response_type": "SINGLE_SELECT",
          "options": [
            {
              "option_label": "Yes",
              "score_weight": 100
            },
            {
              "option_label": "No",
              "score_weight": 0
            }
          ]
        }
      ]
    }
  ]
}</code></pre>
            <div class="mt-3 space-y-1">
                <p class="text-xs text-slate-500 dark:text-slate-400"><strong>target_type_code</strong> must match an existing type: HEALTH_FACILITY, SCHOOL, COMMUNITY, WATER_POINT</p>
                <p class="text-xs text-slate-500 dark:text-slate-400"><strong>question_code</strong> must be globally unique.</p>
                <p class="text-xs text-slate-500 dark:text-slate-400"><strong>response_type</strong> may be SINGLE_SELECT, LIKERT, OPEN_ENDED, or NUMERIC. Scored numeric questions require numeric_bands.</p>
                <p class="text-xs text-slate-500 dark:text-slate-400"><strong>score_weight</strong> is 0–100. Leave blank for unscored options.</p>
            </div>
        </div>

    </div>

</x-admin-layout>
