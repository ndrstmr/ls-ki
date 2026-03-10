# ls-ki

**Leichte Sprache KI** – ein Symfony-7-Service zur automatischen Übersetzung von Verwaltungstexten (Beamtendeutsch) in [Leichte Sprache](https://www.leichte-sprache.org/) mithilfe von Open-Source-LLMs.

Entwickelt für den öffentlichen Sektor: vollständige Datensouveränität, kein Cloud-Zwang, on-premises betreibbar auf Standard-GPU-Hardware.

[![License: EUPL-1.2](https://img.shields.io/badge/License-EUPL%201.2-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4-purple.svg)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7.2-black.svg)](https://symfony.com/)

---

## Features

- **REST API** – synchrone und asynchrone Übersetzung via HTTP
- **Async-Queue** – Symfony Messenger mit PostgreSQL LISTEN/NOTIFY (kein Redis)
- **Provider-Abstraktion** – Mock-Provider für lokale Entwicklung, vLLM für Produktion
- **Modellwechsel** – Wechsel zwischen LLM-Modellen ohne Code-Änderung
- **Prompt-Versionierung** – Prompt-Templates unter Versionskontrolle
- **Tool Registry** – erweiterbar zur Laufzeit (z. B. Quality-Check-Tool)
- **Vollständig on-premises** – keine Cloud-Abhängigkeit, keine Telemetrie

---

## Architektur

```
┌─────────────┐    ┌───────────────┐    ┌──────────────────┐
│   Nginx     │───▶│  PHP-FPM      │───▶│  vLLM            │
│  (Proxy)    │    │  Symfony 7    │    │  (OpenAI API)    │
└─────────────┘    └───────┬───────┘    └──────────────────┘
                           │
                    ┌──────▼──────┐
                    │ PostgreSQL  │
                    │  (Queue +   │
                    │   Storage)  │
                    └─────────────┘
```

**Stack:**
- PHP 8.4 + Symfony 7.2 (REST API, Messenger, Doctrine)
- vLLM v0.16+ mit OpenAI-kompatibler API
- PostgreSQL 17 (Doctrine Messenger Transport + LISTEN/NOTIFY)
- Nginx 1.27 als Reverse Proxy
- Docker Compose V2

---

## Unterstützte Modelle

| Modell | Parameter | Empfohlen für |
|--------|-----------|---------------|
| Llama 3.3 70B Instruct AWQ | 70B (quantisiert) | Produktion, beste Qualität |
| Mistral Small 3.1 24B | 24B | Schnellere Inferenz, Demo |

Voraussetzung für Produktion: NVIDIA GPU mit ≥ 40 GB VRAM (getestet auf A100-80GB).

---

## Schnellstart (lokale Entwicklung)

### Voraussetzungen

- Docker + Docker Compose V2
- PHP 8.2+ + Composer (für lokale Entwicklung ohne Container)

### Setup

```bash
git clone git@github.com:ndrstmr/ls-ki.git
cd ls-ki

# Datenbankpasswort setzen
echo "DB_PASSWORD=dein-passwort" > .env.local

# Stack starten (ohne GPU, mit Mock-LLM)
docker compose --env-file .env --env-file .env.local \
  -f docker-compose.yml -f docker-compose.dev.yml up -d

# Datenbankschema migrieren
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### API testen

```bash
# Verfügbare Modelle
curl http://localhost/api/models

# Text übersetzen (synchron)
curl -X POST http://localhost/api/translate \
  -H "Content-Type: application/json" \
  -d '{"text": "Der Antragsteller hat gemäß § 22 Abs. 1 SGB II Anspruch auf Übernahme der Kosten der Unterkunft."}'

# Async-Job erstellen
curl -X POST http://localhost/api/jobs \
  -H "Content-Type: application/json" \
  -d '{"input_file": "personalausweis.txt"}'
```

---

## API-Referenz

| Methode | Pfad | Beschreibung |
|---------|------|--------------|
| `POST` | `/api/translate` | Synchrone Übersetzung |
| `POST` | `/api/jobs` | Async-Job erstellen |
| `GET` | `/api/jobs/{id}` | Job-Status abfragen |
| `GET` | `/api/models` | Verfügbare Modelle |
| `GET` | `/api/models/active` | Aktives Modell |
| `PUT` | `/api/config/model` | Modell wechseln |

### Beispiel-Response `POST /api/translate`

```json
{
  "job_id": "018e1234-...",
  "input_text": "Der Antragsteller hat gemäß ...",
  "output_text": "Sie können Geld bekommen.\nDas Geld hilft Ihnen ...",
  "model": "casperhansen/llama-3.3-70b-instruct-awq",
  "processing_time_ms": 3840,
  "prompt_version": "v1.0",
  "quality_check": null
}
```

---

## CLI-Kommandos

```bash
# Datei übersetzen
php bin/console app:translate pfad/zur/datei.txt --output ./ausgabe/

# Inbox verarbeiten (var/storage/inbox/)
php bin/console app:process-inbox --dry-run

# Modelle auflisten
php bin/console app:model:list

# Modell wechseln
php bin/console app:model:switch casperhansen/llama-3.3-70b-instruct-awq
```

---

## Produktion (mit GPU)

```bash
# Llama 3.3 70B (Standard)
docker compose --env-file .env --env-file .env.local \
  -f docker-compose.yml -f docker-compose.llama-70b.yml up -d

# Mistral Small 3.1 24B
docker compose --env-file .env --env-file .env.local \
  -f docker-compose.yml -f docker-compose.mistral-small.yml up -d

# Modell wechseln
./switch-model.sh mistral-small
```

Voraussetzungen für GPU-Betrieb:
- NVIDIA Container Toolkit (CDI-Modus empfohlen)
- `HF_TOKEN` in `.env.local` für Modell-Downloads von Hugging Face

---

## Konfiguration

Alle Einstellungen über Umgebungsvariablen in `.env` / `.env.local`:

| Variable | Beschreibung | Standard |
|----------|-------------|---------|
| `LLM_PROVIDER` | `mock` (lokal) oder `vllm` (Produktion) | `mock` |
| `VLLM_API_URL` | URL des vLLM-Servers | `http://vllm:8000` |
| `APP_ACTIVE_MODEL` | Aktives Modell-ID | Llama 3.3 70B AWQ |
| `PROMPT_VERSION` | Prompt-Template-Version | `v1.0` |
| `DATABASE_URL` | PostgreSQL-Verbindung | – |
| `HF_TOKEN` | Hugging Face Token (Produktion) | – |

---

## Projektstruktur

```
src/
├── Agent/          # TranslationAgent – orchestriert den Übersetzungsprozess
├── Command/        # CLI-Kommandos
├── Controller/     # REST API Controller
├── Dto/            # Request/Response DTOs
├── LlmGateway/     # LLM-Abstraktion (Mock + vLLM)
├── Message/        # Symfony Messenger Messages
├── MessageHandler/ # Async Job Handler
└── Tool/           # Tool Registry + QualityCheckTool

config/
├── packages/       # Symfony Bundle-Konfiguration
├── prompts/        # Versionierte Prompt-Templates
│   └── v1.0/
│       ├── translate.txt
│       └── quality-check.txt
└── services.yaml

docker/
├── nginx/          # Nginx-Konfiguration
├── php/            # PHP-FPM Dockerfile + php.ini
└── postgres/       # PostgreSQL-Konfiguration
```

---

## Entwicklung

```bash
# Makefile-Shortcuts
make up-dev     # Dev-Stack starten
make logs       # Container-Logs
make sf CMD="debug:router"  # Symfony Console

# PHPStan
composer require --dev phpstan/phpstan
vendor/bin/phpstan analyse src/
```

---

## Lizenz

[European Union Public Licence v. 1.2 (EUPL-1.2)](LICENSE)

Copyright © 2026 [Andreas Teumer](https://github.com/ndrstmr)
