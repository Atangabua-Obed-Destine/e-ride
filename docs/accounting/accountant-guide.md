# The accountant's guide: routine, reports and controls

How a business's books work in PosInnova, what to do each day, month and year,
and every report the system produces: what it answers, where its figures come
from, and what it must agree with.

The accounting is the same as in the school ERP (paxhitestbed): the same
screens, menus, wording and review dialog, so an accountant trained on one can
work on the other. What differs is what feeds the books: here it is sales,
purchases, expenses and payments instead of fees and payroll.

The books follow SYSCOHADA (standard numbering). Amounts are in FCFA. Each
business on an install keeps its own books.

The illustrated handbook (every screen, with a sample pharmacy) is built from
this guide: see the end of this file.

---

## 1. How the books are kept

Everything ends up as **posted journal entries** in the ledger. Almost all are
written for you when a document is saved in PosInnova; a few are entered by
hand. The method is SYSCOHADA's **intermittent inventory** (inventaire
intermittent): goods bought are a purchase expense when received, and the stock
account (31) only changes at the year end, from the stock counted.

| What happens | Where it is recorded | What the ledger gets |
|---|---|---|
| A sale is finalised (POS or Sales → Add sale) | the sale | what the customer owes: Dr **411** / Cr **701** goods sold, **706** services, **707** other charges (delivery), Cr **4431** VAT collected; discounts come off the sales figure |
| The sale is paid (at the till, or later: Add payment) | the payment | Dr the cash box, bank or mobile money account (**571 / 521 / 552**) / Cr **411** |
| A payment taken on a draft or a quotation, or with no invoice | the payment | held for the customer: Dr cash / Cr **4191** advances received, until the sale is final; then 4191 → 411 |
| A sale paid from the customer's advance (method "advance") | the payment | Dr 4191 / Cr 411, no cash moves |
| A sale return | Sales → Sell return | the mirror of the sale (Dr 701, 4431 / Cr 411); the refund is a payment out |
| A purchase is received | Purchases → Add purchase (status Received) | Dr **6011** goods purchased, **6015** shipping and purchase costs, **4452** VAT deductible / Cr **401** supplier |
| A supplier is paid | Purchases → Add payment, or the supplier's Pay | Dr 401 / Cr bank or cash; paid before the goods are received: Dr **409** advances to suppliers |
| A purchase return | Purchases → Purchase return | the mirror of the purchase |
| An expense | Expenses → Add expense | Dr the category's expense account (6xx) / Cr **4718** expenses to pay, or **401** when the expense names a supplier (ENEO, CAMWATER) |
| The expense is paid | the expense's payment | Dr 4718 or 401 / Cr cash or bank |
| Money moved between cash boxes, bank and mobile money | Payment Accounts → Fund Transfer | Dr the receiving account / Cr the sending one |
| A deposit or a payment account's opening balance | Payment Accounts → Deposit / Add | Dr the account / Cr **4711** opening balances (suspense), for the accountant to reclassify |
| Accruals, corrections, opening balances, salaries' social charges | Accounting → Journal Entries (by hand) | as entered |
| Bank charges or interest on the statement | Accounting → Bank Reconciliation | Dr **631** bank charges / Cr bank, or Dr bank / Cr **771** interest |
| Depreciation | Accounting → Fixed Assets | Dr **681** / Cr **28x**, each month |
| The year's stock count | Accounting → Year-End Closing | the stock variation: Dr/Cr **6031** against **311** |

Which account each kind of document uses is set once, under **Accounting →
Mapping Settings** (sales, VAT, purchases, advances, one account for each
expense category). Which ledger account each cash box, bank and mobile money
account is, is set under **Accounting → Cash and bank in the ledger**, with a
default for each payment method. A document the rules cannot post (an expense
category with no account, a payment account not linked) is not lost: it is
marked failed with the reason, and Ledger health lists it.

**Sales are on accrual.** A final sale posts what the customer owes to 411 and
the sale to 7xx; the payment settles 411. So 411 is, at every date, what
customers owe, and the income statement shows the month's sales whether or not
they are paid yet. The same for suppliers on 401.

**How documents reach the books.** A save in PosInnova queues the document; it
is posted as soon as the request's own work is done, so a sale never waits for
the books. A catch-up (`php artisan ledger:sync`) runs every five minutes and
posts anything missed, re-posts a document that changed (the old entry is
reversed) and reverses the entry of a deleted document. Ledger health shows
when it last ran.

### Rules the system enforces

- **Every entry balances**, and posts only to detail accounts of the business.
- **A closed month or year accepts nothing.** A document dated in a closed
  month is posted on the day it is recorded instead, its description saying
  its own date.
- **Nothing posted is deleted.** A mistake is corrected by a reversing entry:
  in the original's month while that month is open, otherwise today.
- **Every action that posts money in the accounting screens goes through a
  review**: the dialog ("Review before confirming") shows the exact ledger
  entry and its effects, and confirming needs the tick box. The server refuses
  the action without it. Sales, purchases and expenses have PosInnova's own
  confirmation step, where the figures are read back before they are saved.
- **Entering and approving are separate** (section 5): the accountant enters
  and prepares; the Admin posts manual entries, approves, completes, closes
  and reverses.

---

## 2. The routine

### Every day
1. Sales are taken at the POS by the cashiers; check the day's cash with the
   cash register report (Reports → Register report) and the till.
2. Record expenses as they are paid (Expenses → Add expense), always with the
   right category. An invoice that will be paid later is still entered now,
   with the supplier named and no payment: it is then owed on 401.
3. Enter purchases when the goods arrive (Purchases → Add purchase, status
   Received). Pay them when due (Add payment).
4. Glance at **Accounting → Ledger health**: it should read green. Anything red
   is money on one side of the books and not the other.

### Every week
1. Pay suppliers that are due. **Accounting Reports → Payables Aging** shows
   what is due and overdue.
2. Bank the cash and the mobile money (Payment Accounts → Fund Transfer).
3. Chase customers who owe: **Receivables Aging**.

### Every month
1. **Bank reconciliation** for each bank, cash and mobile money account
   (Accounting → Bank Reconciliation): enter the statement's closing balance,
   tick the movements that appear on it, post any charges or interest from the
   same screen, and complete it when the difference is nil.
2. **Accruals**: costs incurred but not yet invoiced (Dr expense / Cr 408),
   salaries owed (Cr 422), prepaid expenses (Dr 476), income received in
   advance (Cr 477). Reverse them on the first day of the next month (Journal
   entry → Reverse on…).
3. **Depreciation** of the month (Fixed Assets → Post depreciation) and the
   **recurring entries** that are due (Recurring Entries → Process).
4. Check that **Receivables Aging ties to 411** and **Payables Aging ties to
   401** (the difference line at the foot reads 0).
5. Check **Cash and bank in the ledger**: each payment account beside its
   ledger account; every difference must be explained (section 7).
6. Check the **Income & Expenditure Sheet against the ledger** (its
   reconciliation is at the foot of the sheet): every difference must be named.
7. Read **Financial health** and the **Daybook: Trends & Analysis**.
8. Print the **trial balance, income statement and balance sheet**.
9. **Close the month** (Accounting → Fiscal Years → the year → Close). Months
   close in order; the review shows the month's figures and refuses while an
   earlier month is open or an entry of the month is not posted.

### At the year end
1. All twelve months closed, all bank accounts reconciled to 31 December.
2. Depreciation posted for December.
3. **Count the stock** (Stock → Inventory Count) and value it.
4. **Year-End Closing**: the checklist; the closing stock (the system offers
   PosInnova's own stock valuation, the accountant enters the counted value),
   which posts the stock variation (6031 / 311); then the closing entries
   (results to retained earnings), approved by the Admin.
5. Export the year's statements.

---

## 3. Every report

**Basis** says where the figures come from. *Ledger* reports read posted
journal entries and must agree with the trial balance. *Records* reports read
the POS documents directly; they answer operational questions and are tied to
the ledger by the checks named.

### Financial statements

| Report | Where | Answers | Basis | Must agree with |
|---|---|---|---|---|
| **Trial balance** | Accounting → Trial Balance (Excel) | Does every debit have its credit; what is the balance of each account | Ledger | Debits = credits; each account = its general ledger |
| **General ledger** / account ledger / ledger by class | Accounting → General Ledger | Every movement on an account, with running balance | Ledger | Trial balance |
| **Income statement** (Compte de résultat) | General Ledger → Income statement (PDF, Excel) | Income, expenses and the result for a period | Ledger | Classes 6 and 7 of the trial balance; the result on the balance sheet |
| **Balance sheet** (Bilan), with comparison date | General Ledger → Balance sheet (PDF, Excel) | What the business owns and owes at a date | Ledger | Assets = liabilities + equity + the year's result |
| **Cash flow statement**, comparative | Accounting Reports → Cash Flow Statement | Where cash came from and went: operating, investing, financing | Ledger | The change in class 5 over the period |

### Customers, suppliers and cash

| Report | Where | Answers | Basis | Must agree with |
|---|---|---|---|---|
| **Receivables Aging** | Accounting Reports → Receivables Aging | What each customer owes, by how overdue (from the sale's due date) | Sales and payments | **Account 411**: the difference line must read 0 |
| **Payables Aging** | Accounting Reports → Payables Aging | What is owed to each supplier, by how overdue | Purchases, expenses and payments | **Account 401**: the difference line must read 0 |
| Customer / supplier ledger | Contacts → the contact → Ledger | A contact's documents and payments with running balance | Records | Its line in the aging; the supplier's own statement |
| **Bank reconciliation** | Accounting → Bank Reconciliation | Does the statement agree with the books | Ledger | Difference nil to complete |
| Payment account book | Payment Accounts → Account Book | Movements per cash box, bank or MoMo account | Payment account transactions | **Cash and bank in the ledger** |
| **Daybook** (cash book), Monthly Summary, Trends & Analysis (PDF, Excel) | Budget → Daybook | Every receipt and payment, day by day, with the account on the other side | Ledger (class 5 lines) | The account ledger of each cash box |

### Budget

| Report | Where | Answers | Basis | Must agree with |
|---|---|---|---|---|
| **Income & Expenditure Sheet** (PDF, Excel) | Budget → Income & Expenditure Sheet | Budgeted against actual by line, for the budget year | Ledger | **Ledger**, section by section (its reconciliation is at the foot) |
| Daybook Monthly Summary, budget columns | Budget → Daybook: Monthly Summary | The month against the sheet: Budget, Monthly, BB Forward, Cumulative, Balance | Ledger | The sheet (Cumulative at the year end) |
| Budget vs Actual | Accounting Reports → Budget vs Actual | The same, as a report | Ledger | The sheet |
| Performance, variance, department, cash flow projection | Budget → the reports | Over / under-spending by line and location | Ledger | The sheet |

### Assets

| Report | Where | Answers |
|---|---|---|
| Asset register, asset report | Accounting → Fixed Assets | What the business owns, cost, accumulated depreciation, value left |
| Depreciation report | Fixed Assets: Depreciation Report | The schedule and what has been posted |

### Control screens

| Screen | Where | Answers |
|---|---|---|
| **Ledger health** | Accounting → Ledger health | Documents not posted, entries on the wrong date or amount, mappings missing, entries outside their year or month, unbalanced or duplicated entries, and whether each customer's and supplier's balance on 411 / 401 agrees with PosInnova |
| View Transactions | Accounting → View Transactions | Every document with its posting status; re-post one or all |
| Cash and bank in the ledger | Accounting → Cash and bank in the ledger | Each payment account beside its ledger account |
| Fiscal Years | Accounting → Fiscal Years | Which months are open or closed |

---

## 4. Analysis

| Screen | What it shows |
|---|---|
| **Financial health** (Accounting Reports → Financial health) | From the books, as at any date: cash and bank; **months of cash cover**; surplus and margin for the year; **payroll as a share of income**; **share of sales collected**; supplier bills overdue; money held for customers on 4191; budget spent against the share of the year gone. Each with a verdict (Healthy / Watch / Act now) and what it means; then twelve months of income, expenses, surplus and month-end cash, and this year against last year to the same date. |
| **Daybook: Trends & Analysis** (and its PDF pack) | Cash position month by month; money in and out; surplus or deficit by month; spending against the Income & Expenditure Sheet (pacing); where money comes from and goes; what changed between months; the largest movements; money on no account; who owes the most. Each chart says how to read it and what to do. |
| Budget reports | Variance and performance by line and location. |
| Home dashboard | Sales, purchases and expenses at a glance (from the POS). |

---

## 5. Who can do what

| Action | Accountant | Admin |
|---|---|---|
| Record expenses; take and record payments | yes | yes |
| Enter purchases; pay suppliers | yes | yes |
| Manual journal entries | enter (draft) | enter and post |
| Reverse or un-post a posted entry | | yes |
| Prepare a bank reconciliation (tick, post charges) | yes | yes |
| Complete a bank reconciliation | | yes |
| Prepare the budget | yes | yes |
| Approve the budget | | yes |
| Post the month's depreciation | yes (with "Fixed assets: edit") | yes |
| Process recurring entries | | yes |
| Close or reopen a month; close the year | | yes |

Permissions are under Settings → Roles → the role → **Accounting**; their names
are paxhitestbed's (`journal-entry-post`, `bank-reconciliation-complete`,
`accounting-period-close`, …). The Admin role has all of them.

---

## 6. Money in transit and the payment accounts

- A payment that names a payment account posts to that account's ledger
  account; one that does not posts to the default for its method (Cash and
  bank in the ledger). Link every payment account, or cash taken by bank
  transfer may land in the cash box.
- Money that moves **only in the books** (an asset bought from the bank by
  journal entry, a standing order processed as a recurring entry, bank charges
  posted from the reconciliation) is in the ledger and not in the payment
  account book. "Cash and bank in the ledger" shows these as differences; the
  ledger and the bank reconciliation are the reference. To keep the two books
  together, record such payments in PosInnova (an expense, a purchase) rather
  than by journal entry when you can.
- **Opening balances at go-live**: enter each payment account's opening
  balance in Payment Accounts (it posts Dr the account / Cr 4711), then one
  journal entry that moves 4711 to capital (101) or retained earnings (121) and
  brings in the counted stock (Dr 311). What customers and suppliers owed
  before the books start comes in by itself as the opening entry when the
  books are set up "from today".

---

## 7. Known limits, and what to decide

1. **Stock moves in the books only at the year end** (intermittent inventory).
   A month in which a lot was bought shows a low result, the next month a high
   one. For a monthly margin, compare sales with the cost of goods sold in
   PosInnova's own reports (Reports → Profit / Loss), or post a monthly stock
   variation by journal entry from a stock valuation.
2. **Payroll is not a module here.** Record net salaries as an expense in a
   category mapped to 661, and the social charges and tax withheld (664 / 431 /
   447) by journal entry or as expenses in their own categories.
3. **The books start when they are set up.** "From today" brings what
   customers and suppliers owe as one opening entry; "from the start of this
   year" posts every document of the year. Choose before the first month is
   closed; the start date is not changed afterwards.
4. **A product's type decides 701 or 706.** Services sold must be products of
   type "service"; everything else posts as goods.
5. **Unmapped expense categories post to 638** (other external costs) until
   given their own account in Mapping Settings.

---

## The illustrated handbook

`storage/app/accountant-handbook/` holds the handbook (HTML and PDF): the same
routine with a screenshot of every screen, taken on a demo pharmacy with
invented data (October and November 2026). It is not in the repository; it is
rebuilt from the test bed (`C:\xampp\posinnova-testbed\handbook`: `make_db.sh`
builds the demo database `posinnova_handbook`, `shots.py` takes the
screenshots, `build.py` writes the PDF).
