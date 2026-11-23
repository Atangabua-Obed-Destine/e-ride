# E-Ride ↔ POS Innova channel: contract v1

**Status:** v1 final, 2026-11-23. Approved by the POS Innova side; its final pass added edited or deleted sales, a fresh order check, separate rate limits and late retries. Nothing is built yet: the owner says when to start.

## Rules the owner set

**Linking**
- Each E-Ride branch links to the POS Innova system of that branch. That can be a shop PC install or a location in the cloud install.
- Warehouses are never linked.

**Sales**
- POS Innova makes the final sale. E-Ride never writes a sale into POS.
- A paid E-Ride order shows on the POS screen, and the salesperson rings it up herself.

**Money**
- E-Ride settles the delivery fees a shop collects for E-Ride riders weekly. Its commission is settled monthly.
- A shop with its own Fapshi account:
  - The customer pays that account for the items and the delivery fee. E-Ride never holds that money.
  - E-Ride bills its commission and its riders' delivery fees in a statement.
- A shop without a Fapshi account: E-Ride collects the money and pays the shop out, as it does today.

**Display and products**
- The business chooses what customers see: available / sold out, or the exact quantity.
- Pharmacies may publish:
  - Publishing is opt-in per product.
  - A per-product "Never on E-Ride" switch and a per-category block keep prescription drugs out.
- POS owns the E-Ride price. E-Ride shows the price POS sends, and the order carries that same price back.

## Direction and transport

- **POS always calls E-Ride; E-Ride never calls POS.** Shop PCs behind a router need nothing opened.
- **Base URL:** `https://<eride-host>/api/channel/posinnova/v1`.
- **Format:**
  - HTTPS only. JSON in and out, UTF-8, except image uploads, which are multipart.
  - Times are ISO 8601 with offset.
  - Money is whole FCFA, as integers, with tax included.
  - Quantities are decimals, in the published unit.
- **Clocks:** E-Ride judges freshness by its own receive time, never by POS timestamps, because shop PC clocks are often wrong. `/hello` returns `server_time`, and POS warns when its clock is off by more than 5 minutes.

## Linking and auth

1. In the E-Ride partner portal, the owner opens Settings > POS Innova and clicks **Link a POS** for one branch.
   - E-Ride creates a key, `erk_` followed by 40 random characters.
   - E-Ride stores only its hash and shows the key once.
2. In POS Innova, under that location's E-Ride settings, the owner pastes the E-Ride URL and the key.
   - POS stores the key encrypted.
   - The key never reaches the browser: the POS page calls its own server, and that server calls E-Ride.
3. POS calls `GET /hello` to check the link.

**Headers:**
- Every request sends `Authorization: Bearer erk_...` and `X-POS-Version`.
- POST requests also send `Idempotency-Key: <uuid>`.

**Errors:**
- They come back as `{"error": {"code": "...", "message": "..."}}` with HTTP 401, 403, 404, 409, 422 or 429.
- When E-Ride unlinks a branch, it answers 401 `link_revoked`.

**Rate limits** are per key, in three separate buckets, so a big first sync never starves the order polling:

| Endpoints | Limit |
|---|---|
| `/catalog/full`, `/catalog/changes` | 120 per minute |
| `/catalog/images` | 60 per minute |
| `/orders/*`, `/heartbeat`, `/hello` | 60 per minute |

Over the limit: 429 with `Retry-After`. POS sends images in the background and scales them down to at most 1200 px before uploading.

**Late messages.** When the shop's internet is down, POS queues `rung-up`, `unrung`, `cancel-seen` and catalogue pushes, and its scheduled task retries them. So E-Ride can receive them hours late, with past timestamps. E-Ride judges "not yet rung up" from the order's state, never from a time window it expects a message in.

## Endpoints

### GET /hello

Checks the link.

```json
{
  "branch": {"id": "uuid", "store": "Pacesetters Supermarket", "branch": "Commercial Avenue", "type": "shop"},
  "linked_at": "2026-11-23T14:05:00+01:00",
  "last_catalog_at": null,
  "server_time": "2026-11-23T14:05:02+01:00"
}
```

### POST /heartbeat

- Sent every 5 minutes by the POS scheduled task. Polling `/orders/pending` does not count as a heartbeat, because that only runs while a POS screen is open.
- **Body:** `{"pos_time": "...", "published": 812}`.
- **Answer:** `{"ok": true, "server_time": "..."}`.
- **Staleness on E-Ride:**
  - No heartbeat for 15 minutes during the branch's opening hours: the store sees "POS not connected since 14:10". Customers' availability stays as last received.
  - No heartbeat for 2 hours: the store and the E-Ride admin are warned.

### POST /catalog/full

A full snapshot of every product published for this location.

**When it is sent:**
- at first link;
- nightly, just after midnight, which catches lots that expired at midnight;
- when the owner presses "Send everything again".

**Paging:**
- Pages of up to 500 items.
- Each page is idempotent on `snapshot_id` + `page`.
- E-Ride keeps an unfinished snapshot open for 2 hours.

**What E-Ride does:**
- When the last page arrives, items E-Ride knew from this location that are missing from the snapshot become `removed`.
- A snapshot that never completes removes nothing, and the old data stays.

```json
{
  "snapshot_id": "uuid",
  "page": 1,
  "pages": 4,
  "generated_at": "2026-11-24T00:05:00+01:00",
  "location": {"id": 3, "name": "Commercial Avenue", "qty_display": "available"},
  "items": [ <Item>, ... ]
}
```

**Answer:** `{"received": 500, "need_images": ["<sha1>", ...]}`.

### POST /catalog/changes

The items whose E-Ride view changed. POS decides what changed by keeping a "last sent" hash per (location, variation):
- every minute, for the items whose updated_at moved;
- every hour, for all published items.

That way, changes without timestamps are caught too: group prices, product locations, tax, category names, expiry.

Sent at most once a minute, and only when something changed.

```json
{
  "cursor": "2026-11-23T14:04:00+01:00",
  "location": {"id": 3, "qty_display": "available"},
  "items": [ <Item>, ... ],
  "removed": [123, 456]
}
```

- **`cursor`:** informational only.
- **`removed`:** variation ids that are unpublished, deleted, removed from the location, set to "Never on E-Ride", or now in a blocked category.
- **Answer:** `{"received": 2, "need_images": []}`. E-Ride upserts by `variation_id`, so a resend is harmless.

### POST /catalog/images

Multipart upload of a product's main image, sent only for hashes E-Ride listed in `need_images`. Extra images are not sent in v1.

- **Fields:**
  - `sha1`: hex of the file.
  - `file`: jpg, png or webp, at most 5 MB.
  - `variation_id` or `product_id`.
- **Answer:** `{"ok": true}`.
- E-Ride stores the file once per sha1 and serves it from its own storage.

### `<Item>`

```json
{
  "variation_id": 123,
  "product_id": 45,
  "sku": "PCM500-10",
  "name": "Paracetamol 500 mg",
  "variation_name": null,
  "description": "Pain and fever relief",
  "category": {"id": 7, "name": "Pain relief"},
  "sub_category": null,
  "image_sha1": "3f2a...",
  "unit_id": 4,
  "unit": "box",
  "price": 1500,
  "track_stock": true,
  "qty_available": 12,
  "low_stock_threshold": 2,
  "next_expiry": "2027-01-31",
  "updated_at": "2026-11-23T13:58:10+01:00"
}
```

**Identity**
- **`sku`:** `variations.sub_sku`. In UltimatePOS this is also what a barcode encodes. There is no separate barcode field.
- **`variation_name`:**
  - null for single products, whose hidden "DUMMY" variation is not sent;
  - "<attribute>: <value>" for variable products, e.g. "Size: Large".

**Content**
- **`description`:** plain text. POS strips the HTML of `product_description`.
- **`image_sha1`:** the main image (`products.image`), or null.

**Unit, price and stock**
- **`unit_id`:** POS's id for the published unit, which may be a sub-unit. Order lines echo it, so the cart uses the right unit.
- **`price`:** the final price, tax included and rounded to whole FCFA. POS resolves it from the location's selling price group (fixed or percentage) or from the owner's E-Ride price set in POS.
- **`track_stock`:** from `enable_stock`. When it is false, the item is always available and `qty_available` is null.
- **`qty_available`:** the sellable quantity in the published unit. Expired lots, and lots within the stop-selling days, are left out. It is never below 0.
- **`low_stock_threshold`:**
  - Defaults to `products.alert_quantity`, converted to the published unit.
  - The owner can override it per product for E-Ride.
  - E-Ride shows the item as sold out when `qty_available` ≤ this number.
- **`next_expiry`:** the earliest expiry of a lot with stock left, or null.

**Not sent in v1:** combo products.

### GET /orders/pending

Asked by the POS server for each location. The POS server caches the answer for 15 seconds, so several open tills mean about 4 calls a minute.

The answer lists:
- paid orders not yet rung up, oldest first, at most 50;
- orders cancelled after they were rung up, which need a sell return.

```json
{
  "orders": [
    {
      "order_id": "9b1f...-uuid",
      "number": "1019",
      "status": "paid",
      "placed_at": "2026-11-23T12:01:00+01:00",
      "paid_at": "2026-11-23T12:03:30+01:00",
      "customer": {"name": "Awa N.", "phone": "+237670000000", "address": "Nkwen, near Finance Junction, blue gate"},
      "way": "delivery",
      "lines": [
        {"variation_id": 123, "unit_id": 4, "name": "Paracetamol 500 mg", "unit": "box", "qty": 2, "unit_price": 1500, "total": 3000},
        {"variation_id": null, "unit_id": null, "name": "Bag of ice", "unit": null, "qty": 1, "unit_price": 500, "total": 500}
      ],
      "items_total": 3500,
      "discount": 0,
      "delivery_fee": 1200,
      "delivery_fee_collected_by_shop": true,
      "total": 4700,
      "payment": {"method": "mobile_money", "provider": "fapshi", "reference": "FPS-8H2K91", "paid_to": "shop"},
      "note": "Please add a receipt",
      "eride_url": "https://<eride-host>/partner/orders/9b1f..."
    }
  ],
  "cancelled": [
    {"order_id": "77aa...", "number": "1012", "pos_sale_id": 991, "reason": "Customer cancelled", "cancelled_at": "..."}
  ]
}
```

**Totals**
- `items_total` − `discount` + `delivery_fee` = `total`.
- `discount` is an integer, 0 or more. POS puts it in the sale discount.

**Lines**
- **`variation_id`:** null for an E-Ride item not linked to a POS product. POS shows that line in red, and the salesperson decides.
- **`unit_price` and `total`:** what the customer paid. The cart uses these prices.
- **The lot:** POS chooses the `purchase_line_id` itself, FIFO.

**Delivery fee**
- When `delivery_fee_collected_by_shop` is true, POS puts it in shipping charges, so the Fapshi balance matches.

**`payment.paid_to`**
- **`"shop"`:** the customer paid the shop's own Fapshi account. POS proposes the location's "Mobile Money" method.
- **`"eride"`:** E-Ride collected the money and pays the shop out later, minus its commission. `delivery_fee_collected_by_shop` is false.
  - POS records a credit sale on the business's "E-Ride" contact (the owner's decision, 2026-11-23). E-Ride's payout is later recorded as a payment on that contact, so the shop always sees what E-Ride still owes it.
  - The sale must still reference the real customer, as below.

**Customer and receipt**
- **The contact:**
  - For `"shop"`, the customer is matched by phone to an existing POS contact, or the walk-in customer is used.
  - For `"eride"`, the contact is the business's "E-Ride" contact.
- **The end customer** is recorded the same way in both cases:
  - `delivered_to`: the customer's name.
  - `shipping_address`: the phone, plus `customer.address` when E-Ride sends one.
  - `shipping_details`: "E-Ride #<number>".
  - `shipping_status`: "ordered". The shop moves it through the native Shipments list.
- **The sale note** (`additional_notes`) repeats them as "E-Ride #1019 · Awa N. · +237670000000". The note prints on every receipt layout and can be searched in the sales list.
- **`customer.address`:** the delivery address as text, or null when the customer collects or eats there.

### GET /orders/{order_id}

A fresh check of one order, with no cache. POS calls it when the salesperson clicks "Ring up", so a cancellation from a few seconds ago is caught before she starts.

The answer is the same order object as in `/orders/pending`, with its current `status`: `paid`, `cancelled` or `rung_up`.

### POST /orders/{order_id}/rung-up

Sent by POS after the salesperson completes the sale.

Sending it again with the **same** `pos_sale_id` updates the record: new `sale_total` and lines, and `matches` is worked out again. This covers a sale edited in POS later. A **different** `pos_sale_id` is refused, as below.

```json
{
  "pos_sale_id": 991,
  "invoice_no": "CA-2026-00991",
  "rung_up_at": "2026-11-23T12:10:00+01:00",
  "by": "Brenda",
  "sale_total": 4700,
  "lines": [{"variation_id": 123, "qty": 2, "total": 3000}]
}
```

**Answer:**
- `{"ok": true, "matches": true}`, or `"matches": false` when `sale_total` or the lines differ from the order.
- When they differ, E-Ride flags the order to the store and the admin: "Rung up for less than paid: settle the difference with the customer."

**Conflicts (409):**
- `already_rung_up`: another sale was already recorded. The answer carries the first `pos_sale_id`.
- `order_cancelled`: the customer cancelled while she was ringing it up. POS tells her to void the sale.

POS keeps its own unique link table, `order_id` → `transaction_id`.

### POST /orders/{order_id}/unrung

A sale was deleted in POS after it was rung up. The order goes back to "not yet rung up", and the reminders start again.

- **Body:** `{"pos_sale_id": 991, "reason": "sale deleted"}`.
- **Answer:** `{"ok": true}`.
- If `pos_sale_id` is not the recorded sale: 409 `not_this_sale`.

### POST /orders/{order_id}/cancel-seen

POS confirms it showed a cancelled order. The salesperson then does a normal sell return in POS.

- **Body:** `{"seen_at": "..."}`.
- **Answer:** `{"ok": true}`.

## What E-Ride does with it

**Linking products**
- E-Ride keeps the link from a menu item to (branch, POS `variation_id`).
- A setup screen per branch:
  - links exact SKU matches by itself;
  - suggests same-name matches;
  - leaves the rest for linking by hand.
- New POS items show as "Not on the menu yet".

**Availability**
- Per branch, from `qty_available` and `low_stock_threshold`. When `track_stock` is false, the item is always available.
- The display follows `qty_display`: "available" shows available / sold out, "exact" shows "12 left".

**Other**
- **Prices:** E-Ride shows `price` as sent. E-Ride does not set its own price on linked items.
- **Images:** E-Ride uses the POS image unless the store set its own photo on E-Ride. The same goes for the description.
- **Rung up:**
  - The partner portal and the admin show "Rung up in POS" or "Not yet rung up".
  - A reminder goes out after 30 minutes, and there is a daily list.
  - A rung-up sale that doesn't match the order is flagged.

## Not in v1

- Holding stock for E-Ride orders. The store confirms each order anyway.
- Restaurant modifiers.
- Combo products.
- Extra product images.
- E-Ride writing anything into POS.
- More than one POS per E-Ride branch.
