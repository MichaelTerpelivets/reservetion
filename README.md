# Housing Offers REST API

Laravel 12 REST API для асинхронного імпорту пропозицій житла, пошуку найдешевшої актуальної пропозиції та безпечного бронювання.

> Додайте сюди посилання на Git-репозиторій після публікації.

## Стек

- PHP 8.3 / Laravel 12
- MySQL 8
- Redis (черга)
- Docker Compose (`app`, `nginx`, `mysql`, `redis`, `queue`)

## Встановлення та запуск

Через Makefile (потрібен GNU Make):

```bash
make setup
```

Або вручну:

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

API буде доступне за адресою: `http://localhost:8080`

### Корисні команди

```bash
make help            # список цілей
make up              # підняти контейнери
make down            # зупинити
make migrate         # міграції
make seed            # seeders (supplier-a, supplier-b)
make fresh           # migrate:fresh --seed
make test            # тести
make queue-restart   # перезапуск queue worker
make shell           # bash у app
make logs            # логи
```

Еквіваленти без Make:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose restart queue
docker compose exec app php artisan test
```

Без Docker (локально) потрібні PHP 8.2+, розширення `pdo_mysql`/`pdo_sqlite`, Composer, MySQL/Redis і налаштований `.env`.

## API

| Метод | Endpoint | Опис |
|-------|----------|------|
| `POST` | `/api/imports` | Прийняти імпорт (202, обробка в черзі) |
| `GET` | `/api/imports/{id}` | Статус імпорту |
| `GET` | `/api/properties` | Пошук з найдешевшою актуальною пропозицією |
| `POST` | `/api/offers/{id}/reservations` | Забронювати пропозицію |

### Приклад імпорту

```bash
curl -X POST http://localhost:8080/api/imports \
  -H "Content-Type: application/json" \
  -d '{
    "supplier": "supplier-a",
    "external_import_id": "import-2026-09-01-001",
    "sent_at": "2026-09-01T10:00:00Z",
    "offers": [
      {
        "external_id": "offer-a-10001",
        "property": {
          "code": "BCN-0001",
          "name": "Apartment near Sagrada Familia",
          "city": "Barcelona"
        },
        "check_in": "2026-10-10",
        "check_out": "2026-10-15",
        "max_guests": 4,
        "price": 72500,
        "currency": "EUR",
        "available_units": 2,
        "expires_at": "2026-09-10T23:59:59Z"
      }
    ]
  }'
```

### Приклад пошуку

```bash
curl "http://localhost:8080/api/properties?city=Barcelona&check_in=2026-10-10&check_out=2026-10-15&guests=2&page=1"
```

Відповідь містить `data`, `next`, `prev`, `per_page`.

## Ідемпотентність імпорту

Комбінація `supplier + external_import_id` унікальна на рівні БД (`unique` індекс).

При `POST /api/imports`:

1. У транзакції виконується `SELECT ... FOR UPDATE` існуючого імпорту.
2. Якщо запис уже є — повертається той самий `id` і поточний `status`, Job **не** ставиться повторно.
3. Якщо запису немає — створюється `pending` імпорт, payload зберігається в JSON-колонці, у чергу йде `ProcessOfferImportJob`.

Комбінація `supplier + offer.external_id` також унікальна: повторна поява тієї ж пропозиції в іншому імпорті оновлює дані через `updateOrCreate`.

Job атомарно переводить статус `pending → processing`; якщо статус уже не `pending`, обробка пропускається (захист від подвійної обробки).

## Захист від подвійного бронювання останньої одиниці

`POST /api/offers/{offer}/reservations` виконується всередині `DB::transaction()` з `Offer::lockForUpdate()`.

Механізм:

1. Транзакція відкривається.
2. Рядок пропозиції блокується (`SELECT ... FOR UPDATE` у InnoDB).
3. Перевіряються `expires_at` і `available_units > 0`.
4. `available_units` зменшується на 1, створюється `Reservation`.
5. Транзакція комітиться і блокування знімається.

Другий паралельний запит чекає на lock, після чого бачить уже оновлений `available_units`. Якщо одиниць не лишилось — отримує `409 Conflict`. Унікальний `client_reference` додатково захищає від дублікатів бронювання.

## Структура сутностей

- `Supplier` — постачальник (`supplier-a`, `supplier-b` у seeder)
- `Property` — об'єкт житла (унікальний `code`)
- `OfferImport` — факт імпорту (`pending|processing|completed|failed`)
- `Offer` — пропозиція на дати
- `Reservation` — бронювання пропозиції

Пошук найдешевшої пропозиції, сортування та пагінація виконуються SQL-запитом (subquery + `MIN(price)` / `MIN(id)` при рівній ціні), без групування в PHP.
