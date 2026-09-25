<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseStorage
{
    public function baseUrl(): string
    {
        return rtrim((string) config('supabase.url'), '/');
    }

    public function serviceKey(): string
    {
        return (string) config('supabase.service_role_key');
    }

    public function bucket(): string
    {
        return (string) config('supabase.bucket', 'checkin-photos');
    }

    /**
     * @return array<string,string>
     */
    private function headers(): array
    {
        return [
            'apikey' => $this->serviceKey(),
            'Authorization' => 'Bearer '.$this->serviceKey(),
        ];
    }

    /**
     * Base HTTP client with the local CA bundle when one is available.
     */
    private function client()
    {
        $cafile = storage_path('app/certs/cacert.pem');

        $request = Http::withHeaders($this->headers());

        if (is_file($cafile)) {
            $request = $request->withOptions(['verify' => $cafile]);
        }

        return $request;
    }

    /**
     * Make sure the configured bucket exists, creating it if necessary.
     */
    public function ensureBucket(bool $public = true): void
    {
        $buckets = $this->client()->acceptJson()->get($this->baseUrl().'/storage/v1/bucket');

        if ($buckets->failed()) {
            throw new RuntimeException('Supabase: ไม่สามารถเชื่อมต่อ Storage API ได้');
        }

        $exists = collect($buckets->json())->contains(fn ($b) => ($b['name'] ?? null) === $this->bucket());

        if (! $exists) {
            $created = $this->client()->acceptJson()->post($this->baseUrl().'/storage/v1/bucket', [
                'name' => $this->bucket(),
                'public' => $public,
            ]);

            if ($created->failed()) {
                throw new RuntimeException('Supabase: สร้าง bucket "'.$this->bucket().'" ไม่สำเร็จ');
            }
        }
    }

    /**
     * Upload raw binary data into the bucket and return the storage path.
     */
    public function upload(string $path, string $binary, string $mime = 'image/jpeg'): string
    {
        $path = ltrim($path, '/');

        $origin = $this->client()
            ->withHeaders(['Content-Type' => $mime, 'x-upsert' => 'true'])
            ->withBody($binary, $mime)
            ->post($this->baseUrl().'/storage/v1/object/'.$this->bucket().'/'.$path);

        if ($origin->failed()) {
            $this->ensureBucket();
            $origin = $this->client()
                ->withHeaders(['Content-Type' => $mime, 'x-upsert' => 'true'])
                ->withBody($binary, $mime)
                ->post($this->baseUrl().'/storage/v1/object/'.$this->bucket().'/'.$path);
        }

        if ($origin->failed()) {
            throw new RuntimeException('Supabase: อัปโหลดรูป Selfie ไม่สำเร็จ');
        }

        return $path;
    }

    public function publicUrl(string $path): string
    {
        return $this->baseUrl().'/storage/v1/object/public/'.$this->bucket().'/'.ltrim($path, '/');
    }

    /**
     * Generate a short-lived signed URL for displaying images privately.
     */
    public function signedUrl(string $path, int $expiresIn = 3600): string
    {
        $response = $this->client()->acceptJson()->post(
            $this->baseUrl().'/storage/v1/object/sign/'.$this->bucket().'/'.ltrim($path, '/'),
            ['expiresIn' => $expiresIn]
        );

        if ($response->failed() || ! ($signed = $response->json('signedURL'))) {
            throw new RuntimeException('Supabase: ไม่สามารถสร้าง URL สำหรับแสดงรูปได้');
        }

        $prefix = str_starts_with($signed, '/storage/v1') ? '' : '/storage/v1';

        return $this->baseUrl().$prefix.$signed;
    }

    /**
     * Delete an object from the bucket (used on cancellation / cleanup).
     */
    public function delete(string $path): void
    {
        $this->client()->acceptJson()->delete(
            $this->baseUrl().'/storage/v1/object/'.$this->bucket().'?prefix='.urlencode(ltrim($path, '/'))
        );
    }
}