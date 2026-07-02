<?php

namespace App\Console\Commands;

use App\Support\Privacy\DocumentHasher;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Centro de Privacidad · Cifra los datos sensibles ya existentes en BD.
 *
 * Al agregar los casts AsEncryptedString a User (document, phone_whatsapp),
 * ClubPlayer y TeamPlayer (document), los registros NUEVOS se cifran solos. Este
 * comando cifra los registros PREVIOS (creados en texto plano) y rellena el
 * blind index `document_hash`.
 *
 * Trabaja a nivel de DB::table() (no del modelo) para no chocar con el accessor
 * que intentaría descifrar un valor que todavía está en claro. Idempotente:
 * detecta y salta lo ya cifrado.
 *
 * ⚠️ Hacer backup de la BD antes de correrlo (ver docs/OPERACIONES.md).
 */
class EncryptSensitiveData extends Command
{
    protected $signature = 'futgo:encrypt-sensitive {--force : Ejecutar sin confirmación (asumir backup hecho)}';

    protected $description = 'Cifra document/phone existentes y rellena document_hash (idempotente).';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('¿Hiciste backup de la BD? El cifrado modifica columnas en su lugar.')) {
            $this->warn('Cancelado. Corre `php artisan backup:run --only-db` y vuelve a intentar.');

            return self::FAILURE;
        }

        $users = $this->encryptTable('users', ['document' => true, 'phone_whatsapp' => false]);
        $club  = $this->encryptTable('club_players', ['document' => true]);
        $team  = $this->encryptTable('team_players', ['document' => true]);

        $this->info("Listo. users: {$users} · club_players: {$club} · team_players: {$team} fila(s) cifradas.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string,bool>  $columns  columna => ¿mantener blind index document_hash?
     */
    private function encryptTable(string $table, array $columns): int
    {
        $count = 0;

        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $columns, &$count) {
            foreach ($rows as $row) {
                $update = [];

                foreach ($columns as $column => $hashed) {
                    $value = $row->{$column} ?? null;

                    if ($value === null || $value === '' || $this->isEncrypted($value)) {
                        continue;
                    }

                    $update[$column] = Crypt::encryptString($value);

                    if ($hashed) {
                        $update['document_hash'] = DocumentHasher::hash($value);
                    }
                }

                if ($update !== []) {
                    DB::table($table)->where('id', $row->id)->update($update);
                    $count++;
                }
            }
        });

        return $count;
    }

    /** ¿El valor ya está cifrado con la APP_KEY actual? */
    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
