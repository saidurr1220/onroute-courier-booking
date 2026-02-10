# OnRoute Actual Pricing Specification

## Client Requirements (CLEAR)

### Distance Calculation

- ✅ Distance calculated **ONCE** using Distance Matrix API
- ✅ Distance is **REUSED** for all vehicle quotes
- ✅ **NO new API calls** on vehicle switching
- ✅ **NO new API calls** on time changes

### Day Rate Pricing (06:00 - 21:59)

```
Small Van:  Distance × £1.35/mile + £15 admin (min £45)
MWB:        Distance × £1.55/mile + £20 admin (min £55)
LWB:        Distance × £1.75/mile + £25 admin (min £65)
```

### Night Rate Pricing (22:00 - 05:59)

```
Per-mile rate × 2.0 (doubling the rate)
Admin fee = UNCHANGED (NOT doubled)

Example:
Small Van Night: Distance × (£1.35 × 2.0) + £15 admin
              = Distance × £2.70 + £15 admin
```

### Formula (NO Service Multipliers!)

```
final_price = max(distance_miles × rate_per_mile, min_charge) + admin_fee

Where:
  rate_per_mile = base_rate (if day) OR base_rate × 2.0 (if night)
  min_charge = vehicle-specific minimum
  admin_fee = vehicle-specific admin fee (NOT multiplied)
```

### What Should NOT Trigger API Calls

- ❌ Switching vehicles (use same distance)
- ❌ Changing delivery time (use same distance)
- ❌ Selecting different service type (if separate from vehicle)
- ✅ ONLY calculate distance ONCE on initial postcode entry

---

## Test Cases (CORRECTED - No Service Multipliers)

### Test 1: Small Van, DAY, 170 miles

```
distance_cost = 170 × £1.35 = £229.50
chargeable = max(£229.50, £45) = £229.50
final = £229.50 + £15 = £244.50
```

### Test 2: Small Van, NIGHT, 170 miles (23:00)

```
rate = £1.35 × 2.0 = £2.70/mile
distance_cost = 170 × £2.70 = £459.00
chargeable = max(£459.00, £45) = £459.00
final = £459.00 + £15 = £474.00
Night surcharge = £474.00 - £244.50 = £229.50
```

### Test 3: Medium Van, DAY, 80 miles

```
distance_cost = 80 × £1.55 = £124.00
chargeable = max(£124.00, £55) = £124.00
final = £124.00 + £20 = £144.00
```

### Test 4: Medium Van, NIGHT, 80 miles (04:30)

```
rate = £1.55 × 2.0 = £3.10/mile
distance_cost = 80 × £3.10 = £248.00
chargeable = max(£248.00, £55) = £248.00
final = £248.00 + £20 = £268.00
Night surcharge = £268.00 - £144.00 = £124.00
```

### Test 5: Large Van, DAY, 170 miles

```
distance_cost = 170 × £1.75 = £297.50
chargeable = max(£297.50, £65) = £297.50
final = £297.50 + £25 = £322.50
```

### Test 6: Large Van, NIGHT, 170 miles (02:30)

```
rate = £1.75 × 2.0 = £3.50/mile
distance_cost = 170 × £3.50 = £595.00
chargeable = max(£595.00, £65) = £595.00
final = £595.00 + £25 = £620.00
Night surcharge = £620.00 - £322.50 = £297.50
```

### Test 7: Small Van, SHORT DISTANCE (20 miles, DAY)

```
distance_cost = 20 × £1.35 = £27.00
chargeable = max(£27.00, £45) = £45.00 ← MIN CHARGE APPLIES
final = £45.00 + £15 = £60.00

Note: Without min charge, would be £27 + £15 = £42, but minimum is £45
So charging £45 for distance before adding admin fee
```

---

## AJAX Implementation

### 1st AJAX Call: ocb_quote_search (DISTANCE CALCULATED HERE)

```javascript
// Frontend sends postcodes
POST /wp-admin/admin-ajax.php?action=ocb_quote_search
{
  pickup_code: "SW1A 1AA",
  delivery_code: "M1 1AE"
}

// Backend returns (one distance call):
{
  distance: 170,
  quotes: {
    "same_day": [
      {vehicle_id: "small_van", price: 244.50, night_price: 474.00},
      {vehicle_id: "mwb", price: 269.00, night_price: 538.00},
      {vehicle_id: "lwb", price: 322.50, night_price: 620.00}
    ]
  }
}
```

### 2nd AJAX Call: ocb_calculate_price (NO NEW DISTANCE CALL)

```javascript
// Frontend sends selection (no postcodes needed!)
POST /wp-admin/admin-ajax.php?action=ocb_calculate_price
{
  vehicle_id: "lwb",
  collection_time: "08:00",
  delivery_time: "23:45",
  // NO postcodes - use stored distance from session or step 1
}

// Backend uses STORED distance and recalculates based on time
// Returns pricing breakdown with night surcharge
```

---

## Database Nodes (What Can Be Changed in Admin)

```
ocb_vehicles = [
  {
    id: "small_van",
    name: "Small Van",
    rate_per_mile: 1.35,
    admin_fee: 15.00,
    min_charge: 45.00
  },
  {
    id: "mwb",
    name: "Medium Van",
    rate_per_mile: 1.55,
    admin_fee: 20.00,
    min_charge: 55.00
  },
  {
    id: "lwb",
    name: "Large Van",
    rate_per_mile: 1.75,
    admin_fee: 25.00,
    min_charge: 65.00
  }
]

ocb_night_enabled = 1
ocb_night_start = 22  (10 PM)
ocb_night_end = 6     (6 AM)
ocb_night_multiplier = 2.0
ocb_night_apply_mode = "either"  (Collection OR Delivery)
```

---

## Summary

| Aspect             | Current Spec                    |
| ------------------ | ------------------------------- |
| **Distance Calls** | 1 per booking (reused)          |
| **Price Formula**  | max(dist × rate, min) + admin   |
| **Night Rate**     | Multiplies rate ONLY, not admin |
| **Service Types**  | NO MULTIPLIERS (just day/night) |
| **Min Charge**     | Enforced before admin fee       |
| **Admin Fee**      | NEVER multiplied                |
| **Switching Vans** | No new API call (same distance) |
| **Time Changes**   | No new API call (same distance) |
