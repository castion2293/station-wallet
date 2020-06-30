<?php

use Faker\Generator as Faker;
use Illuminate\Support\Carbon;
use SuperPlatform\StationWallet\Models\StationWallet;

$factory->define(StationWallet::class, function (Faker $faker) {
    return [
        'station' => $faker->randomElement(['sa_gaming', 'all_bet', 'bingo', 'holdem', 'super_sport']),
        'status' => $faker->randomElement(['active', 'freezing']),
        'balance' => $faker->randomFloat(4, 10, 1500),
        'created_at' => Carbon::now()->toDateTimeString(),
        'updated_at' => Carbon::now()->toDateTimeString(),
    ];
});
