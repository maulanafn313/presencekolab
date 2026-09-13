<?php

namespace App\Services;

use App\Models\User;

class FaceRegistration
{
    use \App\Traits\ImageOptimizer;

    public function registerImage(User $user, string $image): bool
    {
        $name = $this->optimizeAndSaveBase64($image, 'users', 'face_'.bin2hex(random_bytes(16)).'.jpg', 640, 85);
        if (!$name) throw \Illuminate\Validation\ValidationException::withMessages(['image'=>'Foto tidak valid atau ukurannya terlalu besar.']);
        $saved = false;
        try {
            $user->foto_base64 = $name;
            $saved = \Illuminate\Support\Facades\DB::transaction(fn () => $this->generate($user));
            return $saved;
        } finally {
            if (!$saved) {
                $path = storage_path('app/private/media/users/'.$name);
                if (is_file($path)) unlink($path); // Only this request's generated file; previous photo is preserved.
                $user->refresh();
            }
        }
    }

    public function generate(User $user): bool
    {
        $name = $user->getAttributes()['foto_base64'] ?? null;
        if (! $name || basename($name) !== $name) {
            return false;
        }
        $path = PrivateMedia::path($name);
        if (!$path) {
            return false;
        }
        $process = app(FaceNetProcess::class)->make(json_encode(['action' => 'generate_embedding', 'image' => $path]));
        $process->mustRun();
        $output = json_decode($process->getOutput(), true);
        $embedding = $output['data']['embedding'] ?? null;
        if (empty($output['success']) || ! is_array($embedding) || ! in_array(count($embedding), [128, 512])) {
            return false;
        }
        foreach ($embedding as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                return false;
            }
        }
        $user->face_embedding = json_encode($embedding);
        $user->face_embedding_updated = now();
        $user->save();

        return true;
    }
}
