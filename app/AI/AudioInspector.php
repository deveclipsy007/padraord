<?php

namespace App\AI;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AudioInspector
{
    public function inspect(UploadedFile $file): array
    {
        return $this->inspectPath($file->getRealPath());
    }

    public function inspectPath(string $path): array
    {
        $info = (new \getID3)->analyze($path);
        $seconds = (float) ($info['playtime_seconds'] ?? 0);
        $format = $info['fileformat'] ?? '';
        if (! empty($info['error']) || ! isset($info['audio']) || isset($info['video']) || ! is_finite($seconds) || $seconds <= 0 || $seconds > config('ai.audio_max_seconds', 3600) || ! in_array($format, ['mp3', 'wav', 'quicktime'])) {
            throw ValidationException::withMessages(['audio' => 'Não foi possível confirmar um áudio compatível e sua duração. Use MP3, WAV ou M4A de até 60 minutos, ou cole a transcrição.']);
        }

        return ['seconds' => (int) ceil($seconds), 'mime' => match ($format) {
            'mp3' => 'audio/mpeg','wav' => 'audio/wav',default => 'audio/mp4'
        }, 'extension' => match ($format) {
            'mp3' => 'mp3','wav' => 'wav',default => 'm4a'
        }];
    }

    public static function maxKilobytes(): int
    {
        $values = [20480];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $value = trim(ini_get($key));
            $n = (float) $value;
            $bytes = $n * match (strtolower(substr($value, -1))) {
                'g' => 1073741824,'m' => 1048576,'k' => 1024,default => 1
            };
            if ($bytes > 0) {
                $values[] = max(1, (int) floor($bytes / 1024) - 64);
            }
        }

        return min($values);
    }
}
