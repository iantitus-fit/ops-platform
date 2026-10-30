<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Rooms\CompanyKnowledge\CompanyKnowledgeServiceProvider;
use App\Rooms\ContentGeneration\ContentGenerationServiceProvider;
use App\Rooms\FrontDesk\FrontDeskServiceProvider;
use App\Rooms\GoogleBusinessProfile\GoogleBusinessProfileServiceProvider;
use App\Rooms\Leads\LeadsServiceProvider;
use App\Rooms\Marketing\MarketingServiceProvider;
use App\Rooms\Money\MoneyServiceProvider;
use App\Rooms\Team\TeamServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CompanyKnowledgeServiceProvider::class,
    MarketingServiceProvider::class,
    ContentGenerationServiceProvider::class,
    MoneyServiceProvider::class,
    GoogleBusinessProfileServiceProvider::class,
    FrontDeskServiceProvider::class,
    TeamServiceProvider::class,
    LeadsServiceProvider::class,
];
