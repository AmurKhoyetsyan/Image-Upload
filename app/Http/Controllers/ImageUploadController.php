<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
                'image_edit' => null, // Will be set after Gemini response
            ]);

            // Send image to Gemini API
            $geminiResponse = $this->sendToGemini($file);

            // Save Gemini text response to database
            $geminiText = null;
            if ($geminiResponse['success'] && isset($geminiResponse['text'])) {
                $geminiText = $geminiResponse['text'];
                $image->update([
                    'image_edit' => $geminiText
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'image' => [
                    'id' => $image->id,
                    'image_original' => Storage::url($path),
                    'image_edit' => $geminiText,
                ],
                'gemini' => $geminiResponse
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'No image file provided'
        ], 400);
    }

    private function sendToGemini($file)
    {
        $apiKey = config('api.gemini.api_key');
        
        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'GEMINI_KEY not configured in .env file'
            ];
        }

        try {
            // Convert image to base64
            $imageContent = file_get_contents($file->getRealPath());
            $base64Image = base64_encode($imageContent);
            
            // Get MIME type
            $mimeType = $file->getMimeType();
            
            // Prepare the request for Gemini API
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key={$apiKey}";
            
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Опиши это изображение подробно. Что ты видишь на изображении?'
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Image
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $response = Http::timeout(30)->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Extract text from Gemini response
                $text = '';
                if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
                }

                return [
                    'success' => true,
                    'text' => $text,
                    'full_response' => $responseData
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Gemini API error: ' . $response->body(),
                    'status' => $response->status()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error sending to Gemini: ' . $e->getMessage()
            ];
        }
    }
}
