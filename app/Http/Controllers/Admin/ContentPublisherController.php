<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPublisher;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentPublisherController extends Controller
{
    public function index(Request $request): View
    {
        $query = ContentPublisher::query()->withCount('governanceClaims')->orderBy('name');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->lower().'%';
            $query->whereRaw('LOWER(name) LIKE ?', [$search]);
        }

        return view('admin.publishers.index', [
            'publishers' => $query->paginate(20)->withQueryString(),
            'types' => $this->types(),
            'visibilities' => $this->visibilities(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $validated = $this->validated($request);
        $publisher = ContentPublisher::create([
            ...$validated,
            'publisher_code' => strtoupper($validated['publisher_code']),
            'verification_status' => ContentPublisher::STATUS_UNVERIFIED,
        ]);

        $audit->record('content.publisher.created', $publisher, newValues: $publisher->only([
            'publisher_code', 'name', 'publisher_type', 'visibility',
        ]));

        return back()->with('success', 'Publisher created. Its identity must be verified before Vytte presents it as verified.');
    }

    public function update(Request $request, ContentPublisher $publisher, AuditService $audit): RedirectResponse
    {
        $validated = $this->validated($request, $publisher);
        $old = $publisher->only(['publisher_code', 'name', 'publisher_type', 'visibility', 'attribution', 'website_url', 'contact_email']);
        $publisher->update([...$validated, 'publisher_code' => strtoupper($validated['publisher_code'])]);
        $audit->record('content.publisher.updated', $publisher->fresh(), $old, $validated);

        return back()->with('success', 'Publisher details saved.');
    }

    public function verify(ContentPublisher $publisher, AuditService $audit): RedirectResponse
    {
        $old = $publisher->only(['verification_status', 'verified_by', 'verified_at']);
        $publisher->update([
            'verification_status' => ContentPublisher::STATUS_VERIFIED,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);
        $audit->record('content.publisher.verified', $publisher->fresh(), $old, [
            'verification_status' => ContentPublisher::STATUS_VERIFIED,
            'verified_by' => auth()->id(),
        ]);

        return back()->with('success', 'Publisher identity marked as verified. Content reviews are recorded separately.');
    }

    public function suspend(ContentPublisher $publisher, AuditService $audit): RedirectResponse
    {
        if ($publisher->publisher_type === ContentPublisher::TYPE_VYTTE) {
            return back()->withErrors(['publisher' => 'The core Vytte publisher cannot be suspended.']);
        }

        $old = $publisher->only(['verification_status']);
        $publisher->update([
            'verification_status' => ContentPublisher::STATUS_SUSPENDED,
            'verified_by' => null,
            'verified_at' => null,
        ]);
        $audit->record('content.publisher.suspended', $publisher->fresh(), $old, [
            'verification_status' => ContentPublisher::STATUS_SUSPENDED,
        ]);

        return back()->with('success', 'Publisher suspended. Historical attribution remains visible.');
    }

    /** @return array<string, string> */
    private function validated(Request $request, ?ContentPublisher $publisher = null): array
    {
        return $request->validate([
            'publisher_code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('content_publishers', 'publisher_code')->ignore($publisher?->content_publisher_id, 'content_publisher_id')],
            'name' => ['required', 'string', 'max:180'],
            'publisher_type' => ['required', Rule::in(array_keys($this->types()))],
            'visibility' => ['required', Rule::in(array_keys($this->visibilities()))],
            'attribution' => ['nullable', 'string', 'max:2000'],
            'website_url' => ['nullable', 'url', 'max:2000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);
    }

    /** @return array<string, string> */
    private function types(): array
    {
        return [
            ContentPublisher::TYPE_VYTTE => 'Vytte',
            'STANDARDS_BODY' => 'Standards or regulatory body',
            'GOVERNMENT' => 'Government',
            'HEALTH_SYSTEM' => 'Hospital or health system',
            'NGO_PROGRAMME' => 'NGO or programme',
            'PROFESSIONAL_BODY' => 'Professional association',
            'RESEARCH_INSTITUTION' => 'Research institution',
            'EXPERT' => 'Independent expert',
            'WORKSPACE' => 'Customer organization',
        ];
    }

    /** @return array<string, string> */
    private function visibilities(): array
    {
        return [
            ContentPublisher::VISIBILITY_PRIVATE => 'Private',
            ContentPublisher::VISIBILITY_ORGANIZATION => 'Organization',
            ContentPublisher::VISIBILITY_PARTNER => 'Selected partners',
            ContentPublisher::VISIBILITY_PUBLIC => 'Public',
        ];
    }
}
