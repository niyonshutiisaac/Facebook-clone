<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PostLink;
use App\Services\Media\LinkPreviewService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class GenerateLinkPreview implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  list<int>  $backoff
     */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly PostLink $link)
    {
        $this->onQueue('default');
    }

    public function handle(LinkPreviewService $preview): void
    {
        $preview->persist($this->link);
    }

    /**
     * A dead or unreachable host is not an application error: swallow it after
     * the retries so the post still renders (without a preview).
     */
    public function failed(?\Throwable $exception): void
    {
        report($exception);
    }
}
