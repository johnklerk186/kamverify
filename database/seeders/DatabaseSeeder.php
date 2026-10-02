<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user. In production the password comes from
        // ADMIN_SEED_PASSWORD — if unset, a random one is used so a
        // seeded deploy never ships a publicly-known admin login.
        $adminPassword = env('ADMIN_SEED_PASSWORD');
        $admin = User::firstOrCreate(
            ['email' => 'admin@kamverify.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt($adminPassword
                    ?? (app()->environment('production') ? \Illuminate\Support\Str::random(24) : 'password')),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // An explicitly configured ADMIN_SEED_PASSWORD is kept in sync so
        // re-running db:seed (e.g. after fixing deployment env) resets the
        // admin login deterministically instead of leaving a random one.
        if ($adminPassword) {
            $admin->forceFill(['password' => bcrypt($adminPassword)])->save();
        }

        // Create test customer user
        $customerPassword = env('CUSTOMER_SEED_PASSWORD');
        $customer = User::firstOrCreate(
            ['email' => 'customer@kamverify.com'],
            [
                'name' => 'Test Customer',
                'password' => bcrypt($customerPassword
                    ?? (app()->environment('production') ? \Illuminate\Support\Str::random(24) : 'password')),
                'role' => 'customer',
                'is_active' => true,
            ]
        );

        if ($customerPassword) {
            $customer->forceFill(['password' => bcrypt($customerPassword)])->save();
        }

        // Generate referral code for customer
        $customer->generateReferralCode();

        // Create wallet for customer
        Wallet::firstOrCreate(
            ['user_id' => $customer->id],
            [
                'balance' => 100.00,
                'total_deposited' => 100.00,
                'total_withdrawn' => 0.00,
                'currency' => 'USD',
                'is_active' => true,
            ]
        );

        // Create provider
        Provider::firstOrCreate(
            ['slug' => 'herosms'],
            [
                'name' => 'HeroSMS',
                'base_url' => 'https://herosms.com/api',
                'is_active' => true,
                'config' => [
                    'timeout' => 30,
                    'retry_attempts' => 3,
                ],
            ]
        );

        // Create services
        $services = [
            ['name' => 'WhatsApp', 'slug' => 'whatsapp', 'icon' => 'whatsapp'],
            ['name' => 'Telegram', 'slug' => 'telegram', 'icon' => 'telegram'],
            ['name' => 'Facebook', 'slug' => 'facebook', 'icon' => 'facebook'],
            ['name' => 'Google', 'slug' => 'google', 'icon' => 'google'],
            ['name' => 'Instagram', 'slug' => 'instagram', 'icon' => 'instagram'],
            ['name' => 'TikTok', 'slug' => 'tiktok', 'icon' => 'tiktok'],
            ['name' => 'X (Twitter)', 'slug' => 'twitter', 'icon' => 'x'],
            ['name' => 'Discord', 'slug' => 'discord', 'icon' => 'discord'],
            ['name' => 'Amazon', 'slug' => 'amazon', 'icon' => 'amazon'],
            ['name' => 'Microsoft', 'slug' => 'microsoft', 'icon' => 'microsoft'],
            ['name' => 'Apple', 'slug' => 'apple', 'icon' => 'apple'],
            ['name' => 'LinkedIn', 'slug' => 'linkedin', 'icon' => 'linkedin'],
            ['name' => 'Snapchat', 'slug' => 'snapchat', 'icon' => 'snapchat'],
            ['name' => 'PayPal', 'slug' => 'paypal', 'icon' => 'paypal'],
            ['name' => 'Spotify', 'slug' => 'spotify', 'icon' => 'spotify'],
            ['name' => 'Netflix', 'slug' => 'netflix', 'icon' => 'netflix'],
        ];

        $customerServices = ['facebook', 'whatsapp', 'telegram', 'tiktok'];

        // Fixed XAF markup over live provider cost — admin-editable, so
        // only applied when the service has no markup configured yet.
        $serviceMarkups = [
            'facebook' => 1000,
            'whatsapp' => 1500,
            'telegram' => 1000,
            'tiktok'   => 1000,
        ];

        foreach ($services as $service) {
            $model = Service::updateOrCreate(
                ['slug' => $service['slug']],
                [
                    'name' => $service['name'],
                    'icon' => $service['icon'],
                    'is_active' => true,
                    'customer_enabled' => in_array($service['slug'], $customerServices, true),
                    'sort_order' => 0,
                ]
            );

            if (isset($serviceMarkups[$service['slug']]) && empty($model->pricing_config['markup'])) {
                $model->update(['pricing_config' => [
                    'mode' => 'fixed',
                    'markup' => $serviceMarkups[$service['slug']],
                ]]);
            }
        }

        // Create settings
        $defaultSettings = [
            // General settings
            ['key' => 'site_name', 'value' => 'KamVerify', 'type' => 'string', 'group' => 'general', 'description' => 'Site name'],
            ['key' => 'site_currency', 'value' => 'USD', 'type' => 'string', 'group' => 'general', 'description' => 'Default currency'],
            ['key' => 'default_order_timeout', 'value' => '15', 'type' => 'integer', 'group' => 'general', 'description' => 'Default order timeout in minutes'],
            
            // Referral settings
            ['key' => 'referral_reward_type', 'value' => 'percentage', 'type' => 'string', 'group' => 'referral', 'description' => 'Referral reward type (percentage or fixed)'],
            ['key' => 'referral_reward_value', 'value' => '10', 'type' => 'integer', 'group' => 'referral', 'description' => 'Referral reward value'],
            ['key' => 'referral_require_purchase', 'value' => '1', 'type' => 'boolean', 'group' => 'referral', 'description' => 'Require qualifying purchase for reward'],
            
            // Pricing settings
            ['key' => 'default_markup_type', 'value' => 'fixed', 'type' => 'string', 'group' => 'pricing', 'description' => 'Default markup type'],
            ['key' => 'default_markup_value', 'value' => '0', 'type' => 'integer', 'group' => 'pricing', 'description' => 'Default markup value'],
            
            // Payment settings
            ['key' => 'min_deposit_amount', 'value' => '1', 'type' => 'float', 'group' => 'payment', 'description' => 'Minimum deposit amount'],
            ['key' => 'max_deposit_amount', 'value' => '10000', 'type' => 'float', 'group' => 'payment', 'description' => 'Maximum deposit amount'],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'group' => $setting['group'],
                    'description' => $setting['description'],
                    'is_public' => false,
                ]
            );
        }

        // Create countries
        $countries = [
            ['name' => 'United States', 'code' => 'US', 'dial_code' => '+1'],
            ['name' => 'United Kingdom', 'code' => 'GB', 'dial_code' => '+44'],
            ['name' => 'Canada', 'code' => 'CA', 'dial_code' => '+1'],
            ['name' => 'Germany', 'code' => 'DE', 'dial_code' => '+49'],
            ['name' => 'France', 'code' => 'FR', 'dial_code' => '+33'],
            ['name' => 'Spain', 'code' => 'ES', 'dial_code' => '+34'],
            ['name' => 'Italy', 'code' => 'IT', 'dial_code' => '+39'],
            ['name' => 'Netherlands', 'code' => 'NL', 'dial_code' => '+31'],
            ['name' => 'Belgium', 'code' => 'BE', 'dial_code' => '+32'],
            ['name' => 'Switzerland', 'code' => 'CH', 'dial_code' => '+41'],
            ['name' => 'Austria', 'code' => 'AT', 'dial_code' => '+43'],
            ['name' => 'Sweden', 'code' => 'SE', 'dial_code' => '+46'],
            ['name' => 'Norway', 'code' => 'NO', 'dial_code' => '+47'],
            ['name' => 'Denmark', 'code' => 'DK', 'dial_code' => '+45'],
            ['name' => 'Finland', 'code' => 'FI', 'dial_code' => '+358'],
            ['name' => 'Ireland', 'code' => 'IE', 'dial_code' => '+353'],
            ['name' => 'Portugal', 'code' => 'PT', 'dial_code' => '+351'],
            ['name' => 'Poland', 'code' => 'PL', 'dial_code' => '+48'],
            ['name' => 'Czech Republic', 'code' => 'CZ', 'dial_code' => '+420'],
            ['name' => 'Romania', 'code' => 'RO', 'dial_code' => '+40'],
            ['name' => 'Greece', 'code' => 'GR', 'dial_code' => '+30'],
            ['name' => 'Ukraine', 'code' => 'UA', 'dial_code' => '+380'],
            ['name' => 'Turkey', 'code' => 'TR', 'dial_code' => '+90'],
            ['name' => 'United Arab Emirates', 'code' => 'AE', 'dial_code' => '+971'],
            ['name' => 'Saudi Arabia', 'code' => 'SA', 'dial_code' => '+966'],
            ['name' => 'Israel', 'code' => 'IL', 'dial_code' => '+972'],
            ['name' => 'India', 'code' => 'IN', 'dial_code' => '+91'],
            ['name' => 'Pakistan', 'code' => 'PK', 'dial_code' => '+92'],
            ['name' => 'Indonesia', 'code' => 'ID', 'dial_code' => '+62'],
            ['name' => 'Malaysia', 'code' => 'MY', 'dial_code' => '+60'],
            ['name' => 'Singapore', 'code' => 'SG', 'dial_code' => '+65'],
            ['name' => 'Philippines', 'code' => 'PH', 'dial_code' => '+63'],
            ['name' => 'Thailand', 'code' => 'TH', 'dial_code' => '+66'],
            ['name' => 'Vietnam', 'code' => 'VN', 'dial_code' => '+84'],
            ['name' => 'Japan', 'code' => 'JP', 'dial_code' => '+81'],
            ['name' => 'South Korea', 'code' => 'KR', 'dial_code' => '+82'],
            ['name' => 'Australia', 'code' => 'AU', 'dial_code' => '+61'],
            ['name' => 'New Zealand', 'code' => 'NZ', 'dial_code' => '+64'],
            ['name' => 'South Africa', 'code' => 'ZA', 'dial_code' => '+27'],
            ['name' => 'Nigeria', 'code' => 'NG', 'dial_code' => '+234'],
            ['name' => 'Kenya', 'code' => 'KE', 'dial_code' => '+254'],
            ['name' => 'Ghana', 'code' => 'GH', 'dial_code' => '+233'],
            ['name' => 'Cameroon', 'code' => 'CM', 'dial_code' => '+237'],
            ['name' => 'Egypt', 'code' => 'EG', 'dial_code' => '+20'],
            ['name' => 'Morocco', 'code' => 'MA', 'dial_code' => '+212'],
            ['name' => 'Brazil', 'code' => 'BR', 'dial_code' => '+55'],
            ['name' => 'Argentina', 'code' => 'AR', 'dial_code' => '+54'],
            ['name' => 'Mexico', 'code' => 'MX', 'dial_code' => '+52'],
            ['name' => 'Colombia', 'code' => 'CO', 'dial_code' => '+57'],
            ['name' => 'Chile', 'code' => 'CL', 'dial_code' => '+56'],
            // Full HeroSMS catalog (live getCountries list, 195 entries).
            // 'hero' is the provider's numeric country ID — required for purchases.
            ['name' => 'Kazakhstan', 'code' => 'KZ', 'dial_code' => '+7', 'hero' => 2],
            ['name' => 'China', 'code' => 'CN', 'dial_code' => '+86', 'hero' => 3],
            ['name' => 'Myanmar', 'code' => 'MM', 'dial_code' => '+95', 'hero' => 5],
            ['name' => 'Tanzania', 'code' => 'TZ', 'dial_code' => '+255', 'hero' => 9],
            ['name' => 'Kyrgyzstan', 'code' => 'KG', 'dial_code' => '+996', 'hero' => 11],
            ['name' => 'Hong Kong', 'code' => 'HK', 'dial_code' => '+852', 'hero' => 14],
            ['name' => 'Madagascar', 'code' => 'MG', 'dial_code' => '+261', 'hero' => 17],
            ['name' => 'DR Congo', 'code' => 'CD', 'dial_code' => '+243', 'hero' => 18],
            ['name' => 'Macao', 'code' => 'MO', 'dial_code' => '+853', 'hero' => 20],
            ['name' => 'Cambodia', 'code' => 'KH', 'dial_code' => '+855', 'hero' => 24],
            ['name' => 'Laos', 'code' => 'LA', 'dial_code' => '+856', 'hero' => 25],
            ['name' => 'Haiti', 'code' => 'HT', 'dial_code' => '+509', 'hero' => 26],
            ['name' => 'Ivory Coast', 'code' => 'CI', 'dial_code' => '+225', 'hero' => 27],
            ['name' => 'Gambia', 'code' => 'GM', 'dial_code' => '+220', 'hero' => 28],
            ['name' => 'Serbia', 'code' => 'RS', 'dial_code' => '+381', 'hero' => 29],
            ['name' => 'Yemen', 'code' => 'YE', 'dial_code' => '+967', 'hero' => 30],
            ['name' => 'Estonia', 'code' => 'EE', 'dial_code' => '+372', 'hero' => 34],
            ['name' => 'Azerbaijan', 'code' => 'AZ', 'dial_code' => '+994', 'hero' => 35],
            ['name' => 'Uzbekistan', 'code' => 'UZ', 'dial_code' => '+998', 'hero' => 40],
            ['name' => 'Chad', 'code' => 'TD', 'dial_code' => '+235', 'hero' => 42],
            ['name' => 'Lithuania', 'code' => 'LT', 'dial_code' => '+370', 'hero' => 44],
            ['name' => 'Croatia', 'code' => 'HR', 'dial_code' => '+385', 'hero' => 45],
            ['name' => 'Iraq', 'code' => 'IQ', 'dial_code' => '+964', 'hero' => 47],
            ['name' => 'Latvia', 'code' => 'LV', 'dial_code' => '+371', 'hero' => 49],
            ['name' => 'Belarus', 'code' => 'BY', 'dial_code' => '+375', 'hero' => 51],
            ['name' => 'Taiwan', 'code' => 'TW', 'dial_code' => '+886', 'hero' => 55],
            ['name' => 'Iran', 'code' => 'IR', 'dial_code' => '+98', 'hero' => 57],
            ['name' => 'Algeria', 'code' => 'DZ', 'dial_code' => '+213', 'hero' => 58],
            ['name' => 'Slovenia', 'code' => 'SI', 'dial_code' => '+386', 'hero' => 59],
            ['name' => 'Bangladesh', 'code' => 'BD', 'dial_code' => '+880', 'hero' => 60],
            ['name' => 'Senegal', 'code' => 'SN', 'dial_code' => '+221', 'hero' => 61],
            ['name' => 'Sri Lanka', 'code' => 'LK', 'dial_code' => '+94', 'hero' => 64],
            ['name' => 'Peru', 'code' => 'PE', 'dial_code' => '+51', 'hero' => 65],
            ['name' => 'Guinea', 'code' => 'GN', 'dial_code' => '+224', 'hero' => 68],
            ['name' => 'Mali', 'code' => 'ML', 'dial_code' => '+223', 'hero' => 69],
            ['name' => 'Venezuela', 'code' => 'VE', 'dial_code' => '+58', 'hero' => 70],
            ['name' => 'Ethiopia', 'code' => 'ET', 'dial_code' => '+251', 'hero' => 71],
            ['name' => 'Mongolia', 'code' => 'MN', 'dial_code' => '+976', 'hero' => 72],
            ['name' => 'Afghanistan', 'code' => 'AF', 'dial_code' => '+93', 'hero' => 74],
            ['name' => 'Uganda', 'code' => 'UG', 'dial_code' => '+256', 'hero' => 75],
            ['name' => 'Angola', 'code' => 'AO', 'dial_code' => '+244', 'hero' => 76],
            ['name' => 'Cyprus', 'code' => 'CY', 'dial_code' => '+357', 'hero' => 77],
            ['name' => 'Papua New Guinea', 'code' => 'PG', 'dial_code' => '+675', 'hero' => 79],
            ['name' => 'Mozambique', 'code' => 'MZ', 'dial_code' => '+258', 'hero' => 80],
            ['name' => 'Nepal', 'code' => 'NP', 'dial_code' => '+977', 'hero' => 81],
            ['name' => 'Bulgaria', 'code' => 'BG', 'dial_code' => '+359', 'hero' => 83],
            ['name' => 'Hungary', 'code' => 'HU', 'dial_code' => '+36', 'hero' => 84],
            ['name' => 'Moldova', 'code' => 'MD', 'dial_code' => '+373', 'hero' => 85],
            ['name' => 'Paraguay', 'code' => 'PY', 'dial_code' => '+595', 'hero' => 87],
            ['name' => 'Honduras', 'code' => 'HN', 'dial_code' => '+504', 'hero' => 88],
            ['name' => 'Tunisia', 'code' => 'TN', 'dial_code' => '+216', 'hero' => 89],
            ['name' => 'Nicaragua', 'code' => 'NI', 'dial_code' => '+505', 'hero' => 90],
            ['name' => 'Timor-Leste', 'code' => 'TL', 'dial_code' => '+670', 'hero' => 91],
            ['name' => 'Bolivia', 'code' => 'BO', 'dial_code' => '+591', 'hero' => 92],
            ['name' => 'Costa Rica', 'code' => 'CR', 'dial_code' => '+506', 'hero' => 93],
            ['name' => 'Guatemala', 'code' => 'GT', 'dial_code' => '+502', 'hero' => 94],
            ['name' => 'Zimbabwe', 'code' => 'ZW', 'dial_code' => '+263', 'hero' => 96],
            ['name' => 'Puerto Rico', 'code' => 'PR', 'dial_code' => '+1787', 'hero' => 97],
            ['name' => 'Sudan', 'code' => 'SD', 'dial_code' => '+249', 'hero' => 98],
            ['name' => 'Togo', 'code' => 'TG', 'dial_code' => '+228', 'hero' => 99],
            ['name' => 'Kuwait', 'code' => 'KW', 'dial_code' => '+965', 'hero' => 100],
            ['name' => 'El Salvador', 'code' => 'SV', 'dial_code' => '+503', 'hero' => 101],
            ['name' => 'Libya', 'code' => 'LY', 'dial_code' => '+218', 'hero' => 102],
            ['name' => 'Jamaica', 'code' => 'JM', 'dial_code' => '+1876', 'hero' => 103],
            ['name' => 'Trinidad and Tobago', 'code' => 'TT', 'dial_code' => '+1868', 'hero' => 104],
            ['name' => 'Ecuador', 'code' => 'EC', 'dial_code' => '+593', 'hero' => 105],
            ['name' => 'Eswatini', 'code' => 'SZ', 'dial_code' => '+268', 'hero' => 106],
            ['name' => 'Oman', 'code' => 'OM', 'dial_code' => '+968', 'hero' => 107],
            ['name' => 'Bosnia and Herzegovina', 'code' => 'BA', 'dial_code' => '+387', 'hero' => 108],
            ['name' => 'Dominican Republic', 'code' => 'DO', 'dial_code' => '+1809', 'hero' => 109],
            ['name' => 'Syria', 'code' => 'SY', 'dial_code' => '+963', 'hero' => 110],
            ['name' => 'Qatar', 'code' => 'QA', 'dial_code' => '+974', 'hero' => 111],
            ['name' => 'Panama', 'code' => 'PA', 'dial_code' => '+507', 'hero' => 112],
            ['name' => 'Cuba', 'code' => 'CU', 'dial_code' => '+53', 'hero' => 113],
            ['name' => 'Mauritania', 'code' => 'MR', 'dial_code' => '+222', 'hero' => 114],
            ['name' => 'Sierra Leone', 'code' => 'SL', 'dial_code' => '+232', 'hero' => 115],
            ['name' => 'Jordan', 'code' => 'JO', 'dial_code' => '+962', 'hero' => 116],
            ['name' => 'Barbados', 'code' => 'BB', 'dial_code' => '+1246', 'hero' => 118],
            ['name' => 'Burundi', 'code' => 'BI', 'dial_code' => '+257', 'hero' => 119],
            ['name' => 'Benin', 'code' => 'BJ', 'dial_code' => '+229', 'hero' => 120],
            ['name' => 'Brunei', 'code' => 'BN', 'dial_code' => '+673', 'hero' => 121],
            ['name' => 'Bahamas', 'code' => 'BS', 'dial_code' => '+1242', 'hero' => 122],
            ['name' => 'Botswana', 'code' => 'BW', 'dial_code' => '+267', 'hero' => 123],
            ['name' => 'Belize', 'code' => 'BZ', 'dial_code' => '+501', 'hero' => 124],
            ['name' => 'Central African Republic', 'code' => 'CF', 'dial_code' => '+236', 'hero' => 125],
            ['name' => 'Dominica', 'code' => 'DM', 'dial_code' => '+1767', 'hero' => 126],
            ['name' => 'Grenada', 'code' => 'GD', 'dial_code' => '+1473', 'hero' => 127],
            ['name' => 'Georgia', 'code' => 'GE', 'dial_code' => '+995', 'hero' => 128],
            ['name' => 'Guinea-Bissau', 'code' => 'GW', 'dial_code' => '+245', 'hero' => 130],
            ['name' => 'Guyana', 'code' => 'GY', 'dial_code' => '+592', 'hero' => 131],
            ['name' => 'Iceland', 'code' => 'IS', 'dial_code' => '+354', 'hero' => 132],
            ['name' => 'Comoros', 'code' => 'KM', 'dial_code' => '+269', 'hero' => 133],
            ['name' => 'Saint Kitts and Nevis', 'code' => 'KN', 'dial_code' => '+1869', 'hero' => 134],
            ['name' => 'Liberia', 'code' => 'LR', 'dial_code' => '+231', 'hero' => 135],
            ['name' => 'Lesotho', 'code' => 'LS', 'dial_code' => '+266', 'hero' => 136],
            ['name' => 'Malawi', 'code' => 'MW', 'dial_code' => '+265', 'hero' => 137],
            ['name' => 'Namibia', 'code' => 'NA', 'dial_code' => '+264', 'hero' => 138],
            ['name' => 'Niger', 'code' => 'NE', 'dial_code' => '+227', 'hero' => 139],
            ['name' => 'Rwanda', 'code' => 'RW', 'dial_code' => '+250', 'hero' => 140],
            ['name' => 'Slovakia', 'code' => 'SK', 'dial_code' => '+421', 'hero' => 141],
            ['name' => 'Suriname', 'code' => 'SR', 'dial_code' => '+597', 'hero' => 142],
            ['name' => 'Tajikistan', 'code' => 'TJ', 'dial_code' => '+992', 'hero' => 143],
            ['name' => 'Monaco', 'code' => 'MC', 'dial_code' => '+377', 'hero' => 144],
            ['name' => 'Bahrain', 'code' => 'BH', 'dial_code' => '+973', 'hero' => 145],
            ['name' => 'Reunion', 'code' => 'RE', 'dial_code' => '+262', 'hero' => 146],
            ['name' => 'Zambia', 'code' => 'ZM', 'dial_code' => '+260', 'hero' => 147],
            ['name' => 'Armenia', 'code' => 'AM', 'dial_code' => '+374', 'hero' => 148],
            ['name' => 'Somalia', 'code' => 'SO', 'dial_code' => '+252', 'hero' => 149],
            ['name' => 'Congo', 'code' => 'CG', 'dial_code' => '+242', 'hero' => 150],
            ['name' => 'Burkina Faso', 'code' => 'BF', 'dial_code' => '+226', 'hero' => 152],
            ['name' => 'Lebanon', 'code' => 'LB', 'dial_code' => '+961', 'hero' => 153],
            ['name' => 'Gabon', 'code' => 'GA', 'dial_code' => '+241', 'hero' => 154],
            ['name' => 'Albania', 'code' => 'AL', 'dial_code' => '+355', 'hero' => 155],
            ['name' => 'Uruguay', 'code' => 'UY', 'dial_code' => '+598', 'hero' => 156],
            ['name' => 'Mauritius', 'code' => 'MU', 'dial_code' => '+230', 'hero' => 157],
            ['name' => 'Bhutan', 'code' => 'BT', 'dial_code' => '+975', 'hero' => 158],
            ['name' => 'Maldives', 'code' => 'MV', 'dial_code' => '+960', 'hero' => 159],
            ['name' => 'Guadeloupe', 'code' => 'GP', 'dial_code' => '+590', 'hero' => 160],
            ['name' => 'Turkmenistan', 'code' => 'TM', 'dial_code' => '+993', 'hero' => 161],
            ['name' => 'French Guiana', 'code' => 'GF', 'dial_code' => '+594', 'hero' => 162],
            ['name' => 'Saint Lucia', 'code' => 'LC', 'dial_code' => '+1758', 'hero' => 164],
            ['name' => 'Luxembourg', 'code' => 'LU', 'dial_code' => '+352', 'hero' => 165],
            ['name' => 'Saint Vincent and the Grenadines', 'code' => 'VC', 'dial_code' => '+1784', 'hero' => 166],
            ['name' => 'Equatorial Guinea', 'code' => 'GQ', 'dial_code' => '+240', 'hero' => 167],
            ['name' => 'Djibouti', 'code' => 'DJ', 'dial_code' => '+253', 'hero' => 168],
            ['name' => 'Antigua and Barbuda', 'code' => 'AG', 'dial_code' => '+1268', 'hero' => 169],
            ['name' => 'Cayman Islands', 'code' => 'KY', 'dial_code' => '+1345', 'hero' => 170],
            ['name' => 'Montenegro', 'code' => 'ME', 'dial_code' => '+382', 'hero' => 171],
            ['name' => 'Eritrea', 'code' => 'ER', 'dial_code' => '+291', 'hero' => 176],
            ['name' => 'South Sudan', 'code' => 'SS', 'dial_code' => '+211', 'hero' => 177],
            ['name' => 'Sao Tome and Principe', 'code' => 'ST', 'dial_code' => '+239', 'hero' => 178],
            ['name' => 'Aruba', 'code' => 'AW', 'dial_code' => '+297', 'hero' => 179],
            ['name' => 'Montserrat', 'code' => 'MS', 'dial_code' => '+1664', 'hero' => 180],
            ['name' => 'Anguilla', 'code' => 'AI', 'dial_code' => '+1264', 'hero' => 181],
            ['name' => 'North Macedonia', 'code' => 'MK', 'dial_code' => '+389', 'hero' => 183],
            ['name' => 'Seychelles', 'code' => 'SC', 'dial_code' => '+248', 'hero' => 184],
            ['name' => 'New Caledonia', 'code' => 'NC', 'dial_code' => '+687', 'hero' => 185],
            ['name' => 'Cape Verde', 'code' => 'CV', 'dial_code' => '+238', 'hero' => 186],
            ['name' => 'Palestine', 'code' => 'PS', 'dial_code' => '+970', 'hero' => 188],
            ['name' => 'Fiji', 'code' => 'FJ', 'dial_code' => '+679', 'hero' => 189],
            ['name' => 'Samoa', 'code' => 'WS', 'dial_code' => '+685', 'hero' => 198],
            ['name' => 'Malta', 'code' => 'MT', 'dial_code' => '+356', 'hero' => 199],
            ['name' => 'Liechtenstein', 'code' => 'LI', 'dial_code' => '+423', 'hero' => 200],
            ['name' => 'Gibraltar', 'code' => 'GI', 'dial_code' => '+350', 'hero' => 201],
            ['name' => 'Kosovo', 'code' => 'XK', 'dial_code' => '+383', 'hero' => 203],
            ['name' => 'Niue', 'code' => 'NU', 'dial_code' => '+683', 'hero' => 204],
        ];

        // Countries with no sellable HeroSMS mapping stay disabled:
        // KR has no catalog entry; UA has no stock on any launch service.
        $inactiveCountries = ['KR', 'UA'];

        // Numeric HeroSMS IDs carried on the 'hero' key of catalog-sourced
        // entries — merged into the mapping table below.
        $extraHeroIds = [];
        foreach ($countries as $country) {
            if (isset($country['hero'])) {
                $extraHeroIds[$country['code']] = (string) $country['hero'];
            }
            Country::firstOrCreate(
                ['code' => $country['code']],
                [
                    'name' => $country['name'],
                    'dial_code' => $country['dial_code'],
                    'is_active' => !in_array($country['code'], $inactiveCountries, true),
                    'is_popular' => in_array($country['code'],
                        ['US', 'GB', 'AU', 'AT', 'MX', 'ES', 'DE', 'BR'], true),
                    'sort_order' => 0,
                ]
            );
        }

        // Provider mappings — HeroSMS supplies every active service/country
        // HeroSMS uses SMS-Activate-compatible service codes (wa, tg, fb, lf…),
        // not the internal slug. Known codes are mapped here; admins can correct
        // the rest from the provider edit page's live catalog once connected.
        $provider = Provider::where('slug', 'herosms')->first();
        if ($provider) {
            $herosmsServiceCodes = [
                'whatsapp' => 'wa',
                'facebook' => 'fb',
                'telegram' => 'tg',
                'tiktok'   => 'lf',
            ];
            foreach (Service::all() as $service) {
                \App\Models\ProviderService::firstOrCreate(
                    ['provider_id' => $provider->id, 'service_id' => $service->id],
                    [
                        'provider_service_code' => $herosmsServiceCodes[$service->slug] ?? $service->slug,
                        'cost' => 0.50,
                        'is_active' => true,
                    ]
                );
            }

            // HeroSMS numeric country IDs from the live catalog
            // (getCountries). ISO codes do NOT work — purchases need these.
            $herosmsCountryIds = [
                'AE' => '95', 'AR' => '39', 'AT' => '50', 'AU' => '175',
                'BE' => '82', 'BR' => '73', 'CA' => '36', 'CH' => '173',
                'CL' => '151', 'CM' => '41', 'CO' => '33', 'CZ' => '63',
                'DE' => '43', 'DK' => '172', 'EG' => '21', 'ES' => '56',
                'FI' => '163', 'FR' => '78', 'GB' => '16', 'GH' => '38',
                'GR' => '129', 'ID' => '6', 'IE' => '23', 'IL' => '13',
                'IN' => '22', 'IT' => '86', 'JP' => '182', 'KE' => '8',
                'MA' => '37', 'MX' => '54', 'MY' => '7', 'NG' => '19',
                'NL' => '48', 'NO' => '174', 'NZ' => '67', 'PH' => '4',
                'PK' => '66', 'PL' => '15', 'PT' => '117', 'RO' => '32',
                'SA' => '53', 'SE' => '46', 'SG' => '196', 'TH' => '52',
                'TR' => '62', 'UA' => '1', 'US' => '187', 'VN' => '10',
                'ZA' => '31',
            ];
            $herosmsCountryIds = array_merge($herosmsCountryIds, $extraHeroIds);
            foreach (Country::all() as $country) {
                $heroId = $herosmsCountryIds[$country->code] ?? null;
                \App\Models\ProviderCountry::firstOrCreate(
                    ['provider_id' => $provider->id, 'country_id' => $country->id],
                    [
                        'provider_country_code' => $heroId ?? $country->code,
                        'is_active' => $heroId !== null,
                    ]
                );
            }
        }

        $this->seedBlogPosts();
    }

    /**
     * Original KamVerify blog articles — Insights & Guides.
     */
    private function seedBlogPosts(): void
    {
        $posts = [
            [
                'title' => 'What Is a Virtual Number and Why Would You Use One?',
                'slug' => 'what-is-a-virtual-number',
                'icon' => 'fa-phone',
                'published_at' => '2026-09-16 10:00:00',
                'excerpt' => 'Virtual numbers let you receive SMS codes without exposing your personal phone. Here is how they work and when they make sense.',
                'body' => '<p>A virtual number is a phone number that exists in software rather than on a physical SIM card. You do not hold it in a phone — you rent it for a short window and read the messages it receives from a dashboard.</p>
<h2>Why people use them</h2>
<ul>
<li><strong>Privacy</strong> — every app you sign up for asks for a phone number. Using your real number everywhere links your accounts together and exposes you to spam and data breaches.</li>
<li><strong>Testing</strong> — developers and QA teams verify signup flows without burning through real SIM cards.</li>
<li><strong>Regional access</strong> — some services only accept numbers from specific countries. A virtual number from that country solves it.</li>
<li><strong>Separation</strong> — keeping a work account separate from your personal number.</li>
</ul>
<h2>How it works on KamVerify</h2>
<p>You pick a service (WhatsApp, Telegram, Facebook, or TikTok), pick a country, and we assign you a number instantly. The number stays active while it waits for the SMS. When the code arrives, it shows on your order page — usually within seconds.</p>
<h2>What it costs</h2>
<p>Pricing is per activation and varies by country and service — the exact price is always shown before you pay. If no SMS arrives before the window ends, the order is cancelled and your wallet is refunded automatically.</p>
<blockquote>Bottom line: a virtual number is the easiest way to keep your real phone number out of databases you do not control.</blockquote>',
            ],
            [
                'title' => 'How to Fund Your KamVerify Wallet with MTN Mobile Money',
                'slug' => 'fund-wallet-mtn-momo',
                'icon' => 'fa-wallet',
                'published_at' => '2026-09-18 10:00:00',
                'excerpt' => 'A step-by-step walkthrough of topping up your wallet with MTN MoMo — from entering the amount to confirming on your phone.',
                'body' => '<p>Your KamVerify wallet is the balance you spend when buying numbers. Topping it up with MTN Mobile Money takes under a minute.</p>
<h2>Step by step</h2>
<ol>
<li>Sign in and open <strong>Wallet → Deposit</strong>.</li>
<li>Enter the amount you want to add — the minimum is 100 XAF.</li>
<li>Click <strong>Continue to Payment</strong>. You are redirected to the secure Fapshi checkout page.</li>
<li>Choose MTN Mobile Money and enter the MoMo number you want to pay with.</li>
<li>Approve the prompt on your phone (your MoMo PIN confirms it).</li>
<li>You are sent back to KamVerify and your balance updates immediately.</li>
</ol>
<h2>Safety notes</h2>
<ul>
<li>You only enter your MoMo PIN on the official payment prompt on your own phone — never on our site.</li>
<li>If the payment page times out or you cancel, nothing is charged and nothing is credited.</li>
<li>Every deposit appears in your wallet transaction history with its reference.</li>
</ul>
<p>If a payment ever completes on your phone but your balance does not move, contact support with the time and amount — every transaction is logged and traceable.</p>',
            ],
            [
                'title' => 'Ordered a Number but No Code Arrived? Here Is What Happens',
                'slug' => 'no-code-arrived-what-happens',
                'icon' => 'fa-comment-slash',
                'published_at' => '2026-09-19 10:00:00',
                'excerpt' => 'Sometimes an SMS never reaches the number. Your money is never at risk — here is exactly how the refund works.',
                'body' => '<p>Occasionally a service never sends the SMS — the number may be flagged, the app may delay delivery, or the provider\'s stock for that combination may be exhausted. Here is what KamVerify does in that case.</p>
<h2>You are never charged for silence</h2>
<p>Every order has a waiting window. If no code arrives before it ends, the order is cancelled automatically and the full amount is returned to your wallet — no ticket, no request, no waiting on us.</p>
<h2>You can also cancel early</h2>
<p>Changed your mind? You can cancel an order yourself any time before a code arrives — the refund is automatic. If our provider briefly rejects an early cancellation (some activations cannot be released in their first couple of minutes), we mark it as cancelling and a background job completes the cancel and refund the moment the provider allows it.</p>
<h2>Once a code arrives, the order is final</h2>
<p>The one case where cancellation is not possible: after the SMS has already been delivered. At that point the number has done its job and the provider has charged for it.</p>
<h2>Tips when codes do not arrive</h2>
<ul>
<li>Try a different country — delivery success varies by region.</li>
<li>Wait a few minutes before retrying the same app — rapid repeats get rate-limited by the app itself.</li>
<li>If one service-country combo keeps failing, it is usually the app rejecting that region\'s numbers, not a dead number.</li>
</ul>',
            ],
            [
                'title' => 'Choosing a Country for Your Verification Number',
                'slug' => 'choosing-a-country',
                'icon' => 'fa-earth-africa',
                'published_at' => '2026-09-21 10:00:00',
                'excerpt' => 'Price and delivery rates differ a lot between countries. How to pick the right one for WhatsApp, Telegram, Facebook, or TikTok.',
                'body' => '<p>KamVerify offers numbers across nearly 200 countries, and the country you pick affects both the price and how reliably the code arrives.</p>
<h2>What actually differs between countries</h2>
<ul>
<li><strong>Price</strong> — high-demand regions cost more. A US or UK number typically costs more than an African or Asian one.</li>
<li><strong>Stock</strong> — availability is live and changes constantly. A country can sell out of a service entirely for hours.</li>
<li><strong>Acceptance</strong> — some apps are stricter with numbers from certain regions. If one country fails repeatedly, switching regions usually helps.</li>
</ul>
<h2>How to pick</h2>
<ol>
<li>Start with a lower-priced country that has stock — the buy page shows live availability before you commit.</li>
<li>If the app rejects the number or no code arrives, cancel (automatic refund) and try a different country.</li>
<li>Only pay for premium regions when a cheaper option has failed or the app requires a specific region.</li>
</ol>
<p>The availability and price you see on the buy page are checked against the provider live — if a combination cannot be fulfilled, it shows as unavailable before you pay, not after.</p>',
            ],
            [
                'title' => 'Keeping Your Accounts Safe When Using Virtual Numbers',
                'slug' => 'account-safety-virtual-numbers',
                'icon' => 'fa-shield-halved',
                'published_at' => '2026-09-22 10:00:00',
                'excerpt' => 'Virtual numbers are a privacy tool — but they come with rules. How to use them without losing access to the accounts you create.',
                'body' => '<p>Virtual numbers are legitimate tools for privacy, testing, and regional access — but using them well means understanding their limits.</p>
<h2>The golden rule</h2>
<p>A one-time activation number is <strong>receive-only and temporary</strong>. Once your order completes, the number is gone. That means:</p>
<ul>
<li>Do not use a disposable number for an account you need to recover later — password resets and login re-verification will go to a number you no longer have.</li>
<li>After verifying, set a second factor you control (email, authenticator app, backup codes) inside the account immediately.</li>
<li>If an account matters long-term, consider whether a permanent SIM is the right tool instead.</li>
</ul>
<h2>What we do on our side</h2>
<ul>
<li>Numbers are assigned exclusively to your order for its lifetime.</li>
<li>Received codes are shown only on your order page while signed in.</li>
<li>We never resell a completed activation\'s message history.</li>
</ul>
<h2>Fair use</h2>
<p>Virtual numbers are for legitimate verification and privacy. Using them to abuse platform rules, evade bans, or create fraudulent accounts violates our terms and the platforms\' terms — and abusive activity gets accounts suspended. Used properly, they are the simplest privacy upgrade available.</p>',
            ],
        ];

        foreach ($posts as $post) {
            \App\Models\Post::firstOrCreate(['slug' => $post['slug']], $post);
        }
    }
}