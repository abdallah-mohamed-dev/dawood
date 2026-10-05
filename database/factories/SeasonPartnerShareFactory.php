<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\Season;
use App\Models\SeasonPartnerShare;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonPartnerShare>
 */
class SeasonPartnerShareFactory extends Factory
{
    public function definition(): array
    {
        return [
            'season_id' => Season::factory()->closed(),
            'partner_id' => Partner::factory(),
            'percentage' => 2_000,
            'share_amount' => 0,
            'carried_in' => 0,
            'withdrawn' => 0,
            'carried_out' => 0,
        ];
    }
}
