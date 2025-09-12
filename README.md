# AI Featured Image – Local Docker Setup

This repo ships a Docker environment (WordPress + MariaDB + phpMyAdmin + WP-CLI) to test the plugin locally on Windows with Docker Desktop.

## Prerequisites
- Docker Desktop installed and running
- PowerShell (recommended)

## 1) Configure environment
Copy the example env file and adjust if needed:

```powershell
Copy-Item .env.example .env
```

## 2) Start the stack
```powershell
docker compose up -d
```
- WordPress: http://localhost:8080
- phpMyAdmin: http://localhost:8081 (Host: db, User: wordpress, Pass: wordpress)

The plugin directory is mounted into the container at:
`/var/www/html/wp-content/plugins/ai-featured-image`

## 3) Install WordPress via WP-CLI (first run)
```powershell
docker compose run --rm wpcli wp core install `
  --url=http://localhost:8080 `
  --title="Local WP" `
  --admin_user=admin `
  --admin_password=admin `
  --admin_email=admin@example.com `
  --skip-email
```

## 4) Activate the plugin
```powershell
docker compose run --rm wpcli wp plugin activate ai-featured-image
```

## 5) Helpful WP-CLI commands
- List plugins:
```powershell
docker compose run --rm wpcli wp plugin list
```
- Flush permalinks:
```powershell
docker compose run --rm wpcli wp rewrite flush --hard
```

## 6) Stop and clean up
```powershell
docker compose down
```

## Notes
- WP_DEBUG is enabled by default in `docker-compose.yml` for easier debugging.
- If ports 8080/8081 are taken, change them in `docker-compose.yml`.
- On first run, containers will download images; this may take a few minutes.
