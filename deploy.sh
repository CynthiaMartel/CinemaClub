#!/usr/bin/env bash
set -e

SSH_HOST="u767148652@46.202.172.42"
SSH_PORT="65002"
REMOTE="$SSH_HOST:~/domains/filmoclub.org"
DIR="$(cd "$(dirname "$0")" && pwd)"

SKIP_BUILD=false
SKIP_FRONTEND=false
SKIP_BACKEND=false
WITH_VENDOR=false

for arg in "$@"; do
  case $arg in
    --no-build)    SKIP_BUILD=true ;;
    --only-frontend) SKIP_BACKEND=true ;;
    --only-backend)  SKIP_FRONTEND=true; SKIP_BUILD=true ;;
    --with-vendor)   WITH_VENDOR=true ;;
  esac
done

# ── Frontend ──────────────────────────────────────────────────────────────────
if [ "$SKIP_FRONTEND" = false ]; then
  if [ "$SKIP_BUILD" = false ]; then
    echo "--- Building frontend ---"
    cd "$DIR/frontend"
    npm run build
    cd "$DIR"
  fi

  echo "--- Uploading frontend (public/) ---"
  rsync -avz --delete \
    --exclude='index.php' \
    --exclude='backend-api/' \
    --exclude='storage/' \
    -e "ssh -p $SSH_PORT" \
    "$DIR/public/" \
    "$REMOTE/public_html/"

  echo "--- Uploading backend-api entry point ---"
  scp -P "$SSH_PORT" \
    "$DIR/public/index.php" \
    "$REMOTE/public_html/backend-api/index.php"
fi

# ── Backend ───────────────────────────────────────────────────────────────────
if [ "$SKIP_BACKEND" = false ]; then
  echo "--- Uploading Laravel app ---"

  EXCLUDES=(
    --exclude='.git/'
    --exclude='frontend/'
    --exclude='public/'
    --exclude='node_modules/'
    --exclude='.env'
    --exclude='*.sql'
    --exclude='storage/logs/'
    --exclude='storage/framework/cache/'
    --exclude='storage/framework/sessions/'
    --exclude='storage/framework/views/'
    --exclude='storage/framework/testing/'
    --exclude='bootstrap/cache/'
  )

  if [ "$WITH_VENDOR" = false ]; then
    EXCLUDES+=(--exclude='vendor/')
  fi

  rsync -avz \
    "${EXCLUDES[@]}" \
    -e "ssh -p $SSH_PORT" \
    "$DIR/" \
    "$REMOTE/laravel/"

  echo "--- Running post-deploy commands on server ---"
  ssh -p "$SSH_PORT" "$SSH_HOST" bash << 'ENDSSH'
    set -e
    cd ~/domains/filmoclub.org/laravel
    # Limpiar caché de bootstrap antes de composer para evitar conflictos
    # si el packages.php local y el vendor del servidor están desincronizados
    rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
    composer install --no-dev --optimize-autoloader
    php artisan migrate --force
    php artisan config:clear
    php artisan cache:clear
    php artisan view:clear
ENDSSH
fi

echo ""
echo "Deploy completado."