<?php

namespace Database\Seeders;

use App\Models\NewsSource;
use Illuminate\Database\Seeder;

/**
 * Fuentes de cine canario — verificadas el 2026-04-14.
 *
 * RSS CONFIRMADOS (curl -L da RSS válido):
 *   Muestra Lanzarote  → https://www.muestradecinedelanzarote.com/feed/ ✓
 *   LPA Film Festival  → https://www.lpafilmfestival.com/feed            ✓
 *
 * RSS PENDIENTES DE VERIFICAR EN VIVO (WordPress /feed estándar, pueden
 *   estar bloqueando bots desde esta red — probar desde el servidor de prod):
 *   Animayo, Isla Calavera, De Sal y Lava, CAC
 *   → Configurados como RSS con fallback a scraping si falla 3 veces
 *
 * SCRAPING ESTÁTICO:
 *   TEA Tenerife    → teatenerife.es/noticias/   (200 OK)
 *   lpacultura.com  → lpacultura.com/news          (200 OK, app Laravel custom)
 *   Isla Calavera   → festivalislacalavera.com/noticias/ (200 OK, 5 artículos detectados)
 *
 * SCRAPING GOV/JS (estructura compleja, marcar como "needs_review" si falla):
 *   Teatro Guiniguada, Filmoteca Canaria, Teatro Leal
 */
class NewsSourceSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('NewsSourceSeeder no se ejecuta en producción (usa el panel editorial para gestionar fuentes).');
            return;
        }

        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        NewsSource::truncate();
        \App\Models\NewsItem::truncate();
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $sources = [

            // ════════════════════════════════════════════════
            //  PRENSA CANARIA — RSS activo y verificado
            //  (garantizan contenido inmediato; la IA filtrará
            //   por relevancia cinematográfica en el inbox)
            // ════════════════════════════════════════════════

            [
                'name'                 => 'Canarias7 — Cine',
                'url'                  => 'https://www.canarias7.es/rss/2.0/?section=/cultura/cine',
                'type'                 => 'rss',
                'check_interval_hours' => 6,
                'is_active'            => true,
                'selector_config'      => null,
            ],

            // ════════════════════════════════════════════════
            //  RSS VERIFICADOS
            // ════════════════════════════════════════════════

            [
                'name'                 => 'Muestra de Cine de Lanzarote',
                'url'                  => 'https://www.muestradecinedelanzarote.com/feed/',
                'type'                 => 'rss',
                'check_interval_hours' => 24,
                'is_active'            => true,
                'selector_config'      => null,
            ],

            [
                'name'                 => 'LPA Film Festival — Noticias',
                'url'                  => 'https://www.lpafilmfestival.com/feed',
                'type'                 => 'rss',
                'check_interval_hours' => 12,
                'is_active'            => true,
                'selector_config'      => null,
            ],

            // ════════════════════════════════════════════════
            //  RSS A VERIFICAR EN PROD (WordPress estándar)
            //  Si falla 3 veces → se marcará "needs_review"
            // ════════════════════════════════════════════════

            [
                'name'                 => 'Animayo — Festival Internacional Gran Canaria',
                'url'                  => 'https://www.animayo.com/feed',
                'type'                 => 'rss',
                'check_interval_hours' => 12,
                'is_active'            => true,
                'selector_config'      => null,
            ],

            [
                'name'                 => 'Festival Isla Calavera',
                'url'                  => 'https://festivalislacalavera.com/feed',
                'type'                 => 'rss',
                'check_interval_hours' => 24,
                'is_active'            => true,
                'selector_config'      => null,
            ],

            [
                'name'                 => 'De Sal y Lava — Asociación de Cineastas Canarios',
                'url'                  => 'https://asociacioncineastascanarias.org/feed',
                'type'                 => 'rss',
                'check_interval_hours' => 24,
                'is_active'            => true,
                'selector_config'      => null,
            ],

            [
                'name'                 => 'Clúster Audiovisual Canario (CAC)',
                'url'                  => 'https://webcac.canariasav.com/feed',
                'type'                 => 'rss',
                'check_interval_hours' => 12,
                'is_active'            => true,
                'selector_config'      => null,
            ],

            // ════════════════════════════════════════════════
            //  SCRAPING ESTÁTICO — Verificado HTML estático
            // ════════════════════════════════════════════════

            [
                'name'                 => 'TEA Tenerife — Espacio de las Artes (Cine)',
                'url'                  => 'https://teatenerife.es/noticias/',
                'type'                 => 'scraping',
                'check_interval_hours' => 12,
                'is_active'            => true,
                'selector_config'      => [
                    'items'       => 'article, .post, .entry',
                    'title'       => 'h2 a, h3 a, .entry-title a',
                    'link'        => 'h2 a, h3 a, .entry-title a',
                    'description' => '.entry-summary, .excerpt, p',
                ],
            ],

            [
                'name'                 => 'Isla Calavera — Noticias (scraping)',
                // RSS no responde desde esta red; fallback a scraping HTML
                'url'                  => 'https://festivalislacalavera.com/noticias/',
                'type'                 => 'scraping',
                'check_interval_hours' => 24,
                'is_active'            => false, // Desactivada: usar la versión RSS primero
                'selector_config'      => [
                    'items'       => 'article, .post',
                    'title'       => 'h2 a, h3 a, .entry-title a',
                    'link'        => 'h2 a, h3 a, .entry-title a',
                    'description' => '.entry-summary, .excerpt, p',
                ],
            ],

            [
                'name'                 => 'lpacultura — Actividades y Cartelera (Las Palmas)',
                'url'                  => 'https://lpacultura.com/news',
                'type'                 => 'scraping',
                'check_interval_hours' => 24,
                'is_active'            => true,
                'selector_config'      => [
                    // lpacultura.com es una app Laravel custom — inspeccionar HTML real
                    'items'       => 'article, .activity-card, .news-card, .card, .item',
                    'title'       => 'h2 a, h3 a, h4 a, .card-title, .activity-title',
                    'link'        => 'a.card-link, h2 a, h3 a, h4 a',
                    'description' => '.card-text, .description, p',
                ],
            ],

            // ════════════════════════════════════════════════
            //  SCRAPING LIFERAY — CCA Gran Canaria
            //  Sin RSS. HTML estático servido por Liferay CMS.
            //  Verificado 2026-05-13: selectores extraídos del HTML real.
            // ════════════════════════════════════════════════

            [
                'name'                 => 'CCA Gran Canaria — Noticias',
                'url'                  => 'https://cca.grancanaria.com/noticias',
                'type'                 => 'scraping',
                'purpose'              => 'news',
                'check_interval_hours' => 12,
                'is_active'            => true,
                'selector_config'      => [
                    // Liferay AssetPublisher: cada noticia en div.caja-evento
                    'items'       => 'div.caja-evento',
                    'title'       => 'span.titulo',
                    'link'        => 'a',
                    'description' => 'div.intro-evento',
                ],
            ],

            [
                'name'                 => 'CCA Gran Canaria — Actividades',
                'url'                  => 'https://cca.grancanaria.com/actividades',
                'type'                 => 'scraping',
                'purpose'              => 'events',
                'check_interval_hours' => 24,
                'is_active'            => true,
                'selector_config'      => [
                    // Liferay AssetPublisher: cada actividad en div.entrada-novedad
                    'items'       => 'div.entrada-novedad',
                    'title'       => 'h1',
                    'link'        => 'a',
                    'description' => 'p',
                ],
            ],

            // ════════════════════════════════════════════════
            //  SCRAPING GOV/JS — Estructura compleja
            //  Se intentará con Guzzle estático.
            //  Si el HTML no contiene los selectores → fallará y se marcará
            //  como "needs_review" tras 3 intentos.
            //  Para contenido dinámico real: instalar Browsershot
            //    composer require spatie/browsershot
            //    npm install puppeteer -g
            //  y cambiar 'type' a 'browsershot' en estas fuentes.
            // ════════════════════════════════════════════════

            [
                'name'                 => 'Teatro Guiniguada — Programación Cine Canario',
                'url'                  => 'https://www.teatroguiniguada.com/cartelera/',
                'type'                 => 'scraping',
                'check_interval_hours' => 48,
                'is_active'            => true,
                'selector_config'      => [
                    'items'       => '.evento, .actividad, article, .show-item',
                    'title'       => 'h2, h3, h4, .titulo, .show-title',
                    'link'        => 'a',
                    'description' => '.fecha, .horario, .sinopsis, p',
                ],
            ],

            [
                'name'                 => 'Filmoteca Canaria — Programación Mensual',
                'url'                  => 'https://www.gobiernodecanarias.org/cultura/entidades/filmotecacanaria/',
                'type'                 => 'scraping',
                'check_interval_hours' => 48,
                'is_active'            => true,
                'selector_config'      => [
                    'items'       => '.actividad, .evento, .programacion, article, tr.pelicula',
                    'title'       => 'h2, h3, .titulo, td strong',
                    'link'        => 'a',
                    'description' => '.fecha, .descripcion, td',
                ],
            ],

            [
                'name'                 => 'Teatro Leal — Cartelera Cine (La Laguna)',
                'url'                  => 'https://www.teatroleal.es/cartelera/',
                'type'                 => 'scraping',
                'check_interval_hours' => 48,
                'is_active'            => true,
                'selector_config'      => [
                    'items'       => '.evento, .pelicula, .show, article',
                    'title'       => 'h2 a, h3 a, .titulo a, .event-title',
                    'link'        => 'h2 a, h3 a, .titulo a',
                    'description' => '.fecha, .horario, p',
                ],
            ],

        ];

        foreach ($sources as $source) {
            NewsSource::create($source);
        }

        $rss      = collect($sources)->where('type', 'rss')->count();
        $scraping = collect($sources)->where('type', 'scraping')->count();

        $this->command->info("NewsSourceSeeder: {$rss} RSS + {$scraping} scraping = " . count($sources) . ' fuentes');
        $this->command->line('');
        $this->command->line('  RSS prensa canaria (activo): Canarias7 Cine ✓');
        $this->command->line('  RSS verificados: Muestra Lanzarote ✓, LPA Film Festival ✓');
        $this->command->line('  RSS a verificar en prod: Animayo, Isla Calavera, De Sal y Lava, CAC');
        $this->command->line('  Scraping estático: TEA Tenerife, lpacultura, Isla Calavera (fallback)');
        $this->command->line('  Scraping Liferay: CCA Gran Canaria Noticias, CCA Gran Canaria Actividades');
        $this->command->line('  Scraping Gov/JS: Guiniguada, Filmoteca Canaria, Teatro Leal');
        $this->command->line('');
        $this->command->warn('  Para las fuentes Gov/JS con contenido dinámico instala Browsershot:');
        $this->command->warn('    composer require spatie/browsershot && npm install puppeteer -g');
    }
}
