# Bug Report — Kassa PHP8 Migration

Audit performed 2026-09-27 on branch `feature/php8-migration-completion`.

## Resolved

### 1. Cash register goes the wrong way on refunds

**File:** `src/Rotalia/API/Controller/PurchaseController.php:142`

When `payment=refund`, `$totalSumCents` was negated (`-$sum`), causing the cash register
to *decrease* when a member paid cash in. Credit was double-negated back to positive, so it
went the right way, but the register drifted on every refund.

**Fix:** `$totalSumCents` is now positive for refunds (cash increases), and the credit
adjustment explicitly checks the payment type to add credit instead of subtracting it.

---

### 2. `Product::$activeConventId` never set in ReportsController

**File:** `src/Rotalia/API/Controller/ReportsController.php:267, 327`

`ReportRow::updateCurrentPrice()` and `saveProductCounts()` both call
`Product::getActiveProductInfo()`, which matches on the static `Product::$activeConventId`.
In `ReportsController::create`, this static was never set. Since it is declared without a
default (`public static ?int $activeConventId;`), accessing it is undefined behavior in
PHP 8.2+. When it does not match any ProductInfo, `getActiveProductInfo()` creates a new,
empty ProductInfo with `conventId = null` — inventory updates write into the void.

**Fix:** `Product::$activeConventId = $conventId` is now set at the top of `create()`,
after the authorization check.

---

### 3. Products pagination count is always wrong

**File:** `src/Rotalia/API/Controller/ProductsController.php:97`

The count query reused the same `QueryBuilder` that still carried `setFirstResult` /
`setMaxResults` from the paginated fetch. `COUNT(distinct p.id)` ran with LIMIT/OFFSET,
returning at most `$limit` instead of the true total. The frontend always showed one page.

**Fix:** The count now runs on a cloned QueryBuilder with `setFirstResult(null)` and
`setMaxResults(null)`.

---

### 4. Missing authorization on `GET /reports/-1`

**File:** `src/Rotalia/API/Controller/ReportsController.php:131-148`

When `id === -1`, the method accepted any `conventId` from the query string and returned
the latest verification report without checking whether the user belongs to that convent.
The positive-ID path (line 157) did check. Any authenticated user could read any convent's
reports.

**Fix:** Added the same convent-authorization check that the positive-ID path uses.

---

### 5. `resetSeq` resets ALL products globally

**File:** `src/Rotalia/API/Controller/ProductsController.php:215`

The first `UPDATE product_info SET seq = 99 WHERE convent_id = :conventId` was correctly
scoped, but the second `UPDATE product SET seq = 99` had no WHERE clause. An admin in one
convent reset the base sequence for every product across all convents.

**Fix:** Removed the unscoped `UPDATE product SET seq = 99` statement. The `product.seq`
column is a legacy field (`// TODO: remove from database`); `product_info.seq` is the
authoritative per-convent sequence.

---

### 6. `Product::$status` — nullable column, non-nullable uninitialized property

**File:** `src/App/Entity/Product.php:44-45`

The column is `nullable: true` but the PHP property was typed `string` (not `?string`) with
no default. Doctrine hydrating a NULL value threw `TypeError`.

**Fix:** Changed the property type to `?string` with a `= null` default.

---

### 7. `ProductInfo::$status` — nullable column, non-nullable property

**File:** `src/App/Entity/ProductInfo.php:36-37`

Same pattern as bug 6. The column is `nullable: true`, the property was `string`. The
default `ProductStatus::DISABLED->value` helped new objects but not database hydration of
existing NULLs.

**Fix:** Changed the property type to `?string` (keeps the default for new objects).

---

### 8. DECIMAL columns mapped to `float` — precision loss

**Files:**
- `src/App/Entity/Product.php:41-42` (`$amount`)
- `src/App/Entity/ProductInfo.php:30-31` (`$warehouseCount`)
- `src/App/Entity/ProductInfo.php:33-34` (`$storageCount`)

Doctrine's DECIMAL type returns strings to preserve precision. Mapping to `float` caused
silent coercion (e.g. `"10.10"` became `10.1`). Other DECIMAL fields in the codebase
correctly used `?string`.

**Fix:** Changed all three properties from `?float` to `?string`. Getters still return
`?float` (cast on read), and setters accept `null|float|string` for compatibility.

---

### 9. CORS hides `Content-Range` header — pagination broken cross-origin

**File:** `config/packages/nelmio_cors.yaml:7`

`expose_headers` only listed `['Link']`. The frontend reads `Content-Range` for pagination
footers, but the browser hid it in cross-origin responses. The regex in
`kassa-table-footer` (line 113) threw `TypeError` on `null`.

**Fix:** Added `'Content-Range'` to the `expose_headers` array.

---

### 10. `remember_me: true` without a `remember_me` firewall section

**File:** `config/security.yaml:14`

`json_login` set `remember_me: true` (attaching a `RememberMeBadge` to the passport), but
the `main` firewall had no `remember_me:` section. Depending on the Symfony 7 patch version
this either threw a `LogicException` during compilation or silently did nothing.

**Fix:** Removed `remember_me: true` from `json_login`. If remember-me is needed in the
future, a proper `remember_me:` firewall section should be added alongside it.

---

### 11. `_checkSuccess` loses `this` binding — server errors swallowed

**File:** `src/Rotalia/FrontendBundle/Resources/source/elements/kassa-backend.html:640-651`

Every API call passed `this._checkSuccess` unbound to `.then()`. The success path worked
(does not use `this`), but the error path called `this.fire(...)`, which threw `TypeError`
because `this` was undefined. Server-side validation errors were silently swallowed.

**Fix:** Changed all `.then(this._checkSuccess)` to `.then(this._checkSuccess.bind(this))`.

---

### 12. Duplicate column mappings (9 entities)

**Files:** Transaction, Report, Transfer, PointOfSale, CreditNettingRow, GuardDutyCycle,
Member, User, UserRight

Scalar `$conventId` (or similar) and relationship `$convent` both mapped to the same
database column without `insertable: false, updatable: false` on the Column attribute.
Caused `doctrine:schema:validate` failures and potential data conflicts on flush.

**Fix:** Removed the `#[ORM\Column]` attribute from the scalar properties on all 9
entities, making them plain PHP fields (not Doctrine-mapped). The ManyToOne relationship
is the sole owner of the database column. Getters fall back to `$this->convent?->getId()`
to support hydrated entities loaded from the database.

---

### 13. `setConvent(null)` crashes `setConventId(int)` (7 entities)

**Files:** Transaction, Transfer, Report, PointOfSale, CreditNettingRow, GuardDutyCycle,
ProductInfo

`setConvent(?Convent)` called `setConventId($convent?->getId())`, but `setConventId`
required `int`, not `?int`. Passing `null` threw `TypeError`.

**Fix:** Changed `setConventId(int $conventId)` to `setConventId(?int $conventId)` on all
7 entities.

---

### 14. `MemberCredit::$convent` declares `inversedBy: 'memberCredits'` but Convent has no such property

**File:** `src/App/Entity/MemberCredit.php:26-27`

The `inversedBy` attribute referenced a `$memberCredits` collection on `Convent` that did
not exist. Caused `doctrine:schema:validate` to fail and broke any attempt to traverse the
relationship from the inverse side.

**Fix:** Removed the `inversedBy: 'memberCredits'` argument. The `Member` entity already
has the inverse collection; Convent does not need one.
