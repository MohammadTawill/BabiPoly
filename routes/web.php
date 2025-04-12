<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

// Route for the form page
Route::get('/', function () {
    return view('instagram_form');
});


Route::match(['get', 'post'], '/generate-pdf', [PdfController::class, 'generatePDF']);

// Fallback route to redirect to the root
Route::fallback(function () {
    return redirect('/');
});


