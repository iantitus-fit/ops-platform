<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The shape each kind of row must have, written once. An appointment has a contact, a project, a service slug and a
     * service name, and is booked, cancelled or done. A block has none of those and no booking link, and is booked or
     * cancelled, never done. The same expression is used with a column prefix so the SQLite triggers can read NEW.
     */
    private function shape(string $prefix = ''): string
    {
        $c = fn (string $name): string => $prefix.$name;

        return "(({$c('kind')} = 'appointment'"
            ." and {$c('contact_id')} is not null"
            ." and {$c('project_id')} is not null"
            ." and {$c('service_slug')} is not null"
            ." and {$c('service_name')} is not null"
            ." and {$c('state')} in ('booked', 'cancelled', 'done'))"
            ." or ({$c('kind')} = 'block'"
            ." and {$c('contact_id')} is null"
            ." and {$c('project_id')} is null"
            ." and {$c('booking_link_id')} is null"
            ." and {$c('service_slug')} is null"
            ." and {$c('service_name')} is null"
            ." and {$c('state')} in ('booked', 'cancelled')))"
            ." and {$c('ends_at')} > {$c('starts_at')}";
    }

    /**
     * Run the migrations.
     *
     * The database half of A3 (Ian, 2026-11-02): the named actions hold each kind's shape in code, and this holds it in
     * the database, so a row of the wrong shape is refused even if code forgets. It also refuses an end that is not after
     * the start. Postgres takes a CHECK constraint. SQLite cannot add a constraint to an existing table and Laravel's
     * builder has no check method, so there it is a pair of triggers that abort the insert or update. Any other driver
     * gets no constraint, because this platform runs on those two. A later migration that rebuilds the appointments table
     * on SQLite (a dropped column, say) drops the triggers with it and must put them back.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('alter table appointments add constraint appointments_kind_shape check ('.$this->shape().')');

            return;
        }

        if ($driver === 'sqlite') {
            foreach (['insert', 'update'] as $event) {
                DB::statement(
                    'create trigger appointments_kind_shape_'.$event.' before '.$event.' on appointments'
                    .' when not ('.$this->shape('NEW.').')'
                    ." begin select raise(abort, 'appointments_kind_shape'); end"
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('alter table appointments drop constraint if exists appointments_kind_shape');
        }

        if ($driver === 'sqlite') {
            DB::statement('drop trigger if exists appointments_kind_shape_insert');
            DB::statement('drop trigger if exists appointments_kind_shape_update');
        }
    }
};
