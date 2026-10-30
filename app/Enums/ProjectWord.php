<?php

namespace App\Enums;

/**
 * What a business calls a piece of work for a customer: Job, Project, Matter, Case, Work order or Engagement (D-12).
 * business/profile.md names one in project_word, the sync accepts it in any letter case, and the app reads it back
 * through ActiveConfig::projectWord(). Each word carries its plural here, so nothing else spells them.
 *
 * The same six words and plurals are in docs/SPEC.md under "What a business calls a project", and a test fails if they
 * disagree. Adding a word is a code change plus that SPEC line; removing one breaks any live folder that uses it.
 * Screens set the casing: singular() and plural() return the words as a sentence would start them.
 */
enum ProjectWord: string
{
    case Job = 'job';
    case Project = 'project';
    case Matter = 'matter';
    case Case = 'case';
    case WorkOrder = 'work order';
    case Engagement = 'engagement';

    /**
     * The word as a sentence would start it: "Job", "Work order".
     */
    public function singular(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The plural, written out so no screen has to guess: "Jobs", "Work orders".
     */
    public function plural(): string
    {
        return match ($this) {
            self::Job => 'Jobs',
            self::Project => 'Projects',
            self::Matter => 'Matters',
            self::Case => 'Cases',
            self::WorkOrder => 'Work orders',
            self::Engagement => 'Engagements',
        };
    }

    /**
     * The case an owner's word stands for, or null when it names none. The value is tidied first: trimmed, every run
     * of spaces (including odd Unicode spaces) turned into one space, and lower-cased, so "WORK  order" is Work order.
     * Anything that is not text, such as a number or a list, names no word.
     */
    public static function fromProfile(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        $tidy = mb_strtolower(trim((string) preg_replace('/[\s\p{Z}]+/u', ' ', $value)));

        return self::tryFrom($tidy);
    }

    /**
     * Every word as an owner writes it, in the order of the list, for the lines that tell an owner what to write.
     *
     * @return list<string>
     */
    public static function choices(): array
    {
        return array_map(fn (self $word): string => $word->singular(), self::cases());
    }
}
