<x-app-layout :title="$assessment->moduleScope->first()?->module?->module_name ?? 'Assessment'">

    @livewire('assessment-runner', ['assessment' => $assessment])

</x-app-layout>
