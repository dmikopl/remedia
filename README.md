# System rezerwacji terminów

Backendowe API do rezerwowania 30-minutowych wizyt w jednej lokalizacji. Udostępnia trzy operacje: pobranie wolnych slotów na wybrany dzień, utworzenie rezerwacji i jej anulowanie.

## Stack

- PHP 8.3 z Xdebug
- Symfony 7.4 LTS
- PostgreSQL 16
- Doctrine ORM 3 z migracjami
- PHPUnit 12, PHPStan (poziom 8), PHP_CodeSniffer (PSR-12)
- Docker Compose (php-fpm, nginx, postgres)

## Szybki start

```bash
make build          # zbudowanie środowiska
make up             # uruchomienie kontenerów
make install        # composer install
make migrate        # utworzenie bazy i migracje
make console ARGS="app:seed:holidays 2026" #Utworzenie w bazie polskich dnie wolnych od pracy na rok 2026
```

Aplikacja http://localhost:8088 (zmienna `HTTP_PORT`), Baza Postgres na porcie 5433 (zmienna `POSTGRES_PORT`).

Przydatne:
 `make shell` bash w kontenerze,
  `make logs` logi na żywo,
   `make db-reset` kasuje baze dev i test. Następnie je odtwarza z migracjami,
    `make help` lista dostępnych komend,

## API

### Wolne sloty na dany dzień

```bash
curl "http://localhost:8088/api/slots?date=2026-09-14"
```

```json
{
  "date": "2026-09-14",
  "slots": [
    { "start": "2026-09-14T09:00:00+02:00", "end": "2026-09-14T09:30:00+02:00" },
    { "start": "2026-09-14T09:30:00+02:00", "end": "2026-09-14T10:00:00+02:00" }
  ]
}
```

W dniu zamkniętym lub wolnym od pracy lista jest pusta.

### Utworzenie rezerwacji

```bash
curl -X POST http://localhost:8088/api/reservations \
  -H 'Content-Type: application/json' \
  -d '{
        "slotStart": "2026-09-14T09:00:00+02:00",
        "customerName": "Anna Kowalska",
        "customerEmail": "anna.kowalska@example.com"
      }'
```

Odpowiedź `201`:

```json
{
  "id": "01a09af8-22c6-727e-8879-89cc8cd699a2",
  "slotStart": "2026-09-14T09:00:00+02:00",
  "customerName": "Anna Kowalska",
  "customerEmail": "anna.kowalska@example.com",
  "status": "active"
}
```

Pole `slotStart` musi być w formacie ISO-8601 z jawnym przesunięciem strefy. Bez niego żądanie jest odrzucane, żeby nie było wątpliwości, o który moment chodzi przy zmianie czasu.

### Anulowanie rezerwacji

```bash
curl -X POST http://localhost:8088/api/reservations/{id}/cancel
```

Odpowiedź `204` bez treści.

### Błędy

Wszystkie błędy API mają ten sam schemat: pole `error` z kodem maszynowym i `message` z opisem. Przy błędach walidacji dochodzi lista `violations` z nazwami pól.

| Kod HTTP | `error` | Kiedy |
|---|---|---|
| 400 | `invalid_date` | parametr `date` nie jest datą kalendarzową w formacie `YYYY-MM-DD` |
| 404 | `reservation_not_found` | rezerwacja o podanym identyfikatorze nie istnieje |
| 409 | `slot_already_booked` | slot jest już zajęty przez aktywną rezerwację |
| 409 | `reservation_already_cancelled` | rezerwacja została już wcześniej anulowana |
| 422 | `slot_not_bookable` | termin poza godzinami pracy, w dniu wolnym albo poza siatką 30 minut |
| 422 | `validation_failed` | payload nie przeszedł walidacji |

## Testy

```bash
make test           # cały zestaw
make test-coverage  # z raportem pokrycia w var/coverage
make phpstan
make cs
```

63 testy w trzech warstwach. Jednostkowe sprawdzają generowanie slotów i wyliczanie świąt bez dotykania bazy. Integracyjne uderzają w prawdziwego Postgresa i weryfikują indeks, konwersje stref oraz zachowanie serwisów. Funkcjonalne przechodzą przez pełny stos HTTP.

Testy korzystają z osobnej bazy `app_test`, tworzonej i migrowanej automatycznie przez `make test`. Jeden z testów wprost pilnuje, że zestaw nie uruchomi się na bazie deweloperskiej.

## Struktura

Kod jest podzielony na moduły domenowe, nie na warstwy techniczne.

```
src/Scheduling/    godziny pracy, generowanie slotów, dni wolne
src/Reservation/   encja rezerwacji, serwisy, repozytorium, HTTP
```

Zależność idzie w jedną stronę: `Reservation` korzysta ze `Scheduling`, nigdy odwrotnie. `SlotGenerator` nie wie nic o bazie danych, bo dni wolne dostaje przez interfejs `HolidayCalendar`. Dzięki temu jego testy nie potrzebują Postgresa.

## Założenia biznesowe

- slot trwa 30 minut i musi w całości mieścić się w godzinach pracy,
- poniedziałek-piątek 09:00-17:00, sobota 10:00-14:20, niedziela zamknięta,
- w sobotę ostatni slot to 13:30-14:00, bo kolejny wychodziłby poza 14:20,
- strefa `Europe/Warsaw` dla logiki biznesowej, UTC w bazie danych,
- jedna lokalizacja, brak modelu użytkowników,
- dwie aktywne rezerwacje na ten sam slot są niemożliwe,
- anulowana rezerwacja natychmiast zwalnia termin.

Godziny pracy są w `config/services.yaml`. Trzymanie ich w konfiguracji pozwala testować generator bez bazy.

## Dni wolne

Dni wolne są rekordami w tabeli `holiday`, tworzone komendą:

```bash
make console ARGS="app:seed:holidays 2026"
```

Komenda przyjmuje rok i jest idempotentna. Stałe święta są z tablicy, a ruchome (np Wielkanoc) są wyliczane jako przesunięcia od Wielkanocy. Datę Wielkanocy daje `easter_days()`(rozszerzenie `calendar`).

Wybrałem seeder zamiast wpisania dat w migrację, bo dni wolne zmieniają się co rok, a migracje powinny opisywać strukturę, nie dane biznesowe o ograniczonym terminie ważności.

## Indeksy

**`uniq_reservation_active_slot`** - unikalny indeks częściowy na `reservation (slot_start) WHERE status = 'active'`.

Obsługujetrzy rzeczy:

1. gwarantuje, że nie powstaną dwie aktywne rezerwacje na ten sam termin,
2. anulowana rezerwacja wypada spod indeksu, więc slot zwalnia się sam, bez żadnego kodu czyszczącego,
3. obsługuje zapytanie o dostępność, bo pokrywa dokładnie te wiersze, o które pytamy.

**`reservation_pkey`** - klucz główny na `id` typu UUID, używany przy anulowaniu.

**`uniq_dc9ab234aa9e377a`** - unikalny indeks na `holiday (date)`, blokuje zdublowanie tego samego dnia wolnego i obsługuje sprawdzanie, czy dzień jest świętem.

Celowo nie ma osobnego indeksu na `reservation (slot_start)` bez warunku. Wszystkie zapytania w ścieżce API dotyczą wyłącznie rezerwacji aktywnych, pełny indeks byłby większy i nieużywany.

## Współbieżność

Dwie równoległe próby rezerwacji tego samego slotu rozstrzyga baza danych bez aplikacji.

Nie ma tu sprawdzenia „czy slot jest wolny” przed zapisem. Między takim odczytem a zapisem jest luka w którym inny proces zdąży zająć termin. Zamiast tego rezerwacja jest po prostu wstawiana, a naruszenie unikalnego indeksu zamieniane na `SlotAlreadyBooked` i odpowiedź `409`. Przed zapisem weryfikowana jest jedynie legalność samego terminu.

2 testy integracyjne. 1 sprawdza, że drugie wstawienie tego samego aktywnego slotu kończy się `UniqueConstraintViolationException`. 2 otwiera dwa niezależne połączenia DBAL: pierwsze wstawia rezerwację w niezatwierdzonej transakcji, drugie próbuje zająć ten sam slot, zawisa na blokadzie indeksu i wraca z kodem `55P03`. Test sprawdza dokładnie ten kod, a nie dowolny wyjątek, bo inaczej zaliczyłby się też zwykły błąd połączenia.

## Zachowanie przy dużym wolumenie

Komenda generująca realistyczny wolumen danych (100k):

```bash
make console ARGS="app:seed:reservations 100000 --from=2027-01-04"
```

Na każdy slot przypada jedna rezerwacja aktywna i trzy anulowane, co odwzorowuje sytuację, w której anulacje się kumulują. Dane powstają przez prawdziwy generator slotów(z godzinami pracy i dniami wolnymi).

Pomiar na 100 000 wierszy (25 000 aktywnych, zakres 2027-01-04 do 2032-06-14):

```
Index Only Scan using uniq_reservation_active_slot on reservation (rows=16)
  Index Cond: slot_start >= ... AND slot_start < ...
  Heap Fetches: 0
  Buffers: shared hit=3
  Execution Time: 0.083 ms
```

Istotne:
 `Heap Fetches: 0` zapytanie nie sięga do tabeli
  - indeks częściowy okazuje się przy okazji indeksem pokrywającym, bo potrzebna jest tylko kolumna `slot_start`.
  - W `Index Cond` nie ma też warunku na `status`, ponieważ predykat indeksu sam gwarantuje, że są w nim wyłącznie wiersze aktywne.

To samo zapytanie z wymuszonym skanem sekwencyjnym: 7,494 ms, 1471 buforów, 99 984 wiersze odrzucone filtrem. Różnica to około 90 razy w czasie i blisko 500 razy w liczbie odczytanych stron.

Indeks częściowy zajmuje 568 kB przy tabeli 11 MB i kluczu głównym 3104 kB.

## Świadome uproszczenia

- **Brak uwierzytelniania.** Zadanie go nie wymagało. Dlatego identyfikatorem rezerwacji jest UUIDv7, a nie kolejny numer, bo taki numer dałoby się zgadnąć i anulować cudzą rezerwację.
- **Anulowanie jako `POST /{id}/cancel`, nie `DELETE`.** Rezerwacja nie znika, zmienia stan, a druga próba anulowania jest konfliktem. `DELETE` powinien być idempotentny.
- **Znacznik `createdAt` bierze się z `new DateTimeImmutable()`** zamiast z wstrzykiwanego zegara.
- **Po naruszeniu unikalności Doctrine zamyka `EntityManager`.** W obsłudze pojedynczego żądania HTTP to bez znaczenia, bo i tak kończymy odpowiedzią błędu.
- **Seeder odpytuje tabelę dni wolnych raz na każdy generowany dzień.** Dla zadania to nieistotne.

## Kolejna iteracja

- **Przesunięcie terminu** jako jedna operacja w transakcji, zamiast anulowania i rezerwowania od nowa. Dzisiaj klient może zostać bez terminu, jeśli nowy zostanie zajęty między jednym a drugim żądaniem.
- **Lista rezerwacji z paginacją kluczem**, a nie offsetem. UUIDv7 jest sortowalny po czasie, więc nadaje się na kursor bez dokładania kolumny.
- **Wiele lokalizacji** przez dołożenie `location_id` do indeksu częściowego. Unikalność stałaby się parą `(location_id, slot_start)` i cała reszta logiki zostałaby bez zmian.
- **Klucz idempotentności na tworzeniu rezerwacji**, żeby ponowione żądanie po zerwanym połączeniu nie tworzyło drugiej rezerwacji.
- **Godziny pracy w bazie z historią obowiązywania**, gdyby miały się zmieniać. Dzisiaj zmiana wymaga wdrożenia konfiguracji, a stare rezerwacje nie wiedzą, według jakich godzin powstały.
- **Partycjonowanie tabeli po `slot_start`** przy milionach wierszy i archiwizacja przeszłych okresów. Niepotrzebne przy obecnym rozmiarze.
