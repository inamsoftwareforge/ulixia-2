<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ServiceProvider;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MapController extends Controller
{
    public function getProviders(Request $request): JsonResponse
    {
        try {
            // Cache the providers data for 15 minutes to improve performance
            $providers = Cache::remember('map_providers', 900, function () {
                return ServiceProvider::with([
                    'user:id,name,name,email',
                    'reviews:id,provider_id,rating'
                ])
                ->whereNotNull('profile_photo')
                ->whereNotNull('country')
                ->whereNotNull('provider_address')
                ->where('profile_photo', '!=', '')
                ->select([
                    'id',
                    'user_id',
                    'first_name',
                    'last_name',
                    'native_language',
                    'spoken_language',
                    'services_to_offer',
                    'services_to_offer_category',
                    'provider_address',
                    'operational_countries',
                    'communication_online',
                    'communication_inperson',
                    'profile_description',
                    'profile_photo',
                    'phone_number',
                    'country',
                    'special_status',
                    'email',
                    'slug',
                    'created_at',
                    'updated_at'
                ])
                ->get()
                ->map(function ($provider) {
                    // Calculate average rating and review count
                    $reviews = $provider->reviews;
                    $averageRating = $reviews->avg('rating') ?? 0;
                    $reviewCount = $reviews->count();

                    return [
                        'id' => $provider->id,
                        'first_name' => $provider->first_name,
                        'last_name' => $provider->last_name,
                        'native_language' => $provider->native_language,
                        'spoken_language' => $provider->spoken_language ?? [],
                        'services_to_offer' => $provider->services_to_offer,
                        'services_to_offer_category' => $provider->services_to_offer_category,
                        'provider_address' => $provider->provider_address,
                        'operational_countries' => $provider->operational_countries ?? [],
                        'communication_online' => $provider->communication_online,
                        'communication_inperson' => $provider->communication_inperson,
                        'profile_description' => $provider->profile_description,
                        'profile_photo' => $provider->profile_photo,
                        'phone_number' => $provider->phone_number,
                        'country' => $provider->country,
                        'special_status' => $provider->special_status ?? [],
                        'email' => $provider->email,
                        'slug' => $provider->slug ?? null,
                        'average_rating' => round($averageRating, 1),
                        'reviews_count' => $reviewCount,
                        'created_at' => $provider->created_at,
                        'updated_at' => $provider->updated_at,
                    ];
                });
            });

            // Apply filters if provided
            $filteredProviders = $this->applyFilters($providers, $request)
                                ->map(function ($provider) {
                                    $provider['services_to_offer_category'] = $this->fetchCategoryNames($provider['services_to_offer_category']);
                                    return $provider;
                                });
            return response()->json([
                'success' => true,
                'message' => 'Providers loaded successfully',
                'data' => $filteredProviders,
                'total' => $filteredProviders->count(),
                'filters' => $this->getAvailableFilters($providers)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading providers',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }


    private function applyFilters($providers, Request $request)
    {
        return $providers->filter(function ($provider) use ($request) {
            // Country filter
            if ($request->filled('country') && $provider['country'] !== $request->country) {
                return false;
            }

            // City filter (case-insensitive partial match)
            if ($request->filled('city')) {
                $cityQuery = strtolower($request->city);
                $providerCity = strtolower($provider['provider_address'] ?? '');
                if (!str_contains($providerCity, $cityQuery)) {
                    return false;
                }
            }

            // Category filter
            if ($request->filled('category') && $provider['services_to_offer_category'] !== $request->category) {
                return false;
            }

            // Language filter
            if ($request->filled('language')) {
                $spokenLanguages = $provider['spoken_language'] ?? [];
                if (!in_array($request->language, $spokenLanguages)) {
                    return false;
                }
            }

            // Minimum rating filter
            if ($request->filled('min_rating')) {
                if ($provider['average_rating'] < $request->min_rating) {
                    return false;
                }
            }

            // Verified providers only
            if ($request->boolean('verified_only')) {
                if (empty($provider['special_status']) && $provider['reviews_count'] < 5) {
                    return false;
                }
            }

            return true;
        });
    }

    private function getAvailableFilters($providers): array
    {
        $countries = $providers->pluck('country')->unique()->filter()->sort()->values();
        $categories = $providers->pluck('services_to_offer_category')->unique()->filter()->sort()->values();
        $categoryNames = [];

        foreach ($categories as $category) {
            $decoded = json_decode($category, true);

            if (is_array($decoded)) {
                foreach ($decoded as $cat) {
                    $catName = Category::where('id', $cat)->pluck('name')->first();
                    if ($catName) {
                        $categoryNames[] = $catName;
                    }
                }
            } else {
                $catName = Category::where('id', $category)->pluck('name')->first();
                if ($catName) {
                    $categoryNames[] = $catName;
                }
            }
        }
        $languages = $providers->pluck('spoken_language')
            ->flatten()
            ->unique()
            ->filter()
            ->sort()
            ->values();

        return [
            'countries' => $countries,
            'categories' => $categoryNames,
            'languages' => $languages,
        ];
    }

    private function fetchCategoryNames($categoryIds): string
    {
        $categoryName = '';
        foreach (json_decode($categoryIds, true) as $category) {
            if (is_array($category)) {
                foreach ($category as $cat) {
                    $catName = Category::where('id', $cat)->pluck('name')->first();
                    if (!empty($categoryName)) {
                        $categoryName .= ', ' . $catName;
                    } else {
                        $categoryName = $catName;
                    }
                }
            } else {
                $catName = Category::where('id', $category)->pluck('name')->first();
                if (!empty($categoryName)) {
                    $categoryName .= ', ' . $catName;
                } else {
                    $categoryName = $catName;
                }
            }
        }
        return $categoryName;
    }
}
