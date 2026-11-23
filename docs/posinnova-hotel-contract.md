# POS Innova ↔ E-Ride Hotels channel, v1 (approved 2026-11-23)

The hotel side of the channel in `posinnova-channel-contract.md`. Same base URL, same rules: POS always
calls out, E-Ride never writes into POS. Only hotels running POS Innova's hotel module use it; every
other hotel keeps the E-Ride hotel portal as it is.

## Owner decisions (2026-11-23)

- Build it now (tested on POS demo data; no hotel runs the POS hotel module yet).
- The price guests see on E-Ride ("from FCFA X per night") is set by the hotel in POS per room type,
  as an **E-Ride price**. Empty = the room-only rate.
- POS for the actions (keep, book, decline, short reply); the conversation itself stays in E-Ride.
- E-Ride hotels stay free: no payments, deposits, commission or statements cross this channel.

## The link

- One key per hotel (one POS business location = one E-Ride hotel). Made in the E-Ride hotel portal:
  Setup › POS Innova › Link a POS. Format `erh_` + 40 characters. Shown once, stored hashed.
- Base: `https://eride.innovakickstarter.com/api/channel/posinnova/v1/hotel`
- Headers and errors as in the shop contract: `Authorization: Bearer erh_...`, `X-POS-Version`,
  `{"error":{"code","message"}}`, 401 `invalid_key` / `link_revoked`, 422 `invalid`.
- **Every POST takes an `Idempotency-Key` header.** The same key within 48 hours returns the first answer
  again without doing anything. Messages may arrive hours late (retried by the POS scheduler): E-Ride
  judges by what arrived, not by time windows.
- Once linked (first call), E-Ride:
  - shows guests the POS free counts, and "Ask the reception" when no heartbeat for 15 minutes
    (replaces the 12-hour rule for this hotel);
  - makes its rooms board read-only ("POS rooms") and hides Keep / Arrived / Left there;
  - no longer lets kept rooms lapse by itself: POS lapses its holds and reports them;
  - keeps chat, photos, amenities, beds and descriptions as they are.

## What never crosses

Guest IDs and police register data; the folio, charges, payments and deposits; agreed, B&B and corporate
rates; any guest who did not come through E-Ride (they only make free counts smaller); housekeeping
details and staff names. POS receives only the E-Ride guest's name and phone, which the guest sent to
that hotel. On the POS side the guest becomes a contact (matched by phone, or created) and the
reservation has source `eride`; nothing else about the guest is stored.

## The hotel day

`hotel_day` is POS's hotel day, not the calendar date: after midnight and until the night audit it is
still yesterday (a 00:30 arrival belongs to last night). E-Ride reads `free[0]` as "tonight" as POS sees
it, and a night D as `free[D − hotel_day]`. Nights before `hotel_day` or past the array are unknown:
E-Ride shows "Ask the reception" for dates that need them.

## Endpoints

### GET /hello

`{"hotel":{"id","name"},"linked_at","server_time","room_types":[{"id","name","capacity"}]}`.
`room_types` are the E-Ride room types, for information: the mapping is done in the E-Ride portal.

### POST /heartbeat

`{"queue": 0, "pos_time": "..."}` every 5 minutes → `{"ok":true,"server_time"}`.

### POST /availability/full

The whole picture, nightly and on first link. Replaces everything E-Ride held.

```json
{
  "snapshot_id": "uuid",
  "hotel_day": "2026-11-23",
  "types": [
    {"pos_type_id": 4, "name": "Standard double", "capacity": 2, "eride_price": 25000,
     "rooms_total": 12, "free": [5, 3, 0, 7]}
  ]
}
```

- `free[i]` = rooms of the type POS can still give for night `hotel_day + i`: active rooms, minus nights
  held (tentative holds count), minus bookings by type with no room yet, minus rooms out of order that
  night. Clamped to 0 or more. Up to 120 nights.
- `rooms_total` = active rooms of the type, whatever their out-of-order dates.
- `eride_price` = the E-Ride price, else the room-only rate, rounded to whole FCFA.
- A type missing from a full push is gone.

### POST /availability/changes

Same `types` entries, each with its own `from` date and only the nights that changed:
`{"hotel_day":"2026-11-23","types":[{"pos_type_id":4,"from":"2026-11-25","free":[2,2]}],"removed_types":[7]}`.
`name`, `capacity`, `eride_price`, `rooms_total` are optional here.

### GET /inquiries/pending

What the reception should see on the POS hotel home. No cache.

```json
{
  "inquiries": [
    {"inquiry_id": "uuid", "number": "H-1042", "status": "open",
     "guest": {"name": "Awa N.", "phone": "+2376..."},
     "check_in": "2026-11-25", "check_out": "2026-11-27", "nights": 2, "guests": 2, "rooms": 1,
     "room_type": {"eride_id": "uuid", "name": "Standard double", "pos_type_id": 4},
     "arrival": "evening", "question": "Is breakfast included?",
     "last_guest_message": {"text": "...", "at": "..."}, "unread": 1,
     "reservations": [{"pos_ref": "RES-0091", "status": "kept", "until": "..."}],
     "eride_url": "https://eride.innovakickstarter.com/partner/hotel/inbox/..."}
  ],
  "to_release": [
    {"inquiry_id": "uuid", "number": "H-1040", "pos_ref": "RES-0088", "reason": "guest_let_go", "at": "..."}
  ]
}
```

- `inquiries`: every active inquiry (open, answered, kept, arrived) of this hotel. `status` is one of
  `open | answered | kept | booked | arrived | closed`.
- `room_type.pos_type_id` is null when the E-Ride type is not mapped: the receptionist picks the POS type
  at Keep.
- `to_release`: one row per `pos_ref` POS holds for an inquiry that ended on the E-Ride side (the guest
  let the room go, or the reception closed it in the E-Ride portal). `reason`: `guest_let_go` |
  `closed_on_eride`. POS cancels those reservations, then calls `/release-seen`.

### GET /inquiries/{id}

The same inquiry object, uncached, with its current status. POS checks it when Keep is pressed, so a
guest who let go seconds ago is caught before a reservation is made.

### POST /inquiries/{id}/kept

The receptionist pressed "Keep a room" (TENTATIVE) or booked it at once (CONFIRMED). One POS
reservation per room.

```json
{"status": "tentative", "pos_refs": ["RES-0091", "RES-0092"], "pos_type_id": 4, "rooms": 2,
 "until": "2026-11-25T18:00:00+01:00", "by": "Brenda"}
```

- `tentative`: E-Ride shows "kept for you until 18:00" and the Ride there button. `until` is the hold
  time POS set (the hotel's "hold E-Ride guests until" setting, or what the receptionist chose).
- `confirmed`: `until` is null; E-Ride shows "booked" at once.
- E-Ride keeps one stay per `pos_ref`. The same refs again = a new `until` (or confirmed); extra refs =
  more rooms kept.
- 409 `inquiry_closed` when the inquiry already ended on the E-Ride side: POS cancels the reservations.

### POST /inquiries/{id}/declined

`{"reason":"full"|"other","text":"...","by":"Brenda"}` → E-Ride closes the inquiry and tells the guest
(full = "see other hotels with free rooms"). `text`, when given, is posted in the chat first.

### POST /inquiries/{id}/reply

`{"text":"...","by":"Brenda"}`, up to 500 characters → posted in the E-Ride chat as the hotel.

### POST /inquiries/{id}/event

Per reservation: `{"event":"confirmed|arrived|left|no_show|released|cancelled","pos_ref":"RES-0091","at":"..."}`

| POS | event | E-Ride stay | Guest is told |
|---|---|---|---|
| tentative → confirmed | `confirmed` | booked | "Your room is booked" |
| checked_in | `arrived` | in house | "Welcome to {hotel}" (once per inquiry) |
| checked_out | `left` | left | (nothing) |
| night audit no-show | `no_show` | no show | "The reception marked that you did not come" |
| the hold time passed | `released` | released | "Your room was let go" |
| reception cancelled | `cancelled` | released | "Your room was let go" |

The inquiry follows its stays: arrived when one is in house, closed when none is held or in house.
An event for a stay that already reached that state is a no-op. Unknown `pos_ref` for the inquiry →
409 `not_this_reservation`.

### POST /inquiries/{id}/release-seen

`{"pos_refs":["RES-0088"],"seen_at":"..."}` (no `pos_refs` = all of the inquiry) → `{"ok":true}`.

## On the POS side

- An E-Ride price per room type; `hold_until` becomes a date and time, with a hotel setting "hold
  E-Ride guests until" (default 18:00 on the arrival day); POS lapses holds itself.
- The inquiry inbox on the hotel home with a ring: Keep a room / Book / Decline / Reply, and "Open the
  chat" (`eride_url`).

## On the E-Ride side

- Portal: Setup › POS Innova (key, connected, last seen), and a mapping of POS room types to E-Ride room
  types (link, or add as a new E-Ride type with photos and amenities to fill in). A mapped type takes
  its "from" price and max guests from POS.
- Stays held in POS are kept on E-Ride without a room number (POS gives the room).
