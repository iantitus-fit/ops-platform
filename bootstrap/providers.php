<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Rooms\CompanyKnowledge\CompanyKnowledgeServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CompanyKnowledgeServiceProvider::class,
];
