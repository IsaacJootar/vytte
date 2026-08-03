<?php

namespace App\Services;

use App\Models\ContentPublisher;
use Illuminate\Support\Facades\DB;

class ContentPublisherService
{
    public function vytte(): ContentPublisher
    {
        return ContentPublisher::firstOrCreate(
            ['publisher_code' => 'VYTTE'],
            [
                'name' => 'Vytte',
                'publisher_type' => ContentPublisher::TYPE_VYTTE,
                'visibility' => ContentPublisher::VISIBILITY_PUBLIC,
                'verification_status' => ContentPublisher::STATUS_VERIFIED,
                'attribution' => 'Published through the Vytte governed assessment platform.',
                'verified_at' => now(),
            ],
        );
    }

    public function assignLegacyContent(): ContentPublisher
    {
        $publisher = $this->vytte();

        foreach (['questions', 'department_framework_versions', 'assessment_catalogue_releases'] as $table) {
            DB::table($table)->whereNull('content_publisher_id')->update([
                'content_publisher_id' => $publisher->content_publisher_id,
                'distribution_level' => ContentPublisher::VISIBILITY_PUBLIC,
            ]);
        }

        return $publisher;
    }
}
