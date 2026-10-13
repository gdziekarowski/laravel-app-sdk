<?php

use Idosell\LaravelAppSdk\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooki cyklu życia aplikacji IdoSell Apps
|--------------------------------------------------------------------------
|
| Prefiks, middleware i prefiks nazw pochodzą z `config/idosell.php`; grupę
| tras zakłada IdosellServiceProvider. Adresy do wpisania w panelu dewelopera
| pokaże `php artisan idosell:install` albo `php artisan idosell:doctor`.
|
*/

Route::post('new-license', [WebhookController::class, 'newLicense'])->name('new-license');
Route::post('remove-license', [WebhookController::class, 'removeLicense'])->name('remove-license');
Route::post('launch', [WebhookController::class, 'launch'])->name('launch');
