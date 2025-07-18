<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ServiceProvider;
use App\Models\Category;

class ServiceProviderController extends Controller
{
    public function main(Request $request) {
        $providers = ServiceProvider::with(['user', 'reviews'])->latest()->get();
        $category = Category::where('level', 1)->with('subcategories')->get();
        return view('pages.index', compact('providers', 'category'));
    }
    
    public function serviceproviders(Request $request) {
        // Fetch all service providers with their user info
        $providers = ServiceProvider::with('user')->latest()->get();
        return view('dashboard.provider.service-providers', compact('providers'));
    }

    public function providerDetails(Request $request)
    {
        $id = $request->query('id') ?? $request->route('id');
        $provider = null;
        if ($id) {
            $provider = ServiceProvider::with('user')->where('slug', $id)->first();
        }
        if (!$provider) {
            abort(404, 'Provider not found');
        }
        return view('dashboard.provider.provider-details', compact('provider'));
    }

   
        public function providerProfile($slug) {
            $provider = ServiceProvider::with('user')->where('slug', $slug)->first();

            if (!$provider) {
                abort(404, 'Provider not found');
            }

            return view('dashboard.provider.provider-details', compact('provider'));
        }


    public function getSubcategories($categoryId)
    {
        // Fetch the subcategories for the selected category
        $subcategories = Category::where('parent_id', $categoryId)->get();

        return response()->json($subcategories);
    }

  public function getProviders(Request $request)
    {
        $categoryId = $request->input('category_id');
        $subcategoryId = $request->input('subcategory_id');
        $country = $request->input('country');
        $language = $request->input('language');

        // Convert to integers to match your JSON data
        $categoryId = (int) $categoryId;
        $subcategoryId = (int) $subcategoryId;

        $providers = ServiceProvider::whereJsonContains('services_to_offer', $categoryId)
                                    ->whereJsonContains('services_to_offer_category', $subcategoryId)
                                    ->where('spoken_language', 'LIKE', '%"' . $language . '"%')
                                    ->with(['user', 'reviews'])
                                    ->withAvg('reviews', 'rating') // This adds avg_rating to each provider
                                    ->get();

        // Transform the collection to include avgRating
        $providers = $providers->map(function ($provider) {
            $provider->avgRating = round($provider->reviews()->avg('rating') ?? 5, 1);
            $provider->reviewCount = $provider->reviews->count() ?? 1; // Add review count
            return $provider;
        });
        return response()->json($providers);
    }


}
