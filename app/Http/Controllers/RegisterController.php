<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ServiceProvider;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Services\GeolocationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $expats = $request->all();
        $ip = $request->ip();
        $geoLocationService = new GeolocationService();
        $countryName = $geoLocationService->getCountryFromRequest($request);

        $user = User::where('email', $expats['email'])->first();

        if ($user) {
            // User exists: update user_role and create provider record
            $user->user_role = 'service_provider';
            $user->save();
        } else {
            // Create new user
            $affiliateLink = $this->generateAffiliateLink($expats['email'] ?? '', $expats['first_name'] ?? '', $expats['last_name'] ?? '');
            $user = User::create([
                'name' => trim(($expats['first_name'] ?? '') . ' ' . ($expats['last_name'] ?? '')),
                'email' => $expats['email'],
                'password' => Hash::make($expats['password'] ?? Str::random(12)),
                'country' => $countryName,
                'preferred_language' => $expats['native_language'] ?? null,
                'user_role' => 'service_provider',
                'affiliate_code' => $affiliateLink,
                'referred_by' => $expats['referred_by'] ?? null,
                'referral_stats' => $expats['referral_stats'] ?? null,
                'status' => 'active',
                'is_fake' => $expats['is_fake'] ?? false,
                'last_login_at' => now(),
            ]);
        }

        $profileImagePath = null;
        if (!empty($expats['profile_image'])) {
            $profileImagePath = saveBase64Image($expats['profile_image'], 'assets/profileImages', 'profile-' . $user->id);
        }


        $documents = [];
        $docTypes = ['passport', 'european_id', 'license'];
        foreach ($docTypes as $docType) {
            if (!empty($expats['documents'][$docType])) {
                $docData = $expats['documents'][$docType];
                $docArr = [];
                if (isset($docData['image'])) {
                    $docArr['image'] = saveBase64Image($docData['image'], 'assets/userDocs/docs-' . $user->id, $docType);
                }
                if (isset($docData['front'])) {
                    $docArr['front'] = saveBase64Image($docData['front'], 'assets/userDocs/docs-' . $user->id, $docType . '-front');
                }
                if (isset($docData['back'])) {
                    $docArr['back'] = saveBase64Image($docData['back'], 'assets/userDocs/docs-' . $user->id, $docType . '-back');
                }
                $docArr['uploaded_at'] = $docData['uploaded_at'] ?? now();
                $documents[$docType] = $docArr;
            }
        }
 
        $provider = ServiceProvider::where('user_id', $user->id)->where('email', $expats['email'])->first();

        if($provider) {
            return response()->json([
                'status' => 'success',
                'user' => $user,
                'provider' => $provider,
                'message' => 'Provider Already Exists',
            ]);
        }
        
        $categoriesMetaData = isset($expats['provider_subcategories']) ? json_encode($expats['provider_subcategories']) : null;
        $categoriesArray = json_decode($categoriesMetaData, true); 
        $category = array_keys($categoriesArray);
        $subcategoryArray = [];
        $subcategory = array_values($categoriesArray);
        foreach ($subcategory as $value) {
            if (is_array($value)) {
                foreach ($value as $subValue) {
                    $subcategoryArray[] = $subValue;
                }
            } elseif (is_string($value)) {
                $subcategoryArray[] = json_encode($value);
            } else {
                $subcategoryArray[] = $value;
            }
        }
        $slug = $this->generateSlug($expats, $countryName);
        $countryCoordinates = [
            'Afghanistan' => [33.9391, 67.7100],
            'Albania' => [41.1533, 20.1683],
            'Algeria' => [28.0339, 1.6596],
            'Andorra' => [42.5063, 1.5218],
            'Angola' => [-11.2027, 17.8739],
            'Argentina' => [-38.4161, -63.6167],
            'Armenia' => [40.0691, 45.0382],
            'Australia' => [-25.2744, 133.7751],
            'Austria' => [47.5162, 14.5501],
            'Azerbaijan' => [40.1431, 47.5769],
            'Bahamas' => [25.0343, -77.3963],
            'Bahrain' => [25.9304, 50.6378],
            'Bangladesh' => [23.6850, 90.3563],
            'Barbados' => [13.1939, -59.5432],
            'Belarus' => [53.7098, 27.9534],
            'Belgium' => [50.5039, 4.4699],
            'Belize' => [17.1899, -88.4976],
            'Benin' => [9.3077, 2.3158],
            'Bhutan' => [27.5142, 90.4336],
            'Bolivia' => [-16.2902, -63.5887],
            'Bosnia and Herzegovina' => [43.9159, 17.6791],
            'Botswana' => [-22.3285, 24.6849],
            'Brazil' => [-14.2350, -51.9253],
            'Brunei' => [4.5353, 114.7277],
            'Bulgaria' => [42.7339, 25.4858],
            'Burkina Faso' => [12.2383, -1.5616],
            'Burundi' => [-3.3731, 29.9189],
            'Cabo Verde' => [16.5388, -24.0132],
            'Cambodia' => [12.5657, 104.9910],
            'Cameroon' => [7.3697, 12.3547],
            'Canada' => [56.1304, -106.3468],
            'Central African Republic' => [6.6111, 20.9394],
            'Chad' => [15.4542, 18.7322],
            'Chile' => [-35.6751, -71.5430],
            'China' => [35.8617, 104.1954],
            'Colombia' => [4.5709, -74.2973],
            'Comoros' => [-11.6455, 43.3333],
            'Congo' => [-0.2280, 15.8277],
            'Costa Rica' => [9.7489, -83.7534],
            'Croatia' => [45.1000, 15.2000],
            'Cuba' => [21.5218, -77.7812],
            'Cyprus' => [35.1264, 33.4299],
            'Czech Republic' => [49.8175, 15.4730],
            'Denmark' => [56.2639, 9.5018],
            'Djibouti' => [11.8251, 42.5903],
            'Dominica' => [15.4140, -61.3710],
            'Dominican Republic' => [18.7357, -70.1627],
            'Ecuador' => [-1.8312, -78.1834],
            'Egypt' => [26.0975, 30.0444],
            'El Salvador' => [13.7942, -88.8965],
            'Equatorial Guinea' => [1.6508, 10.2679],
            'Eritrea' => [15.1794, 39.7823],
            'Estonia' => [58.5953, 25.0136],
            'Eswatini' => [-26.5225, 31.4659],
            'Ethiopia' => [9.1450, 40.4897],
            'Fiji' => [-16.7784, 179.4144],
            'Finland' => [61.9241, 25.7482],
            'France' => [46.6034, 1.8883],
            'Gabon' => [-0.8037, 11.6094],
            'Gambia' => [13.4432, -15.3101],
            'Georgia' => [42.3154, 43.3569],
            'Germany' => [51.1657, 10.4515],
            'Ghana' => [7.9465, -1.0232],
            'Greece' => [39.0742, 21.8243],
            'Grenada' => [12.1165, -61.6790],
            'Guatemala' => [15.7835, -90.2308],
            'Guinea' => [9.9456, -9.6966],
            'Guinea-Bissau' => [11.8037, -15.1804],
            'Guyana' => [4.8604, -58.9302],
            'Haiti' => [18.9712, -72.2852],
            'Honduras' => [15.2000, -86.2419],
            'Hungary' => [47.1625, 19.5033],
            'Iceland' => [64.9631, -19.0208],
            'India' => [20.5937, 78.9629],
            'Indonesia' => [-0.7893, 113.9213],
            'Iran' => [32.4279, 53.6880],
            'Iraq' => [33.2232, 43.6793],
            'Ireland' => [53.4129, -8.2439],
            'Israel' => [31.0461, 34.8516],
            'Italy' => [41.8719, 12.5674],
            'Jamaica' => [18.1096, -77.2975],
            'Japan' => [36.2048, 138.2529],
            'Jordan' => [30.5852, 36.2384],
            'Kazakhstan' => [48.0196, 66.9237],
            'Kenya' => [-0.0236, 37.9062],
            'Kiribati' => [-3.3704, -168.7340],
            'Kuwait' => [29.3117, 47.4818],
            'Kyrgyzstan' => [41.2044, 74.7661],
            'Laos' => [19.8563, 102.4955],
            'Latvia' => [56.8796, 24.6032],
            'Lebanon' => [33.8547, 35.8623],
            'Lesotho' => [-29.6100, 28.2336],
            'Liberia' => [6.4281, -9.4295],
            'Libya' => [26.3351, 17.2283],
            'Liechtenstein' => [47.1660, 9.5554],
            'Lithuania' => [55.1694, 23.8813],
            'Luxembourg' => [49.8153, 6.1296],
            'Madagascar' => [-18.7669, 46.8691],
            'Malawi' => [-13.2543, 34.3015],
            'Malaysia' => [4.2105, 101.9758],
            'Maldives' => [3.2028, 73.2207],
            'Mali' => [17.5707, -3.9962],
            'Malta' => [35.9375, 14.3754],
            'Marshall Islands' => [7.1315, 171.1845],
            'Mauritania' => [21.0079, -10.9408],
            'Mauritius' => [-20.3484, 57.5522],
            'Mexico' => [23.6345, -102.5528],
            'Micronesia' => [7.4256, 150.5508],
            'Moldova' => [47.4116, 28.3699],
            'Monaco' => [43.7384, 7.4246],
            'Mongolia' => [46.8625, 103.8467],
            'Montenegro' => [42.7087, 19.3744],
            'Morocco' => [31.7917, -7.0926],
            'Mozambique' => [-18.6657, 35.5296],
            'Myanmar' => [21.9162, 95.9560],
            'Namibia' => [-22.9576, 18.4904],
            'Nauru' => [-0.5228, 166.9315],
            'Nepal' => [28.3949, 84.1240],
            'Netherlands' => [52.1326, 5.2913],
            'New Zealand' => [-40.9006, 174.8860],
            'Nicaragua' => [12.2650, -85.2072],
            'Niger' => [17.6078, 8.0817],
            'Nigeria' => [9.0820, 8.6753],
            'North Korea' => [40.3399, 127.5101],
            'North Macedonia' => [41.6086, 21.7453],
            'Norway' => [60.4720, 8.4689],
            'Oman' => [21.4735, 55.9754],
            'Pakistan' => [30.3753, 69.3451],
            'Palau' => [7.5150, 134.5825],
            'Palestine' => [31.9522, 35.2332],
            'Panama' => [8.5380, -80.7821],
            'Papua New Guinea' => [-6.3140, 143.9555],
            'Paraguay' => [-23.4425, -58.4438],
            'Peru' => [-9.1900, -75.0152],
            'Philippines' => [12.8797, 121.7740],
            'Poland' => [51.9194, 19.1451],
            'Portugal' => [39.3999, -8.2245],
            'Qatar' => [25.3548, 51.1839],
            'Romania' => [45.9432, 24.9668],
            'Russia' => [61.5240, 105.3188],
            'Rwanda' => [-1.9403, 29.8739],
            'Saint Kitts and Nevis' => [17.3578, -62.7830],
            'Saint Lucia' => [13.9094, -60.9789],
            'Saint Vincent and the Grenadines' => [12.9843, -61.2872],
            'Samoa' => [-13.7590, -172.1046],
            'San Marino' => [43.9424, 12.4578],
            'Sao Tome and Principe' => [0.1864, 6.6131],
            'Saudi Arabia' => [23.8859, 45.0792],
            'Senegal' => [14.4974, -14.4524],
            'Serbia' => [44.0165, 21.0059],
            'Seychelles' => [-4.6796, 55.4920],
            'Sierra Leone' => [8.4606, -11.7799],
            'Singapore' => [1.3521, 103.8198],
            'Slovakia' => [48.6690, 19.6990],
            'Slovenia' => [46.1512, 14.9955],
            'Solomon Islands' => [-9.6457, 160.1562],
            'Somalia' => [5.1521, 46.1996],
            'South Africa' => [-30.5595, 22.9375],
            'South Korea' => [35.9078, 127.7669],
            'South Sudan' => [6.8770, 31.3070],
            'Spain' => [40.4637, -3.7492],
            'Sri Lanka' => [7.8731, 80.7718],
            'Sudan' => [12.8628, 30.2176],
            'Suriname' => [3.9193, -56.0278],
            'Sweden' => [60.1282, 18.6435],
            'Switzerland' => [46.8182, 8.2275],
            'Syria' => [34.8021, 38.9968],
            'Taiwan' => [23.6978, 120.9605],
            'Tajikistan' => [38.8610, 71.2761],
            'Tanzania' => [-6.3690, 34.8888],
            'Thailand' => [15.8700, 100.9925],
            'Timor-Leste' => [-8.8742, 125.7275],
            'Togo' => [8.6195, 0.8248],
            'Tonga' => [-21.1789, -175.1982],
            'Trinidad and Tobago' => [10.6918, -61.2225],
            'Tunisia' => [33.8869, 9.5375],
            'Turkey' => [38.9637, 35.2433],
            'Turkmenistan' => [38.9697, 59.5563],
            'Tuvalu' => [-7.1095, 177.6493],
            'Uganda' => [1.3733, 32.2903],
            'Ukraine' => [48.3794, 31.1656],
            'United Arab Emirates' => [23.4241, 53.8478],
            'United Kingdom' => [55.3781, -3.4360],
            'United States' => [39.8283, -98.5795],
            'Uruguay' => [-32.5228, -55.7658],
            'Uzbekistan' => [41.3775, 64.5853],
            'Vanuatu' => [-15.3767, 166.9592],
            'Vatican City' => [41.9029, 12.4534],
            'Venezuela' => [6.4238, -66.5897],
            'Vietnam' => [14.0583, 108.2772],
            'Yemen' => [15.5527, 48.5164],
            'Zambia' => [-13.1339, 27.8493],
            'Zimbabwe' => [-19.0154, 29.1549]
        ];
        $countryCoordinates = $countryCoordinates[$countryName] ?? null;
        $coords = $countryCoordinates[$countryName] ?? null;
        $countryCoords = $coords ? json_encode($coords) : null;
        $provider = ServiceProvider::create([
            'user_id' => $user->id,
            'first_name' => $expats['first_name'] ?? null,
            'last_name' => $expats['last_name'] ?? null,
            'native_language' => $expats['native_language'] ?? null,
            'spoken_language' => $expats['spoken_language'],
            'services_to_offer' =>  json_encode($category) ?? null,
            'services_to_offer_category' => json_encode($subcategoryArray) ?? null,
            'provider_address' => $expats['location'] ?? null,
            'operational_countries' => $expats['operational_countries'] ?? null,
            'communication_online' => $this->truthy($expats, 'communication_preference.Online'),
            'communication_inperson' => $this->truthy($expats, 'communication_preference.In Person'),
            'profile_description' => $expats['profile_description'] ?? null,
            'profile_photo' => $profileImagePath,
            'provider_docs' => null, // deprecated, use 'documents'
            'phone_number' => $expats['phone_number'] ?? null,
            'country' => $countryName, 
            'preferred_language' => $expats['native_language'] ?? null,
            'special_status' => isset($expats['special_status']) ? json_encode($expats['special_status']) : null,
            'email' => $expats['email'],
            'documents' => !empty($documents) ? json_encode($documents) : null,
            'ip_address' => $ip,
            'slug' => $slug,
            'country_coords' => $countryCoords,
            'city_coords' => null 
        ]);
        
        $otp = random_int(100000, 999999);
        EmailVerification::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp' => $otp,
            'is_verified' => false,
        ]);

        Mail::raw(
            "Welcome to Ulixai!\n\nYour verification code is: {$otp}\n\nPlease enter this code to verify your email address.",
            function ($message) use ($user) {
                $fromAddress = config('mail.from.address') ?: 'noreply@ulixai.com';
                $fromName = config('mail.from.name') ?: 'Ulixai';
                $message->to($user->email)
                        ->from($fromAddress, $fromName)
                        ->subject('Welcome to Ulixai - Email Verification');
            }
        );

        return response()->json([
            'status' => 'success',
            'user' => $user,
            'provider' => $provider,
            'message' => 'Registration successful. Please check your email for the verification code.',
        ]);
    }
    private function generateSlug($expats, $country)
    {
        $firstName = Str::slug($expats['first_name'] ?? '');
        $language = Str::slug($expats['native_language'] ?? '');
        $countrySlug = Str::slug($country);
        $baseSlug = $firstName .  '-' . $countrySlug . '-' . $language . '-' . Str::random(6);
        $slug = $baseSlug;
        return $slug;
    }
    public function signupRegister(Request $request)
    {
        try{

            $affiliateCode = $request->input('affiliate_code');
            $referrer = User::where('affiliate_code', $affiliateCode)->first();
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6|confirmed',
                'gender' => 'nullable|in:Male,Female'
            ]);
            if (User::where('email', $request->input('email'))->exists()) {
                return redirect()->back()->with('error', 'A user with this email already exists.');
            }
            $affiliateLink = $this->generateAffiliateLink($request->input('email') ?? '', $request->input('name') ?? '', $request->input('last_name') ?? '');
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'user_role' => 'service_requester',
                'status' => 'active',
                'affiliate_code' => $affiliateLink,
                'gender' => $request->input('gender'),
                'referred_by' => $referrer ? $referrer->id : null,
            ]);

            $otp = random_int(100000, 999999);
            EmailVerification::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'otp' => $otp,
                'is_verified' => false,
            ]);

            Mail::raw(
                "Welcome to Ulixai!\n\nYour verification code is: {$otp}\n\nPlease enter this code to verify your email address.",
                function ($message) use ($user) {
                    $fromAddress = config('mail.from.address') ?: 'noreply@ulixai.com';
                    $fromName = config('mail.from.name') ?: 'Ulixai';
                    $message->to($user->email)
                            ->from($fromAddress, $fromName)
                            ->subject('Welcome to Ulixai - Email Verification');
                }
            );

            return view('user-auth.verify_otp', compact('user'));
        } catch (\Exception $e) {
            return redirect()->back()->with('message', 'Registration failed: ' . $e->getMessage());
        }
        
    }

    private function truthy($array, $keyPath)
    {
        $value = data_get($array, $keyPath, false);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function generateAffiliateLink($email, $first, $last)
    {
        $base = $first . $last . explode('@', $email)[0] . rand(100, 999);
        $slug = strtolower(Str::slug($base));
        return $slug;
    }

    public function verifyEmailOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6'
        ]);

        $verification = EmailVerification::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('is_verified', false)
            ->first();

        if (!$verification) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired code.'
            ], 422);
        }

        $verification->is_verified = true;
        $verification->verified_at = now();
        $verification->save();
        $user = $verification->user;
        if ($user && !$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }
        \Auth::login($user, $request->filled('remember'));
        $request->session()->regenerate();
        \Auth::user()->update(['last_login_at' => now()]);
        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully.'
        ]);
    }

    public function resendEmailOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'No user found with this email.'
            ], 404);
        }

        // Generate new OTP and update/create verification record
        $otp = random_int(100000, 999999);
        $verification = EmailVerification::updateOrCreate(
            ['user_id' => $user->id, 'email' => $user->email],
            ['otp' => $otp, 'is_verified' => false]
        );

        Mail::raw(
            "Welcome to Ulixai!\n\nYour new verification code is: {$otp}\n\nPlease enter this code to verify your email address.",
            function ($message) use ($user) {
                $fromAddress = config('mail.from.address') ?: 'noreply@ulixai.com';
                $fromName = config('mail.from.name') ?: 'Ulixai';
                $message->to($user->email)
                        ->from($fromAddress, $fromName)
                        ->subject('Ulixai - New Email Verification Code');
            }
        );

        return response()->json([
            'status' => 'success',
            'message' => 'A new verification code has been sent to your email.'
        ]);
    }

    
}
