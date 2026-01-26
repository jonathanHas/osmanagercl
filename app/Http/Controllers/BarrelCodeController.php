<?php

namespace App\Http\Controllers;

use App\Models\BarrelCode;
use Illuminate\Http\Request;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class BarrelCodeController extends Controller
{
    /**
     * Display a listing of barrel codes.
     */
    public function index(Request $request)
    {
        $query = BarrelCode::with('supplier')
            ->withCount('deliveryBarrels');

        // Filter by supplier if provided
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Search by code, description, or name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('supplier_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $barrelCodes = $query->orderBy('supplier_code')
            ->paginate(25)
            ->withQueryString();

        // Get suppliers for filter dropdown
        $suppliers = \App\Models\Supplier::orderBy('Supplier')
            ->select('SupplierID', 'Supplier')
            ->get();

        return view('barrel-codes.index', compact('barrelCodes', 'suppliers'));
    }

    /**
     * Show the form for editing a barrel code.
     */
    public function edit(BarrelCode $barrelCode)
    {
        $barrelCode->load('supplier');

        // Get usage statistics
        $usageCount = $barrelCode->deliveryBarrels()->count();
        $totalQuantity = $barrelCode->deliveryBarrels()->sum('quantity');
        $totalValue = $barrelCode->deliveryBarrels()->sum('total');

        return view('barrel-codes.edit', compact('barrelCode', 'usageCount', 'totalQuantity', 'totalValue'));
    }

    /**
     * Update the specified barrel code.
     */
    public function update(Request $request, BarrelCode $barrelCode)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'description' => 'required|string|max:150',
            'unit_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $barrelCode->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'unit_price' => $validated['unit_price'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('barrel-codes.index')
            ->with('success', 'Barrel code updated successfully.');
    }

    /**
     * Serve barrel code image from database.
     */
    public function image(BarrelCode $barrelCode)
    {
        if (! $barrelCode->image) {
            abort(404);
        }

        return response($barrelCode->image)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Update barrel code image.
     */
    public function updateImage(Request $request, BarrelCode $barrelCode)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');

            $imageManager = new ImageManager(new GdDriver);
            $image = $imageManager->read($imageFile->getRealPath());

            // Resize to 100x100 for barrel images (smaller than product images)
            $image->resize(100, 100, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $encodedImage = $image->encodeByExtension($this->determineImageExtension($imageFile));
            $imageData = $encodedImage->toString();

            $barrelCode->update(['image' => $imageData]);

            return response()->json([
                'success' => true,
                'timestamp' => time(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No image uploaded',
        ], 400);
    }

    /**
     * Remove barrel code image.
     */
    public function removeImage(BarrelCode $barrelCode)
    {
        $barrelCode->update(['image' => null]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Determine the image extension from the uploaded file.
     */
    private function determineImageExtension($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'png' => 'png',
            'gif' => 'gif',
            default => 'jpg',
        };
    }
}
