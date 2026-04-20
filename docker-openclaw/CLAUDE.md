# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Docker Compose setup for running [OpenClaw](https://github.com/openclaw/openclaw) — a local AI agent platform — connected to a local Ollama instance.

## Starting / stopping

```bash
docker compose up -d       # Start in background
docker compose down        # Stop
docker compose logs -f     # Follow logs
docker compose pull        # Pull latest image
```

## Ports

- `18789` — Web UI
- `18791` — API

## Architecture

```
docker-compose.yaml        # Single-service compose file
config/                    # Mounted to /home/node/.openclaw (persistent state)
  openclaw.json            # Main app config (models, gateway, agent defaults)
  agents/                  # Agent definitions and session history
  credentials/             # Stored provider credentials
  logs/                    # Application logs
workspace/                 # Mounted to /workspace (agent working directory)
```

## Key config details

- **Model**: `qwen3.5:9b` via local Ollama (`host.docker.internal:11434`)
- **Tools**: Disabled (`OPENCLAW_TOOLS_ENABLED=false`, `OPENCLAW_AGENT_TOOLS=false`)
- **Gateway**: LAN-bound, token auth, local mode
- **Agent defaults**: max 4 concurrent agents, 8 subagents, compaction mode `safeguard`

## Remote access (docker-compose.remote.yaml)

Use this instead of the default compose file when OpenClaw runs on a server accessed from another machine.

```bash
docker compose -f docker-compose.remote.yaml up -d
docker compose -f docker-compose.remote.yaml down
```

**What's different:**
- Adds an nginx reverse proxy in front of OpenClaw (ports are not exposed directly)
- Fixes `host.docker.internal` resolution on Linux via `host-gateway`
- nginx handles WebSocket proxy headers (`Upgrade`, `Connection`) — required for the live chat UI
- nginx adds CORS headers on the API port so browser JS can call it cross-origin
- Long `proxy_read_timeout` / `proxy_send_timeout` (3600s) to prevent dropped connections during slow agent responses

**Files:**
- `docker-compose.remote.yaml` — compose with nginx service
- `config/nginx/nginx.conf` — WebSocket + CORS aware proxy config

**Ports (external):**

| Port  | What             |
|-------|------------------|
| 18789 | Web UI (via nginx on :80) |
| 18791 | API (via nginx)  |

**Firewall:** open both `18789` and `18791` on the server.

## Updating the model or Ollama URL

Edit `docker-compose.yaml` environment vars (`OLLAMA_BASE_URL`, `PRIMARY_MODEL`) or update `config/openclaw.json` directly, then restart the container.

## Config file caution

`config/openclaw.json` is live application state (not just config). The app writes backups (`.bak`, `.bak.1`, etc.) before modifying it. Edit carefully when the container is stopped.
