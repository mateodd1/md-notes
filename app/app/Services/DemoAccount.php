<?php

namespace App\Services;

use App\Models\NoteVersion;
use App\Models\SharedNote;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DemoAccount
{
    public const EMAIL = 'demo@demo';

    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly NoteVersionHistory $history,
    ) {
    }

    public function reset(): User
    {
        $this->history->pruneExpired();

        $user = User::query()->firstOrNew(['email' => self::EMAIL]);
        $user->forceFill([
            'name' => 'Cuenta demo',
            'email' => self::EMAIL,
            'password' => Hash::make('demo'),
        ])->save();

        SharedNote::query()->where('user_id', $user->getKey())->delete();
        NoteVersion::query()->where('user_id', $user->getKey())->delete();
        $this->spaces->deleteSpace($user);

        $this->createNote($user, '', 'Bienvenida', <<<'MARKDOWN'
# Bienvenido a md-notes

Esta es una cuenta de demostración. Puedes crear carpetas, editar apuntes, probar el historial y compartir notas.

Los cambios de esta cuenta se reinician cada hora.
MARKDOWN);

        $this->spaces->createFolder($user, '', 'Ejemplos');
        $this->createNote($user, 'Ejemplos', 'Markdown', <<<'MARKDOWN'
# Ejemplo de Markdown

## Formato

Puedes escribir **negrita**, *cursiva* y listas:

- Una idea
- Otra idea

> También puedes usar citas y bloques de código.

```php
echo 'Hola desde md-notes';
```
MARKDOWN);

        return $user;
    }

    private function createNote(User $user, string $parent, string $name, string $content): void
    {
        $path = $this->spaces->createNote($user, $parent, $name);
        $this->spaces->write($user, $path, $content."\n");
        $this->history->record($user, $path, $content."\n");
    }
}
