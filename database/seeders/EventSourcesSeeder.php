<?php

namespace Database\Seeders;

use App\Models\NewsSource;
use Illuminate\Database\Seeder;

/**
 * Fuentes de eventos cinematográficos en Canarias — purpose='events'.
 *
 * Verificación real 2026-04-22:
 *   type='rss'      → feed XML estático, no requiere selectores CSS
 *   type='scraping' → HTML estático con selectores CSS
 *   is_active=false → URL caída, JS-rendered o sin contenido verificable
 *
 * RSS confirmados:
 *   - LPA Film Festival     /feed/           (WordPress)
 *   - Isla Calavera         /feed/           (WordPress)
 *   - Muestra Lanzarote     /feed/           (WordPress + WPML)
 *   - Teatro Leal           /RSS_Eventos_Leal.xml (CMS propio)
 *
 * Inactivas con motivo documentado:
 *   - MiradasDoc       → certificado SSL expirado (reactivar cuando lo renueven)
 *   - Animayo          → programación solo en JS, sin sección de blog/noticias
 *   - FICMEC           → servidor devuelve error intermitente
 *   - Filmoteca Canaria → URL retorna 404 (pendiente URL correcta)
 *   - TEA Tenerife     → programación JS-rendered
 *   - Teatro Guiniguada → conexión rechazada
 *   - lpacultura       → SPA Livewire/Laravel, sin HTML estático
 *   - CICCA            → DNS no resuelve
 *   - Cines Monopol    → URL inválida
 */
class EventSourcesSeeder extends Seeder
{
    public function run(): void
    {
        NewsSource::where('purpose', 'events')->delete();

        $sources = [

            // ══════════════════════════════════════════════════════════
            //  RSS — feeds verificados, se parsean como XML
            // ══════════════════════════════════════════════════════════

            [
                'name'                 => 'LPA Film Festival — RSS',
                'url'                  => 'https://www.lpafilmfestival.com/feed/',
                'type'                 => 'rss',
                'purpose'              => 'events',
                'check_interval_hours' => 12,
                'is_active'            => true,
                'selector_config'      => [],
            ],

            [
                'name'                 => 'Festival Isla Calavera — RSS',
                'url'                  => 'https://festivalislacalavera.com/feed/',
                'type'                 => 'rss',
                'purpose'              => 'events',
                'check_interval_hours' => 24,
                'is_active'            => true,
                'selector_config'      => [],
            ],

            [
                'name'                 => 'Muestra de Cine de Lanzarote — RSS',
                'url'                  => 'https://www.muestradecinedelanzarote.com/feed/',
                'type'                 => 'rss',
                'purpose'              => 'events',
                'check_interval_hours' => 24,
                'is_active'            => true,
                'selector_config'      => [],
            ],

            [
                'name'                 => 'Teatro Leal — RSS Eventos',
                'url'                  => 'https://www.teatroleal.es/RSS_Eventos_Leal.xml',
                'type'                 => 'rss',
                'purpose'              => 'events',
                'check_interval_hours' => 48,
                'is_active'            => true,
                'selector_config'      => [],
            ],

            // ══════════════════════════════════════════════════════════
            //  INACTIVAS — documentadas para revisión futura
            // ══════════════════════════════════════════════════════════

            [
                'name'                 => 'MiradasDoc — Programación',
                // Certificado SSL expirado. Reactivar cuando lo renueven.
                'url'                  => 'https://miradasdoc.com/programacion/',
                'type'                 => 'scraping',
                'purpose'              => 'events',
                'check_interval_hours' => 24,
                'is_active'            => false,
                'selector_config'      => [
                    'items'       => 'article, .program-item, .evento',
                    'title'       => 'h2 a, h3 a, .titulo',
                    'link'        => 'h2 a, h3 a, a',
                    'date'        => '.fecha, .date, time',
                    'venue'       => '.lugar, .venue, .sala',
                    'description' => '.sinopsis, .excerpt, p',
                ],
            ],

            [
                'name'                 => 'Animayo Gran Canaria — Noticias',
                // Programación JS-rendered. Sin sección de blog verificada.
                'url'                  => 'https://animayo.com/noticias/',
                'type'                 => 'scraping',
                'purpose'              => 'events',
                'check_interval_hours' => 24,
                'is_active'            => false,
                'selector_config'      => [
                    'items'       => 'article, .news-item, .post',
                    'title'       => 'h2 a, h3 a, .entry-title a',
                    'link'        => 'h2 a, h3 a, .entry-title a',
                    'date'        => '.entry-date, time, .fecha',
                    'description' => '.entry-summary, .excerpt, p',
                ],
            ],

            [
                'name'                 => 'FICMEC — Web',
                // Servidor con errores intermitentes. Reactivar cuando esté estable.
                'url'                  => 'https://ficmec.com/',
                'type'                 => 'scraping',
                'purpose'              => 'events',
                'check_interval_hours' => 48,
                'is_active'            => false,
                'selector_config'      => [
                    'items'       => 'article, .event, .programa-item',
                    'title'       => 'h2 a, h3 a, .entry-title a',
                    'link'        => 'h2 a, h3 a, .entry-title a',
                    'date'        => '.fecha, .date, time',
                    'description' => '.excerpt, .sinopsis, p',
                ],
            ],

            [
                'name'                 => 'Filmoteca Canaria — Programación',
                // URL retorna 404. Pendiente encontrar URL correcta en gobiernodecanarias.org
                'url'                  => 'https://www.gobiernodecanarias.org/cultura/entidades/filmotecacanaria/',
                'type'                 => 'scraping',
                'purpose'              => 'events',
                'check_interval_hours' => 48,
                'is_active'            => false,
                'selector_config'      => [
                    'items'       => '.actividad, .evento, article',
                    'title'       => 'h2 a, h3 a, .field-titulo',
                    'link'        => 'h2 a, h3 a, a',
                    'date'        => '.field-fecha, .fecha, time',
                    'venue'       => '.field-lugar, .sala',
                    'description' => '.field-body p, p',
                ],
            ],

            [
                'name'                 => 'TEA Tenerife — Programación Cine',
                // Programación cargada con JS. Sin RSS ni blog estático verificado.
                'url'                  => 'https://teatenerife.es/programacion/?tipo=cine',
                'type'                 => 'scraping',
                'purpose'              => 'events',
                'check_interval_hours' => 48,
                'is_active'            => false,
                'selector_config'      => [
                    'items'       => '.actividad, article, .event-card',
                    'title'       => 'h2 a, h3 a, .titulo a',
                    'link'        => 'h2 a, h3 a, .titulo a',
                    'date'        => '.fecha, time, .horario',
                    'venue'       => '.sala, .espacio',
                    'description' => '.descripcion, p',
                ],
            ],

            [
                'name'                 => 'Teatro Guiniguada — Cartelera',
                // Conexión rechazada en verificación 2026-04-22.
                'url'                  => 'https://www.teatroguiniguada.com/cartelera/',
                'type'                 => 'scraping',
                'purpose'              => 'events',
                'check_interval_hours' => 48,
                'is_active'            => false,
                'selector_config'      => [
                    'items'       => '.evento, article, .cartelera-item',
                    'title'       => 'h2 a, h3 a, .titulo',
                    'link'        => 'h2 a, h3 a, a',
                    'date'        => '.fecha, .date, time',
                    'venue'       => '.sala',
                    'description' => '.sinopsis, p',
                ],
            ],

            [
                'name'                 => 'lpacultura — Agenda Cine',
                // SPA Livewire, contenido JS-rendered. No funciona con scraping estático.
                'url'                  => 'https://lpacultura.com/agenda?tipo=cine',
                'type'                 => 'scraping',
                'purpose'              => 'events',
                'check_interval_hours' => 24,
                'is_active'            => false,
                'selector_config'      => [
                    'items'       => '.activity-card, .event-card, article',
                    'title'       => '.card-title, h3 a',
                    'link'        => 'a.card-link, h3 a',
                    'date'        => '.card-date, .fecha, time',
                    'venue'       => '.card-venue, .sala',
                    'description' => '.card-text, p',
                ],
            ],

            [
                'name'                 => 'CICCA — Agenda Cultural',
                // DNS no resuelve (cicicanarias.es). URL pendiente de verificar.
                'url'                  => 'https://www.cicicanarias.es/agenda/',
                'type'                 => 'scraping',
                'purpose'              => 'events',
                'check_interval_hours' => 48,
                'is_active'            => false,
                'selector_config'      => [
                    'items'       => '.event, article',
                    'title'       => 'h2 a, h3 a',
                    'link'        => 'h2 a, h3 a',
                    'date'        => '.date, .fecha, time',
                    'description' => '.description, p',
                ],
            ],

            [
                'name'                 => 'Cines Monopol — Cartelera',
                // URL no responde. Posible contenido dinámico.
                'url'                  => 'https://cinesmonopol.com/cartelera/',
                'type'                 => 'scraping',
                'purpose'              => 'events',
                'check_interval_hours' => 24,
                'is_active'            => false,
                'selector_config'      => [
                    'items'       => '.pelicula, .movie, article',
                    'title'       => 'h2 a, h3, .movie-title',
                    'link'        => 'h2 a, a.movie-link',
                    'date'        => '.sesiones, .horarios, .fecha',
                    'description' => '.sinopsis, p',
                ],
            ],
        ];

        foreach ($sources as $source) {
            NewsSource::create($source);
        }

        $active   = collect($sources)->where('is_active', true)->count();
        $inactive = collect($sources)->where('is_active', false)->count();
        $rss      = collect($sources)->where('type', 'rss')->where('is_active', true)->count();

        $this->command->info("EventSourcesSeeder: {$active} activas + {$inactive} inactivas = " . count($sources) . ' fuentes');
        $this->command->line('');
        $this->command->line("  RSS activos ({$rss}): LPA Film Festival, Isla Calavera, Muestra Lanzarote, Teatro Leal");
        $this->command->line('  Inactivas: MiradasDoc (SSL), Animayo (JS), FICMEC (servidor), Filmoteca (404),');
        $this->command->line('             TEA (JS), Guiniguada (conexión), lpacultura (JS), CICCA (DNS), Monopol (URL)');
        $this->command->line('');
        $this->command->warn('  Para activar fuentes inactivas: corregir URL o añadir soporte Browsershot.');
    }
}
