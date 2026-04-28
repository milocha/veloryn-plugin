# Veloryn Plugin

Oficjalny plugin Veloryn — regresywny kalkulator cennika, szablon oferty handlowej w Twig/PDF oraz komenda seed.

## Struktura

```
veloryn-plugin/
├── plugin.json                          # Manifest pluginu (wymagany)
├── composer.json                        # PSR-4 autoload dla testów standalone
├── src/
│   ├── Providers/
│   │   └── VelorynPluginServiceProvider.php  # ServiceProvider (entry point)
│   ├── Services/
│   │   ├── RegressiveTierPricingCalculator.php  # Kalkulator cennika regresywnego
│   │   └── VelorynQuoteTokenExtension.php       # Rozszerzenie QuoteTokenBuilder
│   └── Console/
│       └── SeedOfferTemplateCommand.php         # Artisan: veloryn:seed-offer-template
├── resources/
│   └── templates/
│       └── oferta-veloryn-template.twig.html    # Szablon oferty handlowej (Twig/HTML)
├── tests/
│   └── Unit/
│       └── RegressiveTierPricingCalculatorTest.php  # 16 testów PHPUnit
└── README.md
```

## Manifest (`plugin.json`)

| Pole | Opis |
|------|------|
| `id` | Unikalny identyfikator (lowercase, myślniki, 2-99 znaków) |
| `name` | Nazwa wyświetlana |
| `version` | Wersja SemVer (np. `1.0.0`) |
| `vendor` | Producent/organizacja |
| `providers` | Klasy ServiceProvider — MUSZĄ być pod `Plugins\{PluginId}\` |
| `post_install` | Komendy artisan do uruchomienia po instalacji |
| `automation.actions` | Rejestracja akcji Automation |
| `automation.triggers` | Rejestracja triggerów Automation |

## RegressiveTierPricingCalculator

Kalkulator cennika paczkowego (regresywnego) Veloryn:

| Paczka użytkowników | Cena/user | Razem (cała paczka) |
|---------------------|-----------|---------------------|
| 1 – 20              | 120,00 zł | 2 400,00 zł         |
| 21 – 40             | 80,00 zł  | + 1 600,00 zł       |
| 41 – 60             | 50,00 zł  | + 1 000,00 zł       |
| 61 – 80             | 30,00 zł  | + 600,00 zł         |
| 81 – 100            | 20,00 zł  | + 400,00 zł         |
| > 100               | —         | wycena indywidualna |

Logika kumulatywna: paczka 21–40 nie obniża ceny pierwszych 20.

## Komenda seed

```bash
php artisan veloryn:seed-offer-template
```

Idempotentny UPSERT szablonu `offer-veloryn-system` do `documents.document_templates`.
Bezpieczne do wielokrotnego uruchamiania — zwiększa `current_version` przy każdym uruchomieniu.

Uruchamiana automatycznie po instalacji przez `post_install` w `plugin.json`.

## Tokeny szablonu

Plugin rozszerza kontekst Twig oferty o sekcję `pricing`:

| Token | Opis |
|-------|------|
| `pricing.regressive_breakdown` | Szczegółowy breakdown tier'ów (null gdy brak user_count) |
| `pricing.tier_table` | Pełna tabela cennika (5 tier'ów, do pętli `{% for tier in pricing.tier_table %}`) |
| `pricing.total_monthly` | Łączny miesięczny abonament (null gdy >100 users) |
| `pricing.avg_per_user` | Średnia cena per user (null gdy >100 users) |

Wartości są uzupełniane gdy `quote.meta.user_count` jest ustawiony w ofercie.

## Hookowanie tokenów (WARIANT B)

Plugin dziedziczy po `QuoteTokenBuilder` i rejestruje się w `TokenBuilderRegistry` pod kluczem `'quote'`,
zastępując domyślny builder. Zero zmian w core.

## Instalacja (upload ZIP)

```bash
# Spakuj plugin
php artisan plugin:package /ścieżka/do/veloryn-plugin

# lub ZIP ręcznie
zip -r veloryn-plugin-1.0.0.zip veloryn-plugin/
```

Następnie wgraj ZIP w panelu admin: **Administracja → Pluginy → Wgraj ZIP**.

## Wymagania

- Veloryn >= 1.5.0
- PHP 8.2+

## Licencja

Proprietary — Veloryn Team
