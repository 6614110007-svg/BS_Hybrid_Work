<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supabase API
    |--------------------------------------------------------------------------
    | The REST endpoint and keys used to talk to Supabase services
    | (Storage buckets, Auth admin APIs, etc.).
    */

    'url' => env('SUPABASE_URL'),

    'anon_key' => env('SUPABASE_ANON_KEY'),

    'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),

    'bucket' => env('SUPABASE_BUCKET', 'checkin-photos'),

];