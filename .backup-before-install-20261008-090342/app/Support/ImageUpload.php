<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Small helper for uploading and deleting images. $dir is a path inside the public folder. */
class ImageUpload
{
    public static function store(UploadedFile $file, string $dir): string
    {
        $path = public_path($dir);
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $name = Str::random(24) . '.' . $file->extension();
        $file->move($path, $name);

        return $name;
    }

    public static function delete(?string $name, string $dir): void
    {
        if (!$name) {
            return;
        }
        $file = public_path($dir . '/' . basename($name));
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
