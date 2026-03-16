#!/bin/bash
# switch-model.sh – Modellwechsel für LS-KI
# Verwendung: ./switch-model.sh <modell>
#
# Verfügbare Modelle:
#   llama      → Llama 3.3 70B AWQ       (~37 GB VRAM)
#   ministral  → Ministral 3 14B         (~15 GB VRAM)
#   qwen       → Qwen3-Coder-Next AWQ    (~45 GB VRAM)
set -euo pipefail

MODEL=${1:?"Verwendung: $0 <modell>  (llama | ministral | qwen)"}

case "$MODEL" in
    llama)
        OVERRIDE="docker-compose.llama-70b.yml"
        MODEL_ID="/root/.cache/huggingface/llama-3.3-70b-awq"
        ;;
    ministral)
        OVERRIDE="docker-compose.ministral-3-14b.yml"
        MODEL_ID="/root/.cache/huggingface/ministral-3-14b"
        ;;
    qwen)
        OVERRIDE="docker-compose.qwen3-coder-next-awq.yml"
        MODEL_ID="/root/.cache/huggingface/qwen3-coder-next-awq"
        ;;
    *)
        echo "Unbekanntes Modell: $MODEL"
        echo "Verfügbar: llama | ministral | qwen"
        exit 1
        ;;
esac

if [[ ! -f "$OVERRIDE" ]]; then
    echo "Fehler: Override-Datei nicht gefunden: $OVERRIDE"
    exit 1
fi

echo "Wechsle zu: $MODEL ($MODEL_ID)"
echo ""

echo "Stoppe aktuellen vLLM-Container..."
docker compose stop vllm
docker compose rm -f vllm

echo "Starte vLLM mit $OVERRIDE..."
MODEL_ID="$MODEL_ID" docker compose --env-file .env --env-file .env.local \
    -f docker-compose.yml -f "$OVERRIDE" \
    up -d vllm

echo ""
echo "Warte auf vLLM-Modell-Laden (kann 2-5 Minuten dauern)..."
SECONDS=0
until [ "$(docker inspect --format='{{.State.Health.Status}}' ls-ki-vllm-1 2>/dev/null)" = "healthy" ]; do
    sleep 10
    echo "  Noch beim Laden... (${SECONDS}s)"
done

echo ""
echo "vLLM bereit nach ${SECONDS}s – starte PHP + Worker mit neuem Modell..."
MODEL_ID="$MODEL_ID" docker compose --env-file .env --env-file .env.local \
    -f docker-compose.yml -f "$OVERRIDE" \
    up -d --no-deps php messenger_worker

echo ""
echo "Warte auf PHP-Start (Cache-Warmup)..."
sleep 15

echo "Nginx neu starten (aktualisiert upstream PHP-IP)..."
docker compose restart nginx
sleep 3

echo "Aktives Modell (Symfony):"
curl --noproxy '*' -s http://localhost/api/models | python3 -m json.tool
