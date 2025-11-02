<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExampleController extends Controller
{
    /**
     * Example endpoint with error logging
     */
    public function index()
    {
        try {
            // Your business logic here
            $data = ['message' => 'Success', 'data' => []];

            // Log info
            \Log::info('API request successful');

            return response()->json($data);
        } catch (\Exception $e) {
            // Log error with details
            \Log::error('API Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Example of different log levels
     */
    public function logExample()
    {
        // Different log levels available in Laravel:
        \Log::debug('Debug information');
        \Log::info('Informational message');
        \Log::notice('Notable events');
        \Log::warning('Warning message');
        \Log::error('Error message');
        \Log::critical('Critical conditions');
        \Log::alert('Action must be taken immediately');
        \Log::emergency('System is unusable');

        return response()->json(['message' => 'Logs created']);
    }
}
