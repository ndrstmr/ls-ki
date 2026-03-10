#!/bin/bash
# switch-model.sh – Modellwechsel für die Demo
# Verwendung: ./switch-model.sh docker-compose.mistral-small.yml
set -euo pipefail

MODEL_OVERRIDE=${1:?"Verwendung: $0 <override-datei>  (z.B. docker-compose.mistral-small.yml)"}

if [[ ! -f "$MODEL_OVERRIDE" ]]; then
    echo "Fehler: Override-Datei nicht gefunden: $MODEL_OVERRIDE"
    exit 1
fi

echo "Stoppe aktuellen Stack..."
docker compose down

echo "Starte mit Override: $MODEL_OVERRIDE"
docker compose -f docker-compose.yml -f "$MODEL_OVERRIDE" up -d

echo "Warte auf vLLM-Modell-Laden (kann 2-5 Minuten dauern)..."
SECONDS=0
until curl -sf http://localhost:8000/v1/models > /dev/null 2>&1; do
    sleep 10 && echo "  Noch beim Laden... (${SECONDS}s)"
done

echo ""
echo "Modell bereit nach ${SECONDS}s:"
curl -s http://localhost:8000/v1/models | python3 -m json.tool
