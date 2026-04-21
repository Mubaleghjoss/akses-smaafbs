<?php

namespace App\Http\Controllers;

use App\Models\Berita;
use App\Models\BeritaUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $items = Berita::query()
            ->where('status', 'aktif')
            ->when($q !== '', fn ($query) => $query->where('judul', 'like', '%'.$q.'%'))
            ->orderByDesc('tanggal_berita')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('news.index', [
            'title' => 'Berita',
            'q' => $q,
            'items' => $items,
        ]);
    }

    public function show(Berita $news)
    {
        abort_unless(strtolower((string) $news->status) === 'aktif', 404);

        $imageUrl = $this->resolvePublicMediaUrl((string) ($news->gambar ?? ''), defaultDirectory: 'news');
        $timelineUpdates = $news->timelineUpdatesForPublic()
            ->map(function (BeritaUpdate $update): array {
                return [
                    'id' => $update->id,
                    'phase_label' => $update->phase_label,
                    'progress_percent' => $update->progress_percent,
                    'tanggal_update' => $update->tanggal_update,
                    'update_text' => $update->update_text,
                    'live_url' => $update->live_url,
                    'documentation_media_urls' => collect($update->documentation_media ?? [])
                        ->filter(fn ($path) => filled($path))
                        ->map(fn ($path) => $this->resolvePublicMediaUrl((string) $path, defaultDirectory: 'news/documentation'))
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $documentationMediaUrls = $timelineUpdates
            ->pluck('documentation_media_urls')
            ->flatten(1)
            ->filter()
            ->values();

        if ($documentationMediaUrls->isEmpty()) {
            $documentationMediaUrls = collect($news->tracker_documentation_media ?? [])
                ->filter(fn ($path) => filled($path))
                ->map(fn ($path) => $this->resolvePublicMediaUrl((string) $path, defaultDirectory: 'news/documentation'))
                ->filter()
                ->values();
        }

        $description = (string) Str::of(strip_tags((string) ($news->konten ?? '')))
            ->squish()
            ->limit(180, '...');

        if ($description === '') {
            $description = 'Informasi kegiatan terbaru dari sekolah.';
        }

        return view('news.show', [
            'title' => $news->judul,
            'news' => $news,
            'imageUrl' => $imageUrl,
            'documentationMediaUrls' => $documentationMediaUrls->all(),
            'timelineUpdates' => $timelineUpdates,
            'meta' => [
                'description' => $description,
                'og_title' => (string) $news->judul,
                'og_description' => $description,
                'og_image' => $imageUrl,
            ],
        ]);
    }

    protected function resolvePublicMediaUrl(string $rawPath, string $defaultDirectory): ?string
    {
        $rawPath = trim($rawPath);

        if ($rawPath === '') {
            return null;
        }

        if (Str::startsWith($rawPath, ['http://', 'https://'])) {
            return $rawPath;
        }

        $path = $rawPath;

        if (str_starts_with($path, 'assets/')) {
            // legacy path
            $path = preg_replace('#^assets/uploads/#', '', $path) ?: $rawPath;
        }

        if (! str_contains($path, '/')) {
            $path = trim($defaultDirectory, '/').'/'.$path;
        }

        return Storage::disk('public')->url($path);
    }
}
