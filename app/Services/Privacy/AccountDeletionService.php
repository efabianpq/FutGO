<?php

namespace App\Services\Privacy;

use App\Models\Privacy\DataRequest;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\TeamPlayer;
use App\Models\User;
use App\Notifications\Privacy\AccountDeletionCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Centro de Privacidad · Derecho al olvido (Ley 1581/2012, art. supresión).
 *
 * Flujo: solicitar (código por email) → verificar → periodo de gracia → ejecución
 * automática. La ejecución ANONIMIZA (no borra en cascada) para no romper la
 * integridad histórica de standings/player_stats/match_events de otros jugadores.
 */
class AccountDeletionService
{
    public const ANON_USER_NAME   = 'Usuario eliminado';
    public const ANON_PLAYER_NAME = 'Jugador eliminado';

    /** Crea la solicitud y envía el código de verificación al correo del usuario. */
    public function requestDeletion(User $user, Request $request): DataRequest
    {
        // Reutiliza una solicitud viva si ya la había (no acumular).
        $existing = $this->pendingRequest($user);
        if ($existing !== null) {
            $existing->delete();
        }

        $code = (string) random_int(100000, 999999);

        $dr = DataRequest::create([
            'user_id'           => $user->id,
            'type'              => DataRequest::TYPE_DELETE,
            'status'            => DataRequest::STATUS_PENDING,
            'verification_code' => $code,
            'requested_ip'      => $request->ip(),
        ]);

        $user->notify(new AccountDeletionCodeNotification($code));

        return $dr;
    }

    /** Verifica el código e inicia el periodo de gracia. */
    public function verify(DataRequest $dr, string $code): bool
    {
        if ($dr->type !== DataRequest::TYPE_DELETE || $dr->status !== DataRequest::STATUS_PENDING) {
            return false;
        }

        if (! hash_equals((string) $dr->verification_code, trim($code))) {
            return false;
        }

        $graceDays = (int) config('privacy.deletion_grace_days', 30);

        DB::transaction(function () use ($dr, $graceDays) {
            $dr->update([
                'status'      => DataRequest::STATUS_PROCESSING,
                'verified_at' => now(),
                'executes_at' => now()->addDays($graceDays),
            ]);

            $dr->user->forceFill(['delete_requested_at' => now()])->save();
        });

        return true;
    }

    /** Solicitud de eliminación viva (pendiente o en gracia) del usuario. */
    public function pendingRequest(User $user): ?DataRequest
    {
        return $user->dataRequests()
            ->deletes()
            ->whereIn('status', [DataRequest::STATUS_PENDING, DataRequest::STATUS_PROCESSING])
            ->latest()
            ->first();
    }

    /** Cancela la eliminación durante el periodo de gracia. */
    public function cancel(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->dataRequests()
                ->deletes()
                ->whereIn('status', [DataRequest::STATUS_PENDING, DataRequest::STATUS_PROCESSING])
                ->update(['status' => DataRequest::STATUS_CANCELLED]);

            $user->forceFill(['delete_requested_at' => null])->save();
        });
    }

    /** Solicitudes cuyo periodo de gracia venció (para el comando programado). */
    public function dueForExecution(): Collection
    {
        return DataRequest::deletes()
            ->status(DataRequest::STATUS_PROCESSING)
            ->whereNotNull('executes_at')
            ->where('executes_at', '<=', now())
            ->with('user')
            ->get();
    }

    /** Ejecuta la eliminación de una solicitud vencida. */
    public function execute(DataRequest $dr): void
    {
        if ($dr->user !== null) {
            $this->anonymize($dr->user);
        }

        $dr->update([
            'status'       => DataRequest::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Anonimización profunda: users + plantillas (club_players/team_players).
     * Preserva ids y estadísticas; borra/oculta los datos personales.
     */
    public function anonymize(User $user): void
    {
        $this->deleteFromMediaDisk($user->avatar_url);

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'name'                     => self::ANON_USER_NAME,
                'email'                    => 'deleted_'.$user->id.'@futgo.invalid',
                'avatar_url'               => null,
                'document'                 => null,
                'document_hash'            => null,
                'phone_whatsapp'           => null,
                'birthdate'                => null,
                'guardian_email'           => null,
                'pending_guardian_consent' => false,
                'google_id'                => null,
                'delete_requested_at'      => now(),
            ])->save();

            // Plantillas donde el nombre/documento quedan denormalizados.
            ClubPlayer::where('user_id', $user->id)->update([
                'full_name'     => self::ANON_PLAYER_NAME,
                'document'      => null,
                'document_hash' => null,
            ]);
            TeamPlayer::where('user_id', $user->id)->update([
                'full_name'     => self::ANON_PLAYER_NAME,
                'document'      => null,
                'document_hash' => null,
            ]);

            // Revocar todas las sesiones activas (driver database).
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    /** Borra del disco de medios un archivo a partir de su URL pública. */
    private function deleteFromMediaDisk(?string $url): void
    {
        if (! $url) {
            return;
        }

        $disk = config('filesystems.media_disk', 'public');
        $base = rtrim(Storage::disk($disk)->url(''), '/');

        if ($base && str_starts_with($url, $base.'/')) {
            Storage::disk($disk)->delete(substr($url, strlen($base) + 1));
        }
    }
}
