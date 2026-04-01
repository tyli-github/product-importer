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

**1. Install dependencies**
```bash
composer install
```

**2. Configure environment** (`.env.local`, gitignored):
```env
DATABASE_URL="postgresql://<user>:<password>@127.0.0.1:5432/product_importer?serverVersion=16&charset=utf8"
POSTGRES_DB=product_importer
POSTGRES_USER=<user>
POSTGRES_PASSWORD=<password>
```

**3. Configure Docker** (`.compose.override.yaml`, gitignored, auto-loaded):
```yaml
services:
  database:
    environment:
      POSTGRES_DB: product_importer
      POSTGRES_USER: <user>
      POSTGRES_PASSWORD: <password>
    ports:
      - "5432:5432"
```

**4. Start Docker & initialize database**
```bash
docker-compose up -d
php ./bin/console doctrine:migrations:migrate
```

### Import Data

**Step 1: Queue import** (fixtures available in `fixtures/`)
```bash
php ./bin/console import:products fixtures/products.csv
php ./bin/console import:products fixtures/products.json
```

**Step 2: Consume queue** (separate terminal)
```bash
php ./bin/console messenger:consume async
```

### Monitor Jobs

```bash
php ./bin/console import:status      # list recent
php ./bin/console import:status 1    # view job 1
```

### Optional Commands

```bash
php ./bin/console import:products fixtures/products.csv --dry-run      # validate only
php ./bin/console import:products fixtures/products.csv --allow-updates # overwrite existing SKUs

php ./bin/console import:export --format=csv                 # export to var/share/export/
php ./bin/console import:export --format=json --category=PC  # filter by category

php ./bin/console import:cleanup --older-than=30             # delete jobs older than 30 days

php ./bin/console import:products https://example.com/products.csv  # CSV, JSON, XML, YAML, HTTP
php ./bin/console import:products fixtures/products.xml
```

## Testing

### Setup (one-time)

**1. Create `.env.test.local`** (same credentials, `product_importer_test` database):
```env
DATABASE_URL="postgresql://<user>:<password>@127.0.0.1:5432/product_importer_test?serverVersion=16&charset=utf8"
```

**2. Create a test database & run migrations**:
```bash
docker-compose exec database psql -U <user> -d product_importer -c "CREATE DATABASE product_importer_test;"
APP_ENV=test php ./bin/console doctrine:migrations:migrate --no-interaction
```

### Running tests

```bash
./vendor/bin/phpunit                  # all tests
./vendor/bin/phpunit tests/Service/   # unit tests only
./vendor/bin/phpunit tests/Command/   # integration tests only
./vendor/bin/phpunit tests/Message/   # message handler tests
```

**Code Coverage** (requires [Xdebug](https://xdebug.org/) or [PCOV](https://pecl.php.net/package/pcov)):
```bash
php -m | grep -E 'Xdebug|pcov'  # check if installed
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage/  # HTML report (if available)
```

## How It Works

**Pipeline**: Command → Queue (Doctrine) → Worker → Handler → Database

- Statuses: `pending` → `running` → `completed/failed`
- 3 retries with 2x backoff on failure
- `messenger_messages` table: Messages inserted on queue, deleted after processing (empty = successfully consumed)

**Worker**:
```bash
php ./bin/console messenger:consume async --limit=1  # dev (one message, exit)
php ./bin/console messenger:consume async            # prod (continuous)
```

## License

Portfolio demonstration for evaluation and learning only. See [LICENSE](LICENSE) and [NOTICE](NOTICE) for full terms.
