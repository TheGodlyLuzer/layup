<?php

namespace Crumbls\Layup\Forms\Components;

use Crumbls\Layup\Forms\Components\Traits\HandlesColumns;
use Crumbls\Layup\Forms\Components\Traits\HandlesRows;
use Crumbls\Layup\Forms\Components\Traits\HandlesWidgets;
use Filament\Forms\Components\Field;

class LayupBuilder extends Field
{
    use HandlesColumns;
    use HandlesRows;
    use HandlesWidgets;

    protected string $view = 'layup::forms.components.layup-builder';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            $this->rowDeleteAction(),
            $this->rowEditAction(),
            $this->columnDeleteAction(),
            $this->columnEditAction(),
            $this->widgetEditAction(),
            $this->widgetDeleteAction(),
        ]);
    }

    protected function syncContent(): void
    {
        // Intentionally empty — Alpine manages local state via $entangle.
        // Dispatch events directly from actions instead.
    }
}
