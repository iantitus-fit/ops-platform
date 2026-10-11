<?php

namespace App\Icm;

use App\Enums\IcmFileKind;
use App\Icm\Exceptions\IcmFolderMissing;
use DateTimeInterface;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the business folder from disk. This is the only code in the app allowed to do so.
 */
class Loader
{
    /**
     * Front matter types the engine understands today.
     *
     * @var list<string>
     */
    private const TYPED = ['business', 'hours', 'lead_sources', 'knowledge', 'service', 'voice', 'sending_rules'];

    /**
     * Files that only point at other files. They are kept as raw text and never validated.
     *
     * @var list<string>
     */
    private const ROUTERS = ['CLAUDE.md', 'CONTEXT.md', 'START-HERE.md'];

    /**
     * Read every markdown file under $root and split it into front matter and body.
     *
     * @throws IcmFolderMissing
     */
    public function load(string $root): LoadedFolder
    {
        if (! is_dir($root)) {
            throw new IcmFolderMissing($root);
        }

        $files = [];

        $finder = (new Finder)->files()->in($root)->name('*.md')->sortByName();

        foreach ($finder as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());

            if ($file->getFilename() === 'README.md') {
                continue;
            }

            $files[] = $this->read($path, $file);
        }

        return new LoadedFolder($files);
    }

    private function read(string $path, SplFileInfo $file): IcmFile
    {
        $raw = (string) file_get_contents($file->getPathname());

        if (in_array($file->getFilename(), self::ROUTERS, true)) {
            return new IcmFile($path, IcmFileKind::Router, null, [], $raw, $raw);
        }

        $text = preg_replace('/\A\xEF\xBB\xBF/', '', $raw);

        if (! preg_match('/\A---[ \t]*\R(?:(.*?)\R)??---[ \t]*(?:\R|\z)(.*)\z/s', $text, $matches)) {
            return $this->unrecognized($path, $raw, 'no_front_matter');
        }

        [, $yaml, $body] = $matches;

        try {
            $frontMatter = Yaml::parse($yaml, Yaml::PARSE_DATETIME);
        } catch (ParseException $exception) {
            $line = $exception->getParsedLine() >= 0 ? ' (line '.($exception->getParsedLine() + 1).')' : '';

            return $this->unrecognized($path, $raw, 'unreadable:'.$exception->getRawMessage().$line, $body);
        }

        if ($frontMatter === null) {
            return $this->unrecognized($path, $raw, 'empty', $body);
        }

        if (! is_array($frontMatter)) {
            return $this->unrecognized($path, $raw, 'unreadable:front matter must be lines of "key: value"', $body);
        }

        $frontMatter = $this->plainValues($frontMatter);
        $type = $frontMatter['type'] ?? null;

        if (! is_string($type) || $type === '') {
            return $this->unrecognized($path, $raw, 'missing_type', $body, $frontMatter);
        }

        $kind = in_array($type, self::TYPED, true) ? IcmFileKind::Typed : IcmFileKind::Unrecognized;
        $problem = $kind === IcmFileKind::Unrecognized ? 'unknown_type:'.$type : null;

        return new IcmFile($path, $kind, $type, $frontMatter, $body, $raw, $problem);
    }

    /**
     * @param  array<string, mixed>  $frontMatter
     */
    private function unrecognized(string $path, string $raw, string $problem, string $body = '', array $frontMatter = []): IcmFile
    {
        return new IcmFile($path, IcmFileKind::Unrecognized, null, $frontMatter, $body, $raw, $problem);
    }

    /**
     * Turn the date objects YAML produces into Y-m-d strings so the compiled JSON stays plain.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function plainValues(array $values): array
    {
        return array_map(function (mixed $value): mixed {
            return match (true) {
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                is_array($value) => $this->plainValues($value),
                default => $value,
            };
        }, $values);
    }
}
