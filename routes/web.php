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

Route::match(['get', 'post'], '/debug/opportunities/scrape', function () {
    echo "<h1>Radar Debugger — Opportunity Scraper</h1>";
    echo "Starting scrape...<br>";

    try {
        \Illuminate\Support\Facades\Artisan::call('opportunities:scrape');
        $output = \Illuminate\Support\Facades\Artisan::output();

        echo "<pre style='background: #111; color: #eee; padding: 20px; border-radius: 10px;'>";
        echo $output ?: "Scraper ran but returned no output. Check logs.";
        echo "</pre>";
    } catch (\Exception $e) {
        echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
    }

    return "<br>Done.";
});

Route::match(['get', 'post'], '/debug/feeds/scrape', function () {
    echo "<h1>Radar Debugger — Freelance Feed Scraper</h1>";
    echo "Starting RSS feed scrape...<br>";

    try {
        \Illuminate\Support\Facades\Artisan::call('opportunities:scrape-feeds');
        $output = \Illuminate\Support\Facades\Artisan::output();

        echo "<pre style='background: #111; color: #eee; padding: 20px; border-radius: 10px;'>";
        echo $output ?: "Scraper ran but returned no output. Check logs.";
        echo "</pre>";
    } catch (\Exception $e) {
        echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
    }

    return "<br>Done.";
});

Route::get('/api/opportunities', function (\Illuminate\Http\Request $request) {
    $perPage = $request->integer('per_page', 20);
    $opportunities = \App\Models\Opportunity::latest('posted_at')->paginate($perPage);
    return response()->json($opportunities);
});

Route::get('/api/opportunities/stats', function () {
    return response()->json([
        'totalOpportunities'     => \App\Models\Opportunity::count(),
        'totalNotificationsSent' => \App\Models\UserOpportunity::count(),
        'lastRunAt'              => \App\Models\Opportunity::latest('created_at')->value('created_at'),
    ]);
});
