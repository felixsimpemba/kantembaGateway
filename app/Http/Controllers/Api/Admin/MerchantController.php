<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MerchantController extends Controller
{
    public function index(Request $request)
    {
        $query = Merchant::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        $merchants = $query->paginate(15);

        return response()->json($merchants);
    }

    public function show($id)
    {
        $merchant = Merchant::with('apiKeys')->findOrFail($id);
        return response()->json($merchant);
    }

    public function update(Request $request, $id)
    {
        $merchant = Merchant::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'business_name' => 'sometimes|string|max:255',
            'webhook_url' => 'nullable|url',
            'status' => 'sometimes|in:active,suspended',
        ]);

        $merchant->update($validated);

        return response()->json($merchant);
    }

    public function toggleBlock($id)
    {
        $merchant = Merchant::findOrFail($id);
        $merchant->status = $merchant->status === 'active' ? 'suspended' : 'active';
        $merchant->save();

        return response()->json(['message' => 'Merchant status updated', 'status' => $merchant->status]);
    }
}
