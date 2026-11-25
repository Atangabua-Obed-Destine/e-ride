<?php

namespace App\Ledger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Ledger\Models\TransactionMapping;
use App\Ledger\Models\DefaultAccountMapping;
use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;

class ChartOfAccount extends Model
{
    use SoftDeletes, Auditable, BelongsToBusiness;

    protected $fillable = [
        'account_code',
        'account_name',
        'account_name_fr',
        'description',
        'parent_id',
        'class_number',
        'account_type',
        'account_category',
        'opening_balance',
        'current_balance',
        'normal_balance',
        'cash_flow_category',
        'cash_flow_subcategory',
        'is_active',
        'is_system',
        'display_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'display_order' => 'integer',
        'class_number' => 'integer',
    ];

    /**
     * Get the parent account
     */
    public function parent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    /**
     * Get child accounts
     */
    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id')->orderBy('account_code');
    }

    /**
     * Get all descendants recursively
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get journal entry lines for this account
     */
    public function journalEntryLines()
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /**
     * Get transaction mappings where this is the debit account
     */
    public function transactionMappingsAsDebit()
    {
        return $this->hasMany(TransactionMapping::class, 'debit_account_id');
    }

    /**
     * Get transaction mappings where this is the credit account
     */
    public function transactionMappingsAsCredit()
    {
        return $this->hasMany(TransactionMapping::class, 'credit_account_id');
    }

    /**
     * Get default mappings where this is the debit account
     */
    public function defaultMappingsAsDebit()
    {
        return $this->hasMany(DefaultAccountMapping::class, 'debit_account_id');
    }

    /**
     * Get default mappings where this is the credit account
     */
    public function defaultMappingsAsCredit()
    {
        return $this->hasMany(DefaultAccountMapping::class, 'credit_account_id');
    }

    /**
     * Get the full account path (e.g., "5 > 52 > 521 > 5211")
     */
    public function getAccountPathAttribute()
    {
        $path = [$this->account_code];
        $parent = $this->parent;
        
        while ($parent) {
            array_unshift($path, $parent->account_code);
            $parent = $parent->parent;
        }
        
        return implode(' > ', $path);
    }

    /**
     * Get the full account name path
     */
    public function getFullNameAttribute()
    {
        $names = [$this->account_name];
        $parent = $this->parent;
        
        while ($parent) {
            array_unshift($names, $parent->account_name);
            $parent = $parent->parent;
        }
        
        return implode(' > ', $names);
    }

    /**
     * Check if account can be posted to (detail accounts only)
     */
    public function canPost()
    {
        return $this->account_category === 'detail' && $this->is_active;
    }

    /**
     * Get OHADA class name
     */
    public function getClassNameAttribute()
    {
        $classes = [
            1 => 'Comptes de Capitaux',
            2 => 'Comptes d\'Immobilisations',
            3 => 'Comptes de Stocks',
            4 => 'Comptes de Tiers',
            5 => 'Comptes de Trésorerie',
            6 => 'Comptes de Charges',
            7 => 'Comptes de Produits',
            8 => 'Comptes des Autres Charges et Produits',
        ];
        
        return $classes[$this->class_number] ?? 'Unknown';
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for postable accounts (detail only)
     */
    public function scopePostable($query)
    {
        return $query->where('account_category', 'detail')->where('is_active', true);
    }

    /**
     * Scope for specific class
     */
    public function scopeOfClass($query, $classNumber)
    {
        return $query->where('class_number', $classNumber);
    }

    /**
     * Scope for parent accounts only
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get creator
     */
    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Get updater
     */
    public function updater()
    {
        return $this->belongsTo(\App\User::class, 'updated_by');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $accountCode = $this->account_code ?? 'N/A';
        $accountName = $this->account_name ?? 'N/A';
        $accountType = $this->account_type ?? 'N/A';
        $debitBalance = number_format($this->debit_balance ?? 0, 2);
        $creditBalance = number_format($this->credit_balance ?? 0, 2);
        
        return "Chart of Account {$event}: [{$accountCode}] {$accountName} ({$accountType}), Debit: {$debitBalance}, Credit: {$creditBalance}";
    }
}
