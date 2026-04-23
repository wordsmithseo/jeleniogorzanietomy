#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(dirname "$0")"

# 1. Deploy
"$SCRIPT_DIR/deploy.sh"

# 2. Weryfikacja
echo ""
read -rp "Zasoby wysłane na serwer. Sprawdź stronę w przeglądarce. Czy wszystko działa poprawnie? (y/n): " ANSWER

# 3. Git workflow
if [[ "$ANSWER" == "y" || "$ANSWER" == "Y" ]]; then
    read -rp "Treść commita: " COMMIT_MSG
    cd "$SCRIPT_DIR"
    git add .
    git commit -m "$COMMIT_MSG"
    git push origin main
    echo "Zmiany zapisane na GitHubie i wdrożone!"
else
    echo "Przerwano zapisywanie na Git. Napraw błędy i spróbuj ponownie."
    exit 1
fi
