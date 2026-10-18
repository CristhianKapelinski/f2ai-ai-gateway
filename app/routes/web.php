<?php

use App\Http\Controllers\ChaveController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChaveController::class, 'index'])->name('chaves.index');
Route::post('/chaves', [ChaveController::class, 'store'])->name('chaves.store');
