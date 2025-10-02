<?php

namespace App\Http\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class CloudinaryService
{
    /**
     * Upload file ke Cloudinary
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $folder
     * @return array
     */
    public function upload($file, $folder = 'profiles'): array
    {
        $upload = Cloudinary::uploadApi()->upload($file->getRealPath(), [
            'folder' => $folder
        ]);

        return [
            'url'       => $upload['secure_url'],
            'public_id' => $upload['public_id'],
        ];
    }

    /**
     * Hapus file dari Cloudinary berdasarkan URL
     *
     * @param string $url
     * @return void
     */
    public function destroy(string $url): void
    {
        $publicId = $this->extractPublicId($url);

        if ($publicId) {
            try {
                Cloudinary::uploadApi()->destroy($publicId);
            } catch (\Exception $e) {
                \Log::error('Cloudinary delete failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Ambil public_id dari URL Cloudinary
     *
     * @param string $url
     * @return string|null
     */
    private function extractPublicId(string $url): ?string
    {
        $urlPath  = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', $urlPath);

        if (count($segments) < 2) {
            return null;
        }

        $filename = pathinfo(end($segments), PATHINFO_FILENAME);
        $folder   = prev($segments);

        return $folder . '/' . $filename;
    }
}
