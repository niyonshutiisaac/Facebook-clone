<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * FFmpeg wrapper: transcoding ladder, thumbnails and probe metadata.
 *
 * Long running, so it is only ever invoked from a queued job
 * (App\Jobs\ProcessVideoUpload) — never from the request lifecycle.
 */
final class VideoProcessingService
{
    /** Encoding ladder: label => [height, video bitrate kbps, audio bitrate kbps]. */
    private const array LADDER = [
        '360p' => [360, 800, 96],
        '480p' => [480, 1400, 128],
        '720p' => [720, 2800, 128],
        '1080p' => [1080, 5000, 192],
    ];

    /**
     * @return array{duration: int, width: int, height: int, bitrate: int}
     */
    public function probe(string $absolutePath): array
    {
        $command = [
            'ffprobe', '-v', 'quiet', '-print_format', 'json',
            '-show_format', '-show_streams', $absolutePath,
        ];

        $process = new Process($command);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        /** @var array{streams?: list<array<string, mixed>>, format?: array<string, mixed>} $data */
        $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR) ?: [];

        $video = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video') ?? [];

        return [
            'duration' => (int) round((float) ($data['format']['duration'] ?? 0)),
            'width' => (int) ($video['width'] ?? 0),
            'height' => (int) ($video['height'] ?? 0),
            'bitrate' => (int) round(((int) ($data['format']['bit_rate'] ?? 0)) / 1000),
        ];
    }

    /**
     * Transcode every rung of the ladder below the source resolution.
     *
     * @return array<string, array{path: string, height: int, bitrate_kbps: int, size: int}>
     */
    public function transcode(string $absolutePath, string $outputDirectory, int $sourceHeight): array
    {
        $outputs = [];

        foreach (self::LADDER as $label => [$height, $videoBitrate, $audioBitrate]) {
            if ($height > $sourceHeight && $outputs !== []) {
                continue; // never upscale
            }

            $target = sprintf('%s/%s.mp4', $outputDirectory, $label);

            $process = new Process([
                'ffmpeg', '-y',
                '-i', $absolutePath,
                '-vf', sprintf('scale=-2:%d', $height),
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
                '-b:v', $videoBitrate.'k', '-maxrate', ($videoBitrate * 1.5).'k',
                '-bufsize', ($videoBitrate * 2).'k',
                '-c:a', 'aac', '-b:a', $audioBitrate.'k',
                '-movflags', '+faststart',
                '-pix_fmt', 'yuv420p',
                $target,
            ]);

            $process->setTimeout(1800);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('FFmpeg transcode failed', [
                    'label' => $label,
                    'error' => $process->getErrorOutput(),
                ]);

                continue;
            }

            $outputs[$label] = [
                'path' => $target,
                'height' => $height,
                'bitrate_kbps' => $videoBitrate,
                'size' => file_exists($target) ? filesize($target) : 0,
            ];
        }

        if ($outputs === []) {
            throw new \RuntimeException('Video transcoding produced no playable rendition.');
        }

        return $outputs;
    }

    /**
     * Generate a poster frame 1s in (or at 10% of duration for short clips).
     *
     * @return array{thumbnail: string, thumbnails: list<string>}
     */
    public function thumbnails(string $absolutePath, string $outputDirectory, int $duration): array
    {
        $offset = max(1, (int) round($duration * 0.1));
        $poster = sprintf('%s/poster.jpg', $outputDirectory);

        $process = new Process([
            'ffmpeg', '-y',
            '-ss', (string) $offset,
            '-i', $absolutePath,
            '-frames:v', '1',
            '-q:v', '2',
            $poster,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // A sprite sheet of extra frames powers the scrub preview.
        $extra = [];

        foreach ([0.25, 0.5, 0.75] as $fraction) {
            $at = (int) round($duration * $fraction);
            $file = sprintf('%s/frame-%s.jpg', $outputDirectory, Str::padLeft((string) $at, 6, '0'));

            $frame = new Process([
                'ffmpeg', '-y', '-ss', (string) $at, '-i', $absolutePath,
                '-frames:v', '1', '-q:v', '4', $file,
            ]);

            $frame->setTimeout(120);
            $frame->run();

            if ($frame->isSuccessful()) {
                $extra[] = $file;
            }
        }

        return [
            'thumbnail' => $poster,
            'thumbnails' => $extra,
        ];
    }

    /**
     * Copy a file out of storage into a temp dir so FFmpeg can read it.
     */
    public function toTempFile(string $storedPath): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(config('filesystems.default'));

        $temp = sys_get_temp_dir().'/'.Str::uuid()->toString().'.'.pathinfo($storedPath, PATHINFO_EXTENSION);

        file_put_contents($temp, $disk->get($storedPath));

        return $temp;
    }
}
