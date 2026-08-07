<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fxhl_settings')) {
            Schema::create('fxhl_settings', function (Blueprint $table) {
                $table->string('key', 191)->primary();
                $table->longText('value')->nullable();
            });
        }

        if (!Schema::hasTable('fxhl_orders')) {
            Schema::create('fxhl_orders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 40)->unique();
                $table->string('email', 191);
                $table->string('username', 191);
                $table->string('name_first', 191);
                $table->string('name_last', 191);
                $table->text('password_encrypted');
                $table->string('plan_name', 100);
                $table->unsignedBigInteger('base_amount');
                $table->unsignedBigInteger('payable_amount')->index();
                $table->unsignedInteger('duration_days')->default(30);
                $table->string('status', 24)->default('pending')->index();
                $table->string('gateway_reference', 191)->nullable()->unique();
                $table->json('gateway_payload')->nullable();
                $table->text('qris_payload')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->string('signup_ip', 64)->nullable()->index();
                $table->timestamps();
                $table->index(['email', 'status']);
                $table->index(['username', 'status']);
            });
        }

        if (!Schema::hasTable('fxhl_accounts')) {
            Schema::create('fxhl_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('user_id')->unique();
                $table->string('kind', 24)->default('trial')->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->unique();
                $table->string('signup_ip', 64)->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fxhl_accounts');
        Schema::dropIfExists('fxhl_orders');
        Schema::dropIfExists('fxhl_settings');
    }
};
