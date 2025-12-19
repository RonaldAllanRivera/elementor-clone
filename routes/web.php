<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\DesignController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('projects', ProjectController::class);
    Route::resource('projects.designs', DesignController::class)->shallow();
    Route::get('designs/{design}/preview', [DesignController::class, 'preview'])->name('designs.preview');
    Route::get('designs/{design}/diagnostics', [DesignController::class, 'diagnostics'])->name('designs.diagnostics');
    Route::get('designs/{design}/export-elementor', [DesignController::class, 'exportElementor'])->name('designs.exportElementor');
    Route::get('designs/{design}/elementor-json', [DesignController::class, 'elementorJson'])->name('designs.elementorJson');
    Route::post('designs/{design}/import-figma', [DesignController::class, 'importFromFigma'])->name('designs.importFromFigma');
});

require __DIR__.'/auth.php';
