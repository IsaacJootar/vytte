<?php

namespace App\Http\Controllers;

use App\Services\Reporting\ProgrammeOverviewService;
use Illuminate\Contracts\View\View;

class ProgrammeController extends Controller
{
    public function show(string $programme, ProgrammeOverviewService $overview): View
    {
        $data = $overview->forProgramme(app('current.workspace'), $programme);

        abort_if($data === null, 404);

        return view('programmes.overview', $data);
    }
}
