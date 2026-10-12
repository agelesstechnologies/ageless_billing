# Clean Laravel installation and admin setup

Requires PHP 8.2+, Composer and MySQL. No administrator account or password is included. No existing database data is included.

1. Create a MySQL database named `ageless_park` (or change the name in `.env`).
2. Run `composer install --no-dev` in the project directory.
3. Copy `.env.example` to `.env` and update the database credentials.
4. Run `php artisan key:generate`.
5. Run `php artisan migrate --force`.
6. Run `php artisan storage:link` if public file storage is needed.
7. Run `php artisan tinker` (if Tinker is installed).

**Note:** Laravel Tinker is not listed in this project's composer.json. Install it with `composer require laravel/tinker` before step 7.

Inside Tinker:

```php
App\Models\User::create([
    'name' => 'Main Admin',
    'email' => 'admin@example.com',
    'password' => 'REPLACE_WITH_YOUR_OWN_STRONG_PASSWORD',
    'role' => 'admin',
    'active' => true,
]);
```

The User model automatically hashes the password. Use role `admin`, not `super_admin`, because existing access checks use `admin`. Replace the example email/password before executing. Alternatively, the existing `php artisan park:install` interactive command creates the first admin without any hardcoded credentials.

Start with `php artisan serve` and open `/login`.
