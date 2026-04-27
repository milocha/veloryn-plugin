# Veloryn Plugin

Oficjalny plugin demonstracyjny Veloryn — szablon/wzorzec dla twórców pluginów.

## Struktura

```
veloryn-plugin/
├── plugin.json                          # Manifest pluginu (wymagany)
├── src/
│   └── Providers/
│       └── VelorynPluginServiceProvider.php  # ServiceProvider (entry point)
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
