<?php

namespace App\Services\Support;

use App\Models\Support\SupportArticle;
use App\Models\Support\SupportTicket;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class KnowledgeBaseService
{
    /**
     * Búsqueda por relevancia. Usa LIKE (portable MySQL/SQLite); ordena por utilidad.
     */
    public function search(string $query): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        return SupportArticle::published()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->orderByDesc('helpful_count')
            ->take(3)
            ->get(['id', 'title', 'slug', 'content', 'category']);
    }

    public function getByCategory(string $category): Collection
    {
        return SupportArticle::published()
            ->where('category', $category)
            ->orderByDesc('helpful_count')
            ->get();
    }

    public function generateArticleFromTicket(SupportTicket $ticket, string $aiResponse): SupportArticle
    {
        $slug = Str::slug($ticket->subject) . '-' . $ticket->id;

        return SupportArticle::create([
            'title'            => $ticket->subject,
            'slug'             => $slug,
            'content'          => $aiResponse,
            'category'         => $this->categoryToArticleCategory($ticket->category),
            'source'           => 'auto_generado',
            'source_ticket_id' => $ticket->id,
            'is_published'     => false, // requiere revisión manual del admin
        ]);
    }

    private function categoryToArticleCategory(string $cat): string
    {
        return match ($cat) {
            'bug', 'disputa'                             => 'tecnico',
            'cuenta', 'verificacion', 'abuso', 'reclamo' => 'cuenta',
            'duda'                                       => 'torneos',
            'sugerencia', 'funcionalidad'                => 'politicas',
            default                                      => 'tecnico',
        };
    }
}
