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
Create `.env.local` file with the following content:

```dotenv
DATABASE_URL="postgresql://<your_username>:<your_password>@127.0.0.1:5432/product_importer?serverVersion=16&charset=utf8"
POSTGRES_DB=product_importer
POSTGRES_USER=<your_username>
POSTGRES_PASSWORD=<your_password>
```

```bash
composer install
docker-compose --env-file .env.local up -d # use local environment variables
php bin/console doctrine:migrations:migrate
php bin/console import:products data.csv
```
