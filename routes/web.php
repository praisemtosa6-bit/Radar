<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return 'OK';
});

Route::get('/', function () {
    return view('auth.login');
});

Route::get('/setup', function () {
    return view('setup');
});
Route::get('/debug/scrape', function () {
    echo "<h1>Radar Debugger</h1>";
    echo "Starting scraper...<br>";
    
    try {
        $output = "";
        \Illuminate\Support\Facades\Artisan::call('gigs:scrape-nitter');
        $output = \Illuminate\Support\Facades\Artisan::output();
        
        echo "<pre style='background: #111; color: #eee; padding: 20px; border-radius: 10px;'>";
        echo $output ?: "Scraper ran but returned no output. Check if any new gigs were actually found.";
        echo "</pre>";
    } catch (\Exception $e) {
        echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
    }
    
    return "<br>Done.";
});

Route::get('/preferences', function () {
    return view('preferences');
});
