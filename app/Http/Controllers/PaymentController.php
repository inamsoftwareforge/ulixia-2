<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ServiceProvider;
use App\Models\MissionOffer;

class PaymentController extends Controller
{
    public function index(Request $request) {
        $providerId = $request->query('id');
        $missionId = $request->query('mission_id');
        $provider = ServiceProvider::find($providerId);

        if (!$provider) {
            abort(404, 'Provider not found');
        }
        $offer = null;
        if ($missionId) {
            $offer = MissionOffer::where('mission_id', $missionId)
                ->where('provider_id', $providerId)
                ->first();
        }

        // Get provider reviews
        $reviews = $provider->reviews()->get()->avg('rating');
        return view('dashboard.payments', compact('provider', 'offer', 'reviews'));
    }

    public function paymentValidate(Request $request) {
        
        return view('dashboard.payments.payments-validate');
    }
    public function earningAndPayments(Request $request) {
        return view('dashboard.my-earnings-payments');
    }
}
