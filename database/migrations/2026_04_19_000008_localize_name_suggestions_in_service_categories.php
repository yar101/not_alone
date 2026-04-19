<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE service_categories
            SET name_suggestions = jsonb_build_object('ru', name_suggestions::jsonb, 'en', '[]'::jsonb)
            WHERE name_suggestions IS NOT NULL
              AND jsonb_typeof(name_suggestions::jsonb) = 'array'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE service_categories
            SET name_suggestions = (name_suggestions::jsonb)->'ru'
            WHERE name_suggestions IS NOT NULL
              AND jsonb_typeof(name_suggestions::jsonb) = 'object'
        ");
    }
};
