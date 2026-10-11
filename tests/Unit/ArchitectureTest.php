<?php

arch('only the Loader parses YAML front matter')
    ->expect('Symfony\Component\Yaml\Yaml')
    ->toOnlyBeUsedIn('App\Icm\Loader');

arch('only the Loader walks the business folder')
    ->expect('Symfony\Component\Finder\Finder')
    ->toOnlyBeUsedIn('App\Icm\Loader');

arch('IcmVersion rows are used only by the sync, its commands, pages and data setup')
    ->expect('App\Models\IcmVersion')
    ->toOnlyBeUsedIn([
        'App\Icm',
        'App\Console\Commands',
        'App\Filament\Pages',
        'App\Models',
        'Database\Factories',
        'Database\Seeders',
        'Tests',
    ]);
