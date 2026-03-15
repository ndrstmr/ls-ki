# ls-ki

**Version 0.0.1**

**Leichte Sprache KI** – ein Symfony-7-Service zur automatischen Übersetzung von Verwaltungstexten (Beamtendeutsch) in [Leichte Sprache](https://www.leichte-sprache.org/) mithilfe von Open-Source-LLMs.

Entwickelt für den öffentlichen Sektor: vollständige Datensouveränität, kein Cloud-Zwang, on-premises betreibbar auf Standard-GPU-Hardware.

[![License: EUPL-1.2](https://img.shields.io/badge/License-EUPL%201.2-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4-purple.svg)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7.2-black.svg)](https://symfony.com/)
[![Version](https://img.shields.io/badge/Version-0.0.1-green.svg)]()

---

## Features

- **REST API** – synchrone und asynchrone Übersetzung via HTTP
- **Async-Queue** – Symfony Messenger mit PostgreSQL (kein Redis)
- **Provider-Abstraktion** – Mock-Provider für lokale Entwicklung, vLLM für Produktion
- **Modellwechsel** – Wechsel zwischen LLM-Modellen ohne Code-Änderung via `switch-model.sh`
- **Prompt-Versionierung** – Prompt-Templates unter Versionskontrolle
- **Storage-Abstraktion** – austauschbare Job-Speicherung (aktuell: Dateisystem)
- **Tool Registry** – erweiterbar zur Laufzeit (z. B. QualityCheckTool)
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
- PostgreSQL 17 (Doctrine Messenger Transport)
- Nginx 1.27 als Reverse Proxy
- Docker Compose V2

---

## Unterstützte Modelle

| Modell | Parameter | VRAM | Einsatz |
|--------|-----------|------|---------|
| Llama 3.3 70B Instruct AWQ | 70B (quantisiert) | ~37 GB | Übersetzung, Produktion |
| Ministral 3 14B Instruct | 14B (FP8) | ~15 GB | Übersetzung, schnellere Inferenz |
| Qwen3-Coder-Next AWQ 4bit | 80B MoE (quantisiert) | ~45 GB | Code-Generierung |

Voraussetzung: NVIDIA GPU mit ≥ 40 GB VRAM (getestet auf A100-80GB).

---

## Schnellstart (lokale Entwicklung)

### Voraussetzungen

- Docker + Docker Compose V2
- PHP 8.4 + Composer (optional, für Entwicklung außerhalb Container)

### Setup

```bash
git clone https://github.com/ndrstmr/ls-ki.git
cd ls-ki/app

# .env.local anlegen (Pflicht)
echo "DB_PASSWORD=changeme" > .env.local

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
  -d '{"text": "Die Baugenehmigung ist zu erteilen, wenn dem Vorhaben keine öffentlich-rechtlichen Vorschriften entgegenstehen."}'

# Job-Status abfragen
curl http://localhost/api/jobs/<job-id>
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

### Beispiel-Response `POST /api/translate`

```json
{
  "job_id": "018e1234-...",
  "input_text": "Der Antragsteller hat gemäß ...",
  "output_text": "Sie können Geld bekommen.\nDas Geld hilft Ihnen ...",
  "model": "/root/.cache/huggingface/llama-3.3-70b-awq",
  "processing_time_ms": 3840,
  "prompt_version": "v1.0",
  "quality_check": null
}
```

---

## Produktion (mit GPU)

```bash
# .env.local anlegen (siehe .env.prod.dist als Vorlage)
cp .env.prod.dist .env.local
# Werte anpassen: DB_PASSWORD, HF_CACHE_DIR, MODEL_ID, APP_ACTIVE_MODEL

# Llama 3.3 70B (Standard)
docker compose --env-file .env --env-file .env.local \
  -f docker-compose.yml -f docker-compose.llama-70b.yml up -d

# Ministral 3 14B
docker compose --env-file .env --env-file .env.local \
  -f docker-compose.yml -f docker-compose.ministral-3-14b.yml up -d

# Modell wechseln (ohne Stack-Neustart)
./switch-model.sh llama       # Llama 3.3 70B
./switch-model.sh ministral   # Ministral 3 14B
./switch-model.sh qwen        # Qwen3-Coder-Next AWQ
```

---

## Konfiguration

Alle Einstellungen über Umgebungsvariablen in `.env` / `.env.local`:

| Variable | Beschreibung | Standard |
|----------|-------------|---------|
| `LLM_PROVIDER` | `mock` (lokal) oder `vllm` (Produktion) | `mock` |
| `VLLM_API_URL` | URL des vLLM-Servers | `http://vllm:8000` |
| `APP_ACTIVE_MODEL` | Lokaler Pfad zum aktiven Modell | Llama 3.3 70B |
| `MODEL_ID` | Modellpfad für vLLM-Container | Llama 3.3 70B |
| `HF_CACHE_DIR` | Modell-Verzeichnis auf dem Host | `/opt/models/huggingface` |
| `PROMPT_VERSION` | Prompt-Template-Version | `v1.0` |
| `DB_PASSWORD` | PostgreSQL-Passwort | – |

---

## CLI-Kommandos

```bash
# Datei übersetzen
php bin/console app:translate pfad/zur/datei.txt

# Inbox verarbeiten (var/storage/inbox/)
php bin/console app:process-inbox

# Modelle auflisten
php bin/console app:model:list

# Modell wechseln
php bin/console app:model:switch /root/.cache/huggingface/ministral-3-14b
```

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
├── Storage/        # Job-Storage Abstraktion (Interface + LocalImpl)
└── Tool/           # Tool Registry + QualityCheckTool (Stub)

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
# Dev-Stack starten (Mock-LLM, kein GPU nötig)
make up

# Symfony Console
make sf CMD="debug:router"

# Worker-Logs
make worker-logs
```

---

## Lizenz

[European Union Public Licence v. 1.2 (EUPL-1.2)](LICENSE)

Copyright © 2026 [Andreas Teumer](https://github.com/ndrstmr)

---

## Hinweis

Dieses Repository wurde mit Unterstützung von KI-Code-Agenten (Claude Code, Anthropic) entwickelt.
Der Code wurde im Rahmen eines MVP-Sprints erstellt und ist nicht für den produktiven Einsatz ohne
vorherige Prüfung freigegeben. **Die Nutzung erfolgt auf eigene Gefahr.**
