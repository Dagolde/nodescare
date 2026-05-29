# NodesCare Hospital Management System — User Guide

**Version:** 1.0  
**Live URL:** https://nodescare.nodestech.cloud  
**Mobile App:** Install the NodesCare APK on Android

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Super Admin — Platform Setup](#2-super-admin--platform-setup)
3. [Hospital Admin — Hospital Setup](#3-hospital-admin--hospital-setup)
4. [HOD (Head of Department) — Web Dashboard](#4-hod-head-of-department--web-dashboard)
5. [Mobile App — Staff Login](#5-mobile-app--staff-login)
6. [Front Desk — Patient Registration](#6-front-desk--patient-registration)
7. [Nurse / Triage — Vitals Recording](#7-nurse--triage--vitals-recording)
8. [Doctor — Consultation & Routing](#8-doctor--consultation--routing)
9. [Laboratory — Billing & Results](#9-laboratory--billing--results)
10. [Pharmacy — Drug Billing & Dispensing](#10-pharmacy--drug-billing--dispensing)
11. [Accounts — Invoice & Payment](#11-accounts--invoice--payment)
12. [Complete Patient Journey Example](#12-complete-patient-journey-example)

---

## 1. System Overview

NodesCare is a multi-tenant hospital management system. It has two parts:

| Part | Who Uses It | Access |
|------|-------------|--------|
| **Web Dashboard** | Super Admin, Hospital Admin, HOD | Browser |
| **Mobile App** | All department staff | Android phone |

### User Roles

| Role | Description |
|------|-------------|
| Super Admin | Creates hospitals on the platform |
| Hospital Admin | Manages departments, staff, workflows |
| HOD | Head of Department — views stats, reports, manages staff |
| Front Desk Officer | Registers patients |
| Nurse / Triage | Records patient vitals |
| Doctor | Consults patients, routes to lab/pharmacy/admission |
| Lab Technician | Bills lab tests, enters results |
| Pharmacist | Bills drugs, confirms dispensing |
| Accountant | Creates invoices, records payments |

---

## 2. Super Admin — Platform Setup

**URL:** https://nodescare.nodestech.cloud/super-admin/login

> 📸 **[Screenshot: Super Admin Login Page]**

### Step 1 — Login as Super Admin

1. Go to the Super Admin login URL
2. Enter your Super Admin email and password
3. Click **Login**

> 📸 **[Screenshot: Super Admin Dashboard]**

### Step 2 — Create a Hospital (Tenant)

1. Click **Hospitals** in the left sidebar
2. Click **Create Hospital**
3. Fill in:
   - Hospital Name
   - Subdomain / Identifier
   - Select a Plan (Basic / Standard / Premium)
   - Admin Email (this becomes the Hospital Admin login)
   - Admin Password
4. Click **Create**

> 📸 **[Screenshot: Create Hospital Form]**

The hospital is now live. The Hospital Admin can log in at:  
`https://nodescare.nodestech.cloud/hospital-admin/login`

### Step 3 — Manage Plans & Wallet

- Click **Plans** to view/edit subscription plans
- Click **Wallets** to view hospital wallet balances and deduction history

> 📸 **[Screenshot: Plans & Wallets]**

---

## 3. Hospital Admin — Hospital Setup

**URL:** https://nodescare.nodestech.cloud/hospital-admin/login

> 📸 **[Screenshot: Hospital Admin Login]**

### Step 1 — Login

1. Enter the Hospital Admin email and password created by Super Admin
2. Click **Login**

> 📸 **[Screenshot: Hospital Admin Dashboard]**

---

### Step 2 — Create Departments

Departments are the building blocks. You must create them before adding staff.

1. Click **Departments** in the sidebar
2. Click **Add Department**
3. Fill in:
   - Department Name (e.g. "General OPD", "Laboratory", "Pharmacy")
   - Department Code (e.g. "OPD", "LAB", "PHARM")
   - Department Type — choose from:
     - `general` — for OPD, wards
     - `laboratory` — for lab
     - `pharmacy` — for pharmacy
     - `accounts` — for accounts/billing
     - `front_desk` — for reception
4. Click **Save**

> 📸 **[Screenshot: Create Department Form]**

> ⚠️ **Important:** You must create at least one department of type `accounts` for billing to work.

---

### Step 3 — Create Staff

1. Click **Staff** in the sidebar
2. Click **Add Staff**
3. Fill in:
   - Full Name
   - Email
   - Password
   - Role — select from the dropdown:
     - `front_desk_officer`
     - `nurse` / `triage`
     - `doctor`
     - `lab_technician`
     - `pharmacist`
     - `accountant`
     - `hod`
   - Department — assign to the correct department
   - **Is HOD?** — tick this if the staff member is the Head of Department
4. Click **Save**

> 📸 **[Screenshot: Create Staff Form]**

---

### Step 4 — Create Workflow Template

A workflow template defines the order patients move through departments.

1. Click **Workflow Templates** in the sidebar
2. Click **Create Template**
3. Give it a name (e.g. "Standard OPD Flow")
4. Add steps in order:
   - Step 1: Front Desk
   - Step 2: Triage / Nursing
   - Step 3: Doctor
   - Step 4: (optional) Laboratory
   - Step 5: (optional) Pharmacy
   - Step 6: Front Desk (discharge)
5. Click **Save**

> 📸 **[Screenshot: Workflow Template Builder]**

> 💡 The workflow template is assigned when a patient is registered. It controls the automatic routing of patients between departments.

---

### Step 5 — View Reports & Audit Logs

- **Department Reports** — patient counts, average wait times per department
- **Financial Reports** — revenue, invoices, payment methods
- **Audit Logs** — every action taken by every user

> 📸 **[Screenshot: Financial Reports with Date Filter]**

---

## 4. HOD (Head of Department) — Web Dashboard

**URL:** https://nodescare.nodestech.cloud/department/dashboard

> 📸 **[Screenshot: HOD Dashboard]**

The HOD dashboard is only accessible to staff with `is_hod = true`.

### Dashboard Sections

| Section | What It Shows |
|---------|---------------|
| **Queue Statistics** | Current patients in queue, average wait time |
| **Patient Flow Chart** | 7-day patient volume graph |
| **Staff Workload** | How many patients each staff member handled |
| **Recent Activity** | Latest events in the department |

### Navigation (Left Sidebar)

| Menu Item | Purpose |
|-----------|---------|
| Dashboard | Stats overview |
| Queue | View current patient queue |
| Staff Management | View/manage department staff |
| Lab Results | View all lab results for the department |
| Prescriptions | View all prescriptions |
| Reports | Generate and export reports |
| Settings | Department info, working hours, notifications |

---

### Generating a Report

1. Click **Reports** in the sidebar
2. Select report type: Patient Summary / Financial / Staff Performance
3. Set date range (From — To)
4. Click **Generate Report**
5. To export: click **Export PDF** or **Export Excel**

> 📸 **[Screenshot: Reports Page with Generated Data]**

---

### Settings

1. Click **Settings**
2. Update department information (name, description)
3. Set working hours for each day of the week
4. Configure notification preferences (email, in-app)
5. Click **Save**

> 📸 **[Screenshot: Settings Page]**

---

## 5. Mobile App — Staff Login

> 📸 **[Screenshot: Mobile App Login Screen]**

1. Open the NodesCare app on your Android phone
2. Enter your **Email** and **Password** (created by Hospital Admin)
3. Tap **Login**

The app automatically shows the correct dashboard based on your role.

---

## 6. Front Desk — Patient Registration

> 📸 **[Screenshot: Front Desk Dashboard]**

The Front Desk dashboard has three action cards:
- **New Patient** — register a brand new patient
- **Returning Patient** — find an existing patient
- **Check Appointments** — view all booked appointments

---

### Registering a New Patient

1. Tap **New Patient** or the **Register Patient** button
2. Fill in the patient form:
   - Full Name
   - Date of Birth
   - Gender
   - Phone Number
   - Address (optional)
   - Chief Complaint (reason for visit)
   - Next of Kin Name & Phone (optional)
   - HMO Provider & Policy Number (optional)
3. Select a **Workflow Template** (the patient journey path)
4. Tap **Register**

> 📸 **[Screenshot: Patient Registration Form]**

The patient is now in the system and automatically routed to the first department in the workflow (usually Triage/Nursing).

---

### Returning Patient

1. Tap **Returning Patient**
2. Search by patient number or name
3. Select the patient from the results
4. The patient is re-registered and routed to the first step

> 📸 **[Screenshot: Patient Search Screen]**

---

### Checking Appointments

1. Tap **Check Appointments**
2. View all upcoming and past appointments
3. Each card shows: patient name, date, time, doctor, department, status

> 📸 **[Screenshot: Appointments List Screen]**

---

## 7. Nurse / Triage — Vitals Recording

> 📸 **[Screenshot: Nurse Dashboard with Patient Queue]**

1. The nurse sees a list of patients in their queue
2. Tap **Vitals** on a patient card
3. Fill in the vitals form:
   - Blood Pressure (e.g. 120/80)
   - Temperature (°C)
   - Pulse Rate (bpm)
   - Respiratory Rate
   - Oxygen Saturation (%)
   - Weight (kg)
   - Height (cm) — BMI is auto-calculated
   - Blood Glucose
   - Notes (optional)
4. Tap **Save Vitals**

> 📸 **[Screenshot: Vitals Recording Form]**

The patient is then routed to the Doctor automatically (or the nurse can tap **Complete** to advance the workflow).

---

## 8. Doctor — Consultation & Routing

> 📸 **[Screenshot: Doctor Dashboard]**

The Doctor dashboard shows:
- **Book Appointment** — schedule a future appointment
- **Check Appointments** — view all appointments
- **Patient Queue** — current patients waiting

---

### Consulting a Patient

1. Tap **Consult** on a patient card
2. The consultation screen opens with tabs:
   - **Details** — patient info
   - **Vitals** — recorded vitals
   - **Lab Results** — any lab results
   - **Journey** — full patient history

> 📸 **[Screenshot: Doctor Consultation Screen]**

3. Fill in the consultation fields:
   - **Complaint & History** — patient's presenting complaint
   - **Examination Findings** — physical examination notes
   - **Diagnosis** — your diagnosis
   - **Prescription Notes** — drugs to prescribe (if routing to pharmacy)
   - **Investigation Notes** — tests to order (if routing to lab)

> 📸 **[Screenshot: Consultation Form Fields]**

---

### Routing Options

After consultation, tap one of the action buttons at the bottom:

| Button | Action |
|--------|--------|
| **Add Note** | Save notes without routing |
| **Complete** | Route to next step in workflow |
| **Pharmacy** | Route to pharmacy (for drug prescription) |
| **Lab** | Route to laboratory (for investigations) |
| **Admit** | Route to accounts for admission payment |

> 📸 **[Screenshot: Doctor Action Buttons]**

> 💡 When you tap **Pharmacy** or **Lab**, the patient first goes to **Accounts** to pay, then returns to pharmacy/lab after payment.

---

### Booking an Appointment

1. Tap **Book Appointment** or the calendar icon
2. Enter the patient number and tap **Search**
3. Select the appointment date and time
4. Add notes (optional)
5. Tap **Book Appointment**

> 📸 **[Screenshot: Appointment Booking Form]**

---

## 9. Laboratory — Billing & Results

> 📸 **[Screenshot: Lab Dashboard with Patient Queue]**

The Lab dashboard shows patients in the lab queue with action buttons.

---

### Step 1 — Bill the Tests

When a patient arrives from the doctor:

1. Tap **Bill Tests** on the patient card
2. Add each test as a line item:
   - Test Name (e.g. FBC, Malaria RDT, X-Ray)
   - Unit Price
   - Quantity
3. Tap **Submit Bill**

> 📸 **[Screenshot: Lab Billing Form]**

The patient is automatically sent to **Accounts** to pay. The patient card shows **"Awaiting Payment"**.

---

### Step 2 — Enter Results (After Payment)

When the patient returns from Accounts after paying:

1. The patient card shows a green **"Bill Paid ✓"** badge
2. Tap **Enter Results**
3. The screen shows the tests requested by the doctor
4. For each test, fill in:
   - Test Name (pre-filled from doctor's request)
   - Result (e.g. "Haemoglobin: 12.5 g/dL, WBC: 6.2 x10³/µL")
   - Reference Range (optional, e.g. "12.0–16.0 g/dL")
5. Tap **+ Add Another Test** to add more tests
6. Tap **Submit X Results**

> 📸 **[Screenshot: Lab Results Entry — Per Test Cards]**

After submitting, the patient is automatically routed back to the doctor's department.

---

## 10. Pharmacy — Drug Billing & Dispensing

> 📸 **[Screenshot: Pharmacy Dashboard]**

---

### Step 1 — Bill the Drugs

When a patient arrives from the doctor with a prescription:

1. Tap **Bill Drugs** on the patient card
2. Add each drug as a line item:
   - Drug Name
   - Unit Price
   - Quantity
3. Tap **Submit Bill**

> 📸 **[Screenshot: Pharmacy Billing Form]**

The patient is sent to **Accounts** to pay.

---

### Step 2 — Confirm Dispense (After Payment)

When the patient returns after paying:

1. The patient card shows **"Bill Paid ✓"** and a **"Confirm Dispense"** button
2. Tap **Confirm Dispense**
3. Confirm in the dialog
4. The patient is routed to the next step (e.g. Front Desk for discharge)

> 📸 **[Screenshot: Confirm Dispense Dialog]**

---

## 11. Accounts — Invoice & Payment

> 📸 **[Screenshot: Accounts Dashboard with Pending Payments]**

The Accounts dashboard shows all patients waiting for payment.

Each patient card shows:
- Patient name and number
- Payment reason (e.g. "Lab Investigation Payment", "Pharmacy/Drugs Payment", "Admission Payment")
- Time waiting

---

### Creating an Invoice

1. Tap a patient card
2. The invoice creation screen opens with pre-filled items (from lab/pharmacy billing)
3. Review the items and amounts
4. Tap **Create Invoice**

> 📸 **[Screenshot: Invoice Creation Screen]**

---

### Recording a Payment

1. After creating the invoice, tap **Record Payment**
2. Enter:
   - Amount paid
   - Payment method (Cash / Card / Transfer / HMO / Other)
   - Reference number (optional)
3. Tap **Record Payment**

> 📸 **[Screenshot: Payment Recording Form]**

When the invoice is fully paid, the patient is **automatically routed** back to the originating department (lab or pharmacy).

---

## 12. Complete Patient Journey Example

Here is a full example of a patient visit from start to finish:

```
1. FRONT DESK
   └─ Registers patient "John Doe" with chief complaint "Fever and headache"
   └─ Assigns workflow template "Standard OPD"
   └─ Patient routed → TRIAGE

2. TRIAGE / NURSE
   └─ Records vitals: BP 120/80, Temp 38.5°C, Pulse 92bpm
   └─ Taps "Complete"
   └─ Patient routed → DOCTOR

3. DOCTOR
   └─ Reviews vitals
   └─ Fills: Complaint — "3 days fever, headache"
   └─ Fills: Examination — "Febrile, no neck stiffness"
   └─ Fills: Investigation Notes — "FBC, Malaria RDT"
   └─ Taps "Lab"
   └─ Patient routed → ACCOUNTS (for lab payment)

4. ACCOUNTS
   └─ Creates invoice for lab tests
   └─ Patient pays ₦5,000 cash
   └─ Invoice marked PAID
   └─ Patient auto-routed → LABORATORY

5. LABORATORY
   └─ Patient card shows "Bill Paid ✓"
   └─ Taps "Enter Results"
   └─ Enters FBC result: "Haemoglobin 11.2 g/dL, WBC 12.4 x10³/µL"
   └─ Enters Malaria RDT: "Positive (P. falciparum)"
   └─ Taps "Submit 2 Results"
   └─ Patient auto-routed → DOCTOR

6. DOCTOR (second visit)
   └─ Reviews lab results in patient detail
   └─ Fills: Diagnosis — "Malaria"
   └─ Fills: Prescription — "Artemether-Lumefantrine 80/480mg x6 tabs"
   └─ Taps "Pharmacy"
   └─ Patient routed → ACCOUNTS (for drug payment)

7. ACCOUNTS
   └─ Creates invoice for drugs
   └─ Patient pays ₦3,500 cash
   └─ Patient auto-routed → PHARMACY

8. PHARMACY
   └─ Patient card shows "Bill Paid ✓" + "Confirm Dispense"
   └─ Dispenses drugs to patient
   └─ Taps "Confirm Dispense"
   └─ Patient routed → FRONT DESK

9. FRONT DESK
   └─ Patient discharged
   └─ Visit complete ✓
```

---

## Quick Reference — URL Summary

| Portal | URL |
|--------|-----|
| Super Admin | `/super-admin/login` |
| Hospital Admin | `/hospital-admin/login` |
| HOD Dashboard | `/department/dashboard` |
| HOD Queue | `/department/queue` |
| HOD Reports | `/department/reports` |
| HOD Settings | `/department/settings` |

---

## Quick Reference — Mobile App Roles

| Role | Main Actions |
|------|-------------|
| Front Desk | Register patients, check appointments |
| Nurse | Record vitals, complete step |
| Doctor | Consult, route to lab/pharmacy/admit, book appointments |
| Lab Tech | Bill tests, enter per-test results |
| Pharmacist | Bill drugs, confirm dispense |
| Accountant | Create invoices, record payments |

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Patient not appearing in queue | Check patient status — may be `pending_payment` (at Accounts) |
| "Bill Paid" not showing | Refresh the queue; cache clears within 30 seconds |
| Journey tab error | Update to latest app version |
| HOD dashboard blank | Ensure user has `is_hod = true` in Hospital Admin → Staff |
| Lab results not submitting | Ensure all test name and result fields are filled |
| Route to lab/pharmacy fails | Ensure an Accounts department exists with type = `accounts` |

---

*Documentation generated for NodesCare v1.0 — nodescare.nodestech.cloud*
