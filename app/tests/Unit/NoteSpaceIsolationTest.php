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

    public function test_api_upload_creates_missing_nested_folders_inside_the_users_space(): void
    {
        $user = new User(['name' => 'Propietario', 'email' => 'api@example.test']);
        $user->setAttribute('id', 304);
        $spaces = new NoteSpace($this->spacePath);

        $created = $spaces->writeFromApi($user, 'XDP/SECONDARY-DEPLOYMENT.md', '# Secondary deployment', snapshot: false);

        $this->assertTrue($created);
        $this->assertSame('# Secondary deployment', $spaces->read($user, 'XDP/SECONDARY-DEPLOYMENT.md'));
    }

    public function test_a_deleted_note_is_kept_in_trash_and_can_be_restored(): void
    {
        $user = new User(['name' => 'Propietario', 'email' => 'propietario@example.test']);
        $user->setAttribute('id', 404);
        $spaces = new NoteSpace($this->spacePath);

        $path = $spaces->createNote($user, '', 'Recuperable');
        $spaces->write($user, $path, '# Contenido que debe seguir ocupando espacio');
        $usedBeforeTrash = app(\App\Services\StorageQuota::class)->used($user, $spaces->root($user));

        $entry = $spaces->delete($user, $path);

        $this->assertSame([], $spaces->tree($user));
        $this->assertSame('.md-notes-trash/'.$entry['id'].'/content/'.$path, $entry['history_path']);
        $this->assertSame($usedBeforeTrash, app(\App\Services\StorageQuota::class)->used($user, $spaces->root($user)));
        $this->assertCount(1, $spaces->trashItems($user));

        $spaces->restoreTrash($user, $entry['id']);

        $this->assertSame('# Contenido que debe seguir ocupando espacio', $spaces->read($user, $path));
        $this->assertSame([], $spaces->trashItems($user));
    }

    public function test_a_folder_can_be_moved_inside_another_folder_while_keeping_its_icon_colour(): void
    {
        $user = new User(['name' => 'Propietario', 'email' => 'propietario@example.test']);
        $user->setAttribute('id', 505);
        $spaces = new NoteSpace($this->spacePath);

        $spaces->createFolder($user, '', 'Destino');
        $spaces->createFolder($user, '', 'Origen');
        $spaces->rename($user, 'Origen', 'Origen', '#7c3aed', true);

        $this->assertSame('Destino/Origen', $spaces->move($user, 'Origen', 'Destino'));
        $tree = $spaces->tree($user);

        $this->assertSame('#7c3aed', $tree[0]['children'][0]['color']);
        $this->assertTrue($tree[0]['children'][0]['collapsed']);
    }

    public function test_notes_and_folders_can_be_pinned_and_keep_that_state_when_moved(): void
    {
        $user = new User(['name' => 'Propietario', 'email' => 'propietario@example.test']);
        $user->setAttribute('id', 606);
        $spaces = new NoteSpace($this->spacePath);

        $spaces->createFolder($user, '', 'Archivo');
        $note = $spaces->createNote($user, '', 'Importante');
        $spaces->setPinned($user, 'Archivo', true);
        $spaces->setPinned($user, $note, true);

        $tree = $spaces->tree($user);
        $this->assertTrue($tree[0]['pinned']);
        $this->assertTrue($tree[1]['pinned']);

        $spaces->createFolder($user, '', 'Destino');
        $spaces->move($user, 'Archivo', 'Destino');
        $moved = $spaces->tree($user);
        $destination = collect($moved)->firstWhere('name', 'Destino');

        $this->assertTrue($destination['children'][0]['pinned']);
    }
}
