<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvitationCodeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        for ($i = 1; $i <= 10; $i++) {
            $code = sprintf('INV-%03d', $i);
            DB::table('invitation_codes')->updateOrInsert(
                ['code' => $code],
                [
                    'is_used' => false,
                    'used_by_user_id' => null,
                    'used_at' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
