<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentTypeSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const TYPES = [
        'carta' => 'Carta',
        'informe' => 'Informe',
        'memorandum' => 'Memorándum',
        'circular' => 'Circular',
        'solicitud' => 'Solicitud',
        'resolucion' => 'Resolución',
        'nota' => 'Nota',
        'formulario' => 'Formulario',
        'certificacion' => 'Certificación',
        'correspondencia' => 'Correspondencia',
        'documento_kardex' => 'Documento de Kardex',
        'otros' => 'Otros',
    ];

    public function run(): void
    {
        foreach (self::TYPES as $code => $name) {
            $exists = DocumentType::query()
                ->get(['name', 'code'])
                ->contains(fn (DocumentType $type): bool => Str::lower($type->code) === Str::lower($code)
                    || Str::lower($type->name) === Str::lower($name));

            if (! $exists) {
                DocumentType::query()->create([
                    'name' => $name,
                    'code' => $code,
                    'description' => null,
                    'is_active' => true,
                    'created_by' => null,
                ]);
            }
        }
    }
}
