<?php

namespace App\Enums;

/**
 * The kinds of record the platform owns and no connector may (D-23). A connector file may list these under reads, never
 * under owns, so every automated text stays on the platform's own send path and no outside system can take over the
 * platform's sent messages. Conversations, owner alerts and drafts are on neither this list nor OwnableKind.
 *
 * The same list is in docs/SPEC.md under "Kinds of record the platform owns", and a test fails if they disagree.
 * Moving a kind from here onto OwnableKind is its own decision with Ian.
 */
enum PlatformKind: string
{
    case Projects = 'projects';
    case Texts = 'texts';
    case Timeline = 'timeline';

    /**
     * Every kind as the text an owner writes in a connector file.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
