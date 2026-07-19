<x-admin-layout title="Scores">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Scores</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            A department needs at least one score before its questions can affect a result.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
            <ul class="list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($departmentsWithoutScore->isNotEmpty())
        <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950">
            <p class="text-sm font-bold text-amber-900 dark:text-amber-100">
                {{ $departmentsWithoutScore->count() }} {{ Str::plural('department', $departmentsWithoutScore->count()) }} cannot score questions yet
            </p>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                {{ $departmentsWithoutScore->pluck('module_name')->take(5)->join(', ') }}{{ $departmentsWithoutScore->count() > 5 ? ' and others' : '' }}.
                Add a score below to make their questions scorable.
            </p>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-admin-table
                title="All scores"
                description="Each score belongs to one department and measures one area."
                search-placeholder="Search scores by name"
                :headings="['Score', 'Department', 'Measures', 'Questions']"
                :paginator="$scores"
                empty="No scores yet"
                empty-hint="Create the first score using the form beside this table.">

                <x-slot:filters>
                    <select name="department" class="rounded-xl border-slate-300 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->module_id }}" @selected((int) request('department') === (int) $department->module_id)>{{ $department->module_name }}</option>
                        @endforeach
                    </select>
                </x-slot:filters>

                @foreach ($scores as $score)
                    @php $used = (int) ($usage[$score->sub_index_id] ?? 0); @endphp
                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-900 dark:text-white">{{ $score->full_name }}</p>
                            @if ($score->description)
                                <p class="mt-0.5 max-w-sm text-xs text-slate-500 dark:text-slate-400">{{ $score->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $score->module?->module_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $score->domain?->domain_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($used > 0)
                                <span class="inline-block rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">{{ $used }} in use</span>
                            @else
                                <span class="inline-block rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">Not used</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($used === 0)
                                <form method="POST" action="{{ route('admin.scores.destroy', $score) }}"
                                      onsubmit="return confirm('Remove the score “{{ $score->full_name }}”?')">
                                    @csrf @method('DELETE')
                                    <button class="text-sm font-semibold text-slate-500 hover:text-red-600 hover:underline dark:text-slate-400">Remove</button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">In use</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-admin-table>
        </div>

        <form method="POST" action="{{ route('admin.scores.store') }}"
              class="h-fit space-y-5 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
            @csrf
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Add a score</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Questions in the chosen department can then contribute to it.</p>
            </div>

            <div>
                <label for="module_id" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Department</label>
                <select id="module_id" name="module_id" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">Choose a department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->module_id }}" @selected((int) old('module_id') === (int) $department->module_id)>{{ $department->module_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="full_name" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Score name</label>
                <p class="text-xs text-slate-500 dark:text-slate-400">What this score tells the reader, for example "Outpatient Readiness".</p>
                <input id="full_name" name="full_name" value="{{ old('full_name') }}" required maxlength="120"
                       class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </div>

            <div>
                <label for="domain_id" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">What does it measure?</label>
                <p class="text-xs text-slate-500 dark:text-slate-400">Used to group results across departments in reports.</p>
                <select id="domain_id" name="domain_id" required class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">Choose an area</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->domain_id }}" @selected((int) old('domain_id') === (int) $area->domain_id)>{{ $area->domain_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="description" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Description <span class="font-normal text-slate-400">(optional)</span></label>
                <textarea id="description" name="description" rows="2" maxlength="500"
                          class="mt-1.5 w-full rounded-xl border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('description') }}</textarea>
            </div>

            <button class="w-full rounded-xl bg-vytte-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-vytte-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-vytte-500">
                Add score
            </button>
        </form>
    </div>
</x-admin-layout>
