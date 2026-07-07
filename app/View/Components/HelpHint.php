<?php

namespace App\View\Components;

use App\Models\Support\SupportArticle;
use Illuminate\View\Component;
use Illuminate\View\View;

class HelpHint extends Component
{
    public ?SupportArticle $article;

    public function __construct(public string $topic)
    {
        $this->article = SupportArticle::published()
            ->forFeatureKey($topic)
            ->first();
    }

    public function render(): View
    {
        return view('components.help-hint');
    }
}
