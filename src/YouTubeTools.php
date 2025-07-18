<?php

namespace Andrew\YouTubeClipper;

use RuntimeException;
use InvalidArgumentException;

class YouTubeClipper
{
    protected string $userAgent;
    protected string $tempDir;
    protected ?string $cookiesFile;

    public function __construct(string $tempDir = null, string $cookiesFile = null)
    {
        $this->userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/114 Safari/537.36';
        $this->tempDir = $tempDir ?? sys_get_temp_dir() . '/ytclips';
        $this->cookiesFile = $cookiesFile;

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    public function downloadAndCut(string $url, array $clipsJson, string $outputDir): array
    {
        $randomName = 'source_' . mt_rand(100000, 999999);
        $outputTemplate = "{$this->tempDir}/{$randomName}.%(ext)s";

        $downloadCmd = sprintf(
            'yt-dlp %s --user-agent=%s -f "bv*+ba/b" -o %s --merge-output-format mp4 %s 2>&1',
            $this->cookiesFile ? '--cookies ' . escapeshellarg($this->cookiesFile) : '',
            escapeshellarg($this->userAgent),
            escapeshellarg($outputTemplate),
            escapeshellarg($url)
        );

        exec($downloadCmd, $ytOutput, $ytStatus);
        if ($ytStatus !== 0) {
            return [
                'status' => false,
                'error' => 'Download failed',
                'yt-dlp-output' => $ytOutput,
            ];
        }

        $videoPath = "{$this->tempDir}/{$randomName}.mp4";
        if (!file_exists($videoPath)) {
            return ['status' => false, 'error' => 'Downloaded file not found'];
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $outputClips = [];
        foreach ($clipsJson as $index => $clip) {
            $from = $clip['from'] ?? null;
            $to = $clip['to'] ?? null;

            if (!$from || !$to) continue;

            $timeArgs = "-ss $from -to $to";
            $crop = '';

            if (isset($clip['crop_x'], $clip['crop_y'], $clip['crop_width'], $clip['crop_height'])) {
                $crop = sprintf(
                    '-vf "crop=%d:%d:%d:%d"',
                    $clip['crop_width'],
                    $clip['crop_height'],
                    $clip['crop_x'],
                    $clip['crop_y']
                );
            }

            $outputPath = sprintf('%s/clip_%02d.mp4', $outputDir, $index + 1);
            $ffmpegCmd = sprintf(
                'ffmpeg %s -i %s %s -c:a copy -y %s 2>&1',
                $timeArgs,
                escapeshellarg($videoPath),
                $crop,
                escapeshellarg($outputPath)
            );

            exec($ffmpegCmd, $ffmpegOutput, $ffmpegStatus);

            if ($ffmpegStatus === 0 && file_exists($outputPath)) {
                $outputClips[] = [
                    'clip_index' => $index + 1,
                    'path' => realpath($outputPath),
                ];
            }
        }

        return [
            'status' => true,
            'clips' => $outputClips,
        ];
    }
}
