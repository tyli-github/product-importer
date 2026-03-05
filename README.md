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

```bash
composer install
docker-compose up -d
php bin/console doctrine:migrations:migrate
php bin/console import:products data.csv
```

Note: Docker credentials are configured in `docker-compose.override.yaml` (not committed).

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
```
