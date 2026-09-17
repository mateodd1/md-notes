<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\NoteSpace;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class NoteSpaceIsolationTest extends TestCase
{
    private string $spacePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spacePath = sys_get_temp_dir().'/md-notes-isolation-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->spacePath);

        parent::tearDown();
    }

    public function test_a_user_cannot_see_another_users_notes(): void
    {
        $firstUser = new User(['name' => 'Primero', 'email' => 'primero@example.test']);
        $firstUser->setAttribute('id', 101);
        $secondUser = new User(['name' => 'Segundo', 'email' => 'segundo@example.test']);
        $secondUser->setAttribute('id', 202);
        $spaces = new NoteSpace($this->spacePath);

        $spaces->createNote($firstUser, '', 'Privada');

        $this->assertCount(1, $spaces->tree($firstUser));
        $this->assertSame([], $spaces->tree($secondUser));
        $this->expectException(NotFoundHttpException::class);

        $spaces->read($secondUser, 'Privada.md');
    }

    public function test_a_user_can_rename_and_delete_their_own_folders_and_notes(): void
    {
        $user = new User(['name' => 'Propietario', 'email' => 'propietario@example.test']);
        $user->setAttribute('id', 303);
        $spaces = new NoteSpace($this->spacePath);

        $spaces->createFolder($user, '', 'Clase');
        $spaces->createNote($user, 'Clase', 'Borrador');

        $this->assertSame('Clase/Apuntes.md', $spaces->rename($user, 'Clase/Borrador.md', 'Apuntes'));
        $this->assertSame('Universidad', $spaces->rename($user, 'Clase', 'Universidad'));

        $spaces->deleteItem($user, 'Universidad');

        $this->assertSame([], $spaces->tree($user));
    }
}
