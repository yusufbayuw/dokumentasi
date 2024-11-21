<?php

namespace App\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class LeafletMap extends Field
{
    protected string $view = 'forms.components.leaflet-map';

    protected string|Closure|null $floorImage = null;

    public function floorImage(string|Closure $imageUrl): static
    {
        $this->floorImage = $imageUrl;

        return $this;
    }

    public function getFloorImage(): ?string
    {
        return $this->evaluate($this->floorImage); // Use Filament's `evaluate` to resolve the Closure
    }
}
