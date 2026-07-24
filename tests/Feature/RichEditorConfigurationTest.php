<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Forms\Components\RichEditor;
use Tests\TestCase;

class RichEditorConfigurationTest extends TestCase
{
    public function test_every_rich_editor_synchronises_with_livewire_when_it_loses_focus(): void
    {
        $component = RichEditor::make('notes');

        $this->assertTrue($component->isLive());
        $this->assertTrue($component->isLiveOnBlur());
    }
}
