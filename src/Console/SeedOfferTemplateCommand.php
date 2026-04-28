<?php

declare(strict_types=1);

namespace Plugins\VelorynPlugin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SeedOfferTemplateCommand — idempotentny UPSERT systemowego szablonu oferty Veloryn.
 *
 * Wstawia lub aktualizuje rekord w documents.document_templates z kodem 'offer-veloryn-system'.
 * Bezpieczne do wielokrotnego uruchamiania.
 *
 * Użycie:
 *   php artisan veloryn:seed-offer-template
 */
class SeedOfferTemplateCommand extends Command
{
    protected $signature = 'veloryn:seed-offer-template';

    protected $description = 'Seeduje systemowy szablon Veloryn — Oferta handlowa do documents.document_templates (idempotentny UPSERT)';

    public function handle(): int
    {
        $templatePath = __DIR__.'/../../resources/templates/oferta-veloryn-template.twig.html';

        if (! is_file($templatePath)) {
            $this->error("Plik szablonu nie istnieje: {$templatePath}");

            return Command::FAILURE;
        }

        $contentHtml = (string) file_get_contents($templatePath);

        $variablesSchema = json_encode([
            'quote.number' => ['label' => 'Numer oferty', 'type' => 'string', 'required' => false],
            'quote.valid_until' => ['label' => 'Ważna do', 'type' => 'date', 'required' => false],
            'quote.meta.user_count' => ['label' => 'Liczba użytkowników', 'type' => 'integer', 'required' => false],
            'quote.items_by_category' => ['label' => 'Pozycje pogrupowane wg kategorii', 'type' => 'array', 'required' => false],
            'quote.totals.by_category' => ['label' => 'Sumy per kategoria', 'type' => 'array', 'required' => false],
            'quote.totals.total' => ['label' => 'Suma brutto', 'type' => 'decimal', 'required' => false],
            'client.name' => ['label' => 'Nazwa klienta', 'type' => 'string', 'required' => false],
            'client.legal_entity.name' => ['label' => 'Podmiot prawny klienta', 'type' => 'string', 'required' => false],
            'seller.name' => ['label' => 'Nazwa sprzedawcy', 'type' => 'string', 'required' => false],
            'seller.legal_name' => ['label' => 'Pełna nazwa prawna sprzedawcy', 'type' => 'string', 'required' => false],
            'seller.nip' => ['label' => 'NIP sprzedawcy', 'type' => 'string', 'required' => false],
            'seller.regon' => ['label' => 'REGON sprzedawcy', 'type' => 'string', 'required' => false],
            'seller.address' => ['label' => 'Adres sprzedawcy', 'type' => 'object', 'required' => false],
            'contact_person.name' => ['label' => 'Osoba kontaktowa — imię i nazwisko', 'type' => 'string', 'required' => false],
            'contact_person.email' => ['label' => 'Osoba kontaktowa — e-mail', 'type' => 'string', 'required' => false],
            'contact_person.phone' => ['label' => 'Osoba kontaktowa — telefon', 'type' => 'string', 'required' => false],
            'pricing.regressive_breakdown' => ['label' => 'Breakdown cennika regresywnego', 'type' => 'array', 'required' => false],
            'pricing.tier_table' => ['label' => 'Pełna tabela cennika', 'type' => 'array', 'required' => false],
            'pricing.total_monthly' => ['label' => 'Łączny miesięczny abonament', 'type' => 'decimal', 'required' => false],
            'pricing.avg_per_user' => ['label' => 'Średnia per user', 'type' => 'decimal', 'required' => false],
            'generated_at' => ['label' => 'Data wygenerowania', 'type' => 'datetime', 'required' => false],
            'generated_by.name' => ['label' => 'Wygenerował', 'type' => 'string', 'required' => false],
        ], JSON_UNESCAPED_UNICODE);

        $now = now();

        $existing = DB::table('documents.document_templates')
            ->where('code', 'offer-veloryn-system')
            ->whereNull('tenant_id')
            ->first();

        if ($existing !== null) {
            $newVersion = (int) $existing->current_version + 1;

            DB::table('documents.document_templates')
                ->where('code', 'offer-veloryn-system')
                ->whereNull('tenant_id')
                ->update([
                    'name' => 'Veloryn — Oferta handlowa (system)',
                    'description' => 'Globalny systemowy szablon oferty handlowej Veloryn. Renderowany przez Browsershot (HTML→PDF, Chromium).',
                    'category' => 'offer',
                    'output_format' => 'pdf',
                    'engine' => 'browsershot',
                    'template_syntax' => 'twig',
                    'paper_size' => 'a4',
                    'paper_orientation' => 'portrait',
                    'content_html' => $contentHtml,
                    'variables_schema' => $variablesSchema,
                    'is_active' => true,
                    'is_system' => true,
                    'is_default' => false,
                    'locale' => 'pl',
                    'current_version' => $newVersion,
                    'updated_at' => $now,
                    'updated_by' => null,
                ]);

            $this->info("Szablon zaktualizowany do wersji {$newVersion}");
        } else {
            DB::table('documents.document_templates')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => null,
                'code' => 'offer-veloryn-system',
                'name' => 'Veloryn — Oferta handlowa (system)',
                'description' => 'Globalny systemowy szablon oferty handlowej Veloryn. Renderowany przez Browsershot (HTML→PDF, Chromium).',
                'category' => 'offer',
                'output_format' => 'pdf',
                'engine' => 'browsershot',
                'template_syntax' => 'twig',
                'paper_size' => 'a4',
                'paper_orientation' => 'portrait',
                'content_html' => $contentHtml,
                'variables_schema' => $variablesSchema,
                'is_active' => true,
                'is_system' => true,
                'is_default' => false,
                'locale' => 'pl',
                'sandbox_html' => false,
                'sort_order' => 0,
                'current_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => null,
                'updated_by' => null,
            ]);

            $this->info('Szablon utworzony (wersja 1)');
        }

        return Command::SUCCESS;
    }
}
