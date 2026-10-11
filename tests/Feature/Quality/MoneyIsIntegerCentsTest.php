<?php

use App\Models\Activity;
use App\Models\Contact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's own queue table counts jobs in a column named total_jobs; it holds no money.
 */
const MONEY_GUARD_FRAMEWORK_TABLES = ['job_batches'];

/**
 * The money-like columns in the given tables that are not integer columns ending in _cents.
 * Offenders come back as "table.column", sorted, so repeated runs report identically.
 *
 * @param  list<string>  $tables
 * @return list<string>
 */
function moneyGuardOffenders(array $tables): array
{
    $integerTypes = ['integer', 'int', 'int2', 'int4', 'int8', 'tinyint', 'smallint', 'mediumint', 'bigint'];
    $offenders = [];

    foreach ($tables as $table) {
        if (in_array($table, MONEY_GUARD_FRAMEWORK_TABLES, true)) {
            continue;
        }

        foreach (Schema::getColumns($table) as $column) {
            if (preg_match('/cents|price|cost|amount|total|fee|balance/i', $column['name']) !== 1) {
                continue;
            }

            $isIntegerCents = str_ends_with($column['name'], '_cents')
                && in_array(strtolower($column['type_name']), $integerTypes, true);

            if (! $isIntegerCents) {
                $offenders[] = "{$table}.{$column['name']}";
            }
        }
    }

    sort($offenders);

    return $offenders;
}

/**
 * @return list<string>
 */
function moneyGuardTableNames(): array
{
    return array_column(Schema::getTables(), 'name');
}

it('stores every money-like column as an integer column ending in _cents', function () {
    expect(moneyGuardOffenders(moneyGuardTableNames()))->toBe([]);
});

it('has integer cents columns for acquisition cost and activity cost', function () {
    $integerTypes = ['integer', 'int', 'bigint', 'int4', 'int8'];

    $acquisition = collect(Schema::getColumns('contacts'))->firstWhere('name', 'acquisition_cost_cents');
    $activityCost = collect(Schema::getColumns('activities'))->firstWhere('name', 'cost_cents');

    expect($acquisition)->not->toBeNull()
        ->and($activityCost)->not->toBeNull()
        ->and($integerTypes)->toContain(strtolower($acquisition['type_name']))
        ->and($integerTypes)->toContain(strtolower($activityCost['type_name']));
});

it('casts the money fields on the models to integers', function () {
    expect((new Contact)->getCasts()['acquisition_cost_cents'])->toBe('integer')
        ->and((new Activity)->getCasts()['cost_cents'])->toBe('integer');
});

it('passes a table with no money-like columns', function () {
    Schema::create('quality_probe_plain', function (Blueprint $table) {
        $table->id();
        $table->string('label');
    });

    expect(moneyGuardOffenders(['quality_probe_plain']))->toBe([]);

    Schema::drop('quality_probe_plain');
});

it('flags a float or decimal _cents column and a money-like column without the _cents suffix', function () {
    Schema::create('quality_probe_money', function (Blueprint $table) {
        $table->id();
        $table->float('refund_cents');
        $table->decimal('deposit_cents', 8, 2);
        $table->integer('amount');
        $table->integer('late_fee_cents');
    });

    expect(moneyGuardOffenders(['quality_probe_money']))->toBe([
        'quality_probe_money.amount',
        'quality_probe_money.deposit_cents',
        'quality_probe_money.refund_cents',
    ]);

    Schema::drop('quality_probe_money');
});

it('lists offenders in sorted order whatever order the tables came in', function () {
    Schema::create('quality_probe_b', fn (Blueprint $table) => $table->integer('total'));
    Schema::create('quality_probe_a', fn (Blueprint $table) => $table->integer('price'));

    expect(moneyGuardOffenders(['quality_probe_b', 'quality_probe_a']))->toBe([
        'quality_probe_a.price',
        'quality_probe_b.total',
    ]);

    Schema::drop('quality_probe_b');
    Schema::drop('quality_probe_a');
});
