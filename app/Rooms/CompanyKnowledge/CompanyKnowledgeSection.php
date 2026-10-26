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
            $this->faqIsInPlace($config) ? 'FAQ: in place' : 'FAQ: not added yet',
        ];
    }

    /**
     * The FAQ is in place when a knowledge file sits at business/faq.md, the path the room's contract owns for it. It
     * asks for the knowledge files by type, so a file of another type at that path does not count (IN-02).
     */
    private function faqIsInPlace(ActiveConfig $config): bool
    {
        return in_array('business/faq.md', array_column($config->filesOfType('knowledge'), 'path'), true);
    }
}
