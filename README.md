# Rebase Database Command

Adds a `db:rebase` command to Laravel Artisan that generates a new migration based on current DB schema. This effectively allows you to reset or rebase your migrations. For example, if you have an existing database with no migrations, this will generate one to recreate it. Another possibility is that you have hundreds of migrations and you want to clean it all up and start over with a fresh slate.

## Installation

### 1. Install with Composer
```bash
composer require --dev oasis/laravel-rebase-database
```

### 2. Add to `app/config/app.php`
```
    'providers' => array(
        // ...
        Oasis\Providers\RebaseDatabaseServiceProvider::class,
    ),
```

This registers the Artisan command with Laravel.

## Usage

`php artisan db:rebase <database>`


