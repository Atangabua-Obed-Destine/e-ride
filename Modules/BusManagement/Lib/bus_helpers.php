<?php

use Modules\BusManagement\Service\StatementService;

if (!function_exists('busCompanyStatementPaid')) {
    /** E-Ride's Fapshi confirmed a bus company's monthly commission payment (all branches together). */
    function busCompanyStatementPaid($data): void
    {
        app(StatementService::class)->onCompanyGatewayPaid($data);
    }
}

if (!function_exists('busStatementPaid')) {
    /** E-Ride's Fapshi confirmed a bus agency's monthly commission payment. */
    function busStatementPaid($data): void
    {
        app(StatementService::class)->onGatewayPaid($data);
    }
}
