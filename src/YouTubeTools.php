<?php

namespace RoamingWilson\YouTubeTools;

use RuntimeException;
use InvalidArgumentException;

class YouTubeTools
{
    protected string $userAgent;
    protected string $tempDir;
    protected ?string $cookiesFile;

    public function __construct(?string $tempDir = null, ?string $cookiesFile = null)
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

        $downloadCmd = sprintf('yt-dlp %s --user-agent=%s -f "bv*+ba/b" -o %s --merge-output-format mp4 %s 2>&1', $this->cookiesFile ? '--cookies ' . escapeshellarg($this->cookiesFile) : '', escapeshellarg($this->userAgent), escapeshellarg($outputTemplate), escapeshellarg($url));

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
            $start = $clip['start'] ?? null;
            $to = $clip['to'] ?? null;

            if (!$start || !$to) {
                continue;
            }

            $timeArgs = "-ss $start -to $to";
            $crop = '';

            if (isset($clip['crop_x'], $clip['crop_y'], $clip['crop_width'], $clip['crop_height'])) {
                $crop = sprintf('-vf "crop=%d:%d:%d:%d"', $clip['crop_width'], $clip['crop_height'], $clip['crop_x'], $clip['crop_y']);
            }

            $randomName = 'clip_' . mt_rand(100000, 999999) . '.mp4';
            $outputPath = sprintf('%s/%s', $outputDir, $randomName);
            $ffmpegCmd = sprintf('ffmpeg %s -i %s %s -c:a copy -y %s 2>&1', $timeArgs, escapeshellarg($videoPath), $crop, escapeshellarg($outputPath));

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

    public function downloadOnly(string $url, string $outputDir): array
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $randomName = 'clip_' . mt_rand(100000, 999999);
        $outputTemplate = "{$outputDir}/{$randomName}.%(ext)s";

        $downloadCmd = sprintf('yt-dlp %s --user-agent=%s -f "bv*+ba/b" -o %s --merge-output-format mp4 %s 2>&1', $this->cookiesFile ? '--cookies ' . escapeshellarg($this->cookiesFile) : '', escapeshellarg($this->userAgent), escapeshellarg($outputTemplate), escapeshellarg($url));

        exec($downloadCmd, $ytOutput, $ytStatus);

        if ($ytStatus !== 0) {
            return [
                'status' => false,
                'error' => 'Download failed',
                'yt-dlp-output' => $ytOutput,
            ];
        }

        // Find the mp4 file in outputDir
        $downloadedFile = null;
        foreach (glob("{$outputDir}/{$randomName}.*") as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'mp4') {
                $downloadedFile = $file;
                break;
            }
        }

        if ($downloadedFile && file_exists($downloadedFile)) {
            return [
                'status' => true,
                'path' => realpath($downloadedFile),
            ];
        }

        return [
            'status' => false,
            'error' => 'Downloaded file not found',
        ];
    }

    public function cutOnly($videoPath, $clip, $outputDir)
    {
        $start = $clip['start'];
        $end = $clip['end'];

        $timeArgs = "-ss $start -to $end";
        $crop = '';

        if (isset($clip['crop_x'], $clip['crop_y'], $clip['crop_width'], $clip['crop_height'])) {
            $crop = sprintf('-vf "crop=%d:%d:%d:%d"', $clip['crop_width'], $clip['crop_height'], $clip['crop_x'], $clip['crop_y']);
        }

        $outputPath = sprintf('%s/clip_%s.mp4', $outputDir, uniqid());
        $ffmpegCmd = sprintf('ffmpeg %s -i %s %s -c:a copy -y %s 2>&1', $timeArgs, escapeshellarg($videoPath), $crop, escapeshellarg($outputPath));

        exec($ffmpegCmd, $ffmpegOutput, $ffmpegStatus);

        if ($ffmpegStatus === 0 && file_exists($outputPath)) {
            $path = realpath($outputPath);
        } else {
            print_r($ffmpegOutput);
        }

        return [
            'status' => true,
            'path' => $path,
        ];
    }
}
