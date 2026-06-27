<?php

namespace App\Models\Social;

use App\Models\Torneos\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FutGO Social — FriendlyMatch: amistoso confirmado entre dos clubs.
 *
 * Sin `tournament_id`. Resultado por DOBLE CONFIRMACIÓN: cada club reporta su
 * marcador; si coinciden queda `jugado`/`acordado`, si no, `en_disputa` sin
 * tocar reputación. Ver `applyAgreementFromReports()`.
 */
class FriendlyMatch extends Model
{
    /** Estado del partido. */
    public const STATUS_CONFIRMADO = 'confirmado';
    public const STATUS_EN_DISPUTA = 'en_disputa';
    public const STATUS_JUGADO     = 'jugado';
    public const STATUS_CANCELADO  = 'cancelado';

    public const STATUSES = [
        self::STATUS_CONFIRMADO,
        self::STATUS_EN_DISPUTA,
        self::STATUS_JUGADO,
        self::STATUS_CANCELADO,
    ];

    /** Estado del acuerdo de resultado. */
    public const AGREEMENT_PENDIENTE  = 'pendiente';
    public const AGREEMENT_ACORDADO   = 'acordado';
    public const AGREEMENT_EN_DISPUTA  = 'en_disputa';

    protected $fillable = [
        'opportunity_id',
        'home_club_id',
        'away_club_id',
        'scheduled_at',
        'location',
        'venue_id',
        'status',
        'home_reported_home_score',
        'home_reported_away_score',
        'home_reported_at',
        'away_reported_home_score',
        'away_reported_away_score',
        'away_reported_at',
        'result_agreement',
        'final_home_score',
        'final_away_score',
        'escalated_at',
        'escalated_by_club_id',
        'resolved_by_user_id',
        'resolved_at',
        'cancelled_by_club_id',
        'cancellation_reason',
        'cancelled_at',
    ];

    /** Default alineado con la migración. */
    protected $attributes = [
        'status' => self::STATUS_CONFIRMADO,
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at'             => 'datetime',
            'cancelled_at'             => 'datetime',
            'home_reported_at'         => 'datetime',
            'away_reported_at'         => 'datetime',
            'escalated_at'             => 'datetime',
            'resolved_at'              => 'datetime',
            'home_reported_home_score' => 'integer',
            'home_reported_away_score' => 'integer',
            'away_reported_home_score' => 'integer',
            'away_reported_away_score' => 'integer',
            'final_home_score'         => 'integer',
            'final_away_score'         => 'integer',
        ];
    }

    // --- Relaciones ---

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function homeClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'home_club_id');
    }

    public function awayClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'away_club_id');
    }

    public function cancelledByClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'cancelled_by_club_id');
    }

    public function escalatedByClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'escalated_by_club_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    // --- Helpers semánticos de estado ---

    public function isConfirmado(): bool
    {
        return $this->status === self::STATUS_CONFIRMADO;
    }

    public function estaEnDisputa(): bool
    {
        return $this->status === self::STATUS_EN_DISPUTA;
    }

    public function isJugado(): bool
    {
        return $this->status === self::STATUS_JUGADO;
    }

    public function isCancelado(): bool
    {
        return $this->status === self::STATUS_CANCELADO;
    }

    /** ¿La disputa fue escalada a un admin de plataforma? */
    public function isEscalada(): bool
    {
        return $this->escalated_at !== null;
    }

    /** ¿Fue resuelto por un admin (resultado oficial)? */
    public function fueResueltoPorAdmin(): bool
    {
        return $this->resolved_by_user_id !== null;
    }

    // --- Identidad de los participantes ---

    /** ¿El club participa en este amistoso (local o visitante)? */
    public function involvesClub(int $clubId): bool
    {
        return $this->home_club_id === $clubId || $this->away_club_id === $clubId;
    }

    /** Lado del club: 'home' | 'away' | null si no participa. */
    public function sideForClub(int $clubId): ?string
    {
        return match ($clubId) {
            $this->home_club_id => 'home',
            $this->away_club_id => 'away',
            default             => null,
        };
    }

    /** Id del club rival desde la perspectiva de $clubId. */
    public function opponentClubId(int $clubId): ?int
    {
        return match ($clubId) {
            $this->home_club_id => $this->away_club_id,
            $this->away_club_id => $this->home_club_id,
            default             => null,
        };
    }

    /** ¿Ya reportó el lado indicado ('home'|'away')? */
    public function sideHasReported(string $side): bool
    {
        return $side === 'home' ? $this->homeHasReported() : $this->awayHasReported();
    }

    /**
     * Resultado final desde la perspectiva de un club (solo si hay acuerdo).
     * Devuelve ['for','against','outcome' => 'V'|'E'|'D'] o null.
     */
    public function resultForClub(int $clubId): ?array
    {
        if (! $this->hasAgreedResult()) {
            return null;
        }
        $side    = $this->sideForClub($clubId);
        if ($side === null) {
            return null;
        }
        $for     = $side === 'home' ? $this->final_home_score : $this->final_away_score;
        $against = $side === 'home' ? $this->final_away_score : $this->final_home_score;

        return [
            'for'     => $for,
            'against' => $against,
            'outcome' => $for > $against ? 'V' : ($for < $against ? 'D' : 'E'),
        ];
    }

    // --- Doble confirmación de resultado ---

    /** ¿El local ya reportó su marcador? */
    public function homeHasReported(): bool
    {
        return $this->home_reported_home_score !== null
            && $this->home_reported_away_score !== null;
    }

    /** ¿El visitante ya reportó su marcador? */
    public function awayHasReported(): bool
    {
        return $this->away_reported_home_score !== null
            && $this->away_reported_away_score !== null;
    }

    /** ¿Ambos reportaron? */
    public function bothReported(): bool
    {
        return $this->homeHasReported() && $this->awayHasReported();
    }

    /** ¿Los dos marcadores reportados coinciden? (requiere que ambos reporten). */
    public function reportsMatch(): bool
    {
        return $this->bothReported()
            && $this->home_reported_home_score === $this->away_reported_home_score
            && $this->home_reported_away_score === $this->away_reported_away_score;
    }

    /**
     * Resuelve el estado a partir de los marcadores reportados:
     * - ambos coinciden → final_* fijados, status `jugado`, agreement `acordado`.
     * - difieren        → status `en_disputa`, agreement `en_disputa`.
     * - falta uno       → agreement `pendiente`.
     *
     * No persiste: el caller decide cuándo guardar (mantiene el modelo puro).
     */
    public function applyAgreementFromReports(): self
    {
        if (! $this->bothReported()) {
            $this->result_agreement = self::AGREEMENT_PENDIENTE;

            return $this;
        }

        if ($this->reportsMatch()) {
            $this->final_home_score = $this->home_reported_home_score;
            $this->final_away_score = $this->home_reported_away_score;
            $this->result_agreement = self::AGREEMENT_ACORDADO;
            $this->status           = self::STATUS_JUGADO;
        } else {
            $this->final_home_score = null;
            $this->final_away_score = null;
            $this->result_agreement = self::AGREEMENT_EN_DISPUTA;
            $this->status           = self::STATUS_EN_DISPUTA;
        }

        return $this;
    }

    /** ¿Hay un resultado final acordado? */
    public function hasAgreedResult(): bool
    {
        return $this->result_agreement === self::AGREEMENT_ACORDADO
            && $this->final_home_score !== null;
    }

    // --- Scopes ---

    public function scopeEnDisputa($query)
    {
        return $query->where('status', self::STATUS_EN_DISPUTA);
    }

    public function scopeForClub($query, int $clubId)
    {
        return $query->where('home_club_id', $clubId)
                     ->orWhere('away_club_id', $clubId);
    }

    /** Amistosos en los que participa el club (agrupado: seguro de combinar). */
    public function scopeInvolvingClub($query, int $clubId)
    {
        return $query->where(function ($q) use ($clubId) {
            $q->where('home_club_id', $clubId)->orWhere('away_club_id', $clubId);
        });
    }

    /** Amistosos en los que participa alguno de los clubs dados. */
    public function scopeInvolvingAnyClub($query, array $clubIds)
    {
        return $query->where(function ($q) use ($clubIds) {
            $q->whereIn('home_club_id', $clubIds)->orWhereIn('away_club_id', $clubIds);
        });
    }

    public function scopeJugados($query)
    {
        return $query->where('status', self::STATUS_JUGADO);
    }

    public function scopeConfirmados($query)
    {
        return $query->where('status', self::STATUS_CONFIRMADO);
    }

    public function scopeCancelados($query)
    {
        return $query->where('status', self::STATUS_CANCELADO);
    }
}
