<?php

namespace App\Ledger\Services;

/**
 * The SYSCOHADA (revised 2017) chart of accounts for a trading and service
 * business: shops, pharmacies, hotels, clinics. Same shape as paxhitestbed's
 * OHADA seeders (code, English and French names, parent, class, type, normal
 * balance; headings group, only detail accounts take postings), with the
 * standard trading accounts a POS posts to (601 purchases of goods, 701 sales
 * of goods, 411 customers, 401 suppliers, 443/445 VAT, 57 cash, 52 bank,
 * 55 mobile money).
 *
 * Row: [code, name, name_fr, parent_code, type, normal_balance, category]
 * category: h = heading (groups, no postings), d = detail.
 */
class SyscohadaChart
{
    public static function accounts(): array
    {
        return [
            // ---- Class 1: Equity and long-term liabilities -----------------------------
            ['1', 'Equity and long-term liabilities', 'Comptes de ressources durables', null, 'equity', 'credit', 'h'],
            ['10', 'Capital', 'Capital', '1', 'equity', 'credit', 'h'],
            ['101', 'Share capital', 'Capital social', '10', 'equity', 'credit', 'd'],
            ['103', "Owner's capital", "Capital personnel", '10', 'equity', 'credit', 'd'],
            ['104', "Owner's drawings", "Compte de l'exploitant", '10', 'equity', 'debit', 'd'],
            ['11', 'Reserves', 'Réserves', '1', 'equity', 'credit', 'h'],
            ['111', 'Legal reserve', 'Réserve légale', '11', 'equity', 'credit', 'd'],
            ['118', 'Other reserves', 'Autres réserves', '11', 'equity', 'credit', 'd'],
            ['12', 'Retained earnings', 'Report à nouveau', '1', 'equity', 'credit', 'h'],
            ['121', 'Retained earnings (credit)', 'Report à nouveau créditeur', '12', 'equity', 'credit', 'd'],
            ['129', 'Retained earnings (debit)', 'Report à nouveau débiteur', '12', 'equity', 'debit', 'd'],
            ['13', 'Net result for the year', "Résultat net de l'exercice", '1', 'equity', 'credit', 'h'],
            ['130', 'Result awaiting appropriation', "Résultat en instance d'affectation", '13', 'equity', 'credit', 'd'],
            ['131', 'Net result: profit', 'Résultat net : bénéfice', '13', 'equity', 'credit', 'd'],
            ['139', 'Net result: loss', 'Résultat net : perte', '13', 'equity', 'debit', 'd'],
            ['16', 'Loans and similar debts', 'Emprunts et dettes assimilées', '1', 'liability', 'credit', 'h'],
            ['162', 'Bank loans', 'Emprunts auprès des établissements de crédit', '16', 'liability', 'credit', 'd'],
            ['165', 'Deposits and guarantees received', 'Dépôts et cautionnements reçus', '16', 'liability', 'credit', 'd'],
            ['18', 'Liaison accounts', 'Comptes de liaison des établissements et sociétés en participation', '1', 'liability', 'credit', 'h'], // E-Ride (bus)
            ['1811', 'Head office (liaison)', 'Compte de liaison, siège', '18', 'liability', 'credit', 'd'],                               // E-Ride (bus)

            // ---- Class 2: Fixed assets --------------------------------------------------
            ['2', 'Fixed assets', 'Comptes d\'actif immobilisé', null, 'asset', 'debit', 'h'],
            ['21', 'Intangible assets', 'Immobilisations incorporelles', '2', 'asset', 'debit', 'h'],
            ['213', 'Software', 'Logiciels et sites internet', '21', 'asset', 'debit', 'd'],
            ['215', 'Goodwill', 'Fonds commercial', '21', 'asset', 'debit', 'd'],
            ['22', 'Land', 'Terrains', '2', 'asset', 'debit', 'h'],
            ['222', 'Land', 'Terrains nus et bâtis', '22', 'asset', 'debit', 'd'],
            ['23', 'Buildings and fittings', 'Bâtiments, installations techniques et agencements', '2', 'asset', 'debit', 'h'],
            ['231', 'Buildings', 'Bâtiments industriels, commerciaux et administratifs', '23', 'asset', 'debit', 'd'],
            ['234', 'Fixtures and fittings', 'Aménagements, agencements et installations', '23', 'asset', 'debit', 'd'],
            ['24', 'Equipment and furniture', 'Matériel, mobilier et actifs biologiques', '2', 'asset', 'debit', 'h'],
            ['241', 'Machinery and tools', 'Matériel et outillage', '24', 'asset', 'debit', 'd'],
            ['244', 'Office equipment and furniture', 'Matériel et mobilier de bureau', '24', 'asset', 'debit', 'd'],
            ['245', 'Vehicles', 'Matériel de transport', '24', 'asset', 'debit', 'd'],
            ['27', 'Financial assets', 'Immobilisations financières', '2', 'asset', 'debit', 'h'],
            ['275', 'Deposits and guarantees paid', 'Dépôts et cautionnements versés', '27', 'asset', 'debit', 'd'],
            ['28', 'Depreciation', 'Amortissements', '2', 'asset', 'credit', 'h'],
            ['281', 'Depreciation of intangible assets', 'Amortissements des immobilisations incorporelles', '28', 'asset', 'credit', 'd'],
            ['283', 'Depreciation of buildings and fittings', 'Amortissements des bâtiments et agencements', '28', 'asset', 'credit', 'd'],
            ['284', 'Depreciation of equipment', 'Amortissements du matériel', '28', 'asset', 'credit', 'd'],

            // ---- Class 3: Inventory -----------------------------------------------------
            ['3', 'Inventory', 'Comptes de stocks', null, 'asset', 'debit', 'h'],
            ['31', 'Goods for resale', 'Marchandises', '3', 'asset', 'debit', 'h'],
            ['311', 'Goods for resale', 'Marchandises', '31', 'asset', 'debit', 'd'],
            ['32', 'Raw materials', 'Matières premières et fournitures liées', '3', 'asset', 'debit', 'h'],
            ['321', 'Raw materials', 'Matières premières', '32', 'asset', 'debit', 'd'],
            ['39', 'Inventory write-downs', 'Dépréciations des stocks', '3', 'asset', 'credit', 'h'],
            ['391', 'Write-down of goods', 'Dépréciations des stocks de marchandises', '39', 'asset', 'credit', 'd'],

            // ---- Class 4: Third parties -------------------------------------------------
            ['4', 'Third parties', 'Comptes de tiers', null, 'liability', 'credit', 'h'],
            ['40', 'Suppliers', 'Fournisseurs et comptes rattachés', '4', 'liability', 'credit', 'h'],
            ['401', 'Suppliers', 'Fournisseurs, dettes en compte', '40', 'liability', 'credit', 'd'],
            ['408', 'Suppliers, invoices not yet received', 'Fournisseurs, factures non parvenues', '40', 'liability', 'credit', 'd'],
            ['409', 'Advances paid to suppliers', 'Fournisseurs débiteurs, avances et acomptes versés', '40', 'asset', 'debit', 'd'],
            ['41', 'Customers', 'Clients et comptes rattachés', '4', 'asset', 'debit', 'h'],
            ['411', 'Customers', 'Clients', '41', 'asset', 'debit', 'd'],
            ['416', 'Doubtful customers', 'Créances clients litigieuses ou douteuses', '41', 'asset', 'debit', 'd'],
            ['419', 'Customers in credit', 'Clients créditeurs', '41', 'liability', 'credit', 'h'],
            ['4191', 'Advances received from customers', 'Clients, avances et acomptes reçus', '419', 'liability', 'credit', 'd'],
            ['4198', 'Refunds owed to passengers', 'Remboursements dus aux voyageurs (avoirs à établir)', '419', 'liability', 'credit', 'd'], // E-Ride (bus)
            ['42', 'Staff', 'Personnel', '4', 'liability', 'credit', 'h'],
            ['421', 'Staff advances', 'Personnel, avances et acomptes', '42', 'asset', 'debit', 'd'],
            ['422', 'Salaries payable', 'Personnel, rémunérations dues', '42', 'liability', 'credit', 'd'],
            ['43', 'Social security', 'Organismes sociaux', '4', 'liability', 'credit', 'h'],
            ['431', 'Social security (CNPS)', 'Sécurité sociale (CNPS)', '43', 'liability', 'credit', 'd'],
            ['44', 'State and public bodies', 'État et collectivités publiques', '4', 'liability', 'credit', 'h'],
            ['441', 'Income tax payable', 'État, impôt sur les bénéfices', '44', 'liability', 'credit', 'd'],
            ['442', 'Other taxes payable', 'État, autres impôts et taxes', '44', 'liability', 'credit', 'd'],
            ['443', 'VAT charged', 'État, TVA facturée', '44', 'liability', 'credit', 'h'],
            ['4431', 'VAT charged on sales', 'TVA facturée sur ventes', '443', 'liability', 'credit', 'd'],
            ['4432', 'VAT charged on services', 'TVA facturée sur prestations de services', '443', 'liability', 'credit', 'd'],
            ['444', 'VAT payable or credit', 'État, TVA due ou crédit de TVA', '44', 'liability', 'credit', 'h'],
            ['4441', 'VAT payable', 'État, TVA due', '444', 'liability', 'credit', 'd'],
            ['4449', 'VAT credit carried forward', 'État, crédit de TVA à reporter', '444', 'asset', 'debit', 'd'],
            ['445', 'VAT recoverable', 'État, TVA récupérable', '44', 'asset', 'debit', 'h'],
            ['4451', 'VAT recoverable on fixed assets', 'TVA récupérable sur immobilisations', '445', 'asset', 'debit', 'd'],
            ['4452', 'VAT recoverable on purchases', 'TVA récupérable sur achats', '445', 'asset', 'debit', 'd'],
            ['4454', 'VAT recoverable on services', 'TVA récupérable sur services extérieurs', '445', 'asset', 'debit', 'd'],
            ['447', 'Taxes withheld at source', 'État, impôts retenus à la source', '44', 'liability', 'credit', 'd'],
            ['47', 'Other debtors and creditors', 'Débiteurs et créditeurs divers', '4', 'liability', 'credit', 'h'],
            ['471', 'Suspense accounts', 'Comptes d\'attente', '47', 'liability', 'credit', 'h'],
            ['4711', 'Opening balances (suspense)', 'Compte d\'attente, soldes d\'ouverture', '471', 'liability', 'credit', 'd'],
            ['4718', 'Other creditors', 'Créditeurs divers', '471', 'liability', 'credit', 'd'],
            ['476', 'Prepaid expenses', 'Charges constatées d\'avance', '47', 'asset', 'debit', 'd'],
            ['477', 'Deferred income', 'Produits constatés d\'avance', '47', 'liability', 'credit', 'd'],

            // ---- Class 5: Cash and bank -------------------------------------------------
            ['5', 'Cash and bank', 'Comptes de trésorerie', null, 'asset', 'debit', 'h'],
            ['52', 'Banks', 'Banques', '5', 'asset', 'debit', 'h'],
            ['521', 'Local banks', 'Banques locales', '52', 'asset', 'debit', 'd'],
            ['55', 'Electronic money', 'Instruments de monnaie électronique', '5', 'asset', 'debit', 'h'],
            ['552', 'Mobile money', 'Monnaie électronique, téléphone portable', '55', 'asset', 'debit', 'd'],
            ['553', 'Card payments to receive', 'Monnaie électronique, cartes', '55', 'asset', 'debit', 'd'],
            ['5521', 'MTN Mobile Money', 'MTN Mobile Money', '55', 'asset', 'debit', 'd'],                       // E-Ride (bus)
            ['5522', 'Orange Money', 'Orange Money', '55', 'asset', 'debit', 'd'],                               // E-Ride (bus)
            ['5523', 'Fapshi (app payments)', 'Fapshi (paiements dans l\'application)', '55', 'asset', 'debit', 'd'], // E-Ride (bus)
            ['57', 'Cash', 'Caisse', '5', 'asset', 'debit', 'h'],
            ['571', 'Main cash box', 'Caisse principale', '57', 'asset', 'debit', 'd'],
            ['5712', 'Counter tills (agents)', 'Caisses des guichets (agents)', '57', 'asset', 'debit', 'd'],   // E-Ride (bus)
            ['58', 'Internal transfers', 'Régies d\'avances, accréditifs et virements internes', '5', 'asset', 'debit', 'h'],
            ['585', 'Transfers between cash and bank', 'Virements de fonds', '58', 'asset', 'debit', 'd'],

            // ---- Class 6: Expenses ------------------------------------------------------
            ['6', 'Expenses', 'Comptes de charges des activités ordinaires', null, 'expense', 'debit', 'h'],
            ['60', 'Purchases and stock movements', 'Achats et variations de stocks', '6', 'expense', 'debit', 'h'],
            ['601', 'Purchases of goods', 'Achats de marchandises', '60', 'expense', 'debit', 'h'],
            ['6011', 'Purchases of goods', 'Achats de marchandises', '601', 'expense', 'debit', 'd'],
            ['6015', 'Costs on purchases (transport, customs)', 'Frais sur achats', '601', 'expense', 'debit', 'd'],
            ['6019', 'Discounts received on purchases', 'Rabais, remises et ristournes obtenus', '601', 'expense', 'credit', 'd'],
            ['603', 'Change in inventory', 'Variations des stocks de biens achetés', '60', 'expense', 'debit', 'h'],
            ['6031', 'Change in stock of goods', 'Variations des stocks de marchandises', '603', 'expense', 'debit', 'd'],
            ['604', 'Consumable supplies', 'Achats stockés de matières et fournitures consommables', '60', 'expense', 'debit', 'd'],
            ['605', 'Other purchases', 'Autres achats', '60', 'expense', 'debit', 'h'],
            ['6051', 'Water', 'Fournitures non stockables, eau', '605', 'expense', 'debit', 'd'],
            ['6052', 'Electricity', 'Fournitures non stockables, électricité', '605', 'expense', 'debit', 'd'],
            ['6053', 'Fuel and other energy', 'Fournitures non stockables, autres énergies', '605', 'expense', 'debit', 'd'],
            ['6054', 'Cleaning supplies', 'Fournitures d\'entretien non stockables', '605', 'expense', 'debit', 'd'],
            ['6055', 'Office supplies', 'Fournitures de bureau non stockables', '605', 'expense', 'debit', 'd'],
            ['6056', 'Small equipment and tools', 'Achats de petit matériel et outillage', '605', 'expense', 'debit', 'd'],
            ['6058', 'Other purchases', 'Achats de travaux, matériels et équipements', '605', 'expense', 'debit', 'd'],
            ['61', 'Transport', 'Transports', '6', 'expense', 'debit', 'h'],
            ['612', 'Transport on sales', 'Transports sur ventes', '61', 'expense', 'debit', 'd'],
            ['614', 'Staff transport', 'Transports du personnel', '61', 'expense', 'debit', 'd'],
            ['618', 'Other transport', 'Autres frais de transport', '61', 'expense', 'debit', 'd'],
            ['62', 'External services A', 'Services extérieurs A', '6', 'expense', 'debit', 'h'],
            ['621', 'Subcontracting', 'Sous-traitance générale', '62', 'expense', 'debit', 'd'],
            ['622', 'Rent and service charges', 'Locations et charges locatives', '62', 'expense', 'debit', 'd'],
            ['624', 'Maintenance and repairs', 'Entretien, réparations et maintenance', '62', 'expense', 'debit', 'd'],
            ['625', 'Insurance', 'Primes d\'assurance', '62', 'expense', 'debit', 'd'],
            ['627', 'Advertising', 'Publicité, publications, relations publiques', '62', 'expense', 'debit', 'd'],
            ['628', 'Telephone and internet', 'Frais de télécommunications', '62', 'expense', 'debit', 'd'],
            ['63', 'External services B', 'Services extérieurs B', '6', 'expense', 'debit', 'h'],
            ['631', 'Bank charges', 'Frais bancaires', '63', 'expense', 'debit', 'd'],
            ['632', 'Fees (accountant, lawyer, consultant)', 'Rémunérations d\'intermédiaires et de conseils', '63', 'expense', 'debit', 'd'],
            ['633', 'Staff training', 'Frais de formation du personnel', '63', 'expense', 'debit', 'd'],
            ['637', 'Temporary and outside staff', 'Rémunérations de personnel extérieur à l\'entité', '63', 'expense', 'debit', 'd'],
            ['638', 'Other external costs', 'Autres charges externes', '63', 'expense', 'debit', 'd'],
            ['64', 'Taxes and duties', 'Impôts et taxes', '6', 'expense', 'debit', 'h'],
            ['641', 'Direct taxes (licence, property)', 'Impôts et taxes directs', '64', 'expense', 'debit', 'd'],
            ['645', 'Indirect taxes', 'Impôts et taxes indirects', '64', 'expense', 'debit', 'd'],
            ['646', 'Registration fees', 'Droits d\'enregistrement', '64', 'expense', 'debit', 'd'],
            ['648', 'Other taxes', 'Autres impôts et taxes', '64', 'expense', 'debit', 'd'],
            ['65', 'Other expenses', 'Autres charges', '6', 'expense', 'debit', 'h'],
            ['651', 'Bad debts written off', 'Pertes sur créances clients et autres débiteurs', '65', 'expense', 'debit', 'd'],
            ['658', 'Sundry expenses (rounding)', 'Charges diverses', '65', 'expense', 'debit', 'd'],
            ['66', 'Staff costs', 'Charges de personnel', '6', 'expense', 'debit', 'h'],
            ['661', 'Salaries', 'Rémunérations directes versées au personnel national', '66', 'expense', 'debit', 'd'],
            ['663', 'Allowances and benefits', 'Indemnités forfaitaires versées au personnel', '66', 'expense', 'debit', 'd'],
            ['664', 'Employer social charges', 'Charges sociales', '66', 'expense', 'debit', 'd'],
            ['668', 'Other staff costs', 'Autres charges sociales', '66', 'expense', 'debit', 'd'],
            ['67', 'Financial expenses', 'Frais financiers et charges assimilées', '6', 'expense', 'debit', 'h'],
            ['671', 'Interest on loans', 'Intérêts des emprunts', '67', 'expense', 'debit', 'd'],
            ['673', 'Discounts allowed to customers', 'Escomptes accordés', '67', 'expense', 'debit', 'd'],
            ['676', 'Exchange losses', 'Pertes de change', '67', 'expense', 'debit', 'd'],
            ['68', 'Depreciation charges', 'Dotations aux amortissements', '6', 'expense', 'debit', 'h'],
            ['681', 'Depreciation charge', 'Dotations aux amortissements d\'exploitation', '68', 'expense', 'debit', 'd'],
            ['69', 'Provisions', 'Dotations aux provisions et aux dépréciations', '6', 'expense', 'debit', 'h'],
            ['691', 'Provisions charge', 'Dotations aux provisions et dépréciations d\'exploitation', '69', 'expense', 'debit', 'd'],

            // ---- Class 7: Income --------------------------------------------------------
            ['7', 'Income', 'Comptes de produits des activités ordinaires', null, 'revenue', 'credit', 'h'],
            ['70', 'Sales', 'Ventes', '7', 'revenue', 'credit', 'h'],
            ['701', 'Sales of goods', 'Ventes de marchandises', '70', 'revenue', 'credit', 'd'],
            ['706', 'Services sold', 'Services vendus', '70', 'revenue', 'credit', 'd'],
            ['707', 'Other sales income (delivery, packaging)', 'Produits accessoires', '70', 'revenue', 'credit', 'd'],
            ['709', 'Discounts allowed on sales', 'Rabais, remises et ristournes accordés', '70', 'revenue', 'debit', 'd'],
            ['75', 'Other income', 'Autres produits', '7', 'revenue', 'credit', 'h'],
            ['754', 'Gains on disposal of assets', 'Produits des cessions courantes d\'immobilisations', '75', 'revenue', 'credit', 'd'],
            ['758', 'Sundry income (rounding)', 'Produits divers', '75', 'revenue', 'credit', 'd'],
            ['77', 'Financial income', 'Revenus financiers et produits assimilés', '7', 'revenue', 'credit', 'h'],
            ['771', 'Interest received', 'Intérêts de prêts et créances diverses', '77', 'revenue', 'credit', 'd'],
            ['773', 'Discounts received', 'Escomptes obtenus', '77', 'revenue', 'credit', 'd'],
            ['776', 'Exchange gains', 'Gains de change', '77', 'revenue', 'credit', 'd'],

            // ---- Class 8: Other (non-ordinary) ------------------------------------------
            ['8', 'Other income and expenses', 'Comptes des autres charges et des autres produits', null, 'other', 'debit', 'h'],
            ['81', 'Book value of assets disposed', 'Valeurs comptables des cessions d\'immobilisations', '8', 'expense', 'debit', 'h'],
            ['812', 'Book value of assets disposed', 'Immobilisations corporelles cédées', '81', 'expense', 'debit', 'd'],
            ['82', 'Proceeds from asset disposals', 'Produits des cessions d\'immobilisations', '8', 'revenue', 'credit', 'h'],
            ['822', 'Proceeds from asset disposals', 'Immobilisations corporelles', '82', 'revenue', 'credit', 'd'],
            ['89', 'Income tax', 'Impôts sur le résultat', '8', 'expense', 'debit', 'h'],
            ['891', 'Income tax for the year', 'Impôts sur les bénéfices de l\'exercice', '89', 'expense', 'debit', 'd'],
        ];
    }

    /** Cash-flow statement category of an account, from its code. */
    public static function cashFlowCategory(string $code): string
    {
        if ($code[0] === '5') {
            return 'cash';
        }
        if ($code[0] === '2') {
            return 'investing';
        }
        if (in_array(substr($code, 0, 2), ['10', '16'])) {
            return 'financing';
        }

        return 'operating';
    }

    /** Which account each mapping uses on a new set of books. [type => code] */
    public static function defaultMappings(): array
    {
        return [
            // E-Ride (bus): the ticket money, from the bus records (App\Ledger\Services\PostingRules)
            'tickets_held' => '477',       // paid for a trip that has not left yet
            'ticket_sales' => '706',       // the trip left: the tickets are sales (app and counter: the line's channel)
            'refunds_owed' => '4198',      // a refund owed to a passenger, until it is sent
            'refunds_after_trip' => '709', // a refund given after the trip left
            'gateway_fees' => '631',       // Fapshi's share of an app payment
            'eride_commission' => '632',   // E-Ride's monthly commission
            'eride_payable' => '401',      // what is owed to E-Ride until the statement is paid
            'head_office' => '1811',       // a company branch: money held or paid by the head office
            'till_short' => '658',         // a till count short of the books
            'till_over' => '758',          // a till count over the books
            // posinnova's
            'customers' => '411',
            'vat_collected' => '4431',
            'customer_advances' => '4191',
            'suppliers' => '401',
            'supplier_advances' => '409',
            'vat_deductible' => '4452',
            'expense_category' => '638',
            'expense_payable' => '4718',
            'opening_balance' => '4711',
            'transfers' => '585',
            'retained_earnings' => '121',
            'income_summary' => '130',
        ];
    }

    /** Cash side of each payment method on new books. [method => code] (E-Ride: the bus payment methods) */
    public static function defaultPaymentMethods(): array
    {
        return [
            'cash' => '5712',     // counter cash, in the agent's till (each line tagged with the agent)
            'momo' => '5521',     // MTN Mobile Money at the counter
            'orange' => '5522',   // Orange Money at the counter
            'fapshi' => '5523',   // paid in the app or on the web, through the agency's Fapshi
            'company' => '1811',  // collected on the head office's account (company money mode)
        ];
    }
}
