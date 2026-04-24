#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(dirname "$0")"
PLUGIN_FILE="$SCRIPT_DIR/jg-interactive-map/jg-interactive-map.php"

# 1. Analiza zmian przez Claude
FULL_DIFF=$(git -C "$SCRIPT_DIR" diff HEAD -- jg-interactive-map/ || true)
DIFF="${FULL_DIFF:0:8000}"
NEW_FILES=$(git -C "$SCRIPT_DIR" ls-files --others --exclude-standard -- jg-interactive-map/ || true)

if [[ -z "$DIFF" && -z "$NEW_FILES" ]]; then
  echo "Brak zmian w pluginie."
  read -rp "Mimo to deployować? (y/n): " CONT
  [[ "$CONT" != "y" && "$CONT" != "Y" ]] && exit 0
  BUMP="patch"
  SUGGESTED_MSG="Aktualizacja pluginu"
else
  echo "Analizuję zmiany..."

  PROMPT="Jesteś ekspertem od semantic versioning pluginów WordPress.
Na podstawie poniższego git diff oceń:
1. Typ bumpa wersji: 'patch' (poprawki, refactor, style), 'minor' (nowe funkcje, nowe endpointy), 'major' (usunięcia, zmiany API, niekompatybilne)
2. Krótki komunikat commita po polsku, max 72 znaki, czas teraźniejszy (np. 'Dodaje filtrowanie wyników mapy')

Odpowiedz WYŁĄCZNIE w JSON bez żadnego dodatkowego tekstu:
{\"bump\": \"patch\", \"message\": \"...\"}

DIFF:
$DIFF

NOWE PLIKI:
$NEW_FILES"

  ANALYSIS=$(echo "$PROMPT" | claude -p 2>/dev/null || echo '{"bump":"patch","message":"Aktualizacja pluginu"}')

  # Extract first JSON object from response (handles markdown code fence wrapping)
  BUMP=$(echo "$ANALYSIS" | python3 -c "
import sys, json, re
text = sys.stdin.read()
m = re.search(r'\{[^{}]*\}', text, re.DOTALL)
d = json.loads(m.group(0)) if m else {}
print(d.get('bump', 'patch'))
" 2>/dev/null || echo "patch")
  SUGGESTED_MSG=$(echo "$ANALYSIS" | python3 -c "
import sys, json, re
text = sys.stdin.read()
m = re.search(r'\{[^{}]*\}', text, re.DOTALL)
d = json.loads(m.group(0)) if m else {}
print(d.get('message', 'Aktualizacja pluginu'))
" 2>/dev/null || echo "Aktualizacja pluginu")

  [[ -z "$BUMP" ]] && BUMP="patch"
  [[ -z "$SUGGESTED_MSG" ]] && SUGGESTED_MSG="Aktualizacja pluginu"
fi

echo ""
echo "  Typ bumpa : $BUMP"
echo "  Commit    : $SUGGESTED_MSG"
echo ""
read -rp "Zatwierdź Enter lub wpisz własną treść commita: " USER_MSG
COMMIT_MSG="${USER_MSG:-$SUGGESTED_MSG}"

# 2. Bump wersji
CURRENT_VERSION=$(sed -n 's/.*Version: \([0-9]*\.[0-9]*\.[0-9]*\).*/\1/p' "$PLUGIN_FILE" | head -1)
IFS='.' read -r VER_MAJOR VER_MINOR VER_PATCH <<< "$CURRENT_VERSION"
case "$BUMP" in
  major) NEW_VERSION="$((VER_MAJOR + 1)).0.0" ;;
  minor) NEW_VERSION="$VER_MAJOR.$((VER_MINOR + 1)).0" ;;
  *)     NEW_VERSION="$VER_MAJOR.$VER_MINOR.$((VER_PATCH + 1))" ;;
esac
sed -i "s/Version: $CURRENT_VERSION/Version: $NEW_VERSION/" "$PLUGIN_FILE"
sed -i "s/define('JG_MAP_VERSION', '$CURRENT_VERSION')/define('JG_MAP_VERSION', '$NEW_VERSION')/" "$PLUGIN_FILE"
echo "Wersja: $CURRENT_VERSION → $NEW_VERSION"

# 3. Deploy
"$SCRIPT_DIR/deploy.sh"

# 4. Weryfikacja
echo ""
read -rp "Zasoby wysłane na serwer. Sprawdź stronę w przeglądarce. Czy wszystko działa poprawnie? (y/n): " ANSWER

# 5. Git workflow
if [[ "$ANSWER" == "y" || "$ANSWER" == "Y" ]]; then
    cd "$SCRIPT_DIR"
    git add .
    git commit -m "$COMMIT_MSG"
    git pull --rebase origin main
    git push origin main
    echo "Wdrożono i zapisano na GitHubie! [$NEW_VERSION]"
else
    echo "Przerwano. Cofam zmianę wersji..."
    sed -i "s/Version: $NEW_VERSION/Version: $CURRENT_VERSION/" "$PLUGIN_FILE"
    sed -i "s/define('JG_MAP_VERSION', '$NEW_VERSION')/define('JG_MAP_VERSION', '$CURRENT_VERSION')/" "$PLUGIN_FILE"
    echo "Wersja przywrócona do $CURRENT_VERSION. Napraw błędy i spróbuj ponownie."
    exit 1
fi
