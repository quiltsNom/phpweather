---
title: PHP Weather
emoji: 🌤️
colorFrom: blue
colorTo: indigo
sdk: docker
app_port: 7860
pinned: false
---

# PHP Weather App

Free weather data from Open-Meteo (no API key). PHP 8.3 + Apache, Dockerized.

## Run locally
    docker compose up -d --build
    open http://localhost:8080        # JSON: /?city=Tokyo&format=json   Health: /health.php

## Deploy to Hugging Face Spaces (free, no card)
1. https://huggingface.co/new-space → SDK: Docker → Blank → CPU basic (free) → Create.
2. https://huggingface.co/settings/tokens → New token, type **Write**.
3. GitHub repo → Settings → Secrets and variables → Actions:
   - Secret `HF_TOKEN` = the token
   - Variable `HF_SPACE` = `your-hf-username/space-name`
4. Actions → "Deploy to Hugging Face Spaces" → Run workflow. Every push to `main` redeploys.
5. Live at `https://<username>-<space-name>.hf.space`.

## Deploy to Render (free instance, card required for verification)
1. Push this folder to a GitHub repo.
2. Render → New → Web Service → connect the repo → Runtime: Docker → Instance: Free.
3. Health check path: /health.php → Deploy. Render sets $PORT automatically.

### One-click (Blueprint)
This repo includes `render.yaml`: Render → New → Blueprint → select this repo → Apply.
