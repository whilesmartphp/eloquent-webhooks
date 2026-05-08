# Installation

You can install the **Eloquent Webhooks** package via composer:

```bash
composer require whilesmart/webhooks
```

## Publishing Assets

After installing the package, you should publish and run the migrations:

```bash
php artisan vendor:publish --tag="webhooks-migrations"
php artisan migrate
```

You can optionally publish the configuration file to customize the default behavior:

```bash
php artisan vendor:publish --tag="webhooks-config"
```

This will create a `config/webhooks.php` file in your application where you can adjust the model configuration, route configuration, and feature flags.
