<?php

namespace App\Console\Commands\Torneos;

use App\Models\Torneos\Tournament;
use App\Services\Torneos\FixtureGeneratorService;
use Illuminate\Console\Command;
use Throwable;

class GenerateFixture extends Command
{
    protected $signature = 'torneos:generate-fixture {tournament_id : ID del torneo}';

    protected $description = 'Genera el fixture completo (fases, grupos y partidos) de un torneo según su formato.';

    public function handle(FixtureGeneratorService $generator): int
    {
        $id = (int) $this->argument('tournament_id');
        $tournament = Tournament::find($id);

        if (! $tournament) {
            $this->error("No existe el torneo con id {$id}.");
            return self::FAILURE;
        }

        try {
            $summary = $generator->generate($tournament);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $tournament->refresh()->load('phases.groups', 'phases.matches');

        $this->info("Fixture generado para \"{$tournament->name}\" ({$tournament->format}).");
        $this->line("Estado del torneo: {$tournament->status}");
        $this->line('');
        $this->line(sprintf('  Fases creadas:     %d', $summary['phases']));
        $this->line(sprintf('  Grupos creados:    %d', $summary['groups']));
        $this->line(sprintf('  Partidos generados: %d', $summary['matches']));
        $this->line('');

        $this->line('Detalle por fase:');
        foreach ($tournament->phases->sortBy('order') as $phase) {
            $this->line(sprintf(
                '  [%d] %-22s %-12s %2d grupo(s)  %3d partido(s)',
                $phase->order,
                $phase->name,
                $phase->type,
                $phase->groups->count(),
                $phase->matches->count(),
            ));
        }

        return self::SUCCESS;
    }
}
