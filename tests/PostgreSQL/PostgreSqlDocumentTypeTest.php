<?php

namespace Tests\PostgreSQL;

use App\Models\DocumentType;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgreSqlDocumentTypeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_initial_catalog_exists_in_postgresql(): void
    {
        $this->assertGreaterThanOrEqual(12, DocumentType::query()->count());
        $this->assertTrue(DocumentType::query()->where('code', 'documento_kardex')->exists());
        $this->assertTrue(DocumentType::query()->where('code', 'otros')->exists());
    }

    public function test_postgresql_rejects_case_insensitive_name_and_code_duplicates(): void
    {
        $existing = DocumentType::query()->where('code', 'resolucion')->sole();

        $this->assertConstraintViolation(fn () => $this->insertType([
            'name' => Str::lower($existing->name),
            'code' => 'codigo_distinto',
        ]), '23505');

        $this->assertConstraintViolation(fn () => $this->insertType([
            'name' => 'Tipo con otro nombre',
            'code' => Str::upper($existing->code),
        ]), '23505');
    }

    public function test_postgresql_seeder_is_idempotent_and_does_not_overwrite_customizations(): void
    {
        $type = DocumentType::query()->where('code', 'otros')->sole();
        $type->update([
            'description' => 'Personalización administrativa PostgreSQL.',
            'is_active' => false,
        ]);
        $countBefore = DocumentType::query()->count();

        $this->seed(DocumentTypeSeeder::class);
        $this->seed(DocumentTypeSeeder::class);

        $type->refresh();
        $this->assertSame($countBefore, DocumentType::query()->count());
        $this->assertSame('Personalización administrativa PostgreSQL.', $type->description);
        $this->assertFalse($type->is_active);
    }

    public function test_only_active_types_are_available_for_new_documents(): void
    {
        $inactive = DocumentType::query()->where('code', 'nota')->sole();
        $inactive->update(['is_active' => false]);

        $availableIds = DocumentType::query()->availableForNewDocuments()->pluck('id');

        $this->assertFalse($availableIds->contains($inactive->id));
        $this->assertTrue($availableIds->contains(
            DocumentType::query()->where('code', 'carta')->sole()->id,
        ));
    }

    /**
     * @param  callable(): void  $callback
     */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();

        try {
            $callback();
            $this->fail('PostgreSQL aceptó un tipo documental duplicado.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            if (DB::transactionLevel() > 1) {
                DB::rollBack();
            }
        }
    }

    /**
     * @param  array{name: string, code: string}  $attributes
     */
    private function insertType(array $attributes): void
    {
        DB::table('document_types')->insert([
            'public_id' => (string) Str::uuid(),
            ...$attributes,
            'description' => null,
            'is_active' => true,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
