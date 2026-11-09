<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\Reporting\ProgrammeOverviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The portfolio reports for a whole programme, read across workspaces: one for the PHCs and one for the
 * schools. Platform admin only. It reads the same frozen site reports each place already has, so nothing is
 * recalculated or changed.
 *
 * Which workspaces count is a saved choice (platform setting `portfolio.workspaces.<programme>`), so that
 * tests and trials run in other workspaces are never mixed into the portfolio by accident.
 */
class ProgrammePortfolioController extends Controller
{
    /** The kinds of place a portfolio is made for, as the programme names them. */
    private const TYPES = ['phc' => 'PHC', 'school' => 'school'];

    /** The programmes with finished reports, as a list to open. */
    public function index(ProgrammeOverviewService $overview): View
    {
        return view('admin.programmes.index', ['programmes' => $overview->programmesAcrossWorkspaces()]);
    }

    /** One programme's finished reports, to choose from before downloading the portfolios. */
    public function show(string $programme, ProgrammeOverviewService $overview): View
    {
        $found = collect($overview->programmesAcrossWorkspaces())->firstWhere('slug', $programme);
        abort_if($found === null, 404);

        $saved = self::savedWorkspaces($programme);

        return view('admin.programmes.show', [
            'programme' => $found,
            'reports' => $overview->finishedReportsAcrossWorkspaces($programme),
            // Ticked by default: the saved field-team accounts, or everything when none has been saved.
            'ticked' => $saved,
        ]);
    }

    /**
     * `?type=phc` or `?type=school` picks the portfolio. `?workspaces[]=<id>` limits it to those workspaces;
     * without it the saved choice is used, and without that, every workspace.
     */
    public function pdf(string $programme, Request $request, ProgrammeOverviewService $overview): Response
    {
        $type = self::TYPES[strtolower((string) $request->query('type'))] ?? null;
        $only = array_values(array_filter((array) $request->query('workspaces', [])));
        $only = $only !== [] ? $only : self::savedWorkspaces($programme);

        $portfolio = $overview->forAllWorkspaces($programme, self::leaveOut(...), $only, $type);

        abort_if($portfolio === null, 404);

        $suffix = $type === null ? 'portfolio' : 'portfolio-'.Str::slug($type === 'school' ? 'schools' : 'phcs');

        return Pdf::loadView('exports.programme-portfolio', ['portfolio' => $portfolio])
            ->setPaper('a4', 'portrait')
            ->download(Str::slug($portfolio['programme']).'-'.$suffix.'.pdf');
    }

    /** @return array<int, string>|null the saved workspace ids, or null when none are saved */
    private static function savedWorkspaces(string $programme): ?array
    {
        $saved = PlatformSetting::get('portfolio.workspaces.'.$programme);

        return is_array($saved) && $saved !== [] ? array_values($saved) : null;
    }

    /** A body that oversees facilities (a board, ministry or secretariat) is not itself a school or PHC. */
    private static function leaveOut(string $name): ?string
    {
        return preg_match('/\b(board|ministry|secretariat|agency)\b/i', $name)
            ? 'A body that oversees schools or PHCs, not a school or PHC itself, so it is not ranked with them.'
            : null;
    }
}
