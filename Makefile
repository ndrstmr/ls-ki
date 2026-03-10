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
