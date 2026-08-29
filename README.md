# BookingBarber

A barbershop booking application. Customers pick a barber and a service, see the
time slots that are actually free that day, and reserve one.

Built with Laravel 13, Blade + Alpine (Laravel Breeze), Tailwind, and SQLite.

## Requirements

- PHP 8.2+ with the `pdo_sqlite`, `mbstring`, `tokenizer`, `xml` and `zip` extensions
- Composer 2
- Node 20+ and npm

## Setup

```bash
git clone <repository-url> BookingBarber
cd BookingBarber

composer install
npm install

cp .env.example .env
php artisan key:generate
```

The database is SQLite and needs no configuration — Laravel resolves it to
`database/database.sqlite` by default. Create the file and run the migrations:

```bash
touch database/database.sqlite
php artisan migrate
```

To load some barbers, services and working hours to click around with:

```bash
php artisan db:seed
```

## Running it

Two processes: the PHP server and the Vite dev server for assets.

```bash
php artisan serve          # http://localhost:8000
npm run dev                # in a second terminal
```

For a one-off production-style build instead of the dev server:

```bash
npm run build
```

Queued jobs (booking confirmation mail) run on the `database` queue driver, so a
worker needs to be running for mail to actually go out:

```bash
php artisan queue:work
```

In local development `MAIL_MAILER=log`, so confirmation emails are written to
`storage/logs/laravel.log` rather than sent.

## Tests

```bash
./vendor/bin/pest
```

Tests run against an in-memory SQLite database, so they never touch
`database/database.sqlite`.

Run a single file or filter by name:

```bash
./vendor/bin/pest tests/Feature/BookingTest.php
./vendor/bin/pest --filter="rejects overlapping"
```

## Layout

```
app/
  Models/            User, Barber, WorkingHour, Service, Booking
  Services/          Slot availability calculation
  Http/
    Controllers/
    Requests/        Form request validation
database/
  migrations/
  factories/
  seeders/
tests/
  Feature/
  Unit/
```

## Roles

Users carry a `role` of `customer`, `barber` or `admin`. New registrations are
customers. Admin screens for managing barbers, services and bookings are behind
an admin-only check.
