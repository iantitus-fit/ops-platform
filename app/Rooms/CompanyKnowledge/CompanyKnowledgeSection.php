<?php

namespace App\Rooms\CompanyKnowledge;

use App\Icm\ActiveConfig;
use App\Rooms\RoomSection;

/**
 * The company knowledge room's dashboard section: how many services the business lists and whether its FAQ is in.
 * Both facts come from the live version through ActiveConfig. The wording is a placeholder until the wording pass.
 */
class CompanyKnowledgeSection extends RoomSection
{
    public static function roomKey(): string
    {
        return 'company_knowledge';
    }

    /**
     * @return list<string>
     */
    protected function contentLines(): array
    {
        $config = app(ActiveConfig::class);

        return [
            'Services: '.count($config->services()),
            $config->file('business/faq.md') !== null ? 'FAQ: in place' : 'FAQ: not added yet',
        ];
    }
}
