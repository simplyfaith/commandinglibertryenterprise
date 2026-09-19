<?php

/**
 * Uploads validated images to Cloudinary and returns the permanent,
 * publicly-served URL Cloudinary hands back. This replaces local-disk
 * storage entirely: Render's web service filesystem is ephemeral, so any
 * file written to disk while the app is running is lost on the next
 * redeploy or restart. Cloudinary's free tier (25GB storage/bandwidth) is
 * durable storage with its own CDN, so nothing in this app needs to survive
 * a redeploy on Render's own disk anymore.
 */
final class ImageUploader
{
    /**
     * Stores an uploaded image on Cloudinary and returns its public URL.
     *
     * @param array $file   A single entry from $_FILES, e.g. $_FILES['image'].
     * @param array $config The app config array (needs 'max_upload_bytes'
     *                      and 'cloudinary' => ['cloud_name','api_key','api_secret']).
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
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException('Only JPG, PNG, WEBP, and GIF images are allowed');
        }

        $cloud = $config['cloudinary'] ?? [];
        if (empty($cloud['cloud_name']) || empty($cloud['api_key']) || empty($cloud['api_secret'])) {
            error_log('Cloudinary is not configured (missing CLOUDINARY_* env vars).');
            throw new RuntimeException('Image storage is not configured on the server. Please contact the administrator.');
        }

        // Cloudinary signed-upload requires every non-file param that's part
        // of the request to be included in the signature, sorted by key,
        // hashed together with the API secret. We only send `folder` and
        // `timestamp`, keeping this simple and easy to extend later.
        $timestamp = time();
        $params = [
            'folder' => 'commanding-liberty/products',
            'timestamp' => $timestamp,
        ];
        ksort($params);
        $toSign = urldecode(http_build_query($params));
        $signature = sha1($toSign . $cloud['api_secret']);

        $postFields = [
            'file' => new CURLFile($file['tmp_name'], $mime, $file['name'] ?? 'upload'),
            'api_key' => $cloud['api_key'],
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => $params['folder'],
        ];

        $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloud['cloud_name']}/image/upload");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            error_log('Cloudinary upload cURL error: ' . $curlError);
            throw new RuntimeException('The server could not reach the image storage provider. Please try again.');
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200 || empty($decoded['secure_url'])) {
            $reason = $decoded['error']['message'] ?? "HTTP $httpCode";
            error_log('Cloudinary upload failed: ' . $reason . ' | raw: ' . $response);
            throw new RuntimeException('The server could not save the image. Please try again.');
        }

        return $decoded['secure_url'];
    }
}