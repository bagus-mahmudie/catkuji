<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNote;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminNoteController extends Controller
{
    public function index(Request $request): Response
    {
        $period = (string) $request->query('period', 'daily');

        if (! in_array($period, ['daily', 'weekly', 'monthly', 'all'], true)) {
            $period = 'daily';
        }

        $date = (string) $request->query('date', now()->toDateString());
        $search = trim((string) $request->query('search', ''));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        $notes = AdminNote::query()
            ->with(['admin:id,name,email'])
            ->whereBetween('note_date', [$startDate, $endDate])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('admin', function ($adminQuery) use ($search) {
                            $adminQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('note_date')
            ->latest('id')
            ->paginate(18)
            ->withQueryString()
            ->through(fn (AdminNote $note) => $this->mapNote($note));

        return Inertia::render('Admin/Marketing/Notes/Index', [
            'notes' => $notes,
            'filters' => [
                'period' => $period,
                'date' => $date,
                'search' => $search,
                'date_label' => $this->dateLabel($startDate, $endDate),
            ],
            'periodOptions' => [
                ['label' => 'Daily', 'value' => 'daily'],
                ['label' => 'Weekly', 'value' => 'weekly'],
                ['label' => 'Monthly', 'value' => 'monthly'],
                ['label' => 'All', 'value' => 'all'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateNote($request);

        AdminNote::query()->create([
            'admin_id' => auth()->id(),
            'csat_feedback_id' => $validated['csat_feedback_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'note_date' => $validated['note_date'] ?? now(),
        ]);

        return back()->with('success', 'Note berhasil ditambahkan.');
    }

    public function update(Request $request, AdminNote $note): RedirectResponse
    {
        $validated = $this->validateNote($request, true);

        $note->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'note_date' => $validated['note_date'] ?? $note->note_date ?? now(),
        ]);

        return back()->with('success', 'Note berhasil diperbarui.');
    }

    public function destroy(AdminNote $note): RedirectResponse
    {
        $note->delete();

        return back()->with('success', 'Note berhasil dihapus.');
    }

    private function validateNote(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'note_date' => ['nullable', 'date'],
            'csat_feedback_id' => [
                $isUpdate ? 'nullable' : 'nullable',
                'integer',
                'exists:csat_feedbacks,id',
            ],
        ]);
    }

    private function mapNote(AdminNote $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'description' => $note->description,
            'admin_name' => $note->admin?->name ?: 'Admin',
            'admin_email' => $note->admin?->email,
            'csat_feedback_id' => $note->csat_feedback_id,
            'source_label' => $note->csat_feedback_id ? 'CSAT Detail' : 'Manual Note',
            'note_date' => optional($note->note_date)->format('Y-m-d\TH:i'),
            'date_label' => $note->note_date
                ? Carbon::parse($note->note_date)->format('F d, Y • h:i A')
                : Carbon::parse($note->created_at)->format('F d, Y • h:i A'),
        ];
    }

    private function resolveDateRange(string $period, string $date): array
    {
        $baseDate = Carbon::parse($date)->startOfDay();

        return match ($period) {
            'weekly' => [
                $baseDate->copy()->startOfWeek(),
                $baseDate->copy()->endOfWeek(),
            ],
            'monthly' => [
                $baseDate->copy()->startOfMonth(),
                $baseDate->copy()->endOfMonth(),
            ],
            'all' => [
                Carbon::create(2000, 1, 1)->startOfDay(),
                now()->copy()->endOfDay(),
            ],
            default => [
                $baseDate->copy()->startOfDay(),
                $baseDate->copy()->endOfDay(),
            ],
        };
    }

    private function dateLabel(Carbon $startDate, Carbon $endDate): string
    {
        if ($startDate->isSameDay($endDate)) {
            return $startDate->format('j F Y');
        }

        return $startDate->format('j F Y') . ' - ' . $endDate->format('j F Y');
    }
}