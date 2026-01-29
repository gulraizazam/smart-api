
### Table: `appointments_daily_stats`

This table is populated by a cron job (`php artisan appointments:daily-stats`) that runs daily.

### Cron Job Logic (`AppointmentsDailyStatsCron`)

1. Fetches all **consultancy** type appointments scheduled for **today and tomorrow**
2. For each appointment, creates/updates a record using `updateOrCreate`:
   - **Match on**: `appointment_id` + `scheduled_date`
   - **Stores**: `centre_id`, `user_id`, `appointment_status_id`, `cron_current_date`

**Important**: If an appointment is rescheduled to a new date, a **new record** is created (because `scheduled_date` is part of the match key). The old record remains in the table.


### Set-Based Counting Algorithm

The system uses a **set-based counting** approach to handle rescheduled appointments:

1. **Fetch all records** from `appointments_daily_stats` for the date range
2. **Group records** by `centre_id` and `appointment_id`
3. **For each appointment**, divide records into **sets of 2**:
   - `setCount = ceil(recordCount / 2)`
   - Each set = **1 total count**
4. **For each set**, check if any record has an arrived/converted status:
   - If yes → count 1 as arrived
   - If the arrived record was created by an FDM user → count 1 as walk-in

### Example

Appointment 123 rescheduled twice within the same month:

| appointment_id | scheduled_date | status |
|----------------|----------------|--------|
| 123 | 2026-01-30 | Pending |
| 123 | 2026-01-31 | Arrived |

**Calculation for "Last Month" (Jan 1-31):**
- Records for appointment 123: 2
- Sets: `ceil(2/2) = 1`
- **Total = 1** (not 2)
- **Arrived = 1** (set contains an arrived status)

---

