<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Photographer directory
    |--------------------------------------------------------------------------
    |
    | Photographers type their location freely. App\Support\Directory matches
    | it against these cities (by name or alias, e.g. "Gisenyi" or a Kigali
    | neighbourhood) and stores the slug in users.city. Slugs will become
    | directory URLs (/photographers/kigali), so never rename one.
    |
    | Adding an alias is safe: directory:match-cities runs daily and
    | re-matches every saved location.
    |
    */

    'cities' => [
        // Kigali, with its districts and the neighbourhoods people write instead
        'kigali' => ['name' => 'Kigali', 'country' => 'Rwanda', 'aliases' => [
            'kgl', 'nyarugenge', 'gasabo', 'kicukiro', 'remera', 'kimihurura', 'kacyiru', 'kiyovu',
            'nyamirambo', 'kimironko', 'kanombe', 'gikondo', 'kibagabaga', 'gisozi', 'nyarutarama',
            'kagugu', 'kabeza', 'rebero', 'niboye', 'gatenga', 'kinyinya', 'kagarama',
        ]],

        // Rwanda's other districts, with the older town names many people still use
        'musanze' => ['name' => 'Musanze', 'country' => 'Rwanda', 'aliases' => ['ruhengeri', 'kinigi']],
        'rubavu' => ['name' => 'Rubavu', 'country' => 'Rwanda', 'aliases' => ['gisenyi']],
        'huye' => ['name' => 'Huye', 'country' => 'Rwanda', 'aliases' => ['butare']],
        'muhanga' => ['name' => 'Muhanga', 'country' => 'Rwanda', 'aliases' => ['gitarama']],
        'nyanza' => ['name' => 'Nyanza', 'country' => 'Rwanda', 'aliases' => []],
        'ruhango' => ['name' => 'Ruhango', 'country' => 'Rwanda', 'aliases' => []],
        'kamonyi' => ['name' => 'Kamonyi', 'country' => 'Rwanda', 'aliases' => []],
        'nyamagabe' => ['name' => 'Nyamagabe', 'country' => 'Rwanda', 'aliases' => ['gikongoro']],
        'nyaruguru' => ['name' => 'Nyaruguru', 'country' => 'Rwanda', 'aliases' => []],
        'gisagara' => ['name' => 'Gisagara', 'country' => 'Rwanda', 'aliases' => []],
        'rwamagana' => ['name' => 'Rwamagana', 'country' => 'Rwanda', 'aliases' => []],
        'kayonza' => ['name' => 'Kayonza', 'country' => 'Rwanda', 'aliases' => []],
        'ngoma' => ['name' => 'Ngoma', 'country' => 'Rwanda', 'aliases' => ['kibungo']],
        'kirehe' => ['name' => 'Kirehe', 'country' => 'Rwanda', 'aliases' => ['rusumo']],
        'nyagatare' => ['name' => 'Nyagatare', 'country' => 'Rwanda', 'aliases' => []],
        'gatsibo' => ['name' => 'Gatsibo', 'country' => 'Rwanda', 'aliases' => ['kabarore']],
        'bugesera' => ['name' => 'Bugesera', 'country' => 'Rwanda', 'aliases' => ['nyamata']],
        'gicumbi' => ['name' => 'Gicumbi', 'country' => 'Rwanda', 'aliases' => ['byumba']],
        'rulindo' => ['name' => 'Rulindo', 'country' => 'Rwanda', 'aliases' => []],
        'gakenke' => ['name' => 'Gakenke', 'country' => 'Rwanda', 'aliases' => []],
        'burera' => ['name' => 'Burera', 'country' => 'Rwanda', 'aliases' => []],
        'karongi' => ['name' => 'Karongi', 'country' => 'Rwanda', 'aliases' => ['kibuye']],
        'rusizi' => ['name' => 'Rusizi', 'country' => 'Rwanda', 'aliases' => ['cyangugu', 'kamembe']],
        'nyamasheke' => ['name' => 'Nyamasheke', 'country' => 'Rwanda', 'aliases' => []],
        'rutsiro' => ['name' => 'Rutsiro', 'country' => 'Rwanda', 'aliases' => []],
        'ngororero' => ['name' => 'Ngororero', 'country' => 'Rwanda', 'aliases' => []],
        'nyabihu' => ['name' => 'Nyabihu', 'country' => 'Rwanda', 'aliases' => ['mukamira']],

        // Regional cities
        'kampala' => ['name' => 'Kampala', 'country' => 'Uganda', 'aliases' => []],
        'nairobi' => ['name' => 'Nairobi', 'country' => 'Kenya', 'aliases' => []],
        'mombasa' => ['name' => 'Mombasa', 'country' => 'Kenya', 'aliases' => []],
        'dar-es-salaam' => ['name' => 'Dar es Salaam', 'country' => 'Tanzania', 'aliases' => ['dar']],
        'arusha' => ['name' => 'Arusha', 'country' => 'Tanzania', 'aliases' => []],
        'bujumbura' => ['name' => 'Bujumbura', 'country' => 'Burundi', 'aliases' => []],
        'goma' => ['name' => 'Goma', 'country' => 'DR Congo', 'aliases' => []],
        'bukavu' => ['name' => 'Bukavu', 'country' => 'DR Congo', 'aliases' => []],
    ],

    'specialties' => [
        'weddings' => 'Weddings',
        'portraits' => 'Portraits',
        'events' => 'Events',
        'family' => 'Family',
        'maternity-newborn' => 'Maternity & newborn',
        'graduation' => 'Graduation',
        'fashion' => 'Fashion',
        'commercial' => 'Commercial & product',
        'corporate' => 'Corporate',
        'real-estate' => 'Real estate',
        'concerts' => 'Concerts',
        'sports' => 'Sports',
        'travel' => 'Travel & landscape',
        'videography' => 'Videography',
    ],

    'max_specialties' => 5,

];
