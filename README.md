# CinemaClub

Plataforma social de cine canario. Backend en **Laravel 12** + frontend SPA en **Vue 3 + Vite**.

---

## Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 12, PHP 8.3, Sanctum |
| Frontend | Vue 3, Vite, Tailwind CSS, Pinia |
| Base de datos | MySQL / TiDB Cloud |
| Imágenes | Cloudinary |
| IA editorial | OpenAI GPT-4o-mini |
| Películas | TMDB API |
| Traducción | Azure Translator |
| Deploy | Hostinger (SSH puerto 65002) |

---

## Setup local

### Requisitos

- PHP 8.3+ con extensiones: `pdo_mysql`, `mbstring`, `openssl`, `xml`, `curl`
- Composer 2+
- Node 20+
- MySQL 8+ (o acceso a TiDB Cloud)

### 1. Clonar e instalar dependencias

```bash
git clone <repo-url> cinemaclub
cd cinemaclub

composer install
cd frontend && npm install && cd ..
```

### 2. Configurar entorno

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` y rellena al menos:

| Variable | Descripción |
|---|---|
| `DB_*` | Credenciales MySQL local |
| `CLOUDINARY_URL` + `CLOUDINARY_CLOUD_NAME` | Panel de Cloudinary |
| `OPENAI_API_KEY` | Necesaria para el panel editorial (IA) |
| `TMDB_API_KEY` | Metadatos de películas |
| `SANCTUM_STATEFUL_DOMAINS` | Dominio del frontend (`localhost:5173`) |
| `FRONTEND_URL` | URL del frontend para CORS y emails |

### 3. Base de datos

```bash
php artisan migrate
php artisan db:seed          # Solo en desarrollo — ver nota abajo
```

> **Nota sobre los seeders:** `NewsSourceSeeder` y `EventSourcesSeeder` tienen una guardia que impide ejecutarse en producción (`APP_ENV=production`). En producción, gestiona las fuentes desde el panel editorial.

### 4. Arrancar

```bash
# Terminal 1 — backend
php artisan serve --port=8001

# Terminal 2 — frontend
cd frontend && npm run dev

# Terminal 3 — cola de trabajos (scraping, IA, etc.)
php artisan queue:work
```

Frontend disponible en `http://localhost:5173`.

---

## Estructura del proyecto

```
app/
  Http/Controllers/     # API REST (Films, Editorial, Auth, Events…)
  Models/               # Eloquent (User, Film, NewsSource, NewsItem…)
  Jobs/                 # Cola: scraping, RSS, procesamiento IA
database/
  migrations/           # 43 migraciones en orden cronológico
  seeders/              # Solo para desarrollo local
frontend/
  src/
    views/              # Vistas Vue (HomeView, EditorialInboxView…)
    stores/             # Pinia (auth, films…)
    services/           # api.js (Axios configurado con Sanctum)
routes/
  api.php               # Rutas API REST
  console.php           # Scheduler (scraping c/2h, IA c/1h)
```

---

## Panel editorial

El panel (`/editorial`) permite a admins y editores:

1. Ver noticias capturadas automáticamente por fuentes RSS y scraping
2. Procesarlas con IA (resumen, relevancia, categoría, entidades canarias)
3. Aprobar, rechazar o convertirlas en borradores de artículo
4. Gestionar fuentes — añadir nuevas con **auto-detección RSS vs scraping**

El scheduler ejecuta `CheckNewsSourcesJob` cada 2 horas y `ProcessNewsItemWithAIJob` cada hora.

---

## Deploy (Hostinger)


```
