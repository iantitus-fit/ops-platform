<?php

use App\Enums\SyncOutcome;
use App\Icm\ActiveConfig;
use App\Icm\FolderSync;
use App\Rooms\RoomRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Tests\Rooms\PretendRoom\AnnexRoom;

/**
 * The pretend room's contract with its three lists written out, so a test can give the same entries in any order.
 *
 * @param  list<string>  $owns
 * @param  list<string>  $reads
 * @param  list<string>  $writesRecords
 */
function pretendContractListing(array $owns, array $reads, array $writesRecords): string
{
    $list = fn (array $entries): string => '['.implode(', ', $entries).']';

    return "---\ntype: room\nowns: {$list($owns)}\nreads: {$list($reads)}\nwrites_records: {$list($writesRecords)}\nconnector: pretend_feed\n---\n\n# Pretend room\n\nThis room exists only in tests.\n";
}

/**
 * Run the real sync and keep everything a check decides: the outcome, the refusal lines, the notices, the files the
 * room owns and, sorted, the entries of its three lists. Version numbers and the written order of each list are left
 * out, because they are not a check's result.
 *
 * @return array<string, mixed>
 */
function contractSyncResult(): array
{
    $result = app(FolderSync::class)->run();
    $contract = $result->outcome === SyncOutcome::Activated ? app(ActiveConfig::class)->roomContract('pretend') : null;
    $sorted = function (string $field) use ($contract): ?array {
        if ($contract === null) {
            return null;
        }

        $entries = $contract[$field];
        sort($entries, SORT_STRING);

        return $entries;
    };

    return [
        'outcome' => $result->outcome,
        'errors' => $result->errors,
        'notices' => $result->notices,
        'owned files' => $contract === null ? null : app(ActiveConfig::class)->roomOwnedFiles('pretend'),
        'owns' => $sorted('owns'),
        'reads' => $sorted('reads'),
        'writes_records' => $sorted('writes_records'),
    ];
}

it('gives the same result for a valid contract whatever order owns, reads and writes_records are written in', function () {
    enablePretendRoom();
    layPretendRoomFolder();
    writeIcmFile('rooms/pretend/notes/second-note.md', "---\ntype: pretend_note\ntitle: Second note\npinned: false\n---\n\nA second note.\n");

    $owns = ['rooms/pretend/notes/first-note.md', 'rooms/pretend/notes/*.md'];
    $reads = ['business/profile.md', 'business/hours.md', 'business/faq.md'];
    $writesRecords = ['records/today.md', 'records/customers/'];

    writeIcmFile('rooms/pretend/CONTEXT.md', pretendContractListing($owns, $reads, $writesRecords));
    $written = contractSyncResult();

    writeIcmFile('rooms/pretend/CONTEXT.md', pretendContractListing(array_reverse($owns), array_reverse($reads), array_reverse($writesRecords)));
    $reversed = contractSyncResult();

    expect($written['outcome'])->toBe(SyncOutcome::Activated)
        ->and($written['owned files'])->toBe(['rooms/pretend/notes/first-note.md', 'rooms/pretend/notes/second-note.md'])
        ->and($reversed)->toBe($written);
});

it('gives the same refusal for a contract whatever order its owns entries are written in', function () {
    enablePretendRoom();
    layPretendRoomFolder();
    $live = activateExampleFolder();

    $owns = ['rooms/pretend/notes/*.md', 'business/faq.md', 'business/hours.md'];
    $faqLine = 'rooms/pretend/CONTEXT.md: business/faq.md is a knowledge file, which no room handles yet; pretend can read it';
    $hoursLine = 'rooms/pretend/CONTEXT.md: business/hours.md is a hours file, which no room handles yet; pretend can read it';

    writeIcmFile('rooms/pretend/CONTEXT.md', pretendContractListing($owns, ['business/profile.md'], []));
    $written = contractSyncResult();

    writeIcmFile('rooms/pretend/CONTEXT.md', pretendContractListing(array_reverse($owns), ['business/profile.md'], []));
    $reversed = contractSyncResult();

    // The same outcome and the same set of lines either way. Lines about one file follow that contract's own entry
    // order on purpose (Ian, 2026-10-23, Phase 4 UAT test 3): order is presentation, not a check's result.
    expect($written['outcome'])->toBe(SyncOutcome::Rejected)
        ->and($reversed['outcome'])->toBe($written['outcome'])
        ->and($written['errors'])->toBe([$faqLine, $hoursLine])
        ->and($reversed['errors'])->toBe([$hoursLine, $faqLine])
        ->and(Arr::except($reversed, 'errors'))->toBe(Arr::except($written, 'errors'))
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});

it('refuses two connector files that own the same kind the same way whatever order they are written in, blaming the later file in path order', function () {
    enablePretendRoom();
    app(RoomRegistry::class)->add(AnnexRoom::room());
    layPretendRoomFolder('rooms/pretend/CONTEXT.md');
    layFixtureFolder('annex-room');
    $live = activateExampleFolder();

    $pretendFeed = File::get(base_path('tests/Fixtures/connector-conflict/connectors/a.md'));
    $annexFeed = File::get(base_path('tests/Fixtures/connector-conflict/connectors/b.md'));

    $arrangements = [
        'a.md fills pretend_feed, written first' => [['connectors/a.md', $pretendFeed], ['connectors/b.md', $annexFeed]],
        'a.md fills pretend_feed, written second' => [['connectors/b.md', $annexFeed], ['connectors/a.md', $pretendFeed]],
        'contents swapped, a.md written first' => [['connectors/a.md', $annexFeed], ['connectors/b.md', $pretendFeed]],
        'contents swapped, a.md written second' => [['connectors/b.md', $pretendFeed], ['connectors/a.md', $annexFeed]],
    ];

    $refusals = [];

    foreach ($arrangements as $arrangement => $writes) {
        File::deleteDirectory(icmPath('connectors'));

        foreach ($writes as [$path, $contents]) {
            writeIcmFile($path, $contents);
        }

        $this->artisan('icm:sync')->assertExitCode(1);

        $refusals[$arrangement] = rejectedErrors();
    }

    expect(array_unique($refusals, SORT_REGULAR))->toHaveCount(1)
        ->and($refusals['a.md fills pretend_feed, written first'])->toBe(['connectors/b.md: customers is owned by both connectors/a.md and connectors/b.md; pick one, the other can read it'])
        ->and(app(ActiveConfig::class)->versionId())->toBe($live->id);
});
