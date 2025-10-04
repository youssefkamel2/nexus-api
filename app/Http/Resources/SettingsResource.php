<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'our_mission' => $this->our_mission,
            'our_vision' => $this->our_vision,
            'years' => $this->years,
            'projects' => $this->projects,
            'clients' => $this->clients,
            'engineers' => $this->engineers,
            'portfolio' => $this->portfolio,
            'image' => $this->image,
        ];
    }
}
