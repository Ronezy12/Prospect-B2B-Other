# Prospection & Marketing — Entreprise AR

Module de prospection commerciale et marketing B2B (France / UE), application Laravel autonome.

## Documentation

- [Audit initial et cahier technique](docs/01-audit-et-cahier-technique.md)
- [Installation, configuration, sauvegardes et maintenance](docs/02-installation-exploitation.md)
- [Avancement par phase et règles implémentées](docs/03-avancement.md)
- [Tester la plateforme (aperçu, Docker, comptes de démo)](docs/04-tester-la-plateforme.md)

## Tester en une commande

```bash
docker compose up --build
```

Puis http://localhost:8000 — comptes de démonstration dans [docs/04-tester-la-plateforme.md](docs/04-tester-la-plateforme.md).

## Démarrage rapide (développement)

```bash
composer install
cp .env.example .env && php artisan key:generate
# Développement local sur SQLite : DB_CONNECTION=sqlite dans .env
touch database/database.sqlite
php artisan migrate --seed
php artisan pm:create-admin vous@exemple.fr   # ou : php artisan pm:demo (comptes et données fictives)
php artisan serve            # puis, dans un autre terminal :
php artisan queue:work --queue=imports,default
```

Tests : `php artisan test`.
