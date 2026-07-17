<?php

namespace App\Http\Controllers\Curation;

use App\Http\Controllers\Controller;
use App\Models\AssessmentTemplateVersion;
use App\Services\TemplatePublishingService;
use Illuminate\Http\RedirectResponse;

class TemplateVersionController extends Controller
{
    public function publish(
        AssessmentTemplateVersion $templateVersion,
        TemplatePublishingService $publisher,
    ): RedirectResponse {
        $publisher->publish($templateVersion, auth()->id());

        return back()->with('success', 'Template version published. Its content and scoring profile are now immutable.');
    }
}
