<?php

namespace App\Http\Controllers;

use App\Models\PerpustakaanBuku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $items = PerpustakaanBuku::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('judul_buku', 'like', '%'.$q.'%')
                        ->orWhere('penulis', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('library.index', [
            'title' => 'Perpustakaan',
            'q' => $q,
            'items' => $items,
        ]);
    }

    public function show(PerpustakaanBuku $book)
    {
        $bookUrl = null;
        if (($book->file_type ?? 'physical') === 'ebook' && ! empty($book->file_path)) {
            $path = (string) $book->file_path;
            if (str_starts_with($path, 'assets/')) {
                $path = preg_replace('#^assets/uploads/#', '', $path) ?: (string) $book->file_path;
            }
            $bookUrl = Storage::disk('public')->url($path);
        }

        return view('library.show', [
            'title' => $book->judul_buku,
            'book' => $book,
            'bookUrl' => $bookUrl,
        ]);
    }

    public function download(PerpustakaanBuku $book)
    {
        if (($book->file_type ?? 'physical') !== 'ebook' || empty($book->file_path)) {
            abort(404);
        }

        $path = (string) $book->file_path;
        if (str_starts_with($path, 'assets/')) {
            $path = preg_replace('#^assets/uploads/#', '', $path) ?: (string) $book->file_path;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }

        // increment download_count + log
        try {
            $book->increment('download_count');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            DB::table('perpustakaan_ebook_logs')->insert([
                'book_id' => $book->id,
                'user_name' => 'public',
                'user_class' => null,
                'access_type' => 'download',
                'ip_address' => request()->ip() ?? 'unknown',
                'user_agent' => substr((string) request()->userAgent(), 0, 2000),
                'access_time' => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return $disk->download($path);
    }
}
