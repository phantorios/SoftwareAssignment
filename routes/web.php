<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EquipmentController;

//equipments
Route::get('/equipments', [EquipmentController::class, 'index'])->name('equipments.index');
Route::get('/equipments/search', [EquipmentController::class, 'search'])->name('equipments.search');

Route::get('/', function () {
    return view('welcome');
});
