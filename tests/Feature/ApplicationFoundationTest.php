<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Tests\TestCase;

class ApplicationFoundationTest extends TestCase
{
    public function test_the_institutional_login_page_is_available_in_spanish(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertSee('Facultad de Tecnología');
    }

    public function test_the_institutional_defaults_are_configured(): void
    {
        $this->assertSame('America/La_Paz', config('app.timezone'));
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('documents', config('documents.disk'));
        $this->assertSame(20_480, config('documents.uploads.max_size_kb'));
        $this->assertSame(3, config('documents.deadlines.due_soon_days'));
    }

    public function test_the_document_disk_is_private_and_not_directly_served(): void
    {
        $this->assertSame('private', config('filesystems.disks.documents.visibility'));
        $this->assertFalse(config('filesystems.disks.documents.serve'));
        $this->assertTrue(config('filesystems.disks.documents.throw'));
    }

    public function test_livewire_is_available(): void
    {
        $this->assertTrue(class_exists(Livewire::class));
    }
}
