<?php

namespace Tests\Feature\Support;

use App\Models\Quotation;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_code_of_a_year_starts_at_0001(): void
    {
        $this->assertSame('AXNQ-2026-0001', ReferenceCodeGenerator::generate(DocumentType::Quotation, 2026));
    }

    public function test_sequence_increments_from_the_highest_existing_code(): void
    {
        Quotation::factory()->create(['reference_code' => 'AXNQ-2026-0007']);

        $this->assertSame('AXNQ-2026-0008', ReferenceCodeGenerator::generate(DocumentType::Quotation, 2026));
    }

    public function test_each_document_type_keeps_its_own_counter(): void
    {
        Quotation::factory()->create(['reference_code' => 'AXNQ-2026-0007']);

        $this->assertSame('AXNO-2026-0001', ReferenceCodeGenerator::generate(DocumentType::Order, 2026));
        $this->assertSame('AXNI-2026-0001', ReferenceCodeGenerator::generate(DocumentType::Invoice, 2026));
        $this->assertSame('AXNR-2026-0001', ReferenceCodeGenerator::generate(DocumentType::Receipt, 2026));
        $this->assertSame('AXNP-2026-0001', ReferenceCodeGenerator::generate(DocumentType::Payment, 2026));
    }

    public function test_counters_reset_each_year(): void
    {
        Quotation::factory()->create(['reference_code' => 'AXNQ-2025-0042']);

        $this->assertSame('AXNQ-2026-0001', ReferenceCodeGenerator::generate(DocumentType::Quotation, 2026));
        $this->assertSame('AXNQ-2025-0043', ReferenceCodeGenerator::generate(DocumentType::Quotation, 2025));
    }

    public function test_soft_deleted_rows_still_advance_the_sequence(): void
    {
        $quotation = Quotation::factory()->create(['reference_code' => 'AXNQ-2026-0003']);
        $quotation->delete();

        $this->assertSame('AXNQ-2026-0004', ReferenceCodeGenerator::generate(DocumentType::Quotation, 2026));
    }

    public function test_defaults_to_the_current_year(): void
    {
        $code = ReferenceCodeGenerator::generate(DocumentType::Quotation);

        $this->assertSame('AXNQ-'.now()->year.'-0001', $code);
    }
}
