# E-Bike Scoring Research: electricbikereview.com Analysis

## Research Methodology

Browsed electricbikereview.com using Playwright to analyze:
- Individual bike reviews (6+ detailed reviews across categories)
- "Best Electric Bikes" guide pages
- Category pages (mid-drive, fat tire, mountain, cargo)
- Forum structure and user discussions
- Comparison tool structure

## Key Findings: What Matters to E-Bike Buyers

### 1. Motor & Power Delivery (HIGH IMPORTANCE)

**Torque is king for e-bikes** - unlike scooters where raw wattage matters most, e-bike reviews consistently emphasize torque (Nm) as the primary performance metric.

| Factor | Why It Matters |
|--------|----------------|
| Torque (Nm) | Hill climbing, acceleration responsiveness. 90Nm is "excellent", 50Nm is entry-level |
| Motor Position | Mid-drive = efficiency, better weight distribution, uses gears. Hub = simpler, cheaper |
| Sensor Type | Torque sensor = "smooth, natural, accurate" response. Cadence = on/off feeling |
| Assist Modes | More modes = more control. 5 modes typical, some premium have unlimited tuning |
| Power (W) | Less emphasized than torque, but 750W is standard, 250W for lightweight |

**Example from Aventon Pace 4 review:**
> "Refined dual-sided torque sensor providing smooth and natural feeling assistance"

**Example from Ride1Up Trailrush:**
> "Brose mid-drive delivers 90 Nm torque with smooth, responsive power delivery"

### 2. Battery & Range (HIGH IMPORTANCE)

E-bike reviews always include **real-world range testing** vs manufacturer claims. This is a major trust factor.

| Factor | Why It Matters |
|--------|----------------|
| Capacity (Wh) | Primary range determinant. 500Wh average, 700Wh+ for long-range |
| Real vs Claimed Range | Reviews consistently show 20-40% lower than manufacturer claims |
| Charge Time | 3-6 hours typical. Fast charging (3.5hr) highlighted as a pro |
| Removable Battery | Convenience for apartment dwellers, security |
| Dual Battery Option | Cargo bikes often support dual batteries (1200Wh+) |

**Example from Urtopia Carbon Fold:**
> "Limited range compared to manufacturer claims (16.5-35.1 miles real-world vs. 50 miles advertised)"

### 3. Ride Quality & Comfort (HIGH IMPORTANCE)

This is MORE complex than scooters due to frame geometry, multiple suspension points, and traditional cycling ergonomics.

| Factor | Why It Matters |
|--------|----------------|
| Frame Geometry | Head tube angle affects handling (66-67° for trail bikes) |
| Suspension Type | Rigid, hardtail, full-suspension, seatpost suspension |
| Fork Travel | More travel = more capability (80mm urban, 120mm+ trail) |
| Tire Width | Fat (4"+), plus (2.8-3"), standard (1.75-2.4") |
| Handlebar Style | Affects riding position dramatically |
| Step-through Access | Major accessibility feature for many riders |

**Example from Lectric XPeak:**
> "Redesigned frame offers better curb appeal, improved stiffness, and more responsive handling"

### 4. Components & Build Quality (MEDIUM-HIGH IMPORTANCE)

Component brand names matter more in e-bikes than scooters. Shimano, SRAM, Tektro, Magura are mentioned frequently.

| Factor | Why It Matters |
|--------|----------------|
| Brake Type | Hydraulic disc standard on quality bikes. Mechanical = budget |
| Rotor Size | 180mm minimum for stopping power. 203mm for heavier bikes |
| Drivetrain | 7-12 speed. Shimano Deore+ is quality marker |
| Wheel Setup | Tubeless-ready = fewer flats, better handling |
| Display Quality | Size, readability, IP rating all mentioned |

**Example from RadRunner MAX:**
> "Enhanced 2.3mm thick brake rotors improving performance and longevity"

### 5. Weight & Portability (CATEGORY-DEPENDENT)

Weight matters enormously for some categories (folding, lightweight) and less for others (cargo).

| Category | Weight Expectation |
|----------|-------------------|
| Lightweight/Fitness | Under 40 lbs highlighted as exceptional |
| Commuter | 50-60 lbs typical |
| Fat Tire/Utility | 70-80 lbs acceptable |
| Cargo | 80-100+ lbs expected |
| Folding | Under 50 lbs, 32 lbs = "remarkable feat" |

**Example from Urtopia Carbon Fold:**
> "At a tested 32lbs, a fully capable folding ebike is a remarkable feat"

### 6. Safety & Security Features (GROWING IMPORTANCE)

Modern e-bikes increasingly include smart security features, especially at higher price points.

| Factor | Why It Matters |
|--------|----------------|
| Integrated Lights | Front lumens (500-750 typical), rear visibility |
| Braking Performance | Stopping distance, wet weather performance |
| IP Rating | Weather resistance for commuters |
| GPS Tracking | Theft deterrent/recovery |
| Alarm/PIN | Security features now expected on premium bikes |
| Traffic Radar | Emerging feature (RadRunner MAX) |

### 7. Tech & Connectivity (MEDIUM IMPORTANCE)

App connectivity is mentioned but not always decisive.

| Factor | Why It Matters |
|--------|----------------|
| App Connectivity | Ride tracking, motor tuning, theft alerts |
| Display Functions | Speed, assist level, battery, odometer |
| OTA Updates | Future-proofing (Bosch Smart System praised) |
| USB Charging | Convenience feature |

### 8. Value & Ownership (ALWAYS MENTIONED)

Every review discusses price-to-value ratio.

| Factor | Why It Matters |
|--------|----------------|
| Price Point | Budget (<$1500), Mid ($1500-2500), Premium ($2500+) |
| Warranty | 2-year standard, 4+ year is notable |
| Parts Availability | Long-term serviceability |
| Accessory Ecosystem | Especially for utility/cargo bikes |

---

## Comparison: E-Bike vs E-Scooter Priorities

| Factor | E-Scooter | E-Bike |
|--------|-----------|--------|
| Primary Power Metric | Watts | Torque (Nm) |
| Motor Type Importance | Single/Dual | Mid-drive vs Hub |
| Suspension Complexity | Simple (front/rear) | Complex (fork, shock, seatpost) |
| Drivetrain | None | Critical (gears, chain, derailleur) |
| Weight Concern | Always high | Category-dependent |
| Frame Style | Fixed | Huge variety (geometry, step-through) |
| Legal Classification | Usually unclassified | Class 1/2/3 defined |
| Tire Variety | Limited | Massive (fat, plus, road, MTB) |
| Range Testing | Important | Critical (always tested vs claimed) |
| Folding | Common feature | Distinct category |
| Cargo Capacity | Minimal | Can be primary feature |

---

## Recommended E-Bike Scoring Categories

Based on research, I propose **7 scoring categories** (same number as e-scooters for consistency):

### 1. Motor Performance (20%)
*Primary power delivery assessment*

| Spec | Points | Notes |
|------|--------|-------|
| Torque (Nm) | 40 | Log scale: 50→70, 75→85, 100→95 |
| Motor Position | 25 | Mid-drive: 25, Hub: 15 |
| Sensor Type | 20 | Torque sensor: 20, Cadence: 10 |
| Power (W) | 15 | 750W: 15, 500W: 10, 250W: 5 |

**Reasoning:** Torque matters most for e-bikes. Motor position and sensor type dramatically affect ride quality. Raw wattage is secondary.

### 2. Range & Battery (20%)
*Energy capacity and real-world range*

| Spec | Points | Notes |
|------|--------|-------|
| Battery Capacity (Wh) | 50 | Log scale: 400→60, 600→80, 800→90 |
| Voltage | 20 | 52V: 20, 48V: 15, 36V: 10 |
| Charge Time | 15 | Inverse: 3hr→15, 5hr→10, 7hr→5 |
| Removable Battery | 10 | Yes: 10, Integrated: 5 |
| Dual Battery Support | 5 | Yes: 5, No: 0 |

**Reasoning:** Wh is primary range determinant. Voltage affects power delivery. Charge time and removability are convenience factors.

### 3. Ride Quality (20%)
*Comfort and handling characteristics*

| Spec | Points | Notes |
|------|--------|-------|
| Suspension Type | 40 | Full: 40, Front+Seatpost: 30, Front: 20, Rigid: 10 |
| Fork Travel | 20 | Log scale based on bike type |
| Tire Width | 15 | Fat (4+): 15, Plus (3): 12, Standard: 8 |
| Frame Style Bonus | 10 | Step-through: +5, Carbon: +5 |
| Tire Type | 15 | Puncture-protected: 15, Standard: 8 |

**Reasoning:** E-bikes cover diverse terrain. Suspension setup, tire selection, and frame accessibility matter tremendously.

### 4. Drivetrain & Components (15%)
*Mechanical quality and braking*

| Spec | Points | Notes |
|------|--------|-------|
| Brake Type | 35 | Hydraulic: 35, Mechanical: 15 |
| Rotor Size | 15 | 203mm: 15, 180mm: 10, 160mm: 5 |
| Gear Count | 20 | 10+: 20, 8-9: 15, 7: 10, Single: 5 |
| Drivetrain Quality | 15 | Shimano Deore+: 15, Altus: 10, Generic: 5 |
| Belt Drive | 15 | Yes: 15, Chain: 10 |

**Reasoning:** Component quality directly affects safety (brakes) and usability (gears). Brand recognition matters in cycling.

### 5. Weight & Capacity (10%)
*Portability and load handling*

| Spec | Points | Notes |
|------|--------|-------|
| Weight (inverse) | 50 | Category-adjusted scoring |
| Payload Capacity | 30 | 400+lbs: 30, 300lbs: 20, 265lbs: 10 |
| Rack Capacity | 20 | 100+lbs: 20, 50lbs: 10, None: 0 |

**Reasoning:** Weight importance varies by category (critical for folding, less for cargo). Payload matters for utility.

### 6. Features & Tech (10%)
*Smart features and integrated accessories*

| Spec | Points | Notes |
|------|--------|-------|
| Display Quality | 20 | Color LCD: 20, Monochrome: 12, Basic: 5 |
| Integrated Lights | 20 | Front+Rear: 20, Front only: 10, None: 0 |
| App Connectivity | 15 | Full app: 15, Basic: 8, None: 0 |
| Security Features | 15 | GPS+Alarm: 15, Alarm: 10, Basic: 5 |
| Extras | 30 | Fenders, rack, kickstand, USB, etc. |

**Reasoning:** Modern e-bikes are increasingly "smart." Integrated features add value and convenience.

### 7. Safety & Compliance (5%)
*Legal classification and safety equipment*

| Spec | Points | Notes |
|------|--------|-------|
| E-Bike Class | 30 | Class 3: 30, Class 2: 25, Class 1: 20 |
| IP Rating | 25 | IP65+: 25, IP54: 15, None: 0 |
| Certifications | 20 | UL: 20, CE: 15, None: 0 |
| Reflectors/Visibility | 25 | Full kit: 25, Partial: 15, None: 0 |

**Reasoning:** Legal compliance and safety certifications matter for insurance, trail access, and peace of mind.

---

## Category Weight Adjustments

Different e-bike types should adjust category weights:

| Category Type | Motor | Battery | Ride | Components | Weight | Features | Safety |
|--------------|-------|---------|------|------------|--------|----------|--------|
| Commuter | 20% | 20% | 15% | 15% | 10% | 15% | 5% |
| Mountain | 25% | 15% | 25% | 20% | 5% | 5% | 5% |
| Cargo | 20% | 20% | 10% | 15% | 20% | 10% | 5% |
| Folding | 15% | 20% | 15% | 10% | 25% | 10% | 5% |
| Fat Tire | 20% | 20% | 20% | 15% | 10% | 10% | 5% |
| Road/Fitness | 15% | 20% | 20% | 20% | 15% | 5% | 5% |

---

## Implementation Notes

### Key Differences from E-Scooter Scoring

1. **Torque replaces power as primary metric** - E-bikes leverage gears, so torque matters more than raw watts
2. **Motor position is a major factor** - Mid-drive vs hub fundamentally changes the riding experience
3. **Drivetrain gets its own category** - Gears, chains, and component quality are e-bike specific
4. **Weight scoring is category-adjusted** - 60lbs is great for cargo, terrible for folding
5. **Safety category includes e-bike class** - Legal classification affects where you can ride

### Data Sources (ACF Fields)

Most specs already exist in ACF:
- `motor.torque` (Nm)
- `motor.motor_position` (mid-drive, rear hub, front hub)
- `motor.sensor_type` (torque, cadence)
- `battery.battery_capacity` (Wh)
- `suspension.*` (front, rear, seatpost)
- `brakes.*` (type, rotor sizes)
- `drivetrain.*` (gears, system)
- `weight_and_capacity.*`
- `integrated_features.*`

### Missing Data Considerations

Some valuable specs may not be in ACF:
- Real-world tested range (we have claimed range)
- Component brand tiers (Shimano Deore vs Altus)
- Fork stanchion diameter (for quality assessment)

---

## Summary

E-bike scoring should prioritize:
1. **Torque and motor type** over raw wattage
2. **Ride quality** (suspension, tires, geometry) as a major category
3. **Component quality** with brand recognition
4. **Category-adjusted** weight scoring
5. **Feature richness** for modern smart bikes

The 7-category system mirrors e-scooters for consistency while capturing e-bike-specific factors like drivetrain quality and motor positioning.
