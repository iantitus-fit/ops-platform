<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Rooms\CompanyKnowledge\CompanyKnowledgeServiceProvider;
use App\Rooms\ContentGeneration\ContentGenerationServiceProvider;
use App\Rooms\Marketing\MarketingServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CompanyKnowledgeServiceProvider::class,
    MarketingServiceProvider::class,
    ContentGenerationServiceProvider::class,
];
