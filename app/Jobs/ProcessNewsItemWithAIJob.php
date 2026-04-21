<?php

namespace App\Jobs;

use App\Models\NewsItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Procesa los NewsItems pendientes sin procesar con GPT-4o-mini.
 *
 * Para cada item llama a OpenAI con un prompt estructurado que devuelve JSON:
 * {
 *   "summary":           "Resumen en 2-3 frases",
 *   "tags":              ["festival", "convocatoria", ...],
 *   "relevance_score":   7,
 *   "suggested_title":   "Título sugerido para el post",
 *   "category":          "festival|produccion|estreno|convocatoria|otro",
 *   "canarian_entities": ["nombre festival", "director", "película"]
 * }
 *
 * Se ejecuta cada hora via Scheduler. Procesa en lote de 20 items máximo
 * para no consumir demasiados tokens de una vez.
 */
class ProcessNewsItemWithAIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 2;
    public $backoff = 300;
    public $timeout = 300;

    private const BATCH_SIZE = 20;

    public function handle(): void
    {
        $apiKey = config('services.openai.key');

        if (empty($apiKey)) {
            Log::warning('[ProcessNewsItemWithAIJob] OPENAI_API_KEY no configurada.');
            return;
        }

        $items = NewsItem::unprocessed()
            ->pending()
            ->with('source')
            ->orderBy('found_at', 'asc')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        $processed = 0;

        foreach ($items as $item) {
            try {
                $result = $this->callOpenAI($apiKey, $item);

                if ($result) {
                    $item->update([
                        'ai_summary'          => $result['summary']          ?? null,
                        'ai_tags'             => $result['tags']             ?? [],
                        'ai_relevance_score'  => $result['relevance_score']  ?? null,
                        'ai_suggested_title'  => $result['suggested_title']  ?? null,
                        'ai_category'         => $result['category']         ?? 'otro',
                        'ai_canarian_entities'=> $result['canarian_entities'] ?? [],
                        'processed_at'        => now(),
                    ]);
                    $processed++;
                }

                // Pausa breve para no superar el rate limit de OpenAI
                usleep(200_000); // 200ms

            } catch (\Throwable $e) {
                Log::error("[ProcessNewsItemWithAIJob] Item #{$item->id} error: {$e->getMessage()}");
            }
        }

        Log::info("[ProcessNewsItemWithAIJob] Procesados {$processed}/{$items->count()} items.");
    }

    private function callOpenAI(string $apiKey, NewsItem $item): ?array
    {
        $sourceName = $item->source?->name ?? 'desconocida';
        $content    = mb_substr($item->raw_content ?? '', 0, 1500);
        $today      = now()->locale('es')->translatedFormat('j \d\e F \d\e Y');

        // ── System prompt: rol + rúbrica + reglas temporales ─────────────────
        $systemPrompt = <<<'PROMPT'
Eres un asistente editorial especializado en cine canario (Islas Canarias, España).
Tu tarea es analizar noticias del sector audiovisual canario y generar metadatos estructurados en JSON.
Responde ÚNICAMENTE con un objeto JSON válido. Sin markdown, sin texto adicional.

## CRITERIOS DE PUNTUACIÓN (relevance_score, 0–10)

10 — Crítico/Máxima relevancia: noticias sobre cine canario (producción, estrenos, premios) de gran actualidad. Especial atención a eventos INMINENTES (LPA Film Festival, MiradasDoc, FICMEC, etc.) que ocurran en las próximas 4 semanas.

8–9 — Muy alta: rodajes internacionales en Canarias, incentivos fiscales, subvenciones públicas (Gobcan) o éxitos de cineastas canarios fuera de las islas.

6–7 — Alta: eventos culturales en Canarias con componente audiovisual (videoarte, festivales de música con cine, exposiciones de fotografía de cine).

4–5 — Media: cultura general canaria (teatro, literatura, música tradicional) sin relación directa con el cine.

1–3 — Baja: noticias canarias de actualidad general (sociedad, política local) sin trasfondo cultural.

0 — Nula: noticias fuera del ámbito canario y sin relación con el cine.

## AJUSTES POR TEMPORALIDAD (aplicar sobre la puntuación base)
- Si la noticia menciona un evento con fecha específica que ocurrirá en menos de 30 días desde la fecha actual: +1 (máximo 10).
- Si la noticia es una reseña de algo que ocurrió hace más de un mes: −1 (mínimo 0).

## ESQUEMA JSON DE RESPUESTA
{
  "summary": "Resumen en 2-3 frases en español orientado al lector cinéfilo",
  "tags": ["etiqueta1", "etiqueta2"],
  "relevance_score": <entero 0-10 aplicando la rúbrica y los ajustes temporales>,
  "suggested_title": "Título atractivo para la web (máx 80 caracteres)",
  "category": "festival|produccion|estreno|convocatoria|otro",
  "canarian_entities": ["nombre festival, director, productora o película canaria mencionada"]
}
PROMPT;

        // ── Few-shot examples (6 casos representativos de la rúbrica) ─────────
        $fewShot = [
            [
                'user' => "Fecha actual: 15 de abril de 2026\nFUENTE: La Provincia\nTÍTULO ORIGINAL: La 23ª edición del LPA Film Festival presenta su sección 'Canarias Cinema' que arranca el próximo 24 de abril en los multicines Monopol.\nCONTENIDO:\nEl festival internacional de cine de Las Palmas de Gran Canaria abre su programación con una sección dedicada íntegramente al audiovisual canario.",
                'assistant' => '{"summary":"El LPA Film Festival celebra su 23ª edición con la sección \'Canarias Cinema\', que arrancará el 24 de abril en los multicines Monopol de Las Palmas. Una cita imprescindible para los amantes del cine local con programación centrada en la producción audiovisual de las islas.","tags":["festival","cine canario","LPA Film Festival","Canarias Cinema","Las Palmas"],"relevance_score":10,"suggested_title":"LPA Film Festival arranca el 24 de abril con su sección \'Canarias Cinema\'","category":"festival","canarian_entities":["LPA Film Festival","Canarias Cinema","multicines Monopol"]}',
            ],
            [
                'user' => "Fecha actual: 15 de abril de 2026\nFUENTE: Diario de Avisos\nTÍTULO ORIGINAL: Tenerife incentiva los rodajes internacionales: una gran producción de ciencia ficción de Prime Video comenzará a rodarse en el Teide el próximo mes.\nCONTENIDO:\nLa producción internacional aprovechará los incentivos fiscales de la Zona Especial Canaria para rodar escenas en el Parque Nacional del Teide durante mayo.",
                'assistant' => '{"summary":"Una superproducción de ciencia ficción de Prime Video ha elegido el Teide como escenario de rodaje, aprovechando los incentivos fiscales de Tenerife. El proyecto, que arranca en mayo, supone un impulso significativo para la industria audiovisual canaria.","tags":["rodaje","incentivos fiscales","Tenerife","producción internacional","Prime Video"],"relevance_score":9,"suggested_title":"Prime Video rodará una superproducción de ciencia ficción en el Teide","category":"produccion","canarian_entities":["Teide","Zona Especial Canaria"]}',
            ],
            [
                'user' => "Fecha actual: 15 de abril de 2026\nFUENTE: Cine de las Islas\nTÍTULO ORIGINAL: El director tinerfeño Juan Pérez gana el premio al mejor cortometraje en un festival de Berlín.\nCONTENIDO:\nJuan Pérez, nacido en Santa Cruz de Tenerife, se alzó ayer con el galardón al mejor cortometraje en el prestigioso festival alemán con su obra 'Sal y Ceniza'.",
                'assistant' => '{"summary":"El cineasta tinerfeño Juan Pérez ha ganado el galardón al mejor cortometraje en un festival de Berlín con su obra \'Sal y Ceniza\', consolidando la proyección internacional del talento audiovisual canario.","tags":["cortometraje","premio","talento canario","festival internacional","Berlín"],"relevance_score":8,"suggested_title":"El tinerfeño Juan Pérez gana el mejor cortometraje en Berlín","category":"produccion","canarian_entities":["Juan Pérez","Sal y Ceniza"]}',
            ],
            [
                'user' => "Fecha actual: 15 de abril de 2026\nFUENTE: El Día\nTÍTULO ORIGINAL: Concierto de la Orquesta Sinfónica de Tenerife en el Auditorio: un repertorio dedicado a los clásicos de Broadway.\nCONTENIDO:\nLa OST interpretará este sábado obras de Bernstein, Sondheim y Lloyd Webber en el Auditorio de Tenerife.",
                'assistant' => '{"summary":"La Orquesta Sinfónica de Tenerife ofrece un concierto dedicado a los clásicos de Broadway en el Auditorio de Tenerife. El evento es cultural pero no guarda relación directa con el sector cinematográfico canario.","tags":["música","concierto","Tenerife","Broadway","Orquesta Sinfónica"],"relevance_score":4,"suggested_title":"La Sinfónica de Tenerife llena el Auditorio con los clásicos de Broadway","category":"otro","canarian_entities":["Orquesta Sinfónica de Tenerife","Auditorio de Tenerife"]}',
            ],
            [
                'user' => "Fecha actual: 15 de abril de 2026\nFUENTE: El Digital de Canarias\nTÍTULO ORIGINAL: Cartelera de la semana: Estreno de 'Vengadores 15' en los centros comerciales de Las Palmas y Santa Cruz.\nCONTENIDO:\nLa última entrega del universo Marvel llega a los cines canarios con sesiones a partir del viernes.",
                'assistant' => '{"summary":"\'Vengadores 15\' llega a los cines de Las Palmas y Santa Cruz. Se trata de una superproducción de Hollywood sin impacto en la industria audiovisual local ni en la creación canaria.","tags":["cartelera","estreno","Hollywood","Las Palmas","Santa Cruz"],"relevance_score":3,"suggested_title":"\'Vengadores 15\' ya en cartelera en Las Palmas y Santa Cruz","category":"estreno","canarian_entities":[]}',
            ],
            [
                'user' => "Fecha actual: 15 de abril de 2026\nFUENTE: Canarias7\nTÍTULO ORIGINAL: El Cabildo de Gran Canaria anuncia obras de mejora en la GC-1 a la altura de Telde.\nCONTENIDO:\nLas obras comenzarán el lunes y afectarán al carril derecho durante tres semanas.",
                'assistant' => '{"summary":"El Cabildo de Gran Canaria ha comunicado el inicio de obras de mejora en la carretera GC-1 a la altura de Telde. La noticia es de interés local pero no guarda relación con el ámbito cultural ni cinematográfico.","tags":["infraestructuras","Cabildo","Gran Canaria","Telde","obras"],"relevance_score":1,"suggested_title":"Obras en la GC-1 a la altura de Telde durante tres semanas","category":"otro","canarian_entities":[]}',
            ],
        ];

        // Construir el array de mensajes: system + 6 pares few-shot + tarea real
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($fewShot as $example) {
            $messages[] = ['role' => 'user',      'content' => $example['user']];
            $messages[] = ['role' => 'assistant', 'content' => $example['assistant']];
        }

        $messages[] = ['role' => 'user', 'content' => implode("\n", [
            "Fecha actual: {$today}",
            "FUENTE: {$sourceName}",
            "TÍTULO ORIGINAL: {$item->title}",
            "CONTENIDO:",
            $content,
        ])];

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-4o-mini',
                'messages'        => $messages,
                'response_format' => ['type' => 'json_object'],
                'max_tokens'      => 450,
                'temperature'     => 0.2,
            ]);

        if (! $response->successful()) {
            Log::warning("[ProcessNewsItemWithAIJob] OpenAI HTTP {$response->status()} para item #{$item->id}");
            return null;
        }

        $body    = $response->json();
        $rawJson = $body['choices'][0]['message']['content'] ?? null;

        if (! $rawJson) {
            return null;
        }

        $decoded = json_decode($rawJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("[ProcessNewsItemWithAIJob] JSON inválido para item #{$item->id}: {$rawJson}");
            return null;
        }

        // Sanitizar relevance_score
        if (isset($decoded['relevance_score'])) {
            $decoded['relevance_score'] = max(0, min(10, (int) $decoded['relevance_score']));
        }

        return $decoded;
    }
}
