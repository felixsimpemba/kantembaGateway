<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Http\Request;

class GatewayController extends Controller
{
    public function stats()
    {
        $totalMerchants = Merchant::count();

        $totalTransactions = Transaction::where('status', 'success')->count();

        $totalVolume = Transaction::where('status', 'success')->sum('amount');

        // Group volume by currency
        $volumeByCurrency = Transaction::where('status', 'success')
            ->selectRaw('currency, sum(amount) as total')
            ->groupBy('currency')
            ->get();

        return response()->json([
            'total_merchants' => $totalMerchants,
            'total_transactions' => $totalTransactions,
            'total_volume' => $totalVolume, // This might be mixed currency, simpler to rely on volumeByCurrency
            'volume_by_currency' => $volumeByCurrency,
        ]);
    }
}
