<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business Folder Path
    |--------------------------------------------------------------------------
    |
    | The folder the sync reads to build a new live version of the business.
    | ICM_PATH must be an absolute path. When it is not set, the example
    | business that ships with the app (examples/example-hvac) is used.
    |
    */

    'path' => env('ICM_PATH') ?: base_path('examples/example-hvac'),

];
