# Product Importer

A Symfony 7.4 data import & processing pipeline for importing, validating, transforming, and exporting product data (CSV/JSON/XML/APIs). 

Built with Doctrine ORM, PostgreSQL, and Symfony Messenger for async processing.

## Tech Stack
- **Framework**: Symfony 7.4 LTS
- **Database**: PostgreSQL (Docker)
- **ORM**: Doctrine 3.6+
- **Testing**: PHPUnit 13
- **PHP**: 8.4+

## Quick Start

### Initial Setup

```bash
composer install
docker-compose up -d
php bin/console doctrine:migrations:migrate
```

Example data files are in `fixtures/` — ready to import for testing.

### Import Data (Async)

Imports are processed asynchronously via Symfony Messenger. Two-step workflow:

**Step 1: Queue the import**
```bash
# CSV example
php bin/console import:products fixtures/products.csv

# JSON example (additional PC parts dataset)
php bin/console import:products fixtures/products.json
```
Output:
```
✓ Import queued (Job ID: 1)
  Run: php bin/console messenger:consume async
```

**Step 2: Consume the queue** (in a separate terminal/worker)
```bash
php bin/console messenger:consume async
```

The worker processes messages in the queue and persists data to the database.

### Monitor Job Status

```bash
# List recent jobs
php bin/console import:status

# View specific job (with logs)
php bin/console import:status 1
```

### Optional Commands

```bash
# Dry-run: validate without importing
php bin/console import:products fixtures/products.csv --dry-run

# Update mode: overwrite existing SKUs instead of skipping
php bin/console import:products fixtures/products.csv --allow-updates

# Export to CSV or JSON (default output: var/share/export/)
php bin/console import:export --format=csv
php bin/console import:export --format=json --category="Graphics Cards"

# Cleanup old jobs (30 days default)
php bin/console import:cleanup --older-than=30

# Supported formats: CSV, JSON, XML, YAML (file), HTTP (CSV/JSON)
php bin/console import:products https://example.com/products.csv
php bin/console import:products fixtures/products.xml
php bin/console import:products fixtures/products.yaml
```

## Testing

### One-time setup

Create `.env.test.local` (gitignored) with credentials for a dedicated test database:

```dotenv
DATABASE_URL="postgresql://<user>:<pass>@127.0.0.1:5432/product_importer_test?serverVersion=16&charset=utf8"
```

Create the test database and run migrations:

```bash
docker-compose exec database psql -U <user> -d product_importer -c "CREATE DATABASE product_importer_test;"
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
```

### Running tests

```bash
vendor/bin/phpunit                        # all tests
vendor/bin/phpunit tests/Service/         # unit tests only
vendor/bin/phpunit tests/Command/         # integration tests only
vendor/bin/phpunit tests/Message/         # message handler tests
```

## How It Works

**Async Pipeline**: Command → Queue (Doctrine transport) → Worker → Handler → Database

- Job status lifecycle: `pending` → `running` → `completed/failed`
- Retries on failure: 3 attempts with 2x exponential backoff
- Messages stored in `messenger_messages` table; monitor with `import:status`

**Worker Modes**:
```bash
# Dev: process one message, exit
php bin/console messenger:consume async --limit=1

# Prod: continuous processing (use supervisor/systemd to keep running)
php bin/console messenger:consume async
```
