<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->whereIn('image', [
                '0',
                'false',
                '',
            ])
            ->update([
                'image' => null,
            ]);

        DB::table('product_images')
            ->whereIn('image', [
                '0',
                'false',
                '',
            ])
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
