<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\StakeRecommendationService;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class StakeRecommendationTest extends TestCase
{
    public function test_recommended_stake_route_returns_service_result()
    {
        // Préparer un utilisateur factice retourné par Auth
        $user = new User();
        $user->id = 12345;

        Auth::shouldReceive('user')->andReturn($user);

        $fakeResult = [
            'recommended_stake' => 12.34,
            'bankroll' => 1000.00,
            'target_gain' => 10.00,
            'lost_sum' => 0.00,
            'gain_voulu' => 10.00,
            'odds' => 2.0,
            'last_lost_bet_id' => null,
            'message' => 'pour gagner 1%'
        ];

        // Mock du service et binding dans le container
        $mockService = \Mockery::mock(StakeRecommendationService::class);
        $mockService->shouldReceive('recommend')->once()->with($user, \Mockery::on(function ($arr) {
            return isset($arr['bankroll_id']) && ($arr['bankroll_id'] == 2);
        }))->andReturn($fakeResult);
        $this->app->instance(StakeRecommendationService::class, $mockService);

        // Désactiver les middlewares (notamment auth:sanctum) pour ce test
        $this->withoutMiddleware();

        // Appel en passant bankroll_id=2
        $response = $this->getJson('/api/bankrolls/recommended-stake?bankroll_id=2');

        $response->assertStatus(200)
            ->assertJson(array_merge(['success' => true], $fakeResult));
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
