.PHONY: up up-dev up-mistral down logs sf migrate worker-logs switch-model status build help

## Stack starten (Standard: Llama 3.3 70B, Produktion)
up:
	docker compose -f docker-compose.yml -f docker-compose.llama-70b.yml up -d

## Stack starten (lokale Entwicklung, Mock-LLM, kein vLLM)
up-dev:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

## Stack starten mit Mistral Small 3.1
up-mistral:
	docker compose -f docker-compose.yml -f docker-compose.mistral-small.yml up -d

## Stack stoppen
down:
	docker compose down

## Alle Logs (live)
logs:
	docker compose logs -f

## Images neu bauen
build:
	docker compose build

## Symfony-Konsole: make sf CMD="debug:router"
sf:
	docker compose exec php php bin/console $(CMD)

## Doctrine-Migrationen
migrate:
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

## Messenger Worker-Logs
worker-logs:
	docker compose logs -f messenger_worker

## Modell wechseln: make switch-model FILE=docker-compose.mistral-small.yml
switch-model:
	./switch-model.sh $(FILE)

## Status + GPU-Auslastung
status:
	@docker compose ps
	@echo "---"
	@nvidia-smi --query-gpu=name,memory.used,memory.total,utilization.gpu --format=csv 2>/dev/null || echo "(nvidia-smi nicht verfügbar)"

## Diese Hilfe anzeigen
help:
	@grep -E '^##' Makefile | sed 's/## //'

## ── Demo (CuP&Connect) ───────────────────────────────────────────────────────

## Demo-Stack starten (Llama 70B + ttyd + SPA)
up-demo:
	docker compose \
	  -f docker-compose.yml \
	  -f docker-compose.llama-70b.yml \
	  -f docker-compose.demo.yml \
	  up -d
	@echo ""
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo "  LS-KI Demo-Stack gestartet"
	@echo ""
	@echo "  Präsentation:  http://$(shell hostname -I | awk '{print $$1}')/demo/"
	@echo "  Terminal:      http://$(shell hostname -I | awk '{print $$1}')/terminal/"
	@echo "  API:           http://$(shell hostname -I | awk '{print $$1}')/api/health"
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

## Demo-Overlay stoppen (Basis-Stack bleibt)
down-demo:
	docker compose \
	  -f docker-compose.yml \
	  -f docker-compose.llama-70b.yml \
	  -f docker-compose.demo.yml \
	  down

## Shell im ttyd-Container öffnen
ttyd-shell:
	docker exec -it ls-ki-ttyd bash

## ttyd Container-Logs
ttyd-logs:
	docker compose -f docker-compose.yml -f docker-compose.demo.yml logs -f ttyd

## GitHub CLI im ttyd-Container einrichten (Token + gh copilot Extension)
gh-setup:
	docker exec -it ls-ki-ttyd bash -c "gh auth login && gh extension install github/gh-copilot && echo '✓ GitHub Copilot CLI bereit'"

## Pre-Demo Checklist: Container-Status, API-Health, GPU-VRAM
demo-check:
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo "  Pre-Demo Checklist"
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo ""
	@docker compose ps --format "table {{.Name}}\t{{.Status}}" 2>/dev/null || true
	@echo ""
	@curl -s http://localhost/api/health | python3 -m json.tool 2>/dev/null || echo "  ⚠ API nicht erreichbar"
	@echo ""
	@nvidia-smi --query-gpu=name,memory.used,memory.total --format=csv,noheader 2>/dev/null \
	  | awk -F', ' '{printf "  GPU: %s · VRAM: %s / %s\n", $$1, $$2, $$3}' || true
	@echo ""
