<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larc_menus', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key')->unique();
            $table->string('area', 64)->nullable()->index();
            $table->string('parent_key')->nullable()->index();
            $table->string('source', 32)->default('metadata');
            $table->string('icon')->nullable();
            $table->string('path')->nullable();
            $table->boolean('path_locked')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->boolean('order_locked')->default(false);
            $table->string('status', 32)->default('enabled')->index();
            $table->boolean('is_orphaned')->default(false)->index();
            $table->timestamp('orphaned_at')->nullable()->index();
            $table->timestamp('maintenance_starts_at')->nullable();
            $table->unsignedInteger('maintenance_notify_before_value')->nullable();
            $table->string('maintenance_notify_before_unit', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('larc_menu_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('larc_menus')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('label');
            $table->text('maintenance_message')->nullable();
            $table->timestamps();

            $table->unique(['menu_id', 'locale']);
        });

        Schema::create('larc_features', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key')->unique();
            $table->string('area', 64)->nullable()->index();
            $table->foreignId('menu_id')->nullable()->constrained('larc_menus')->nullOnDelete();
            $table->string('source', 32)->default('metadata');
            $table->string('path')->nullable();
            $table->boolean('path_locked')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->string('placement')->nullable()->index();
            $table->boolean('order_locked')->default(false);
            $table->string('status', 32)->default('enabled')->index();
            $table->boolean('is_orphaned')->default(false)->index();
            $table->timestamp('orphaned_at')->nullable()->index();
            $table->timestamp('maintenance_starts_at')->nullable();
            $table->unsignedInteger('maintenance_notify_before_value')->nullable();
            $table->string('maintenance_notify_before_unit', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('larc_feature_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feature_id')->constrained('larc_features')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('label');
            $table->text('maintenance_message')->nullable();
            $table->timestamps();

            $table->unique(['feature_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larc_feature_translations');
        Schema::dropIfExists('larc_features');
        Schema::dropIfExists('larc_menu_translations');
        Schema::dropIfExists('larc_menus');
    }
};
