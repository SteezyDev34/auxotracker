<?php

namespace App\Console\Concerns;

trait HasConsoleOutput
{
    /**
     * Afficher un titre de section dans les logs avec séparateurs.
     */
    private function lineHeader(string $title, int $width = 50): void
    {
        $this->line('');
        $this->line(str_repeat('-', $width));
        $this->line($title);
        $this->line(str_repeat('-', $width));
        $this->line('');
    }
}
