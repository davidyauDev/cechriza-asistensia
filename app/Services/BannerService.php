<?php

namespace App\Services;

use App\Models\Banner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BannerService
{
    private const MAX_PER_PAGE = 100;

    public function index(array $params): LengthAwarePaginator
    {
        $perPage = isset($params['per_page']) ? (int) $params['per_page'] : 15;
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $query = Banner::query();

        // Filter by status
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        // Filter by current validity
        if (!empty($params['valid']) && $params['valid'] == '1') {
            $now = now();
            $query->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function store(array $data): Banner
    {
        if (isset($data['media']) && $data['media'] instanceof UploadedFile) {
            $data['image_url'] = $this->storeUploadedFile($data['media']);
            unset($data['media']);
        }

        return Banner::create($data);
    }

    public function show(int $id): ?Banner
    {
        return Banner::find($id);
    }

    public function update(int $id, array $data): ?Banner
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return null;
        }

        if (isset($data['media']) && $data['media'] instanceof UploadedFile) {
            // delete old
            if ($banner->image_url) {
                $this->deleteStoredFile($banner->image_url);
            }

            $data['image_url'] = $this->storeUploadedFile($data['media']);
            unset($data['media']);
        }

        $banner->fill($data);
        $banner->save();

        return $banner;
    }

    public function delete(int $id): bool
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return false;
        }

        // Optionally delete file
        if ($banner->image_url) {
            $this->deleteStoredFile($banner->image_url);
        }

        return (bool) $banner->delete();
    }

    private function storeUploadedFile(UploadedFile $file): string
    {
    $path = $file->store('banners', 'public');
    return Storage::url($path);
    }

    private function deleteStoredFile(string $url): void
    {
        // attempt to convert url to storage path
        $base = rtrim(Storage::url('/'), '/');
        if (Str::startsWith($url, $base)) {
            $relative = ltrim(Str::after($url, $base), '/');
            Storage::delete($relative);
        }
    }
}
