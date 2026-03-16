# Doctor Performance Dashboard — Implementation Plan

> **Status:** Requirements Finalized | Ready for Implementation  
> **Last Updated:** 2026-03-16  
> **Spec Reference:** docs/doctor_dashboard_spec_v1.5.txt

---

## 1. Confirmed Requirements Summary

### 1.1 Doctor Identification
- **Roles:** `Aesthetic Doctor`, `Consultant`, `Lifestyle Consultant`
- **Source of truth:** `doctor_has_locations` where `is_allocated = 1`
- **Multi-branch:** Doctor is ONE entity; data combined from all assigned branches
- **Benchmark pool:** All 3 roles, active, is_allocated=1, minimum 5 consultations in period

### 1.2 KPI Definitions

| # | KPI | Formula | Data Source |
|---|-----|---------|-------------|
| 1 | **Total Revenue** | Sum of `package_advances.cash_amount` for all patients where this doctor is the LAST consultant, within date range. Excludes product sales. | `appointments` (type=1, arrived) → patient pool → `package_advances` |
| 2 | **Conversion Rate** | Converted Consultations / Total Arrived Consultations × 100 | `appointments` (type=1, arrived) + `package_advances` (cash_amount > 0) |
| 3 | **Avg Client Value** | Total Revenue / Converted Consultations | Derived from #1 and #2 |
| 4 | **Upsell Revenue** | Sum of `package_services.tax_including_price` where `sold_by = doctor` AND `sold_by != appointment.doctor_id` | `package_services` + `appointments` |
| 5 | **Upsell Rate** | Unique upsold patients / Unique treated patients × 100 (monthly dedup) | `package_services` + `appointments` (type=2, arrived) |
| 6 | **Gold Memberships Sold** | COUNT of memberships where `package_services.sold_by = doctor` AND membership_type is Gold or Gold renewal | `package_services` → `packages` (plan_type='membership') → `package_bundles` → `membership_types` |
| 7 | **Feedback Score** | AVG(rating) from feedback records for this doctor | `feedback` table (rating is 1-10 scale) |
| 8 | **Google Reviews** | Monthly manual entry count per doctor | New `doctor_google_reviews` table |
| 9 | **Product Revenue** | SUM of `orders.total_price` where `prescribed_by = doctor` | `orders` table |
| 10 | **Patient Return Rate** | % of treated patients who return within 45 days (any doctor) | `appointments` (type=2, arrived) |
| 11 | **Avg Procedures/Patient** | Total arrived treatments / Unique treated patients | `appointments` (type=2, arrived) |
| 12 | **Today's Appointments** | Count of today's consultations + treatments | `appointments` (one-time load, no polling) |
| 13 | **Patients Seen** | Unique patients from arrived consultations + treatments in period | `appointments` |
| 14 | **New vs Returning** | Breakdown of consultation patients (first-ever vs repeat) | `appointments` + patient history |

### 1.3 Total Revenue — "Last Consultant" Algorithm (Critical)

```
1. Get all arrived consultations (appointment_type_id=1, arrived status)
   ordered by scheduled_date DESC, grouped by patient_id
2. For each patient, the doctor_id of the MOST RECENT arrived consultation
   = that patient's "owning doctor" (regardless of branch)
3. For the selected date range, sum package_advances.cash_amount
   for all patients owned by this doctor
4. If two doctors consulted same patient same day → use later scheduled_time
```

This means revenue follows the patient relationship, not the specific consultation date. A doctor who consulted in January still receives credit for March payments from that patient, until another doctor re-consults the patient.

### 1.4 Upsell Rate Edge Cases
- 0 treated patients + >0 upsells → display **"N/A"** with tooltip
- 0 treated + 0 upsells → display **"0%"**
- Previous-month patient upsell → numerator only, not denominator

### 1.5 Streak
- Consecutive **weeks** (Sun–Sat) with ≥50% conversion rate
- No consultations in week → pause (neither continues nor resets)
- <50% conversion → reset to 0
- Display: current streak + best in last 6 months

### 1.6 Personal Best (7 metrics, rolling 6 months)
1. Highest revenue month
2. Highest conversion rate month
3. Highest upsell amount month
4. Most patients seen in a single day
5. Longest streak ever
6. Highest feedback score month
7. Most Google reviews in a single month

### 1.7 Targets (System-Wide Defaults — New Storage)
| Target | Base Value | Scope |
|--------|-----------|-------|
| Conversion % | 50% | System-wide |
| Avg Conversion Revenue | 15,000 | System-wide |
| Feedback Score | 9.5 | System-wide |
| Upselling Target | TBD | System-wide |
| Branch Revenue | Per-branch/month | Per-branch (daily × working days) |

All defined in revamped Targets screen (admin/centre_targets).

### 1.8 "vs Last Month" UX
- **KPI Cards:** MoM change indicator always visible; toggle adds last month value as secondary number
- **Charts:** Toggle OFF = single line; Toggle ON = dual overlaid (current=solid, last=dashed)
- **Toggle:** Small pill at top near date filter

---

## 2. Architecture Plan

### 2.1 New Files to Create

**Backend:**
- `app/Services/DoctorDashboard/DoctorDashboardService.php` — Main service (orchestrator)
- `app/Services/DoctorDashboard/RevenueCalculator.php` — Total Revenue + Avg Client Value
- `app/Services/DoctorDashboard/ConversionCalculator.php` — Conversion Rate
- `app/Services/DoctorDashboard/UpsellCalculator.php` — Upsell Revenue + Rate
- `app/Services/DoctorDashboard/MembershipCalculator.php` — Gold Memberships count
- `app/Services/DoctorDashboard/FeedbackCalculator.php` — Feedback Score
- `app/Services/DoctorDashboard/ProductRevenueCalculator.php` — Product Revenue
- `app/Services/DoctorDashboard/PatientReturnCalculator.php` — Return Rate
- `app/Services/DoctorDashboard/StreakCalculator.php` — Streak tracking
- `app/Services/DoctorDashboard/PersonalBestCalculator.php` — Personal Bests
- `app/Services/DoctorDashboard/BenchmarkCalculator.php` — Nationwide benchmarks
- `app/Services/DoctorDashboard/DoctorIdentifier.php` — Doctor role/allocation lookup
- `app/Http/Controllers/DoctorDashboardController.php` — Controller
- `app/Helpers/DoctorDashboardHelper.php` — Shared utilities

**Frontend:**
- `resources/views/admin/doctor_dashboard/index.blade.php` — Main view
- `resources/views/admin/doctor_dashboard/partials/` — Blade partials for each section
- `public/assets/js/pages/doctor_dashboard/dashboard.js` — Main JS
- `public/assets/css/doctor-dashboard.css` — Custom styles

**Admin (Targets Revamp + Google Reviews):**
- `resources/views/admin/centre_targets/index.blade.php` — Revamped (flat grid, inline edit)
- `public/assets/js/pages/admin_settings/centre-targets.js` — Revamped JS
- `resources/views/admin/google_reviews/index.blade.php` — New Google Reviews entry
- `public/assets/js/pages/admin_settings/google-reviews.js` — Google Reviews JS
- `app/Models/DoctorGoogleReview.php` — New model
- `app/Http/Controllers/Admin/GoogleReviewsController.php` — New controller

**Database Migrations:**
- `create_doctor_google_reviews_table` — (doctor_id, month, year, review_count, created_by, timestamps)
- `add_system_targets_to_centertarget_meta` — Add system-wide target fields (conversion_pct, avg_revenue, feedback_score, upselling_target)
- OR `create_system_targets_table` — Separate table for system-wide targets

### 2.2 Modified Files

- `app/Http/Controllers/HomeController.php` — Permission routing (doctor dashboard vs default)
- `app/Http/Controllers/Admin/CentreTargetsController.php` — Revamp for flat grid UI
- `app/Models/CentertargetMeta.php` — Add new target fields
- `app/Http/Controllers/InventoryReportsController.php` — Fix: use sale_price × qty
- `routes/web.php` — Add doctor dashboard routes
- `routes/api.php` — Add doctor dashboard API endpoints
- Navigation blade partials — Add doctor dashboard menu item

### 2.3 Route Structure

```
GET  /admin/doctor-dashboard          → DoctorDashboardController@index (blade view)
GET  /api/doctor-dashboard/kpis       → KPI data (JSON)
GET  /api/doctor-dashboard/charts     → Chart data (JSON)
GET  /api/doctor-dashboard/streak     → Streak data (JSON)
GET  /api/doctor-dashboard/personal-bests → Personal bests (JSON)
GET  /api/doctor-dashboard/appointments → Today's appointments (JSON)
GET  /api/doctor-dashboard/benchmarks → Benchmark data (JSON)

POST /api/admin/google-reviews/save   → Save Google review entries
GET  /api/admin/google-reviews        → Get Google review entries for month
```

---

## 3. Implementation Phases

### Phase 1: Foundation (Database + Backend Services)
1. Create migrations (google_reviews table, system targets)
2. Create DoctorIdentifier service (role lookup, allocation check)
3. Create RevenueCalculator (most complex — "last consultant" algorithm)
4. Create ConversionCalculator
5. Fix InventoryReportsController (sale_price × qty)

### Phase 2: Remaining Calculators
6. UpsellCalculator
7. MembershipCalculator
8. FeedbackCalculator
9. ProductRevenueCalculator
10. PatientReturnCalculator
11. StreakCalculator
12. PersonalBestCalculator
13. BenchmarkCalculator

### Phase 3: Admin Features
14. Revamp Centre Targets (flat grid, inline edit, system-wide targets)
15. Google Reviews admin page (simple grid, immediate save)

### Phase 4: Dashboard UI
16. DoctorDashboardController + routes
17. Permission routing in HomeController
18. Blade layout (sticky header, hero strip, KPI groups)
19. ApexCharts integration
20. "vs Last Month" toggle
21. Mobile responsive polish

### Phase 5: Integration & Testing
22. End-to-end data verification
23. Edge case handling (no data, division by zero, missing targets)
24. Performance optimization (query efficiency, caching)

---

## 4. Key Technical Notes

### Total Revenue Query Approach (Pseudocode)
```sql
-- Step 1: Find each patient's last consulting doctor
WITH last_consultants AS (
    SELECT patient_id, doctor_id,
           ROW_NUMBER() OVER (PARTITION BY patient_id ORDER BY scheduled_date DESC, scheduled_time DESC) as rn
    FROM appointments
    WHERE appointment_type_id = 1
      AND base_appointment_status_id IN (arrived_id, converted_id)
)
-- Step 2: Get this doctor's patient pool
SELECT lc.patient_id FROM last_consultants lc
WHERE lc.rn = 1 AND lc.doctor_id = :doctorId

-- Step 3: Sum payments for those patients in date range
SELECT SUM(pa.cash_amount)
FROM package_advances pa
JOIN packages p ON pa.package_id = p.id
WHERE p.patient_id IN (:patientPool)
  AND pa.created_at BETWEEN :startDate AND :endDate
```

### Gold Membership Identification
```sql
-- Find Gold membership type IDs (parent + renewals)
SELECT id FROM membership_types
WHERE name LIKE '%Gold%'
   OR parent_id IN (SELECT id FROM membership_types WHERE name LIKE '%Gold%')
```

### Benchmark Minimum Threshold
Doctors with <5 arrived consultations in the period are excluded from benchmark calculations.

---

## 5. Side Fix: Inventory Report

**File:** `app/Http/Controllers/InventoryReportsController.php`  
**Change:** Replace `$detail->quantity * ($detail->product->sale_price ?? 0)` with `$detail->quantity * ($detail->sale_price ?? 0)`  
**Reason:** `sale_price` on `order_details` already has the per-line discount applied. Using `product.sale_price` ignores discounts.
