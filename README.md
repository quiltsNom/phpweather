# PHP Weather App

Free weather data from Open-Meteo (no API key). PHP 8.3 + Apache, Dockerized.

## Run locally
    docker compose up -d --build
    open http://localhost:8080        # JSON: /?city=Tokyo&format=json   Health: /health.php

## Deploy to Render (free, gives a public URL)
1. Push this folder to a GitHub repo.
2. Render → New → Web Service → connect the repo → Runtime: Docker → Instance: Free.
3. Health check path: /health.php → Deploy. Render sets $PORT automatically.

### One-click (Blueprint)
This repo includes `render.yaml`: Render → New → Blueprint → select this repo → Apply.
