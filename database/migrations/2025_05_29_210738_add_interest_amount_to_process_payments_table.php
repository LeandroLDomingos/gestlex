<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            $table->decimal('interest_amount', 15, 2)->nullable()->after('value_of_installment')->comment('Valor dos juros pagos manualmente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_payments', function (Blueprint $table) {
            //
        });
    }
};
