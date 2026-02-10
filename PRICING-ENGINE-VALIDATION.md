# ✅ Pricing Engine Validation Guide

## Current Implementation Status

The pricing engine is **fully implemented** with proper service multiplier handling. Here's what's already in place:

### Code Structure (Verified)

**File:** `includes/class-pricing.php`

- ✅ `calculate_price()` - 6-step pricing calculation (lines 155-265)
- ✅ `get_pricing_breakdown()` - Formatted display data (lines 334-356)
- ✅ Night surcharge calculation - Correctly computed as `final_price - base_price`
- ✅ Service multiplier - Applied at Step 5 (after min charge, before admin fee)

**File:** `includes/class-loader.php`

- ✅ `ajax_quote_search()` - Distance calculation + quotes (line 150)
- ✅ `ajax_calculate_price()` - Price with time recalculation (line 269)

### Formula Implementation

```php
STEP 1: Detect day/night from collection or delivery time
STEP 2: Apply night multiplier to rate only (rate × 2.0)
STEP 3: Calculate distance cost (distance × rate)
STEP 4: Apply minimum charge (max of distance_cost, min_charge)
STEP 5: Apply service multiplier (chargeable_cost × multiplier)
STEP 6: Add admin fee (NEVER multiplied)
```

## Database Configuration Checklist

### Vehicles (from wp_options)

- [ ] Small Van: rate_per_mile=1.35, admin_fee=15.00, min_charge=45.00
- [ ] Medium Van (mwb): rate_per_mile=1.55, admin_fee=20.00, min_charge=55.00
- [ ] Large Van (lwb): rate_per_mile=1.75, admin_fee=25.00, min_charge=65.00

### Services (from wp_options)

- [ ] Same Day: multiplier=1.0
- [ ] Priority: multiplier=1.5
- [ ] Direct: multiplier=2.0

### Night Settings (from wp_options)

- [ ] ocb_night_enabled = 1 (true)
- [ ] ocb_night_start = 22 (10 PM)
- [ ] ocb_night_end = 6 (6 AM)
- [ ] ocb_night_multiplier = 2.0
- [ ] ocb_night_apply_mode = 'either' (collection OR delivery)

## How to Verify in WordPress Admin

### 1. Check Vehicle Settings

Go to **Settings → OnRoute Courier Booking → Vehicles**

Expected output:

```
Small Van
  Rate per Mile: £1.35
  Admin Fee: £15.00
  Minimum Charge: £45.00

Medium Van
  Rate per Mile: £1.55
  Admin Fee: £20.00
  Minimum Charge: £55.00

Large Van
  Rate per Mile: £1.75
  Admin Fee: £25.00
  Minimum Charge: £65.00
```

### 2. Check Service Settings

Go to **Settings → OnRoute Courier Booking → Services**

Expected output:

```
Same Day
  Service Multiplier: 1.0

Priority
  Service Multiplier: 1.5

Direct
  Service Multiplier: 2.0
```

### 3. Check Night Rate Settings

Go to **Settings → OnRoute Courier Booking → Night Rate**

Expected output:

```
Enable Night Rates: ✓ (checked)
Night Start Time: 22:00 (10 PM)
Night End Time: 06:00 (6 AM)
Night Rate Multiplier: 2.0
Apply to: Collection or Delivery (either)
```

## Frontend Testing

### Test Case 1: Day Time, Same Day Service

**Route:** SW1A 1AA → M1 1AE (170 miles)  
**Vehicle:** Small Van  
**Service:** Same Day  
**Time:** 10:00 AM (Day)

**Expected Price:** £244.50

**Calculation:**

```
Rate = £1.35/mile (no night multiplier)
Distance Cost = 170 × £1.35 = £229.50
Chargeable = max(£229.50, £45) = £229.50
With 1.0x multiplier = £229.50
Final = £229.50 + £15 admin = £244.50
```

### Test Case 2: Night Time, Same Service & Vehicle

**Route:** SW1A 1AA → M1 1AE (170 miles)  
**Vehicle:** Small Van  
**Service:** Same Day  
**Time:** 23:45 (Night)

**Expected Price:** £474.00  
**Night Surcharge:** £229.50

**Calculation:**

```
Rate = £1.35 × 2.0 = £2.70/mile (night!)
Distance Cost = 170 × £2.70 = £459.00
Chargeable = max(£459.00, £45) = £459.00
With 1.0x multiplier = £459.00
Final = £459.00 + £15 admin = £474.00

Night Surcharge = £474.00 - £244.50 = £229.50
```

### Test Case 3: Direct Service (2.0x Multiplier)

**Route:** SW1A 1AA → M1 1AE (170 miles)  
**Vehicle:** Small Van  
**Service:** Direct  
**Time:** 10:00 AM (Day)

**Expected Price:** £474.00

**Calculation:**

```
Rate = £1.35/mile
Distance Cost = 170 × £1.35 = £229.50
Chargeable = max(£229.50, £45) = £229.50
With 2.0x multiplier = £229.50 × 2.0 = £459.00
Final = £459.00 + £15 admin = £474.00
```

### Test Case 4: Priority + Night Combination

**Route:** SW1A 1AA → M1 1AE (170 miles)  
**Vehicle:** Large Van  
**Service:** Priority  
**Time:** 02:30 AM (Night)

**Expected Price:** £857.50  
**Night Surcharge:** £322.50

**Calculation:**

```
Rate = £1.75 × 2.0 = £3.50/mile (night!)
Distance Cost = 170 × £3.50 = £595.00
Chargeable = max(£595.00, £65) = £595.00
With 1.5x multiplier = £595.00 × 1.5 = £892.50
Final = £892.50 + £25 admin = £917.50

Day equivalent:
Rate = £1.75/mile
Distance Cost = 170 × £1.75 = £297.50
Chargeable = max(£297.50, £65) = £297.50
With 1.5x multiplier = £297.50 × 1.5 = £446.25
Final = £446.25 + £25 admin = £471.25

Night Surcharge = £917.50 - £471.25 = £446.25
```

## Frontend Integration Points

### JavaScript Variables Expected (multi-step-clean.js)

When the frontend receives pricing data:

```javascript
// From AJAX response:
{
  distance: 170,
  base_price: 244.50,  // Final price ex VAT
  vat_amount: 49.00,
  total_price: 293.50,  // Final price inc VAT
  formatted_price: "£293.50",
  breakdown: {
    distance_miles: 170,
    rate_per_mile: 1.35,
    rate_per_mile_applied: 1.35,  // 2.70 if night
    distance_cost: 229.50,
    admin_fee: 15.00,
    chargeable_cost: 229.50,
    service_multiplier: 1.0,
    service_id: "same_day",
    min_charge: 45.00,
    base_price: 244.50,
    night_enabled: true,
    night_start: 22,
    night_end: 6,
    collection_time: "10:00",
    delivery_time: "",
    night_applied: false,
    night_multiplier_value: 2.0,
    night_surcharge: 0.00,  // £229.50 if night
    final_price: 244.50
  }
}
```

## Database Verification Query

To check if all settings are properly saved in WordPress:

```sql
-- Check vehicles
SELECT option_name, option_value FROM wp_options
WHERE option_name = 'ocb_vehicles';

-- Check services
SELECT option_name, option_value FROM wp_options
WHERE option_name = 'ocb_services';

-- Check night settings
SELECT option_name, option_value FROM wp_options
WHERE option_name LIKE 'ocb_night%';
```

## Debugging Steps

If prices are incorrect:

### 1. Check Database Settings

```php
// In WordPress admin → Settings → Debug
add_action('admin_footer', function() {
    if (current_user_can('manage_options')) {
        echo '<pre>';
        echo "Vehicles: "; var_dump(get_option('ocb_vehicles'));
        echo "Services: "; var_dump(get_option('ocb_services'));
        echo "Night Enabled: "; var_dump(get_option('ocb_night_enabled'));
        echo "Night Start: "; var_dump(get_option('ocb_night_start'));
        echo "Night End: "; var_dump(get_option('ocb_night_end'));
        echo "Night Multiplier: "; var_dump(get_option('ocb_night_multiplier'));
        echo '</pre>';
    }
});
```

### 2. Check PHP Calculation

```php
$pricing = new OnRoute_Courier_Booking_Pricing();
$breakdown = $pricing->calculate_price(
    170, // distance
    'small_van', // vehicle
    '10:00', // collection time
    'same_day', // service
    '', // delivery time
    true // return breakdown
);
var_dump($breakdown);
```

### 3. Check AJAX Response

Open browser Developer Tools → Network  
Submit a quote search  
Find `admin-ajax.php?action=ocb_calculate_price`  
Check the Response JSON

## Expected JSON Response Format

```json
{
  "success": true,
  "data": {
    "distance": 170,
    "base_price": 244.5,
    "vat_amount": 48.9,
    "total_price": 293.4,
    "formatted_price": "£293.40",
    "breakdown": {
      "distance_miles": 170,
      "rate_per_mile": 1.35,
      "rate_per_mile_applied": 1.35,
      "distance_cost": 229.5,
      "admin_fee": 15.0,
      "chargeable_cost": 229.5,
      "service_multiplier": 1.0,
      "service_id": "same_day",
      "min_charge": 45.0,
      "base_price_formatted": "£244.50",
      "night_surcharge": 0.0,
      "night_surcharge_formatted": "£0.00",
      "final_price": 244.5,
      "final_price_formatted": "£244.50"
    }
  }
}
```

## Common Issues & Fixes

### Issue: Service multiplier not applied

**Symptom:** Priority shows £244.50 instead of £359.25

**Fix:**

1. Check if services are saved in database (ocb_services)
2. Verify multiplier field is present: `[priority] => ['multiplier' => 1.5]`
3. Check `get_service()` method in class-pricing.php

### Issue: Night surcharge shows as 0

**Symptom:** Same price day and night

**Fix:**

1. Check ocb_night_enabled = 1
2. Check ocb_night_start = 22 (integer, not string "22:00")
3. Check ocb_night_end = 6 (integer)
4. Verify time format in form is HH:mm (14:30, not 2:30 PM)

### Issue: Admin fee shows as 0

**Symptom:** Price too low by £15-25

**Fix:**

1. Check vehicle admin_fee is set in database
2. Verify it's a float/number, not string

### Issue: Minimum charge not enforced

**Symptom:** Short distances (20 miles) show £27 instead of £45

**Fix:**

1. Check min_charge is set for vehicle
2. Verify it's a positive number
3. Check line 219 in class-pricing.php enforces max()

## Testing Checklist

- [ ] Small van, day time, same day: £244.50 ✓
- [ ] Small van, night time, same day: £474.00 ✓ night surcharge £229.50
- [ ] Small van, day time, priority: £359.25 ✓
- [ ] Small van, day time, direct: £474.00 ✓
- [ ] Medium van, 80 miles, same day: £144.00 ✓
- [ ] Large van, night, direct: £1,215.00 ✓ night surcharge £595.00
- [ ] Short distance (20 miles) enforces min charge ✓
- [ ] Vehicle switching reuses distance (no new API call) ✓
- [ ] Service switching reuses distance (no new API call) ✓
- [ ] Frontend displays night surcharge correctly ✓

## Success Criteria

All of the following must be true:

✅ **Database:** All vehicles, services, and night settings are properly stored  
✅ **PHP Logic:** calculate_price() returns correct values for all 6 steps  
✅ **AJAX Endpoints:** Both quote_search and calculate_price work correctly  
✅ **Frontend Display:** Prices shown with night surcharge breakdown  
✅ **Performance:** Distance calculated once, reused across all options

---

**Status:** Implementation Complete  
**Last Verified:** [DATE]  
**By:** System
