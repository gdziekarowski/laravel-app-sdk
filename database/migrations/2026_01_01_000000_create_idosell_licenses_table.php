<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('idosell.licenses.connection');
        $table = (string) config('idosell.licenses.table', 'idosell_licenses');

        $schema = Schema::connection($connection ?: null);

        if ($schema->hasTable($table)) {
            return;
        }

        $schema->create($table, function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('client_id')->comment('Id konta sprzedawcy w IdoSell');
            $table->unsignedBigInteger('application_id')->comment('Id aplikacji z panelu dewelopera');
            $table->string('api_url')->comment('Adres Admin API sprzedawcy przekazany w webhooku');
            $table->text('api_license')->comment('Klucz licencji (SEKRET, zaszyfrowany)');
            $table->text('api_key')->nullable()->comment('Klucz/token Admin API (SEKRET, zaszyfrowany)');
            $table->string('authorization_type')->default('key')->comment('key | OAuth');
            $table->boolean('active')->default(true);
            $table->boolean('installation_confirmed')->default(false)->comment('Czy wysłano installation/done');

            // longText zamiast json — starsze MySQL (< 5.7.8) nie znają typu `json`.
            // Serializacją zajmuje się cast `array` na modelu; funkcji JSON po stronie bazy nie używamy.
            $table->longText('meta')->nullable()->comment('contact_data, selected_shops itp.');

            $table->timestamps();

            $table->unique(['client_id', 'application_id']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('idosell.licenses.connection') ?: null)
            ->dropIfExists((string) config('idosell.licenses.table', 'idosell_licenses'));
    }
};
