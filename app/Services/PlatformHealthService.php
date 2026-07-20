<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Operational health, described the way an operator would say it out loud.
 *
 * Every check answers "is this fine, and if not what do I do?" — never "here is a
 * metric, work it out". A check that cannot be read reports that honestly rather than
 * claiming health it has not verified, because a monitoring page that invents a green
 * light is worse than no monitoring page.
 */
class PlatformHealthService
{
    /**
     * A job waiting longer than this means the queue is not keeping up.
     */
    private const QUEUE_BACKLOG_MINUTES = 5;

    /**
     * @return array<int, array{key: string, label: string, status: string, headline: string, detail: string, action: ?string}>
     */
    public function checks(): array
    {
        return [
            $this->database(),
            $this->queue(),
            $this->failedJobs(),
            $this->scheduler(),
            $this->storage(),
            $this->email(),
        ];
    }

    private function database(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $ms = (int) round((microtime(true) - $start) * 1000);

            return $this->check('database', 'Database', $ms < 250 ? 'ok' : 'warn',
                $ms < 250 ? 'Responding normally' : 'Responding slowly',
                "Answered a test query in {$ms}ms.",
                $ms < 250 ? null : 'If this stays high, check database load before customers notice.');
        } catch (Throwable $e) {
            return $this->check('database', 'Database', 'down', 'Not reachable',
                'Vytte could not reach the database.', 'This is urgent — the app cannot serve customers.');
        }
    }

    private function queue(): array
    {
        try {
            $waiting = DB::table('jobs')->count();

            $oldest = DB::table('jobs')->min('available_at');
            $waitingMinutes = $oldest ? (int) round((now()->timestamp - (int) $oldest) / 60) : 0;

            if ($waiting === 0) {
                return $this->check('queue', 'Background work', 'ok', 'Nothing waiting',
                    'All background work has been picked up.', null);
            }

            $isBacked = $waitingMinutes >= self::QUEUE_BACKLOG_MINUTES;

            return $this->check('queue', 'Background work', $isBacked ? 'warn' : 'ok',
                $waiting.' '.str('job')->plural($waiting).' waiting',
                $isBacked
                    ? 'The oldest has been waiting '.$waitingMinutes.' minutes, which usually means no worker is running.'
                    : 'Being worked through normally.',
                $isBacked ? 'Check that the queue worker is running.' : null);
        } catch (Throwable $e) {
            return $this->unreadable('queue', 'Background work');
        }
    }

    private function failedJobs(): array
    {
        try {
            $total = DB::table('failed_jobs')->count();
            $recent = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

            if ($total === 0) {
                return $this->check('failed_jobs', 'Failed background work', 'ok', 'None',
                    'No background work has failed.', null);
            }

            return $this->check('failed_jobs', 'Failed background work', $recent > 0 ? 'warn' : 'ok',
                $total.' failed in total',
                $recent > 0
                    ? $recent.' failed in the last 24 hours. Something is going wrong right now.'
                    : 'All of them are older than 24 hours, so whatever caused them may already be fixed.',
                $recent > 0 ? 'Look at the most recent failures below.' : null);
        } catch (Throwable $e) {
            return $this->unreadable('failed_jobs', 'Failed background work');
        }
    }

    private function scheduler(): array
    {
        try {
            $lastRun = DB::table('audit_logs')
                ->where('event', 'like', 'schedule.%')
                ->max('created_at');

            if (! $lastRun) {
                return $this->check('scheduler', 'Scheduled tasks', 'unknown', 'Never recorded a run',
                    'Vytte has no record of a scheduled task running. This is expected if none are set up yet.', null);
            }

            $ranAt = Carbon::parse($lastRun);
            $stale = $ranAt->lt(now()->subHours(25));

            return $this->check('scheduler', 'Scheduled tasks', $stale ? 'warn' : 'ok',
                'Last ran '.$ranAt->diffForHumans(),
                $stale ? 'Scheduled tasks appear to have stopped running.' : 'Running on time.',
                $stale ? 'Check that the scheduler is still running on the server.' : null);
        } catch (Throwable $e) {
            return $this->unreadable('scheduler', 'Scheduled tasks');
        }
    }

    private function storage(): array
    {
        try {
            $disk = Storage::disk('local');
            $bytes = collect($disk->allFiles())->sum(fn ($file) => $disk->size($file));

            return $this->check('storage', 'File storage', 'ok', $this->humanBytes($bytes),
                'Uploaded and generated files currently stored by Vytte.', null);
        } catch (Throwable $e) {
            return $this->unreadable('storage', 'File storage');
        }
    }

    private function email(): array
    {
        $mailer = config('mail.default');

        if ($mailer === 'log') {
            return $this->check('email', 'Email delivery', 'warn', 'Not actually sending',
                'Email is being written to the log file instead of delivered. Customers are not receiving anything.',
                'Set a real mail service before going live.');
        }

        if (in_array($mailer, ['array', 'null'], true)) {
            return $this->check('email', 'Email delivery', 'warn', 'Disabled',
                'Email sending is switched off in this environment.', null);
        }

        return $this->check('email', 'Email delivery', 'ok', 'Configured',
            'Email is being sent through '.$mailer.'.', null);
    }

    /**
     * @return array<int, object>
     */
    public function recentFailures(int $limit = 10): array
    {
        try {
            return DB::table('failed_jobs')->orderByDesc('failed_at')->limit($limit)->get()->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    private function unreadable(string $key, string $label): array
    {
        return $this->check($key, $label, 'unknown', 'Could not be checked',
            'Vytte could not read this. It is not necessarily broken — it just could not be confirmed.', null);
    }

    private function check(string $key, string $label, string $status, string $headline, string $detail, ?string $action): array
    {
        return compact('key', 'label', 'status', 'headline', 'detail', 'action');
    }

    private function humanBytes(int|float $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return $bytes.' B';
    }
}
