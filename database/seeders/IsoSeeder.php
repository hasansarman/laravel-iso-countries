<?php

namespace Io238\ISOCountries\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Io238\ISOCountries\Models\Country;
use Io238\ISOCountries\Models\Currency;
use Io238\ISOCountries\Models\Language;


class IsoSeeder extends Seeder {

    public function run(): void
    {
        $this->command->info('Seeding Currencies...');
        Currency::query()->truncate();

        // Load Currency JSON
        $response = Http::get('https://gist.githubusercontent.com/Fluidbyte/2973986/raw/8bb35718d0c90fdacb388961c98b8d56abc392c9/Common-Currency.json');

        if ($response->successful()) {

            collect($response->json())->each(function ($currency) {

                // replace empty string values with NULL
                $currency = array_map(function ($value) {
                    return $value === '' ? null : $value;
                }, $currency);

                Currency::create([
                    'id'             => $currency['code'],
                    'name'           => $currency['name'],
                    'name_plural'    => $currency['name_plural'],
                    'symbol'         => $currency['symbol'],
                    'symbol_native'  => $currency['symbol_native'],
                    'decimal_digits' => $currency['decimal_digits'],
                    'rounding'       => $currency['rounding'],
                ]);

            });

        }

        // ==================================================================

        $this->command->info('Seeding Languages...');
        Language::query()->truncate();

        // Load Currency JSON
        $response = Http::get('https://raw.githubusercontent.com/haliaeetus/iso-639/master/data/iso_639-1.json');

        if ($response->successful()) {

            collect($response->json())->each(function ($language) {

                // replace empty string values with NULL
                $language = array_map(function ($value) {
                    return $value === '' ? null : $value;
                }, $language);

                Language::create([
                    'id'          => $language['639-1'],
                    'iso639_2'    => $language['639-2'],
                    'iso639_2b'   => $language['639-2/B'] ?? null,
                    'name'        => $language['name'],
                    'native_name' => $language['nativeName'] ?? null,
                    'family'      => $language['family'] ?? null,
                    'wiki_url'    => $language['wikiUrl'] ?? null,
                ]);

            });

        }

        // ==================================================================

        $this->command->info('Seeding Countries...');
        Country::query()->truncate();
        DB::table('country_language')->truncate();
        DB::table('country_currency')->truncate();
        DB::table('country_country')->truncate();

        // Load countries and relationships as JSON from RestCountries API
        //$response = Http::get('https://restcountries.eu/rest/v2/all');
        
        //URLS CHANGED
        $response = Http::get('https://restcountries.com/v2/all');
        if ($response->successful()) {

            collect($response->json())->each(function ($country) {

                // replace empty string values with NULL
                $country = array_map(function ($value) {
                    return $value === '' ? null : $value;
                }, $country);

                $country_model = Country::create([
                    'id'               => $country['alpha2Code'],
                    'alpha_3'          => $country['alpha3Code'],
                    'name'             => $country['name'],
                    'native_name'      => $country['nativeName'] ?? null,
                    'capital'          => $country['capital'] ?? null,
                    'top_level_domain' => collect($country['topLevelDomain'])->first(),
                    'calling_code'     => collect($country['callingCodes'])->first(),
                    'region'           => $country['region'] ?? null,
                    'subregion'        => $country['subregion'] ?? null,
                    'population'       => $country['population'] ?? null,
                    'lat'              => isset($country['latlng']) ? $country['latlng'][0] :0,
                    'lon'              => isset($country['latlng']) ? $country['latlng'][1] :0,
                    'demonym'          => $country['demonym'] ?? null,
                    'area'             => $country['area'] ?? null,
                    'gini'             => $country['gini'] ?? null,
                ]);

                // Attach relations
                 if(isset($country['languages']) && count($country['languages']) > 0)
                $country_model->languages()->attach(Language::find(collect($country['languages'])->pluck('iso639_1')));
                if(isset($country['currencies']) && count($country['currencies']) > 0)
                $country_model->currencies()->attach(Currency::find(collect($country['currencies'])->pluck('code')));
                if(isset($country['borders']) && count($country['borders']) > 0)
                    $country_model->neighbours()->attach(Country::whereIn('alpha_3', $country['borders'])->get());

            });

        }

        // Download name translations
        $this->downloadTranslations(Country::class);
        $this->downloadTranslations(Language::class);
        $this->downloadTranslations(Currency::class);

    }


    public function downloadTranslations($model): void
    {
        $this->command->info('Downloading translations for ' . $model);

        $locales = collect(config('app.locale'))->merge(config('app.fallback_locale'))->merge(config('iso-countries.locales'))->unique();

        foreach ($locales as $locale) {

            $urls = [
                Country::class  => 'https://raw.githubusercontent.com/umpirsky/country-list/master/data/' . $locale . '/country.json',
                Language::class => 'https://raw.githubusercontent.com/umpirsky/language-list/master/data/' . $locale . '/language.json',
                Currency::class => 'https://raw.githubusercontent.com/umpirsky/currency-list/master/data/' . $locale . '/currency.json',
            ];

            $this->command->info('Loading names for locale "' . $locale . '"...');

            $response = Http::get($urls[$model]);

            if ($response->successful()) {
                foreach ($response->json() as $id => $name) {
                    $item = app($model)::find($id);

                    if ($item) {
                        $item->setTranslation('name', $locale, $name);
                        $item->save();
                    }
                }
            }
            else {
                $this->command->warn('Locale not available for download!');
            }

        }
    }

}
