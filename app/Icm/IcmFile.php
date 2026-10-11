<?php

namespace App\Icm;

use App\Enums\IcmFileKind;

/**
 * One markdown file read from the business folder, split into front matter and body.
 */
readonly class IcmFile
{
    /**
     * @param  array<string, mixed>  $frontMatter
     */
    public function __construct(
        public string $path,
        public IcmFileKind $kind,
        public ?string $type,
        public array $frontMatter,
        public string $body,
        public string $raw,
        public ?string $problem = null,
    ) {}
}
