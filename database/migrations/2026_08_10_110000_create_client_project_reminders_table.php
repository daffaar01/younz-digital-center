<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_project_reminders', function (Blueprint $table): void {
            $table->id();
            $table->string('project_name', 180);
            $table->string('customer_name', 180);
            $table->string('whatsapp', 30)->nullable();
            $table->string('email', 180)->nullable();
            $table->text('address')->nullable();
            $table->string('project_type', 20);
            $table->string('hosting_provider', 100)->nullable();
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable()->index();
            $table->text('hosting_login_email')->nullable();
            $table->text('hosting_login_password')->nullable();
            $table->string('payment_status', 20);
            $table->unsignedBigInteger('amount')->nullable();
            $table->date('transaction_date')->nullable()->index();
            $table->string('contact_method', 30);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_reminders');
    }
};
