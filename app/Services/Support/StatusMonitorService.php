<?php

namespace App\Services\Support;

use App\Models\Support\SupportServiceStatus;
use App\Models\Support\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatusMonitorService
{
    public function runAllChecks(): void
    {
        $checks = [
            'plataforma'     => fn () => $this->checkPlataforma(),
            'login'          => fn () => $this->checkLogin(),
            'correos'        => fn () => $this->checkCorreos(),
            'notificaciones' => fn () => $this->checkNotificaciones(),
            'ranking'        => fn () => $this->checkRanking(),
            'scheduler'      => fn () => $this->checkScheduler(),
        ];

        foreach ($checks as $component => $check) {
            try {
                $result = $check();
                SupportServiceStatus::where('component', $component)->update([
                    'status'          => $result['status'],
                    'message'         => $result['message'],
                    'last_checked_at' => now(),
                    'auto_detected'   => true,
                ]);

                if ($result['status'] === 'caido') {
                    $this->createIncidentTicket($component, $result['message']);
                }
            } catch (\Throwable $e) {
                Log::error("StatusMonitor error for {$component}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function checkPlataforma(): array
    {
        DB::select('SELECT 1');

        return ['status' => 'operativo', 'message' => null];
    }

    private function checkLogin(): array
    {
        $reciente = DB::table('sessions')
            ->where('last_activity', '>', now()->subMinutes(10)->timestamp)
            ->exists();

        return $reciente
            ? ['status' => 'operativo', 'message' => null]
            : ['status' => 'degradado', 'message' => 'Sin actividad de sesión reciente.'];
    }

    private function checkCorreos(): array
    {
        $failedCount = DB::table('failed_jobs')->count();
        if ($failedCount > 10) {
            return ['status' => 'degradado', 'message' => "{$failedCount} jobs fallidos acumulados."];
        }

        return ['status' => 'operativo', 'message' => null];
    }

    private function checkNotificaciones(): array
    {
        // No es un error si no hay notificaciones (puede que no haya partidos próximos).
        \App\Models\Torneos\TournamentMatchNotification::where('created_at', '>', now()->subHours(25))->exists();

        return ['status' => 'operativo', 'message' => null];
    }

    private function checkRanking(): array
    {
        $ultimo = \App\Models\Torneos\FutgoRanking::latest('updated_at')->first();
        if (! $ultimo) {
            return ['status' => 'degradado', 'message' => 'Sin datos de ranking.'];
        }
        if ($ultimo->updated_at->lt(now()->subHours(25))) {
            return ['status' => 'degradado', 'message' => 'Ranking no actualizado en 25 horas.'];
        }

        return ['status' => 'operativo', 'message' => null];
    }

    private function checkScheduler(): array
    {
        $log = storage_path('logs/torneos-reminders.log');
        if (file_exists($log) && filemtime($log) > now()->subMinutes(65)->timestamp) {
            return ['status' => 'operativo', 'message' => null];
        }

        return ['status' => 'degradado', 'message' => 'Scheduler sin actividad en más de 1 hora.'];
    }

    private function createIncidentTicket(string $component, ?string $message): void
    {
        // Evitar duplicados: no crear si ya hay un ticket abierto para este componente.
        $exists = SupportTicket::where('category', 'bug')
            ->where('subject', 'like', "%{$component}%")
            ->where('status', '!=', 'cerrado')
            ->where('created_at', '>', now()->subHour())
            ->exists();

        if ($exists) {
            return;
        }

        $adminUser = User::where('role', 'admin')->first();
        if (! $adminUser) {
            return;
        }

        SupportTicket::create([
            'user_id'               => $adminUser->id,
            'category'              => 'bug',
            'status'                => 'en_revision',
            'priority'              => 'critica',
            'classifier_confidence' => 1.0,
            'subject'               => "🚨 Monitor: componente '{$component}' caído",
            'context_snapshot'      => ['auto_generated' => true, 'component' => $component],
            'error_trace'           => ['message' => $message, 'detected_at' => now()->toISOString()],
            'assigned_to'           => $adminUser->id,
        ]);
    }
}
