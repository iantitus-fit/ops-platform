<?php

namespace App\Enums;

/**
 * The kinds of record a business may hand to an outside system (D-22). A connector file lists these under owns when
 * that system is the truth for the kind: the platform then never treats its own copy as the truth. A kind is accepted
 * even when the platform keeps no records of it yet, such as invoices. Ownership is per kind, not per field.
 *
 * The same list is in docs/SPEC.md under "Kinds of record a connector may own", and a test fails if they disagree.
 * Adding a kind, or renaming one, changes every client's connector files, so it is its own decision with Ian.
 */
enum OwnableKind: string
{
    case Customers = 'customers';
    case SalesActivity = 'sales_activity';
    case Invoices = 'invoices';
    case Payments = 'payments';
    case Expenses = 'expenses';
    case WorkOrders = 'work_orders';
    case Schedule = 'schedule';
    case Documents = 'documents';

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
