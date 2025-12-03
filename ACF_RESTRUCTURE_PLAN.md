# ACF Field Restructuring Plan

## Overview

Current state: E-scooter fields are flat (~100+ fields at root). E-bikes are properly nested.
Goal: Organize all product types with nested groups for better admin UX.

**Important**: This is a UI-only change. The `wp_postmeta` table structure doesn't change. No data migration required for existing products - ACF handles this automatically.

---

## Proposed Field Structure

### Shared Fields (All Product Types)

```
Products Field Group
├── thumbnail (image)
├── big_thumbnail (image)
├── product_type (select) - Electric Scooter, Electric Bike, etc.
├── brand (text)
├── model (text)
├── release_year (number)
├── release_quarter (select)
├── aff_link (url)
├── force_custom_aff_link (true_false)
├── amazon_overwrite (text)
├── coupon (repeater)
│   ├── website
│   ├── code
│   ├── discount_type
│   └── discount_value
├── video (group)
│   ├── video_link
│   └── video_thumbnail
└── pros_cons (group)
    ├── pros (repeater)
    └── cons (repeater)
```

### E-Scooter Fields (Conditional: product_type == Electric Scooter)

```
e-scooters (group)
├── performance (group) - "Performance & Testing"
│   ├── tested_top_speed (number, MPH)
│   ├── manufacturer_top_speed (number, MPH)
│   ├── acceleration (group)
│   │   ├── accel_0_15 (number, secs)
│   │   ├── accel_0_20 (number, secs)
│   │   ├── accel_0_25 (number, secs)
│   │   ├── accel_0_30 (number, secs)
│   │   └── accel_0_top (number, secs)
│   ├── fastest_acceleration (group)
│   │   ├── fastest_0_15 (number, secs)
│   │   ├── fastest_0_20 (number, secs)
│   │   ├── fastest_0_25 (number, secs)
│   │   ├── fastest_0_30 (number, secs)
│   │   └── fastest_0_top (number, secs)
│   └── hill_climb (group)
│       ├── hill_test_grade (number, %)
│       ├── hill_test_speed (number, MPH)
│       └── hill_test_result (text)
│
├── range (group) - "Range Testing"
│   ├── manufacturer_range (number, miles)
│   ├── tested_range_fast (number, miles)
│   ├── tested_range_regular (number, miles)
│   ├── tested_range_slow (number, miles)
│   ├── tested_range_avg_speed_fast (number, MPH)
│   ├── tested_range_avg_speed_regular (number, MPH)
│   └── tested_range_avg_speed_slow (number, MPH)
│
├── motor (group) - "Motor"
│   ├── motors (select: Single, Dual)
│   ├── motor_type (select: Hub, Belt, Gear)
│   ├── motor_position (select: Front, Rear, Dual)
│   ├── nominal_motor_wattage (number, W)
│   └── peak_motor_wattage (number, W)
│
├── battery (group) - "Battery"
│   ├── battery_type (select: Lithium-ion, Lead-acid)
│   ├── battery_brand (select: LG, Samsung, etc.)
│   ├── battery_voltage (number, V)
│   ├── battery_amphours (number, Ah)
│   ├── battery_capacity (number, Wh)
│   └── charge_time (number, hours)
│
├── build (group) - "Build & Dimensions"
│   ├── weight (number, lbs)
│   ├── max_load (number, lbs)
│   ├── deck_length (number, inches)
│   ├── deck_width (number, inches)
│   ├── deck_height (number, inches)
│   ├── handlebar_height_min (number, inches)
│   ├── handlebar_height_max (number, inches)
│   ├── folded_dimensions (text)
│   └── water_resistance (select: None, IP54, IP55, IP56, IPX5, etc.)
│
├── wheels (group) - "Wheels & Tires"
│   ├── tire_size_front (number, inches)
│   ├── tire_size_rear (number, inches)
│   ├── tire_type (checkbox: Pneumatic, Solid, Mixed)
│   ├── tire_width (number, inches)
│   └── split_rim (true_false)
│
├── brakes (group) - "Brakes"
│   ├── brake_type (checkbox: Disc Mechanical, Disc Hydraulic, Drum, Foot, Electronic)
│   ├── brake_size_front (number, mm)
│   └── brake_size_rear (number, mm)
│
├── suspension (group) - "Suspension"
│   ├── front_suspension (select: None, Spring, Hydraulic, Air, etc.)
│   ├── rear_suspension (select: None, Spring, Hydraulic, Air, etc.)
│   ├── front_travel (number, mm)
│   └── rear_travel (number, mm)
│
├── features (group) - "Features"
│   ├── display_type (select: LCD, LED, None)
│   ├── app_connectivity (true_false)
│   ├── turn_signals (true_false)
│   ├── headlight (true_false)
│   ├── taillight (true_false)
│   ├── horn (true_false)
│   └── cruise_control (true_false)
│
└── warranty (group) - "Warranty & Support"
    ├── warranty_months (number)
    └── warranty_notes (textarea)
```

### E-Bike Fields (Already Nested - Keep/Enhance)

```
e-bikes (group) - EXISTING, enhance slightly
├── category (select: Commuter, Mountain, Road, Cargo, etc.)
├── motor (group)
│   ├── motor_type (select: Hub, Mid-Drive)
│   ├── motor_position (select: Front Hub, Rear Hub, Mid, Dual)
│   ├── motor_brand (text)
│   ├── motor_model (text)
│   ├── power_nominal (number, W)
│   ├── power_peak (number, W)
│   └── torque (number, Nm)
├── battery (group)
│   ├── battery_capacity (number, Wh)
│   ├── battery_position (select: Frame-integrated, Rear rack, etc.)
│   ├── removable (true_false)
│   └── range (number, miles)
├── drivetrain (group) - ADD
│   ├── gears (number)
│   ├── derailleur_brand (text)
│   └── class (select: Class 1, Class 2, Class 3)
└── frame (group) - ADD
    ├── frame_material (select: Aluminum, Carbon, Steel)
    ├── frame_size (text)
    └── step_through (true_false)
```

### Electric Skateboard Fields (New Group)

```
e-skateboards (group)
├── performance (group)
│   ├── top_speed (number, MPH)
│   ├── range (number, miles)
│   └── hill_climb (number, %)
├── motor (group)
│   ├── motor_type (select: Hub, Belt)
│   ├── motor_count (number)
│   ├── motor_power (number, W)
│   └── motor_position (select: Rear, All-wheel)
├── battery (group)
│   ├── battery_capacity (number, Wh)
│   ├── battery_type (text)
│   └── charge_time (number, hours)
├── deck (group)
│   ├── deck_material (select: Bamboo, Maple, Carbon, etc.)
│   ├── deck_length (number, inches)
│   ├── deck_type (select: Drop-through, Top-mount, etc.)
│   └── flex (select: Stiff, Medium, Flexy)
├── wheels (group)
│   ├── wheel_size (number, mm)
│   ├── wheel_type (select: Street, All-terrain, Pneumatic)
│   └── wheel_material (select: Polyurethane, Rubber)
└── trucks (group)
    ├── truck_type (text)
    └── truck_width (number, mm)
```

### EUC Fields (New Group)

```
euc (group)
├── performance (group)
│   ├── top_speed (number, MPH)
│   ├── range (number, miles)
│   └── max_gradient (number, %)
├── motor (group)
│   ├── motor_power (number, W)
│   └── motor_type (text)
├── battery (group)
│   ├── battery_capacity (number, Wh)
│   ├── battery_voltage (number, V)
│   └── charge_time (number, hours)
├── wheel (group)
│   ├── wheel_size (number, inches)
│   ├── tire_type (select: Pneumatic, Solid)
│   └── tire_width (number, inches)
├── build (group)
│   ├── weight (number, lbs)
│   ├── max_load (number, lbs)
│   ├── pedal_height (number, mm)
│   └── trolley_handle (true_false)
└── features (group)
    ├── headlight (true_false)
    ├── taillight (true_false)
    ├── speakers (true_false)
    ├── suspension (true_false)
    └── kickstand (true_false)
```

### Hoverboard Fields (New Group)

```
hoverboards (group)
├── performance (group)
│   ├── top_speed (number, MPH)
│   ├── range (number, miles)
│   └── max_incline (number, %)
├── motor (group)
│   ├── motor_power (number, W)
│   └── motor_type (select: Dual Hub)
├── battery (group)
│   ├── battery_capacity (number, Wh)
│   ├── battery_type (text)
│   └── charge_time (number, hours)
├── build (group)
│   ├── weight (number, lbs)
│   ├── max_load (number, lbs)
│   ├── wheel_size (number, inches)
│   └── tire_type (select: Solid, Pneumatic)
└── features (group)
    ├── bluetooth (true_false)
    ├── led_lights (true_false)
    ├── self_balancing (true_false)
    └── app_connectivity (true_false)
```

---

## Migration Approach

### Phase 1: Create New Field Groups (No Breaking Changes)

1. Create new `e-scooters` group with nested structure
2. Add conditional logic: `product_type == Electric Scooter`
3. **Keep old flat fields temporarily** (don't delete yet)

### Phase 2: Data Mapping Script

Create a one-time migration script to copy data from old field names to new field names:

```php
// Example migration for acceleration fields
$old_to_new = [
    'acceleration:_0-15_mph' => 'e-scooters_performance_acceleration_accel_0_15',
    'acceleration:_0-20_mph' => 'e-scooters_performance_acceleration_accel_0_20',
    'tested_range_fast' => 'e-scooters_range_tested_range_fast',
    'battery_capacity' => 'e-scooters_battery_battery_capacity',
    // ... etc
];

$products = get_posts(['post_type' => 'products', 'numberposts' => -1]);
foreach ($products as $product) {
    $type = get_field('product_type', $product->ID);
    if ($type !== 'Electric Scooter') continue;
    
    foreach ($old_to_new as $old_key => $new_key) {
        $value = get_post_meta($product->ID, $old_key, true);
        if ($value) {
            update_post_meta($product->ID, $new_key, $value);
        }
    }
}
```

### Phase 3: Update Code References

Update all template/plugin code to use new field structure:

```php
// Old
$battery = get_field('battery_capacity', $product_id);
$accel = get_field('acceleration:_0-15_mph', $product_id);

// New
$scooter = get_field('e-scooters', $product_id);
$battery = $scooter['battery']['battery_capacity'];
$accel = $scooter['performance']['acceleration']['accel_0_15'];

// Or with null-safe operator (PHP 8+)
$battery = get_field('e-scooters', $id)['battery']['battery_capacity'] ?? null;
```

### Phase 4: Remove Old Fields

After confirming everything works:
1. Remove old flat fields from ACF
2. Optionally clean up old meta keys from database

---

## Benefits of This Structure

1. **Admin UX**: Collapsible sections, logical grouping
2. **Conditional Logic**: Only shows relevant fields per product type
3. **Code Organization**: Access like `$product['motor']['power']` instead of flat keys
4. **Maintainability**: Easy to add new fields to the right section
5. **Type Safety**: Each product type has its own validated structure

---

## What NOT to Change

1. **wp_postmeta table** - ACF handles storage automatically
2. **wp_product_data cache table** - Keep as-is, update the cron job to read from new field paths
3. **HFT tables** - Separate concern, not related to specs
4. **URL structure** - No change needed
5. **Post type structure** - Keep single `products` CPT

---

## Timeline Estimate

| Phase | Task | Time |
|-------|------|------|
| 1 | Create new ACF field groups | 2-3 hours |
| 2 | Write migration script | 2 hours |
| 3 | Run migration, verify data | 1 hour |
| 4 | Update theme templates | 4-6 hours |
| 5 | Update plugin (cron jobs, etc.) | 2-3 hours |
| 6 | Testing | 2 hours |
| 7 | Remove old fields | 30 min |

**Total: ~2 days of focused work**

This can be done AFTER the theme rebuild, or in parallel. The flat fields will continue to work until you're ready to migrate.

---

## Recommendation

**Do the ACF restructuring AFTER the main theme rebuild.**

Reasoning:
1. You can ship the new theme with the current flat field structure
2. Add ACF restructuring as a "polish" phase
3. Less risk during the main rebuild
4. You'll have a working fallback if needed

Add this to Phase 10 of your implementation checklist.
