<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as DownloadResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class AdminBannerController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $startDate = (string) $request->query('start_date', '');
        $endDate = (string) $request->query('end_date', '');

        $banners = Banner::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('alt_text', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($startDate !== '', function ($query) use ($startDate) {
                $query->where(function ($q) use ($startDate) {
                    $q->whereNull('end_at')->orWhereDate('end_at', '>=', $startDate);
                });
            })
            ->when($endDate !== '', function ($query) use ($endDate) {
                $query->where(function ($q) use ($endDate) {
                    $q->whereNull('start_at')->orWhereDate('start_at', '<=', $endDate);
                });
            })
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate(6)
            ->withQueryString()
            ->through(fn (Banner $banner) => $this->mapBanner($banner));

        $previewBanners = Banner::query()
            ->active()
            ->orderBy('sort_order')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Banner $banner) => $this->mapBanner($banner))
            ->values();

        return Inertia::render('Admin/Marketing/Banners/Index', [
            'banners' => $banners,
            'previewBanners' => $previewBanners,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBanner($request);

        $validated['image'] = $request->file('image')->store('banners', 'public');
        $validated['is_active'] = (bool) $validated['is_active'];
        $validated['sort_order'] = max(1, (int) ($validated['sort_order'] ?? 1));

        DB::transaction(function () use ($validated) {
            Banner::query()
                ->where('sort_order', '>=', $validated['sort_order'])
                ->increment('sort_order');

            Banner::query()->create($validated);
        });

        return redirect()
            ->route('admin.marketing.banners.index')
            ->with('success', 'Banner promo berhasil ditambahkan.');
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $validated = $this->validateBanner($request, true);

        unset($validated['image']);

        if ($request->hasFile('image')) {
            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }

            $validated['image'] = $request->file('image')->store('banners', 'public');
        }

        $validated['is_active'] = (bool) $validated['is_active'];

        $oldOrder = max(1, (int) $banner->sort_order);
        $newOrder = max(1, (int) ($validated['sort_order'] ?? $oldOrder));

        DB::transaction(function () use ($banner, $validated, $oldOrder, $newOrder) {
            unset($validated['sort_order']);

            if ($newOrder !== $oldOrder) {
                if ($newOrder < $oldOrder) {
                    Banner::query()
                        ->where('id', '!=', $banner->id)
                        ->whereBetween('sort_order', [$newOrder, $oldOrder - 1])
                        ->increment('sort_order');
                }

                if ($newOrder > $oldOrder) {
                    Banner::query()
                        ->where('id', '!=', $banner->id)
                        ->whereBetween('sort_order', [$oldOrder + 1, $newOrder])
                        ->decrement('sort_order');
                }

                $validated['sort_order'] = $newOrder;
            }

            $banner->update($validated);
        });

        return redirect()
            ->route('admin.marketing.banners.index')
            ->with('success', 'Banner promo berhasil diperbarui.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        DB::transaction(function () use ($banner) {
            $deletedOrder = max(1, (int) $banner->sort_order);

            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }

            $banner->delete();

            Banner::query()
                ->where('sort_order', '>', $deletedOrder)
                ->decrement('sort_order');
        });

        return redirect()
            ->route('admin.marketing.banners.index')
            ->with('success', 'Banner promo berhasil dihapus permanen.');
    }

    public function export(Request $request)
    {
        $rows = Banner::query()
            ->orderBy('sort_order')
            ->latest('id')
            ->get();

        $filename = 'banner-promos-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID',
                'Title',
                'Image',
                'URL',
                'Alt Text',
                'Description',
                'Start At',
                'End At',
                'Status',
                'Sort Order',
                'Created At',
            ]);

            foreach ($rows as $banner) {
                fputcsv($file, [
                    $banner->id,
                    $banner->title,
                    $banner->image,
                    $banner->url,
                    $banner->alt_text,
                    $banner->description,
                    optional($banner->start_at)->format('Y-m-d H:i:s'),
                    optional($banner->end_at)->format('Y-m-d H:i:s'),
                    $banner->is_active ? 'Active' : 'Inactive',
                    $banner->sort_order,
                    optional($banner->created_at)->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return DownloadResponse::stream($callback, 200, $headers);
    }

    private function validateBanner(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'image' => [
                $isUpdate ? 'sometimes' : 'required',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:51200',
            ],
            'url' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'is_active' => ['required', Rule::in(['0', '1', 0, 1, true, false])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function mapBanner(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'image' => $banner->image,
            'image_url' => $this->imageUrl($banner->image),
            'url' => $banner->url,
            'alt_text' => $banner->alt_text,
            'description' => $banner->description,
            'start_at' => optional($banner->start_at)->format('Y-m-d'),
            'end_at' => optional($banner->end_at)->format('Y-m-d'),
            'date_label' => $this->dateLabel($banner),
            'is_active' => (bool) $banner->is_active,
            'status_label' => $banner->is_active ? 'Aktif' : 'Tidak Aktif',
            'sort_order' => (int) $banner->sort_order,
        ];
    }

    private function imageUrl(?string $path): string
    {
        if (! $path) {
            return asset('images/placeholder-product.png');
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, ['/storage/'])) {
            return $path;
        }

        if (Str::startsWith($path, ['storage/'])) {
            return asset($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    private function dateLabel(Banner $banner): string
    {
        $start = optional($banner->start_at)->format('d M Y');
        $end = optional($banner->end_at)->format('d M Y');

        if ($start && $end) {
            return "{$start} - {$end}";
        }

        if ($start) {
            return "Mulai {$start}";
        }

        if ($end) {
            return "Sampai {$end}";
        }

        return 'Tanpa batas tanggal';
    }
}