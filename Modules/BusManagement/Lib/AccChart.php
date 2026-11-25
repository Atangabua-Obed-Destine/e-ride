<?php

namespace Modules\BusManagement\Lib;

/**
 * The chart of accounts a branch starts with (OHADA numbering, SYSCOHADA revised). A role is the
 * account's job in automatic postings; "category" accounts are offered when recording spending or
 * other income. Names are English keys, translated on screen.
 *
 * [code, name, class, type, money, role, category]
 */
class AccChart
{
    public const ACCOUNTS = [
        // Class 1: equity and the head office liaison account.
        ['1011', 'Owner capital', 1, 'equity', false, 'capital', false],
        ['1081', 'Owner account (money taken out or put in)', 1, 'equity', false, 'owner', false],
        ['1211', 'Profit or loss of past years', 1, 'equity', false, 'retained', false],
        ['1301', 'Result of the year', 1, 'equity', false, 'result', false],
        ['1621', 'Loans', 1, 'liability', false, 'loans', false],
        ['1811', 'Head office (money it holds for the branch)', 1, 'asset', false, 'head_office', false],
        // Class 2: buses carried as assets (agencies without a head office).
        ['2451', 'Buses', 2, 'asset', false, 'buses', false],
        ['2845', 'Bus wear so far', 2, 'asset', false, 'bus_wear', false],
        // Class 4: third parties.
        ['4011', 'Suppliers', 4, 'liability', false, 'suppliers', false],
        ['4012', 'E-Ride (commission owed)', 4, 'liability', false, 'eride', false],
        ['4221', 'Salaries owed to staff', 4, 'liability', false, 'pay_owed', false],
        ['4311', 'CNPS (social security) owed', 4, 'liability', false, 'cnps', false],
        ['4431', 'VAT collected', 4, 'liability', false, 'vat_out', false],
        ['4441', 'VAT to pay', 4, 'liability', false, 'vat_due', false],
        ['4452', 'VAT to recover', 4, 'asset', false, 'vat_in', false],
        ['4471', 'Taxes withheld for the State', 4, 'liability', false, 'state_tax', false],
        ['4081', 'Bills not received yet', 4, 'liability', false, null, false],
        ['4191', 'Refunds owed to passengers', 4, 'liability', false, 'refunds_owed', false],
        ['4281', 'Staff pay owed', 4, 'liability', false, null, false],
        ['4761', 'Expenses paid in advance', 4, 'asset', false, null, false],
        ['4771', 'Tickets paid for trips not gone yet', 4, 'liability', false, 'unearned', false],
        // Class 5: where money is kept.
        ['5211', 'Bank account', 5, 'asset', true, 'bank', false],
        ['5521', 'MTN Mobile Money', 5, 'asset', true, 'mtn', false],
        ['5522', 'Orange Money', 5, 'asset', true, 'orange', false],
        ['5523', 'Fapshi balance (app payments)', 5, 'asset', true, 'fapshi', false],
        ['5711', 'Counter cash (with the agents)', 5, 'asset', true, 'counter_cash', false],
        ['5712', 'Office safe', 5, 'asset', true, 'safe', false],
        // Class 6: spending.
        ['6052', 'Electricity and water', 6, 'expense', false, null, true],
        ['6053', 'Fuel', 6, 'expense', false, 'fuel', true],
        ['6055', 'Office supplies and printing', 6, 'expense', false, null, true],
        ['6181', 'Tolls and road fees', 6, 'expense', false, null, true],
        ['6222', 'Office rent', 6, 'expense', false, null, true],
        ['6242', 'Bus repairs and maintenance', 6, 'expense', false, null, true],
        ['6252', 'Bus insurance', 6, 'expense', false, null, true],
        ['6271', 'Advertising', 6, 'expense', false, null, true],
        ['6281', 'Phone and internet', 6, 'expense', false, null, true],
        ['6311', 'Bank and Mobile Money charges', 6, 'expense', false, 'charges', true],
        ['6324', 'E-Ride commission', 6, 'expense', false, 'eride_commission', false],
        ['6461', 'Licences and vehicle taxes', 6, 'expense', false, null, true],
        ['6581', 'Cash short', 6, 'expense', false, 'cash_short', false],
        ['6588', 'Other expenses', 6, 'expense', false, 'other_expense', true],
        ['6413', 'Employer payroll taxes (CFC, FNE)', 6, 'expense', false, 'payroll_taxes', false],
        ['6641', 'Employer CNPS contributions', 6, 'expense', false, 'employer_cnps', false],
        ['6611', 'Staff salaries', 6, 'expense', false, 'salaries', true],
        ['6618', 'Driver and loader trip pay', 6, 'expense', false, null, true],
        ['6813', 'Bus wear of the month', 6, 'expense', false, 'depreciation', false],
        // Class 7: income.
        ['7061', 'Ticket sales (app and web)', 7, 'revenue', false, 'sales_online', false],
        ['7062', 'Ticket sales (counter)', 7, 'revenue', false, 'sales_counter', false],
        ['7071', 'Parcels and luggage', 7, 'revenue', false, null, true],
        ['7072', 'Bus hire', 7, 'revenue', false, null, true],
        ['7091', 'Refunds given after the trip', 7, 'revenue', false, 'refunds_given', false],
        ['7581', 'Cash over', 7, 'revenue', false, 'cash_over', false],
        ['7588', 'Other income', 7, 'revenue', false, 'other_income', true],
        // Class 8: buses sold or retired (outside ordinary activity).
        ['8120', 'Book value of buses sold or retired', 8, 'expense', false, 'disposal_value', false],
        ['8220', 'Sale price of buses sold', 8, 'revenue', false, 'disposal_price', false],
    ];

    /**
     * A head office's own books: what it owns and pays centrally. One "branch account" per branch
     * (OHADA 18, liaison) is added when the books open or a branch joins (role branch, link_id).
     */
    public const COMPANY_ACCOUNTS = [
        ['1011', 'Owner capital', 1, 'equity', false, 'capital', false],
        ['1081', 'Owner account (money taken out or put in)', 1, 'equity', false, 'owner', false],
        ['1211', 'Profit or loss of past years', 1, 'equity', false, 'retained', false],
        ['1301', 'Result of the year', 1, 'equity', false, 'result', false],
        ['1621', 'Loans', 1, 'liability', false, 'loans', false],
        ['2451', 'Buses', 2, 'asset', false, 'buses', false],
        ['2845', 'Bus wear so far', 2, 'asset', false, 'bus_wear', false],
        ['4011', 'Suppliers', 4, 'liability', false, 'suppliers', false],
        ['4012', 'E-Ride (commission owed)', 4, 'liability', false, 'eride', false],
        ['4221', 'Salaries owed to staff', 4, 'liability', false, 'pay_owed', false],
        ['4311', 'CNPS (social security) owed', 4, 'liability', false, 'cnps', false],
        ['4431', 'VAT collected', 4, 'liability', false, 'vat_out', false],
        ['4441', 'VAT to pay', 4, 'liability', false, 'vat_due', false],
        ['4452', 'VAT to recover', 4, 'asset', false, 'vat_in', false],
        ['4471', 'Taxes withheld for the State', 4, 'liability', false, 'state_tax', false],
        ['4081', 'Bills not received yet', 4, 'liability', false, null, false],
        ['4281', 'Staff pay owed', 4, 'liability', false, null, false],
        ['4761', 'Expenses paid in advance', 4, 'asset', false, null, false],
        ['5211', 'Bank account', 5, 'asset', true, 'bank', false],
        ['5521', 'MTN Mobile Money', 5, 'asset', true, 'mtn', false],
        ['5522', 'Orange Money', 5, 'asset', true, 'orange', false],
        ['5523', 'Fapshi balance (app payments)', 5, 'asset', true, 'fapshi', false],
        ['5712', 'Office safe', 5, 'asset', true, 'safe', false],
        ['6052', 'Electricity and water', 6, 'expense', false, null, true],
        ['6055', 'Office supplies and printing', 6, 'expense', false, null, true],
        ['6222', 'Office rent', 6, 'expense', false, null, true],
        ['6252', 'Bus insurance', 6, 'expense', false, null, true],
        ['6271', 'Advertising', 6, 'expense', false, null, true],
        ['6281', 'Phone and internet', 6, 'expense', false, null, true],
        ['6311', 'Bank and Mobile Money charges', 6, 'expense', false, 'charges', true],
        ['6461', 'Licences and vehicle taxes', 6, 'expense', false, null, true],
        ['6588', 'Other expenses', 6, 'expense', false, 'other_expense', true],
        ['6413', 'Employer payroll taxes (CFC, FNE)', 6, 'expense', false, 'payroll_taxes', false],
        ['6641', 'Employer CNPS contributions', 6, 'expense', false, 'employer_cnps', false],
        ['6611', 'Staff salaries', 6, 'expense', false, 'salaries', true],
        ['6813', 'Bus wear of the month', 6, 'expense', false, 'depreciation', false],
        ['7072', 'Bus hire', 7, 'revenue', false, null, true],
        ['7588', 'Other income', 7, 'revenue', false, 'other_income', true],
        ['8120', 'Book value of buses sold or retired', 8, 'expense', false, 'disposal_value', false],
        ['8220', 'Sale price of buses sold', 8, 'revenue', false, 'disposal_price', false],
    ];

    /** Money accounts a head office gives opening balances for. */
    public const COMPANY_OPENING_MONEY = ['safe', 'bank', 'mtn', 'orange', 'fapshi'];

    /** Money accounts a branch can give opening balances for (the counter cash is counted to zero first). */
    public const OPENING_MONEY = ['safe', 'bank', 'mtn', 'orange', 'fapshi', 'head_office'];

    /** Where a payment method's money lands. */
    public const METHOD_ROLE = ['cash' => 'counter_cash', 'momo' => 'mtn', 'orange' => 'orange', 'fapshi' => 'fapshi', 'company' => 'head_office'];

    public static function typeForClass(int $class): ?string
    {
        return match ($class) {
            1 => 'equity',
            2, 3, 5 => 'asset',
            6 => 'expense',
            7 => 'revenue',
            default => null,
        };
    }
}
