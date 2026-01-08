# Patient View Screen Enhancements

## Overview

This document outlines the proposed enhancements for the Patient View Screen to improve functionality and user experience. The changes focus on three main areas: Profile Tab, Vouchers Tab, and Documents Tab.

---

## Table of Contents

1. [Profile Tab Enhancements](#1-profile-tab-enhancements)
2. [Vouchers Tab Enhancements](#2-vouchers-tab-enhancements)
3. [Documents Tab Enhancements](#3-documents-tab-enhancements)
4. [Technical Implementation Details](#4-technical-implementation-details)
5. [UX Improvement Suggestions](#5-ux-improvement-suggestions)
6. [Database Changes Required](#6-database-changes-required)
7. [Files to be Modified](#7-files-to-be-modified)

---

## 1. Profile Tab Enhancements

### Current State
- Profile tab displays patient information in read-only mode
- Fields shown: Name, Patient ID, Phone, Email, Gender, Membership Type, Membership Expiry
- No edit capability from this view

### Proposed Changes

#### 1.1 Add Edit Button
- Add an "Edit" button in the Profile tab header/toolbar
- Button should only be visible to users with `patients_edit` permission
- Clicking the button opens the same edit modal used in the main patients datatable

#### 1.2 Implementation Details

**Frontend Changes:**
```javascript
// Add edit button to profile tab toolbar
<button type="button" 
    class="btn btn-sm btn-primary mr-2" 
    id="edit-patient-profile-btn"
    onclick="editPatientFromProfile();">
    <i class="la la-pencil"></i> Edit
</button>

// Function to open edit modal
function editPatientFromProfile() {
    let patientId = patientCardID; // Already available in patient card context
    let editUrl = route('admin.patients.edit', {id: patientId});
    // Open edit modal with patient data
    openEditPatientModal(editUrl, patientId);
}
```

**Backend Changes:**
- Reuse existing `edit()` and `update()` methods from `PatientsController`
- No new API endpoints required

---

## 2. Vouchers Tab Enhancements

### Current State
- Displays basic voucher information: Name, Amount, Start Date, End Date, Services, Created At
- No consumed amount or balance information
- No voucher usage history

### Proposed Changes

#### 2.1 Display Consumed Amount and Balance
Add two new columns to the vouchers datatable:
- **Consumed Amount**: Total amount used from the voucher
- **Balance**: Remaining amount available

**Calculation:**
```
Consumed Amount = Total Amount - Current Amount (from user_vouchers table)
Balance = Current Amount (from user_vouchers table)
```

#### 2.2 Voucher Usage History Modal
When clicking on a voucher row, show a modal with complete usage history:

| Field | Description |
|-------|-------------|
| Plan/Package ID | The plan where voucher was applied |
| Service Name | The service on which voucher was used |
| Amount Deducted | How much was deducted in this transaction |
| Balance After | Remaining balance after this deduction |
| Applied Date | Date when voucher was applied |

#### 2.3 Updated Datatable Columns

```javascript
var table_columns = [
    { field: 'name', title: 'Voucher', width: 120 },
    { field: 'total_amount', title: 'Total Amount', width: 100 },
    { field: 'consumed_amount', title: 'Consumed', width: 100 },
    { field: 'balance', title: 'Balance', width: 100 },
    { field: 'startDate', title: 'Start Date', width: 100 },
    { field: 'endDate', title: 'End Date', width: 100 },
    { field: 'service', title: 'Services', width: 'auto' },
    { field: 'actions', title: 'Actions', width: 80 }
];
```

#### 2.4 Voucher History Modal Content

```html
<!-- Voucher Summary Card -->
<div class="card card-custom bg-light mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>Voucher Name:</strong><br>
                <span id="voucher_name"></span>
            </div>
            <div class="col-md-3">
                <strong>Total Amount:</strong><br>
                <span id="voucher_total" class="text-primary font-weight-bold"></span>
            </div>
            <div class="col-md-3">
                <strong>Consumed:</strong><br>
                <span id="voucher_consumed" class="text-danger"></span>
            </div>
            <div class="col-md-3">
                <strong>Balance:</strong><br>
                <span id="voucher_balance" class="text-success font-weight-bold"></span>
            </div>
        </div>
    </div>
</div>

<!-- Usage History Table -->
<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>#</th>
            <th>Plan ID</th>
            <th>Service</th>
            <th>Amount Deducted</th>
            <th>Balance After</th>
            <th>Applied Date</th>
        </tr>
    </thead>
    <tbody id="voucher_history_body">
        <!-- Dynamic content -->
    </tbody>
</table>
```

---

## 3. Documents Tab Enhancements

### Current State
- Displays document name, patient name, created date, and actions
- No visual indication of file type
- Actions available: Edit, View, Delete

### Proposed Changes

#### 3.1 File Type Icons in Datatable
Add a new column showing file type icons:

| File Type | Icon | Description |
|-----------|------|-------------|
| Images (jpg, jpeg, png, gif, webp, bmp) | 📷 Image icon | Clickable to download |
| PDF files | 📄 PDF icon (red) | Clickable to download |
| Word documents (doc, docx) | 📝 Word icon (blue) | Clickable to download |
| Excel files (xls, xlsx) | 📊 Excel icon (green) | Clickable to download |
| Other files | 📎 Generic file icon | Clickable to download |

#### 3.2 Updated Datatable Columns

```javascript
var table_columns = [
    {
        field: 'file_type',
        title: 'Type',
        width: 60,
        textAlign: 'center',
        template: function(data) {
            return getFileTypeIcon(data.url, data.full_url);
        }
    },
    { field: 'name', title: 'Name', width: 'auto' },
    { field: 'patient.name', title: 'Patient Name', width: 'auto', sortable: false },
    { field: 'created_at', title: 'Created At', width: 'auto' },
    { field: 'actions', title: 'Actions', sortable: false, width: 100 }
];
```

#### 3.3 File Type Icon Function

```javascript
function getFileTypeIcon(fileUrl, fullUrl) {
    if (!fileUrl) return '<i class="la la-file text-muted"></i>';
    
    let ext = fileUrl.split('.').pop().toLowerCase();
    let downloadUrl = fullUrl || fileUrl;
    let iconHtml = '';
    
    // Image files
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext)) {
        iconHtml = `<a href="${downloadUrl}" download class="file-icon-link" title="Download Image">
            <img src="/assets/media/svg/files/image.svg" alt="Image" width="32" height="32">
        </a>`;
    }
    // PDF files
    else if (ext === 'pdf') {
        iconHtml = `<a href="${downloadUrl}" download class="file-icon-link" title="Download PDF">
            <img src="/assets/media/svg/files/pdf.svg" alt="PDF" width="32" height="32">
        </a>`;
    }
    // Word documents
    else if (['doc', 'docx'].includes(ext)) {
        iconHtml = `<a href="${downloadUrl}" download class="file-icon-link" title="Download Word Document">
            <img src="/assets/media/svg/files/doc.svg" alt="Word" width="32" height="32">
        </a>`;
    }
    // Excel files
    else if (['xls', 'xlsx'].includes(ext)) {
        iconHtml = `<a href="${downloadUrl}" download class="file-icon-link" title="Download Excel">
            <img src="/assets/media/svg/files/xls.svg" alt="Excel" width="32" height="32">
        </a>`;
    }
    // Other files
    else {
        iconHtml = `<a href="${downloadUrl}" download class="file-icon-link" title="Download File">
            <img src="/assets/media/svg/files/file.svg" alt="File" width="32" height="32">
        </a>`;
    }
    
    return iconHtml;
}
```

#### 3.4 CSS Styling for File Icons

```css
.file-icon-link {
    display: inline-block;
    transition: transform 0.2s ease;
}

.file-icon-link:hover {
    transform: scale(1.1);
}

.file-icon-link img {
    cursor: pointer;
}
```

---

## 4. Technical Implementation Details

### 4.1 Backend API Changes

#### Voucher Datatable Enhancement
**File:** `app/Http/Controllers/Admin/PatientsController.php`

```php
public function voucherDatatable($id, Request $request)
{
    // ... existing code ...
    
    $transformedVouchers = $vouchers->map(function ($voucher) use ($id) {
        $userVoucher = $voucher->userVouchers->first();
        
        // Calculate consumed and balance
        $totalAmount = $userVoucher->total_amount ?? 0;
        $currentBalance = $userVoucher->amount ?? 0;
        $consumedAmount = $totalAmount - $currentBalance;
        
        return [
            'id' => $voucher->id,
            'user_voucher_id' => $userVoucher->id,
            'name' => $voucher->name,
            'total_amount' => number_format($totalAmount, 2),
            'consumed_amount' => number_format($consumedAmount, 2),
            'balance' => number_format($currentBalance, 2),
            'startDate' => $voucher->start,
            'endDate' => $voucher->end,
            'service' => $serviceNames,
            'created_at' => $voucher->created_at,
        ];
    });
    
    // ... rest of code ...
}
```

#### New Voucher History API Endpoint
**Add to routes/api.php:**
```php
Route::get('/patients/{patientId}/voucher-history/{userVoucherId}', [PatientController::class, 'getVoucherHistory'])
    ->name('api.patients.voucher-history');
```

**Controller Method:**
```php
public function getVoucherHistory($patientId, $userVoucherId)
{
    $userVoucher = UserVouchers::with(['user', 'voucher'])->findOrFail($userVoucherId);
    
    // Get all package_vouchers entries for this user and voucher
    $usageHistory = PackageVouchers::where('user_id', $patientId)
        ->where('voucher_id', $userVoucher->voucher_id)
        ->with(['package', 'service'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($item) {
            return [
                'package_id' => $item->package_id,
                'service_name' => $item->service ? $item->service->name : 'N/A',
                'amount_deducted' => $item->amount,
                'applied_date' => $item->created_at->format('M d, Y h:i A'),
            ];
        });
    
    return ApiHelper::apiResponse($this->success, 'Voucher history retrieved', true, [
        'voucher_name' => $userVoucher->voucher->name ?? 'N/A',
        'total_amount' => $userVoucher->total_amount,
        'consumed_amount' => $userVoucher->total_amount - $userVoucher->amount,
        'balance' => $userVoucher->amount,
        'history' => $usageHistory,
    ]);
}
```

### 4.2 Frontend Changes

#### Files to Modify:
1. `resources/views/admin/patients/card/profile/personal-info.blade.php` - Add edit button
2. `resources/views/admin/patients/card/vouchers/index.blade.php` - Add history modal
3. `public/assets/js/pages/patients/voucher-form.js` - Update columns, add history function
4. `public/assets/js/pages/patients/document-form.js` - Add file type icons
5. `resources/views/admin/patients/card/preview.blade.php` - Add edit button to toolbar

---

## 5. UX Improvement Suggestions

### 5.1 Profile Tab Improvements

| Suggestion | Description | Priority |
|------------|-------------|----------|
| **Quick Actions Bar** | Add a floating action bar with common actions (Edit, Print, Export) | High |
| **Profile Completeness Indicator** | Show a progress bar indicating how complete the patient profile is | Medium |
| **Last Activity Display** | Show when the patient last visited or had an appointment | Medium |
| **Quick Contact Buttons** | Add WhatsApp, Call, Email buttons for quick communication | High |
| **Photo Upload from Profile** | Allow direct photo upload without switching tabs | Low |

### 5.2 Vouchers Tab Improvements

| Suggestion | Description | Priority |
|------------|-------------|----------|
| **Visual Balance Indicator** | Show a progress bar for voucher usage (consumed vs remaining) | High |
| **Expiry Warning** | Highlight vouchers expiring within 30 days with warning color | High |
| **Quick Apply Button** | Add button to quickly apply voucher to new service | Medium |
| **Export Voucher Statement** | Allow exporting voucher usage history as PDF | Medium |
| **Voucher Status Badges** | Show badges: Active, Expired, Fully Used, Partially Used | High |

### 5.3 Documents Tab Improvements

| Suggestion | Description | Priority |
|------------|-------------|----------|
| **Thumbnail Preview** | Show small thumbnail for image files in the table | High |
| **Drag & Drop Upload** | Allow drag and drop file upload | Medium |
| **Document Categories** | Add categories (Medical, ID, Consent, Reports) for organization | High |
| **Bulk Download** | Allow selecting multiple documents for bulk download as ZIP | Medium |
| **Document Preview Modal** | Preview images and PDFs in a modal without downloading | High |
| **Search/Filter by Type** | Add filter to show only specific file types | Medium |

### 5.4 General Patient View Improvements

| Suggestion | Description | Priority |
|------------|-------------|----------|
| **Sticky Navigation** | Make the tab navigation sticky when scrolling | Medium |
| **Tab Badges** | Show count badges on tabs (e.g., "Documents (5)") | High |
| **Recent Activity Timeline** | Add a timeline widget showing recent patient activities | Medium |
| **Print Patient Card** | Add option to print a summary patient card | Low |
| **Notes/Comments Section** | Add a notes section for internal staff comments | Medium |
| **Appointment Quick Book** | Add quick appointment booking from patient view | High |
| **Financial Summary Widget** | Show total spent, outstanding balance, membership status | High |
| **Dark Mode Support** | Ensure all components work well in dark mode | Low |

### 5.5 Mobile Responsiveness

| Suggestion | Description | Priority |
|------------|-------------|----------|
| **Collapsible Sidebar** | Make patient info sidebar collapsible on mobile | High |
| **Swipeable Tabs** | Allow swiping between tabs on mobile devices | Medium |
| **Touch-Friendly Actions** | Larger touch targets for action buttons | High |
| **Responsive Tables** | Use card layout for tables on small screens | Medium |

---

## 6. Database Changes Required

### 6.1 For Voucher History Enhancement

**No schema changes required** - The existing tables have all necessary data:
- `user_vouchers` - Contains `total_amount` and `amount` (balance)
- `package_vouchers` - Contains usage history with `amount`, `created_at`
- `packages` - Contains plan information
- `services` - Contains service names

### 6.2 Optional Enhancement: Voucher Transaction Log

For more detailed tracking, consider adding a new table:

```sql
CREATE TABLE voucher_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_voucher_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    voucher_id BIGINT UNSIGNED NOT NULL,
    package_id BIGINT UNSIGNED NULL,
    service_id BIGINT UNSIGNED NULL,
    transaction_type ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_voucher_id) REFERENCES user_vouchers(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_voucher (user_voucher_id),
    INDEX idx_user_id (user_id)
);
```

---

## 7. Files to be Modified

### Frontend Files

| File | Changes |
|------|---------|
| `resources/views/admin/patients/card/preview.blade.php` | Add edit button to profile toolbar |
| `resources/views/admin/patients/card/profile/personal-info.blade.php` | Add edit button styling |
| `resources/views/admin/patients/card/vouchers/index.blade.php` | Add voucher history modal |
| `resources/views/admin/patients/card/documents/index.blade.php` | Update for file type icons |
| `public/assets/js/pages/patients/voucher-form.js` | Add columns, history function |
| `public/assets/js/pages/patients/document-form.js` | Add file type icon template |
| `public/assets/js/pages/patients/patient-card.js` | Add edit patient function |
| `public/assets/css/custom.css` | Add file icon styles |

### Backend Files

| File | Changes |
|------|---------|
| `app/Http/Controllers/Admin/PatientsController.php` | Update voucherDatatable |
| `app/Http/Controllers/Api/PatientController.php` | Add getVoucherHistory method |
| `routes/api.php` | Add voucher history route |

### Asset Files (Icons)

Ensure these SVG icons exist in `public/assets/media/svg/files/`:
- `image.svg` - For image files
- `pdf.svg` - For PDF files
- `doc.svg` - For Word documents
- `xls.svg` - For Excel files
- `file.svg` - For generic files

---

## Implementation Priority

| Phase | Feature | Effort | Priority |
|-------|---------|--------|----------|
| 1 | Profile Edit Button | Low | High |
| 1 | Voucher Consumed/Balance Columns | Low | High |
| 1 | Document File Type Icons | Medium | High |
| 2 | Voucher History Modal | Medium | High |
| 2 | Document Thumbnail Preview | Medium | Medium |
| 3 | UX Improvements (badges, indicators) | Medium | Medium |
| 3 | Mobile Responsiveness | High | Medium |

---

## Testing Checklist

- [ ] Profile edit button visible only with `patients_edit` permission
- [ ] Edit modal opens with correct patient data
- [ ] Patient data updates correctly from profile view
- [ ] Voucher consumed amount calculated correctly
- [ ] Voucher balance matches `user_vouchers.amount`
- [ ] Voucher history modal shows all usage records
- [ ] Correct file type icons displayed for all file types
- [ ] File download works when clicking icons
- [ ] All features work on mobile devices
- [ ] Permissions respected across all new features

---

*Document Version: 1.0*
*Created: January 2026*
*Author: Development Team*
