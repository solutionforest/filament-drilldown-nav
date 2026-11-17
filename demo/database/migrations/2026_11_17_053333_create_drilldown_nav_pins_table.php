<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drilldown_nav_pins', function (Blueprint $table) {
            $table->id();
            $table->string('user_type');
            $table->string('user_id');
            $table->string('panel');
            $table->string('tenant')->default(''); // '' = no tenant (keeps the unique index working)
            $table->text('url');
            $table->char('url_hash', 40);
            $table->string('label');
            $table->timestamps();

            $table->unique(['user_type', 'user_id', 'panel', 'tenant', 'url_hash'], 'drilldown_nav_pins_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drilldown_nav_pins');
    }
};
