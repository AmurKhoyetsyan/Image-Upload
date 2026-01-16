<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // Store the original image
            $path = $file->storeAs('images', $filename, 'public');
            
            // Save to database
            $image = Image::create([
                'image_original' => $path,
                'image_edit' => null, // Will be set when image is edited
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'image' => [
                    'id' => $image->id,
                    'image_original' => Storage::url($path),
                    'image_edit' => null,
                ]
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'No image file provided'
        ], 400);
    }
}
