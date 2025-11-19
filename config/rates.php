<?php
return [
  'base_fare' => env('RATE_BASE_FARE', 40),
  'per_km' => env('RATE_PER_KM', 30),
  'per_min' => env('RATE_PER_MIN', 5),
  'avg_speed_kph' => env('RATE_AVG_SPEED_KPH', 30),
  'fuel_price' => env('FUEL_PRICE', 200),
  'fuel_eff_km_per_l' => env('FUEL_EFF_KM_PER_L', 10),
];
