<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ProgrammeOverviewService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * One portfolio report for a whole programme, read across every workspace. Platform admin only.
 * It reads the same frozen site reports each place already has, so nothing is recalculated or changed.
 */
class ProgrammePortfolioController extends Controller
{
    public function pdf(string $programme, ProgrammeOverviewService $overview): Response
    {
        $portfolio = $overview->forAllWorkspaces($programme, self::leaveOut(...));

        abort_if($portfolio === null, 404);

        return Pdf::loadView('exports.programme-portfolio', ['portfolio' => $portfolio])
            ->setPaper('a4', 'portrait')
            ->download(Str::slug($portfolio['programme']).'-portfolio.pdf');
    }

    /** A body that oversees facilities (a board, ministry or secretariat) is not itself a school or PHC. */
    private static function leaveOut(string $name): ?string
    {
        return preg_match('/\b(board|ministry|secretariat|agency)\b/i', $name)
            ? 'A body that oversees schools or PHCs, not a school or PHC itself, so it is not ranked with them.'
            : null;
    }
}
