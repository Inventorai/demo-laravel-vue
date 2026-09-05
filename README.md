# Inventorai Demo — Laravel + Vue

A working demo app showing how to integrate the [Inventorai Laravel SDK](https://github.com/Inventorai/sdk-laravel) into a Laravel + Vue (Inertia) application.

## What it demonstrates

- **Properties** — List and view properties with addresses, images, and map pins
- **Inspections** — Browse, filter, and edit inspections including areas, items, conditions, and cleanliness ratings
- **Photo uploads** — Upload photos to inspection areas and items via the SDK
- **Phrase autocomplete** — Search and select from pre-built phrase libraries when writing descriptions
- **Real-time updates** — Listen for changes via WebSockets (Reverb) so the UI stays in sync
- **API activity tracker** — See every SDK request in real time (method, endpoint, status, duration)
- **Dashboard** — Property and inspection stats with charts

## Requirements

- PHP 8.3+
- Node.js 18+
- Composer
- An [Inventorai](https://inventorai.co.uk) account with an API token

## Getting started

### 1. Clone the repo

```bash
git clone https://github.com/Inventorai/demo-laravel-vue.git
cd demo-laravel-vue
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and add your API token:

```
INVENTORAI_API_TOKEN=your-token-here
```

You can generate a token from **Team Settings > API** in your [Inventorai dashboard](https://app.inventorai.co.uk).

### 4. Set up the database

The app uses SQLite by default — no database server needed.

```bash
touch database/database.sqlite
php artisan migrate
```

### 5. Create a user

```bash
php artisan tinker
> User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => bcrypt('password')]);
```

### 6. Run the app

```bash
composer dev
```

This starts the Laravel server, queue worker, log watcher, and Vite dev server concurrently. Visit [http://localhost:8000](http://localhost:8000) and log in.

## Project structure

```
app/Http/Controllers/
├── DashboardController.php      # Stats and charts via SDK
├── PropertyController.php       # Property list and detail
├── InspectionController.php     # Inspection CRUD, photo uploads, phrase search
├── SettingsController.php       # API token configuration
└── BroadcastingAuthController.php  # Proxies WebSocket auth to Inventorai API

app/Services/
└── ApiActivityTracker.php       # Logs SDK requests for the activity feed

resources/js/Pages/
├── Dashboard.vue
├── Properties/
│   ├── Index.vue
│   └── Show.vue
├── Inspections/
│   ├── Index.vue
│   └── Show.vue                 # Editable areas/items, photo upload, phrase autocomplete
└── Settings/
    └── Index.vue
```

## Tech stack

- [Laravel 13](https://laravel.com) + [Inertia.js](https://inertiajs.com)
- [Vue 3](https://vuejs.org) + TypeScript
- [Tailwind CSS 4](https://tailwindcss.com) + [shadcn-vue](https://www.shadcn-vue.com)
- [Inventorai Laravel SDK](https://github.com/Inventorai/sdk-laravel), built on the [Inventorai PHP SDK](https://github.com/Inventorai/sdk-php)

## Useful commands

| Command | Description |
|---|---|
| `composer dev` | Start all dev services |
| `composer test` | Run tests |
| `npm run build` | Build frontend for production |

## License

MIT
