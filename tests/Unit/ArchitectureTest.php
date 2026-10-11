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

arch('nothing outside the sync reads files straight from disk')
    ->expect(['file_get_contents', 'file_put_contents', 'fopen', 'file', 'glob', 'scandir', 'parse_ini_file'])
    ->not->toBeUsedIn([
        'App\Http',
        'App\Actions',
        'App\Livewire',
        'App\Filament',
        'App\Channels',
        'App\Jobs',
        'App\Models',
        'App\Records',
        'App\Listeners',
        'App\Events',
        'App\Support',
    ]);

arch('only the loader, the changelog and the records writer use the File facade')
    ->expect('Illuminate\Support\Facades\File')
    ->toOnlyBeUsedIn([
        'App\Icm\Loader',
        'App\Icm\Changelog',
        'App\Records\RecordsWriter',
    ]);

arch('application code never calls env() directly')
    ->expect('env')
    ->not->toBeUsedIn('App');
