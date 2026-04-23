<?php

namespace App\Jobs;

use App\Models\CinemaEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Procesa los CinemaEvents pendientes con GPT-4o-mini.
 *
 * A partir del raw_text scrapeado, la IA extrae:
 * - start_date / end_date  (YYYY-MM-DD)
 * - event_type             (festival|projection|cycle|workshop|other)
 * - venue                  (nombre del cine/sala/espacio)
 * - island                 (GC|TF|LZ|FV|LP|EH|GO|ALL)
 * - description            (resumen limpio del evento)
 * - confidence             (0.0–1.0, qué tan seguro está el modelo)
 *
 * Si confidence < 0.6 el evento queda en status='needs_review'.
 * Si confidence >= 0.6 pasa a status='confirmed'.
 *
 * Proceso en lote de 15 items por ejecución.
 */
class ProcessEventWithAIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 2;
    public $backoff = 300;
    public $timeout = 300;

    private const BATCH_SIZE = 15;

    public function handle(): void
    {
        $apiKey = config('services.openai.key');

        if (empty($apiKey)) {
            Log::warning('[ProcessEventWithAIJob] OPENAI_API_KEY no configurada.');
            return;
        }

        $events = CinemaEvent::unprocessed()
            ->where('status', 'pending')
            ->with('source')
            ->orderBy('created_at', 'asc')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $processed = 0;

        foreach ($events as $event) {
            try {
                $result = $this->callOpenAI($apiKey, $event);

                if ($result) {
                    $confidence = (float) ($result['confidence'] ?? 0);

                    // La categorización festival/projection la hacemos aquí con lógica determinista,
                    // usando las fechas extraídas por la IA como input.
                    $eventType = $this->resolveEventType(
                        $result['start_date'] ?? null,
                        $result['end_date']   ?? null,
                        $result['event_type'] ?? 'other'
                    );

                    $event->update([
                        'start_date'    => $result['start_date']   ?? $event->start_date,
                        'end_date'      => $result['end_date']      ?? null,
                        'event_type'    => $eventType,
                        'venue'         => $result['venue']         ?? null,
                        'island'        => $result['island']        ?? null,
                        'description'   => $result['description']   ?? null,
                        'ai_confidence' => $confidence,
                        'status'        => $confidence >= 0.6 ? 'confirmed' : 'needs_review',
                    ]);

                    $processed++;
                }

                usleep(200_000); // 200ms entre llamadas a OpenAI

            } catch (\Throwable $e) {
                Log::error("[ProcessEventWithAIJob] Evento #{$event->id} error: {$e->getMessage()}");
            }
        }

        Log::info("[ProcessEventWithAIJob] Procesados {$processed}/{$events->count()} eventos.");
    }

    /**
     * Determina el tipo de evento con lógica determinista a partir de las fechas.
     * La sugerencia de la IA sirve solo como fallback cuando no hay fechas.
     */
    private function resolveEventType(?string $startDate, ?string $endDate, string $aiSuggestion): string
    {
        if (! $startDate) {
            return $aiSuggestion;
        }

        if (! $endDate || $startDate === $endDate) {
            return 'projection';
        }

        try {
            $start = new \DateTime($startDate);
            $end   = new \DateTime($endDate);
            $days  = (int) $start->diff($end)->days;

            if ($days >= 3) {
                return 'festival';
            }
            if ($days >= 1) {
                return 'cycle';
            }
        } catch (\Throwable) {
            // Si las fechas no parsean, usamos la sugerencia de la IA
        }

        return $aiSuggestion;
    }

    private function callOpenAI(string $apiKey, CinemaEvent $event): ?array
    {
        $sourceName = $event->source?->name ?? 'desconocida';
        $rawText    = mb_substr($event->raw_text ?? $event->title, 0, 1500);
        $today      = now()->locale('es')->translatedFormat('j \d\e F \d\e Y');
        $year       = now()->year;

        $systemPrompt = <<<PROMPT
Eres un asistente especializado en extraer datos estructurados de eventos cinematográficos en Canarias (España).
Tu tarea es analizar texto bruto scrapeado de webs de cine canario y devolver un JSON con los datos del evento.
Responde ÚNICAMENTE con un objeto JSON válido. Sin markdown, sin texto adicional.

## ISLAS DE CANARIAS (códigos)
GC = Gran Canaria, TF = Tenerife, LZ = Lanzarote, FV = Fuerteventura,
LP = La Palma, EH = El Hierro, GO = La Gomera, ALL = todas las islas

## TIPOS DE EVENTO
- festival:    evento de varios días (≥ 3 días) con programación variada
- projection:  proyección única (1 día)
- cycle:       ciclo o maratón de 2 días
- workshop:    taller o masterclass
- other:       si no encaja en ninguna categoría

## FECHAS
- Usa formato YYYY-MM-DD
- Si el texto dice "del 12 al 18 de mayo" y el año actual es {$year}: start_date={$year}-05-12, end_date={$year}-05-18
- Si solo hay mes y año sin día: usa el día 1 como start_date
- Si la fecha ya pasó hace más de 6 meses y no hay año explícito, asume el año siguiente

## CONFIANZA (confidence)
1.0 = fechas exactas, isla, venue claros
0.8 = fechas claras pero algún dato falta
0.6 = fechas incompletas o isla inferida del venue
< 0.6 = fechas ausentes o muy ambiguas

## ESQUEMA JSON
{
  "start_date":  "YYYY-MM-DD o null",
  "end_date":    "YYYY-MM-DD o null (null si es un solo día)",
  "event_type":  "festival|projection|cycle|workshop|other",
  "venue":       "nombre del cine o espacio (string o null)",
  "island":      "GC|TF|LZ|FV|LP|EH|GO|ALL|null",
  "description": "1-2 frases describiendo el evento en español (string o null)",
  "confidence":  0.0 a 1.0
}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            // Few-shot: festival con rango de fechas
            [
                'role'    => 'user',
                'content' => "Fecha actual: 22 de abril de {$year}\nFUENTE: MiradasDoc\nTÍTULO: MiradasDoc 2025 — Festival Internacional de Documentales\nFecha: Del 14 al 21 de noviembre\nLugar: Casa de la Cultura de Guía de Isora, Tenerife",
            ],
            [
                'role'    => 'assistant',
                'content' => "{\"start_date\":\"{$year}-11-14\",\"end_date\":\"{$year}-11-21\",\"event_type\":\"festival\",\"venue\":\"Casa de la Cultura de Guía de Isora\",\"island\":\"TF\",\"description\":\"MiradasDoc es un festival internacional de cine documental celebrado anualmente en Guía de Isora, Tenerife.\",\"confidence\":0.95}",
            ],
            // Few-shot: proyección única
            [
                'role'    => 'user',
                'content' => "Fecha actual: 22 de abril de {$year}\nFUENTE: Filmoteca Canaria\nTÍTULO: Proyección: 'El sur' de Víctor Erice\nFecha: Miércoles 7 de mayo, 19:30h\nLugar: Sala Guiniguada, Las Palmas de Gran Canaria",
            ],
            [
                'role'    => 'assistant',
                'content' => "{\"start_date\":\"{$year}-05-07\",\"end_date\":null,\"event_type\":\"projection\",\"venue\":\"Sala Guiniguada\",\"island\":\"GC\",\"description\":\"Proyección de 'El sur' de Víctor Erice en la Sala Guiniguada de Las Palmas.\",\"confidence\":0.95}",
            ],
            // Few-shot: texto ambiguo solo con alt de imagen
            [
                'role'    => 'user',
                'content' => "Fecha actual: 22 de abril de {$year}\nFUENTE: Cines Monopol\nTÍTULO: Ciclo de cine clásico | Cartel del evento\n[OG] Ciclo de verano en los Cines Monopol — junio 2025",
            ],
            [
                'role'    => 'assistant',
                'content' => "{\"start_date\":\"{$year}-06-01\",\"end_date\":null,\"event_type\":\"cycle\",\"venue\":\"Cines Monopol\",\"island\":\"GC\",\"description\":\"Ciclo de cine clásico de verano en los Cines Monopol.\",\"confidence\":0.55}",
            ],
            // Tarea real
            [
                'role'    => 'user',
                'content' => implode("\n", [
                    "Fecha actual: {$today}",
                    "FUENTE: {$sourceName}",
                    "TÍTULO: {$event->title}",
                    $rawText,
                ]),
            ],
        ];

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-4o-mini',
                'messages'        => $messages,
                'response_format' => ['type' => 'json_object'],
                'max_tokens'      => 300,
                'temperature'     => 0.1, // máxima determinismo para fechas
            ]);

        if (! $response->successful()) {
            Log::warning("[ProcessEventWithAIJob] OpenAI HTTP {$response->status()} para evento #{$event->id}");
            return null;
        }

        $body    = $response->json();
        $rawJson = $body['choices'][0]['message']['content'] ?? null;

        if (! $rawJson) {
            return null;
        }

        $decoded = json_decode($rawJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("[ProcessEventWithAIJob] JSON inválido para evento #{$event->id}: {$rawJson}");
            return null;
        }

        // Sanitizar confidence
        if (isset($decoded['confidence'])) {
            $decoded['confidence'] = max(0.0, min(1.0, (float) $decoded['confidence']));
        }

        return $decoded;
    }
}
