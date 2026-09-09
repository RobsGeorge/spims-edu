=== STEP W3-c — Finance / Wallet UX ===

SCOPE (presentation-only — no controller, route, or migration changes):
  resources/views/finance/index.blade.php
  lang/{ar,en,fr}/finance.php   (append keys only)

GOAL:
Replace the raw `<ul><li>` transaction list with a structured ledger table, and improve the
invoice table so it's actionable at a glance.

CURRENT STATE:
- Transactions: an unordered list of badges with no dates, no column structure, no visual hierarchy.
- Invoice table: shows truncated ULID as "ID", raw `amountDue()` output, and a link labelled "Pay"
  with no visual urgency for overdue items.

CHANGES REQUIRED:

1. TRANSACTION LEDGER TABLE
   Replace the `<ul class="small mb-0">` inside the transactions card with a responsive table:
   ```blade
   <div class="spims-table-wrap">
   <table class="table table-sm align-middle mb-0">
       <thead>
           <tr>
               <th>{{ __('finance.tx_date') }}</th>
               <th>{{ __('finance.tx_direction') }}</th>
               <th class="text-end">{{ __('finance.tx_amount') }}</th>
               <th>{{ __('finance.tx_kind') }}</th>
               <th>{{ __('finance.tx_reason') }}</th>
           </tr>
       </thead>
       <tbody>
           @forelse($transactions as $tx)
               <tr>
                   <td class="text-nowrap spims-text-dim small">{{ $tx->created_at->format('Y-m-d') }}</td>
                   <td><x-badge :value="$tx->direction" /></td>
                   <td class="text-end tabular-nums"><x-money :minor="(int) $tx->amount_minor" :currency="$tx->currency" /></td>
                   <td><x-badge :value="$tx->kind" /></td>
                   <td><x-badge :value="$tx->reason" /></td>
               </tr>
           @empty
               <tr><td colspan="5" class="spims-text-dim py-3 text-center">{{ __('ui.empty') }}</td></tr>
           @endforelse
       </tbody>
   </table>
   </div>
   ```
   The `spims-table-wrap` class already enforces `overflow-x: auto` for mobile.

2. INVOICE TABLE IMPROVEMENTS
   - Replace truncated ULID "ID" column with the invoice's `created_at` date (already available on
     the model). Label the column `{{ __('finance.invoice_date') }}`.
   - Wrap the `amountDue()` output in `<x-money>`:
     ```blade
     <x-money :minor="(int) $invoice->amountDue()" :currency="$invoice->currency" />
     ```
     `amountDue()` already returns integer minor units — confirm in the model before applying.
     If it returns a float or Money object, read the model first and adapt accordingly.
   - Add overdue visual urgency: if `$invoice->due_at` is in the past and `$invoice->amountDue() > 0`,
     wrap the due-date cell in `<span class="text-danger fw-semibold">`.
   - Keep the "Pay" and "View receipt" links exactly as they are — do not move or relabel them.

3. LANG KEYS TO ADD (all three locales):
   finance.tx_date       → "Date"     / "التاريخ" / "Date"
   finance.tx_direction  → "Dir."     / "الاتجاه" / "Dir."
   finance.tx_amount     → "Amount"   / "المبلغ"  / "Montant"
   finance.tx_kind       → "Kind"     / "النوع"   / "Type"
   finance.tx_reason     → "Reason"   / "السبب"   / "Raison"
   finance.invoice_date  → "Date"     / "التاريخ" / "Date"

RULES:
- Presentation-only. Do NOT touch any controller, route, or migration.
- `amountDue()` — check the Invoice model before assuming its return type. Use `(int)` cast only
  if it is confirmed to return integer minor units; otherwise use the appropriate Money helper.
- Every string goes in lang/{ar,en,fr}/finance.php.
- No banned patterns (text-muted, bg-light, bg-secondary, card border-0 shadow-sm, raw ->value in output).
- Mobile-first: both tables are inside `.spims-table-wrap`; numeric columns get `tabular-nums`.
- `text-end` on the amount column is correct for LTR; RTL will mirror it via logical CSS — do NOT
  use `text-right`.

DONE WHEN:
- `./scripts/validate-step.sh W3-c` exits 0 (PASS).
- Transactions are in a table with date, direction, amount, kind, reason columns.
- Invoice table shows date instead of ULID; overdue items are visually highlighted.
- Committed and pushed to the worktree branch.
