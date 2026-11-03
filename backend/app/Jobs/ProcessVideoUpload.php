<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Reel;
use App\Models\Video;
use App\Services\Media\VideoProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Transcodes an uploaded video into the delivery ladder and generates its
 * thumbnails.
 *
 * Runs on the `media` queue with a generous timeout: transcoding a 90 second
 * clip at 720p takes tens of seconds, and a dead worker must not block chat.
 */
final class ProcessVideoUpload implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 2;

    public function __construct(public readonly Reel|Video $media)
    {
        $this->onQueue('media');
    }

    public function handle(VideoProcessingService $video): void
    {
        $model = $this->media;

        $temp = $video->toTempFile($model->path);
        $outputDir = sys_get_temp_dir().'/'.pathinfo($model->path, PATHINFO_FILENAME);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            $probe = $video->probe($temp);

            $ladder = $video->transcode($temp, $outputDir, (int) ($probe['height'] ?: 720));

            $disk = Storage::disk(config('filesystems.default'));
            $base = pathinfo($model->path, PATHINFO_DIRNAME);

            foreach ($ladder as $label => $rendition) {
                $target = sprintf('%s/%s.mp4', $base, $label);

                $disk->put($target, file_get_contents($rendition['path']), ['visibility' => 'public']);

                if ($model instanceof Video) {
                    $model->qualities()->updateOrCreate(
                        ['label' => $label],
                        [
                            'height' => $rendition['height'],
                            'bitrate_kbps' => $rendition['bitrate_kbps'],
                            'path' => $target,
                            'size' => $rendition['size'],
                        ]
                    );
                }
            }

            $thumbs = $video->thumbnails($temp, $outputDir, (int) $probe['duration']);

            $posterTarget = sprintf('%s/poster.jpg', $base);
            $disk->put($posterTarget, file_get_contents($thumbs['thumbnail']), ['visibility' => 'public']);

            $model->forceFill([
                'duration' => $probe['duration'],
                'width' => $probe['width'],
                'height' => $probe['height'],
                'thumbnail_path' => $posterTarget,
                'status' => 'ready',
            ])->save();

            if ($model instanceof Video) {
                foreach ($thumbs['thumbnails'] as $index => $frame) {
                    $frameTarget = sprintf('%s/frame-%d.jpg', $base, $index);
                    $disk->put($frameTarget, file_get_contents($frame), ['visibility' => 'public']);

                    $model->thumbnails()->create([
                        'path' => $frameTarget,
                        'time_offset' => (int) ($probe['duration'] * (($index + 1) / 4)),
                        'is_default' => false,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $model->forceFill([
                'status' => 'failed',
                'processing_error' => $e->getMessage(),
            ])->save();

            Log::error('Video processing failed', [
                'media' => $model->getMorphClass(),
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            @unlink($temp);
        }
    }
}
