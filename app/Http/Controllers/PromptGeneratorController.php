<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PromptGeneratorController extends Controller
{
    public function index()
    {
        return view('prompt-generator.index');
    }

    public function autocomplete(Request $request)
    {
        try {
            $query = $request->input('query');
            
            if (empty($query)) {
                return response()->json([]);
            }

            $apiKey = env('GEOAPIFY_API_KEY');
            if (empty($apiKey)) {
                throw new \Exception('Geoapify API key not configured');
            }

            $response = Http::withOptions(['verify' => false])
                ->timeout(10)
                ->get("https://api.geoapify.com/v1/geocode/autocomplete", [
                    'text' => $query,
                    'limit' => 5,
                    'filter' => 'countrycode:us',
                    'apiKey' => $apiKey
                ]);

            if (!$response->successful()) {
                throw new \Exception('Geoapify API request failed: '.$response->status());
            }

            $suggestions = collect($response->json('features'))
                ->map(function ($feature) {
                    return [
                        'formatted' => $feature['properties']['formatted'] ?? '',
                        'lat' => $feature['properties']['lat'] ?? 0,
                        'lon' => $feature['properties']['lon'] ?? 0
                    ];
                })
                ->filter(fn($item) => !empty($item['formatted']))
                ->toArray();

            return response()->json($suggestions);

        } catch (\Exception $e) {
            Log::error('Autocomplete error: '.$e->getMessage());
            return response()->json([
                'error' => 'Service unavailable',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'rooms' => 'required|integer|between:1,10',
            'washrooms' => 'required|integer|between:1,10',
            'description' => 'required|string|max:500',
            'platform' => 'required|in:Facebook,Instagram,Own Website'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', $validator->errors()->toArray());
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $inputData = $request->all();
            Log::info('Generating description with data:', $inputData);

            $nearbyPlaces = $this->getNearbyPlaces(
                $request->input('latitude'),
                $request->input('longitude')
            );

            Log::info('Nearby places found:', $nearbyPlaces);

            $prompt = $this->constructPrompt(
                $request->input('location'),
                $request->input('rooms'),
                $request->input('washrooms'),
                $request->input('description'),
                $nearbyPlaces,
                $request->input('platform')
            );

            Log::info('Generated prompt:', ['prompt' => $prompt]);

            $generatedDescription = $this->generateWithGemini($prompt);

            Log::info('Successfully generated description');

            return response()->json([
                'description' => $generatedDescription,
                'input_data' => $inputData,
                'nearby_places' => $nearbyPlaces,
                'prompt' => $prompt
            ]);

        } catch (\Exception $e) {
            Log::error('Generate description error: '.$e->getMessage());
            return response()->json([
                'error' => 'Failed to generate description',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTrace() : null
            ], 500);
        }
    }

    private function getNearbyPlaces($latitude, $longitude)
    {
        $apiKey = env('GEOAPIFY_API_KEY');
        $response = Http::withOptions(['verify' => false])
            ->timeout(10)
            ->get("https://api.geoapify.com/v2/places", [
                'categories' => 'commercial,leisure',
                'filter' => "circle:{$longitude},{$latitude},5000",
                'limit' => 10,
                'apiKey' => $apiKey
            ]);

        if ($response->successful()) {
            return collect($response->json('features'))
                ->map(function ($feature) {
                    return $feature['properties']['name'];
                })
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        }

        return [];
    }

    private function constructPrompt($location, $rooms, $washrooms, $description, $nearbyPlaces, $platform)
    {
        $nearbyText = empty($nearbyPlaces) ? '' : "Nearby attractions include: ".implode(', ', $nearbyPlaces).". ";

        $platformSpecific = match($platform) {
            'Facebook' => "The description should be engaging and encourage sharing. Include emojis where appropriate.",
            'Instagram' => "The description should be concise but impactful, with hashtag suggestions at the end. Include emojis.",
            default => "The description should be detailed and professional, optimized for SEO with relevant keywords."
        };

        return "Write a detailed 500-700 word property description for a real estate listing in {$location}. ".
            "The property has {$rooms} bedrooms and {$washrooms} bathrooms. ".
            "Key features mentioned by the owner: {$description}. ".
            $nearbyText.
            "Structure the description with these sections: 1) Engaging introduction, 2) Detailed property features, ".
            "3) Nearby attractions and amenities, 4) Closing statement with a call to action. ".
            "The description should be optimized for {$platform}. {$platformSpecific} ".
            "Use a professional but inviting tone. Avoid generic phrases and highlight unique aspects of the property.";
    }

    private function generateWithGemini($prompt)
    {
        $apiKey = env('GEMINI_API_KEY');
        $response = Http::withoutVerifying()
            ->timeout(30)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

        if ($response->successful()) {
            return $response->json('candidates.0.content.parts.0.text');
        }

        throw new \Exception('Gemini API error: '.$response->body());
    }
}