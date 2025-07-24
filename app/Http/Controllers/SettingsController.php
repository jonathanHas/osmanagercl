<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'app_name' => config('app.name'),
            'app_debug' => config('app.debug'),
            'cache_status' => $this->getCacheStatus(),
            'database_status' => $this->getDatabaseStatus(),
            'storage_status' => $this->getStorageStatus(),
        ];

        return view('settings.index', compact('settings'));
    }

    public function clearCache()
    {
        try {
            // Clear application caches
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('route:clear');
            \Artisan::call('view:clear');

            return redirect()->route('settings.index')->with('success', 'All caches cleared successfully.');
        } catch (\Exception $e) {
            Log::error('Cache clear failed: ' . $e->getMessage());
            return redirect()->route('settings.index')->with('error', 'Failed to clear caches.');
        }
    }

    public function systemInfo()
    {
        $info = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database_connection' => config('database.default'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
        ];

        return view('settings.system-info', compact('info'));
    }

    private function getCacheStatus()
    {
        try {
            Cache::put('test_key', 'test_value', 60);
            $value = Cache::get('test_key');
            Cache::forget('test_key');
            
            return $value === 'test_value' ? 'Working' : 'Not Working';
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    private function getDatabaseStatus()
    {
        try {
            \DB::connection()->getPdo();
            return 'Connected';
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    private function getStorageStatus()
    {
        $storagePath = storage_path();
        return is_writable($storagePath) ? 'Writable' : 'Not Writable';
    }
}