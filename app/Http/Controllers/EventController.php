<?php

namespace App\Http\Controllers;

use App\Jobs\FetchEventSourceJob;
use App\Jobs\ProcessEventWithAIJob;
use App\Models\CinemaEvent;
use App\Models\NewsSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Event Manager — acceso restringido a Admin (1) y Editor (2).
 * Gestión de eventos cinematográficos de Canarias.
 */
class EventController extends Controller
{
    private function authorizeEditor(): ?JsonResponse
    {
        $user = auth('sanctum')->user();
        if (! $user || ! in_array($user->idRol, [1, 2])) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }
        return null;
    }

    // ─── EVENTOS ─────────────────────────────────────────────────────────────

    /**
     * GET /api/events
     * Parámetros: status, island, event_type, source_id, date_from, date_to, upcoming, page
     */
    public function index(Request $request): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $query = CinemaEvent::with('source');

        match ($request->input('sort', 'fecha_asc')) {
            'fecha_desc'      => $query->orderBy('start_date', 'desc'),
            'confidence_desc' => $query->orderByRaw('ai_confidence IS NULL, ai_confidence DESC'),
            default           => $query->orderBy('start_date', 'asc'),
        };

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('island')) {
            $query->byIsland($request->island);
        }
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        if ($request->filled('source_id')) {
            $query->where('source_id', $request->source_id);
        }
        if ($request->filled('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('start_date', '<=', $request->date_to);
        }
        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }
        if ($request->boolean('ongoing')) {
            $query->ongoing();
        }

        $events = $query->paginate(20);

        return response()->json(['success' => 1, 'data' => $events]);
    }

    /**
     * GET /api/events/public
     * Agenda pública: solo eventos confirmados, no requiere auth.
     * Parámetros: island, event_type, date_from, date_to
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $query = CinemaEvent::where('status', 'confirmed')
            ->orderBy('start_date', 'asc');

        if ($request->filled('island')) {
            $query->byIsland($request->island);
        }
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        if ($request->boolean('ongoing')) {
            $query->ongoing();
        } elseif ($request->boolean('upcoming')) {
            $query->upcoming();
        } elseif ($request->filled('date_from') && $request->filled('date_to')) {
            $query->inRange($request->date_from, $request->date_to);
        } else {
            $query->upcoming();
        }

        $events = $query->paginate(20);

        return response()->json(['success' => 1, 'data' => $events]);
    }

    /**
     * POST /api/events
     * Creación manual de un evento por parte de un editor/admin.
     */
    public function store(Request $request): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'event_type'  => 'nullable|in:festival,projection,cycle,workshop,other',
            'venue'       => 'nullable|string|max:255',
            'island'      => 'nullable|in:GC,TF,LZ,FV,LP,EH,GO,ALL',
            'ticket_url'  => 'nullable|url|max:500',
            'image_url'   => 'nullable|url|max:500',
            'source_url'  => 'nullable|url|max:500',
            'status'      => 'nullable|in:confirmed,pending',
        ]);

        // Categorización determinista por duración (igual que en ProcessEventWithAIJob)
        if (empty($validated['event_type'])) {
            $validated['event_type'] = $this->resolveEventType(
                $validated['start_date'],
                $validated['end_date'] ?? null
            );
        }

        $event = CinemaEvent::create([
            ...$validated,
            'status'        => $validated['status'] ?? 'confirmed',
            'ai_confidence' => null,
        ]);

        return response()->json([
            'success' => 1,
            'message' => 'Evento creado.',
            'data'    => $event,
        ], 201);
    }

    private function resolveEventType(string $startDate, ?string $endDate): string
    {
        if (! $endDate || $startDate === $endDate) return 'projection';
        try {
            $days = (new \DateTime($startDate))->diff(new \DateTime($endDate))->days;
            if ($days >= 3) return 'festival';
            if ($days >= 1) return 'cycle';
        } catch (\Throwable) {}
        return 'other';
    }

    /**
     * GET /api/events/{id}
     */
    public function show(int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $event = CinemaEvent::with('source')->findOrFail($id);

        return response()->json(['success' => 1, 'data' => $event]);
    }

    /**
     * PATCH /api/events/{id}/status
     * Body: { "status": "confirmed|rejected|needs_review|pending" }
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,rejected,needs_review',
        ]);

        $event = CinemaEvent::findOrFail($id);
        $event->update(['status' => $validated['status']]);

        return response()->json([
            'success' => 1,
            'message' => 'Estado actualizado.',
            'data'    => $event,
        ]);
    }

    /**
     * PATCH /api/events/{id}
     * Permite al editor corregir datos extraídos por la IA.
     * Body: { title, description, start_date, end_date, event_type, venue, island, ticket_url }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $validated = $request->validate([
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
            'event_type'  => 'nullable|in:festival,projection,cycle,workshop,other',
            'venue'       => 'nullable|string|max:255',
            'island'      => 'nullable|in:GC,TF,LZ,FV,LP,EH,GO,ALL',
            'ticket_url'  => 'nullable|string|max:500',
            'image_url'   => 'nullable|string|max:500',
            'source_url'  => 'nullable|string|max:500',
        ]);

        $event = CinemaEvent::findOrFail($id);
        $event->update(array_filter($validated, fn ($v) => ! is_null($v)));

        return response()->json([
            'success' => 1,
            'message' => 'Evento actualizado.',
            'data'    => $event,
        ]);
    }

    /**
     * DELETE /api/events/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        CinemaEvent::findOrFail($id)->delete();

        return response()->json(['success' => 1, 'message' => 'Evento eliminado.']);
    }

    // ─── FUENTES ─────────────────────────────────────────────────────────────

    /**
     * GET /api/events/sources
     * Lista las fuentes con purpose='events'.
     */
    public function sources(): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $sources = NewsSource::where('purpose', 'events')
            ->orderBy('name')
            ->get()
            ->map(fn (NewsSource $s) => [
                'id'                   => $s->id,
                'name'                 => $s->name,
                'url'                  => $s->url,
                'type'                 => $s->type,
                'is_active'            => $s->is_active,
                'last_checked_at'      => $s->last_checked_at?->toIso8601String(),
                'failed_attempts'      => $s->failed_attempts ?? 0,
                'needs_review'         => (bool) $s->needs_review,
                'last_error'           => $s->last_error,
                'events_total'         => $s->newsItems()->count(), // reutiliza la relación hasta tener la propia
                'events_today'         => CinemaEvent::where('source_id', $s->id)
                                            ->whereDate('created_at', today())
                                            ->count(),
            ]);

        return response()->json(['success' => 1, 'data' => $sources]);
    }

    /**
     * PATCH /api/events/sources/{id}
     * Actualiza nombre y/o URL de una fuente de eventos.
     */
    public function updateSource(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url'  => 'sometimes|url|max:500',
        ]);

        $source = NewsSource::where('purpose', 'events')->findOrFail($id);
        $source->update($validated);

        return response()->json([
            'success' => 1,
            'message' => 'Fuente actualizada.',
            'data'    => $source,
        ]);
    }

    /**
     * POST /api/events/sources/{id}/check-now
     * Lanza FetchEventSourceJob de forma SÍNCRONA.
     */
    public function checkNow(int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        set_time_limit(60);

        $source = NewsSource::where('purpose', 'events')->findOrFail($id);
        $before = CinemaEvent::where('source_id', $source->id)->count();

        try {
            (new FetchEventSourceJob($source->id))->handle();

            $new = max(0, CinemaEvent::where('source_id', $source->id)->count() - $before);
            $msg = $new > 0
                ? "{$new} nuevo(s) evento(s) encontrados en \"{$source->name}\"."
                : "\"{$source->name}\" rastreada — sin eventos nuevos.";

        } catch (\Throwable $e) {
            Log::warning("[Events checkNow] {$source->name}: {$e->getMessage()}");
            $msg = "Error al rastrear \"{$source->name}\": " . $e->getMessage();
        }

        return response()->json(['success' => 1, 'message' => $msg]);
    }

    /**
     * POST /api/events/process-ai
     * Lanza ProcessEventWithAIJob de forma SÍNCRONA.
     */
    public function processAI(): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        set_time_limit(180);

        $job = new ProcessEventWithAIJob();
        $job->handle();

        $pending = CinemaEvent::unprocessed()->where('status', 'pending')->count();

        return response()->json([
            'success'      => 1,
            'message'      => $pending > 0
                ? "Lote procesado. Quedan {$pending} eventos sin procesar."
                : 'Todos los eventos han sido procesados.',
            'pending_left' => $pending,
        ]);
    }
}
