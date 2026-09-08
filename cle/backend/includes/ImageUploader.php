<?php

final class ImageUploader
{
    /**
     * Stores an uploaded image and returns its public URL.
     */
    public static function store(array $file, array $config): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Image upload failed');
        }
        if (($file['size'] ?? 0) > $config['max_upload_bytes']) {
            throw new InvalidArgumentException('Image must be 5 MB or smaller');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException('Only JPG, PNG, WEBP, and GIF images are allowed');
        }

        $directory = $config['upload_dir'];
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Upload directory is unavailable');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('Image could not be stored');
        }

        return rtrim($config['app_url'], '/') . '/storage/uploads/' . $filename;
    }
}
