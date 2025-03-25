<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PromptGeneratorController;

Route::get('/prompt-generator', [PromptGeneratorController::class, 'index'])->name('prompt-generator');
Route::get('/location-autocomplete', [PromptGeneratorController::class, 'autocomplete'])->name('location-autocomplete');
Route::post('/generate-prompt', [PromptGeneratorController::class, 'generate'])->name('generate-prompt');