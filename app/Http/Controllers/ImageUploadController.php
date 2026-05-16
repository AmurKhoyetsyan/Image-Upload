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
        try {
            $request->validate([
                'image' => 'required|image',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            if ($request->hasFile('image')) {
                $file = $request->file('image');

                // Generate unique filename
                $extension = $file->getClientOriginalExtension();
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $uniqueId = uniqid() . '_' . time() . '_' . substr(md5($originalName . microtime()), 0, 8);
                $filename = $uniqueId . '.' . $extension;

                // Store the original image with unique name
                $path = $file->storeAs('images', $filename, 'public');

                // Save to database with unique path
                $image = Image::create([
                    'image_original' => $path,
                    'image_edit' => null, // Will be set after Gemini response
                ]);

                // Send image to Gemini API
                $geminiResponse = $this->sendToGemini($file);

                // Save Gemini response to database
                $geminiText = null;
                $nutritionData = null;
                if ($geminiResponse['success'] && isset($geminiResponse['text'])) {
                    $geminiText = $geminiResponse['text'];
                    $nutritionData = $geminiResponse['nutrition_data'] ?? null;

                    // Save full response as JSON string
                    $responseToSave = json_encode([
                        'text' => $geminiText,
                        'nutrition_data' => $nutritionData
                    ], JSON_UNESCAPED_UNICODE);

                    $image->update([
                        'image_edit' => $responseToSave
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
                    'nutrition_data' => $nutritionData,
                    'gemini' => $geminiResponse
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'No image file provided'
            ], 400);
        } catch (\Exception $e) {
            \Log::error('ImageUpload Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
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

            $prompt = <<<'PROMPT'
You are an expert Nutrition Fact Extraction AI. Analyze this image carefully.

### STEP 1: COLUMN DETECTION

1. **LOOK FOR "Per 100g/100ml" COLUMN FIRST** - headers like "Per 100g", "Por 100g", "pour 100g", "pro 100g"

   - If found: Set `"is_per_100g": true`, `"serving_size_g": 100`



2. **FALLBACK TO SERVING** - If NO 100g column exists:

   - Extract serving size weight (e.g., "113g") into `"serving_size_g"`

   - Set `"is_per_100g": false`



### STEP 2: VALUE EXTRACTION

Read EACH value carefully. Common issues to avoid:

- Don't confuse 1 with 7 (OCR issue)

- Don't mix kJ with kcal (energy_kcal should be kcal only)

- Don't swap protein and carbohydrate values

- Hebrew/Arabic: 100g column is usually on RIGHT side (RTL)



### STEP 3: SANITY CHECK (CRITICAL)

After extraction, verify your values make sense:

- **energy_kcal** should roughly equal: (protein × 4) + (carbs × 4) + (fat × 9)

- If your calculated kcal differs by >30% from extracted kcal, LOWER your confidence

- **fat** is typically 0-50g for most foods (only oils exceed this)

- **protein** is typically 0-30g for most foods (only protein powders exceed this)

- **carbohydrate** should be >= sugars



### STEP 4: CONFIDENCE SCORING (BE CONSERVATIVE!)

Set confidence based on:

- **90-100%**: Crystal clear, values pass sanity check, you are 100% certain

- **70-89%**: Readable but some uncertainty OR minor value concerns

- **50-69%**: Partial visibility OR sanity check questionable

- **30-49%**: Difficult to read, guessing some values

- **0-29%**: Cannot read OR not a nutrition label



IMPORTANT: If you are NOT 100% sure about a value, LOWER your confidence!



Set `needs_review: true` if:

- Any value seems uncertain

- Sanity check shows discrepancy

- Column detection was ambiguous



### JSON SCHEMA - Return ONLY valid JSON, no markdown

{
  "readable_text": boolean,
  "confidence_score": number (0-100),
  "needs_review": boolean,
  "extraction_notes": "string",
  "sanity_check_passed": boolean,
  "product_name": "string or null",
  "is_per_100g": boolean,
  "serving_size_g": number or null,
  "energy_kcal": number or null,
  "fat": number or null,
  "saturated_fat": number or null,
  "carbohydrate": number or null,
  "sugars": number or null,
  "fiber": number or null,
  "protein": number or null,
  "salt": number or null
}
PROMPT;

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
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

            $response = Http::timeout(300)->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                // Extract text from Gemini response
                $text = '';
                if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = $responseData['candidates'][0]['content']['parts'][0]['text'];
                }

                // Try to parse JSON from response
                $nutritionData = null;
                if (!empty($text)) {
                    // Remove markdown code blocks if present
                    $cleanedText = preg_replace('/```json\s*/', '', $text);
                    $cleanedText = preg_replace('/```\s*/', '', $cleanedText);
                    $cleanedText = trim($cleanedText);

                    // Try to extract JSON from response
                    if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $cleanedText, $jsonMatches)) {
                        $jsonData = json_decode($jsonMatches[0], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $nutritionData = $jsonData;
                        }
                    }
                }

                return [
                    'success' => true,
                    'text' => $text,
                    'nutrition_data' => $nutritionData,
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
