#!/bin/bash
set -euo pipefail

HOST="h59.seohost.pl"
PORT="57185"
USER="srv88838"
KEY="$HOME/.ssh/id_nowy_klucz"
SRC="$(dirname "$0")/jg-interactive-map/"
DEST="/home/srv88838/domains/jeleniogorzanietomy.pl/public_html/wp-content/plugins/jg-interactive-map/"

echo "Deploying jg-interactive-map → $HOST:$DEST"

rsync -avzc --delete \
  -e "ssh -p $PORT -i $KEY" \
  --no-perms \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='deploy.sh' \
  "$SRC" "$USER@$HOST:$DEST"

# Ustaw poprawne uprawnienia (644/755) i odśwież timestamps PHP dla cache-bustingu WP
ssh -p "$PORT" -i "$KEY" "$USER@$HOST" "
  find ${DEST} -type f -exec chmod 644 {} \;
  find ${DEST} -type d -exec chmod 755 {} \;
  find ${DEST} -name '*.php' -exec touch {} \;
"

echo "Deploy zakończony."
