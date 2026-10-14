<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The five fixed categories with literal identifiers (data-model.md section 9).
 * Names are product data in Spanish and are not translated (DT-19).
 */
return new class extends Migration
{
    private const CATEGORIES = [
        '11111111-1111-4111-8111-111111111111' => 'Novela',
        '22222222-2222-4222-8222-222222222222' => 'Ciencia',
        '33333333-3333-4333-8333-333333333333' => 'Tecnología',
        '44444444-4444-4444-8444-444444444444' => 'Historia',
        '55555555-5555-4555-8555-555555555555' => 'Infantil',
    ];

    public function up(): void
    {
        foreach (self::CATEGORIES as $id => $name) {
            DB::table('library.category')->insert(['id' => $id, 'name' => $name]);
        }
    }

    public function down(): void
    {
        DB::table('library.category')->whereIn('id', array_keys(self::CATEGORIES))->delete();
    }
};
