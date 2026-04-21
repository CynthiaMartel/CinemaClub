<?php

namespace App\Http\Controllers;

use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RecommenderController extends Controller
{
    /**
     * Filtra películas según las preferencias del usuario (SQL puro).
     * Devuelve hasta 30 candidatas ordenadas por valoración de comunidad.
     */
    public function filter(Request $request)
    {
        $genre    = $request->genre;
        $duration = $request->duration;
        $era      = $request->era;
        $country  = $request->country;

        $query = Film::query()
            ->select(['idFilm', 'title', 'genre', 'origin_country', 'duration',
                      'release_date', 'frame', 'vote_average', 'globalRate', 'overview'])
            ->where('duration', '>', 0)
            ->whereNotNull('frame')
            ->where('frame', '!=', '');

        if ($genre) {
            $query->where('genre', 'LIKE', "%{$genre}%");
        }

        if ($duration === 'corta') {
            $query->where('duration', '<', 90);
        } elseif ($duration === 'media') {
            $query->whereBetween('duration', [90, 120]);
        } elseif ($duration === 'larga') {
            $query->where('duration', '>', 120);
        }

        if ($era && $era !== 'all') {
            if ($era === 'clasicos') {
                $query->whereYear('release_date', '<', 1980);
            } elseif ($era === 'recientes') {
                $query->whereYear('release_date', '>=', 2020);
            } else {
                $start = (int) $era;
                $end   = $start + 9;
                $query->whereYear('release_date', '>=', $start)
                      ->whereYear('release_date', '<=', $end);
            }
        }

        if ($country && $country !== 'all') {
            $query->where('origin_country', 'LIKE', "%{$country}%");
        }

        // Priorizar películas bien valoradas por la comunidad
        $query->orderByDesc('globalRate')->orderByDesc('vote_average');

        $films = $query->limit(30)->get()->map(fn ($film) => [
            'id'           => $film->idFilm,
            'title'        => $film->title,
            'genre'        => $film->genre,
            'country'      => $film->origin_country,
            'duration'     => $film->duration,
            'year'         => $film->release_date ? substr($film->release_date, 0, 4) : null,
            'frame'        => $film->frame,
            'globalRate'   => round((float) $film->globalRate, 1),
            'vote_average' => round((float) $film->vote_average, 1),
            'overview'     => $film->overview,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $films,
            'total'   => $films->count(),
        ]);
    }

    /**
     * Rankea las películas filtradas usando OpenAI gpt-4o-mini.
     * Si no hay clave API, devuelve las top 5 por globalRate como fallback.
     */
    public function rank(Request $request)
    {
        $films       = $request->films ?? [];
        $preferences = $request->preferences ?? '';

        if (empty($films)) {
            return response()->json(['success' => false, 'message' => 'No hay películas para rankear'], 400);
        }

        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            return $this->fallbackRanking($films);
        }

        $filmList = collect($films)->take(30)->map(fn ($f, $i) =>
            ($i + 1) . ". [ID:{$f['id']}] {$f['title']} ({$f['year']}) | "
            . "Géneros: {$f['genre']} | "
            . "Valoración comunidad: {$f['globalRate']}/10 | "
            . "TMDB: {$f['vote_average']}/10"
        )->join("\n");

        $systemPrompt = "Eres un experto recomendador de películas para una plataforma cinéfila. "
            . "Tu tarea es elegir las 5 películas más adecuadas de una lista ya filtrada y explicar "
            . "brevemente por qué encajan con las preferencias del usuario. "
            . "Responde SIEMPRE en JSON válido con exactamente este formato: "
            . "[{\"id\": 123, \"explanation\": \"...\"}]. "
            . "Solo devuelves el JSON, sin texto adicional ni bloques de código.";

        $userPrompt = "El usuario busca: {$preferences}\n\n"
            . "Elige las 5 mejores películas de esta lista y explica en 1-2 frases "
            . "por qué cada una encaja con lo que busca:\n\n{$filmList}";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])->timeout(20)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => 'gpt-4o-mini',
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 600,
            ]);

            $content = $response->json('choices.0.message.content', '');

            // Limpiar posibles bloques de código Markdown
            $content = preg_replace('/^```json\s*/m', '', $content);
            $content = preg_replace('/^```\s*/m', '', $content);
            $ranked  = json_decode(trim($content), true);

            if (!is_array($ranked)) {
                throw new \Exception('JSON inválido de OpenAI');
            }

            $filmsById = collect($films)->keyBy('id');

            $result = collect($ranked)
                ->filter(fn ($r) => isset($filmsById[$r['id']]))
                ->map(fn ($r) => [
                    'film'        => $filmsById[$r['id']],
                    'explanation' => $r['explanation'],
                ])
                ->values();

            return response()->json([
                'success'    => true,
                'data'       => $result,
                'ai_powered' => true,
            ]);

        } catch (\Exception $e) {
            return $this->fallbackRanking($films);
        }
    }

    private function fallbackRanking(array $films): \Illuminate\Http\JsonResponse
    {
        $ranked = collect($films)
            ->sortByDesc('globalRate')
            ->take(5)
            ->values()
            ->map(fn ($f) => [
                'film'        => $f,
                'explanation' => 'Seleccionada por su alta valoración en nuestra comunidad.',
            ]);

        return response()->json([
            'success'    => true,
            'data'       => $ranked,
            'ai_powered' => false,
        ]);
    }
}
