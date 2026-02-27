
# 09_UI_UX_Specification.md

**Anka Shipping & Logistics Management System (ASLMS)**

This document defines the user interface structure, layout hierarchy, interaction patterns, and usability standards for the system.

The goal is:

* Operational clarity
* Financial transparency
* Reduced cognitive load
* Role-based interface rendering
* Alignment with legacy system strengths (without legacy flaws)

---

# 1. UI/UX Design Principles

1. **Clarity over decoration**
2. **Role-based visibility**
3. **Information grouped by operational priority**
4. **Status always visible**
5. **Financial data visually separated**
6. **Action buttons context-aware**
7. **Audit trail always accessible**
8. **Mobile-friendly but desktop-optimized**

---

# 2. Layout Architecture

## 2.1 Global Layout Structure

Every authenticated screen contains:

### A. Top Navigation Bar

* Logo (left)
* Search bar (global shipment search)
* Notification bell (dropdown)
* User profile dropdown

### B. Sidebar Navigation

Role-based menu rendering.

### C. Main Content Area

Dynamic page rendering.

---

# 3. Dashboard UI Specification

## 3.1 Admin / Staff Dashboard

### Top Metrics Cards

Display:

* Total Active Shipments
* Pending Invoices
* Completed Shipments
* Escalated Conversations

Each card:

* Count
* Small status indicator
* Clickable link to filtered list

---

### Shipment Overview Table

Columns:

* Reference No
* VIN
* Shipper
* Destination
* Package Status
* Invoice Status
* Clearance Status
* Created Date
* Actions (View)

Pagination required.

---

## 3.2 Agent Dashboard

Display:

* Active Conversations
* Escalated Conversations
* Assigned Conversations
* Response time metric

Primary focus:
Conversation list panel.

---

## 3.3 Driver Dashboard

Display:

* Assigned Shipments
* Pickup Pending
* Delivery Pending

Minimal interface.

---

# 4. Shipment View Page Specification (Core Screen)

This page mirrors legacy strengths but improves structure and synchronization.

---

# 4.1 Header Section

Top Row:

* Shipment Reference (large, bold)
* Invoice Number (if exists)
* Action Dropdown:

  * Assign Driver
  * Generate Dock Receipt
  * Create Invoice
  * Mark Completed
  * Upload Document

Right side:
Status badges:

* Package Status
* Invoice Status
* Clearance Status

Badges color-coded:

* Pending → Yellow
* In Progress → Blue
* Completed → Green
* Voided → Red

---

# 4.2 Operational Details Section

Two-column grid:

Left Column:

* Auction Name
* Auction Location
* Origin Port
* Destination Port
* Shipping Company
* Booking Number

Right Column:

* Shipper Name
* Contact
* Driver Assigned
* Created By
* Created Date

---

# 4.3 Vehicle Details Section

Card layout.

Left side:
Vehicle Image (primary)

Right side:

* VIN
* Make
* Model
* Year
* Color
* Purchase Price
* Runner / Non-runner
* Primary Damage
* Secondary Damage

Vehicle photos appear in carousel below primary image.

---

# 4.4 Financial Breakdown Section

Clear financial separation.

Invoice Summary Card:

Table:

| # | Description      | Amount |
| - | ---------------- | ------ |
| 1 | Inland Transport | $400   |
| 2 | Auction Storage  | $100   |
| 3 | Ocean Freight    | $1385  |
| 4 | Non-runner Fee   | $400   |
|   | TOTAL            | $2285  |

Below table:

* Invoice Status Badge
* Cleared Date (if cleared)

If no invoice:
Display “No Invoice Created”.

---

# 4.5 Documents Section

Grid layout:

Each document card shows:

* Type
* Version
* Uploaded Date
* Uploaded By
* Download Button

Documents grouped by:

* Required
* Optional

---

# 4.6 Action History Timeline

Vertical chronological timeline.

Each entry displays:

* User Name
* Role
* Action
* Timestamp

Example:
“Abdullah Abbas Anka (Admin) assigned driver.”

Timeline always at bottom of page.

---

# 5. Invoice Creation UI

---

## 5.1 Invoice Builder Interface

Layout:

Left:
Invoice Item Template Dropdown

Right:
Dynamic Invoice Table

When template selected:
Add new row:

| Description | Editable Notes | Amount | Remove |

Total updates automatically.

Validation:

* No negative values
* At least one line required

---

## 5.2 Invoice Finalization

Button:
“Mark as Cleared”

Confirmation modal:
“This action will lock this invoice permanently.”

---

# 6. WhatsApp Agent Interface

---

## 6.1 Layout Structure

Two-panel layout.

Left Panel:
Conversation list.

Shows:

* Customer name
* Last message preview
* Timestamp
* Status indicator (active, escalated)

Right Panel:
Active conversation.

---

## 6.2 Conversation View

Top:

* Customer Name
* Phone
* Linked Shipment (clickable)

Middle:
Chat thread (scrollable).

Bottom:
Message input
Attachment button
Escalate button

---

## 6.3 Escalation UI

Click “Escalate”:

Modal:

* Select reason
* Confirm

Status updates visually to:
Escalated (red indicator)

---

# 7. Notification Dropdown UI

Bell icon in navbar.

Dropdown shows:

* Title
* Short description
* Timestamp

Unread notifications bold.

Clicking:

* Marks as read
* Redirects to relevant entity

---

# 8. Role-Based UI Rendering Rules

| Component                | Admin | Staff | Agent     | Driver | Shipper |
| ------------------------ | ----- | ----- | --------- | ------ | ------- |
| Create Shipment          | ✔     | ✔     | ✖         | ✖      | ✖       |
| Create Invoice           | ✔     | ✔     | ✖         | ✖      | ✖       |
| Assign Driver            | ✔     | ✔     | ✖         | ✖      | ✖       |
| Escalate Conversation    | ✔     | ✖     | ✔         | ✖      | ✖       |
| View Financial Breakdown | ✔     | ✔     | Read-only | ✖      | ✔       |

UI must hide buttons rather than disable them when unauthorized.

---

# 9. Mobile Responsiveness

Breakpoints:

* Desktop (primary)
* Tablet
* Mobile

Mobile adjustments:

* Sidebar collapsible
* Shipment sections stacked vertically
* Financial table scrollable horizontally

---

# 10. Visual System Guidelines

---

## 10.1 Color System

* Primary: Dark Blue
* Success: Green
* Warning: Yellow
* Danger: Red
* Neutral: Gray

---

## 10.2 Typography

* Headings: Bold
* Status labels: Medium weight
* Financial totals: Larger font

---

## 10.3 Spacing

* Section padding: consistent
* Cards: 2xl rounded corners
* Soft shadows
* Clear visual grouping

---

# 11. UX Safeguards

1. Confirmation modal for:

   * Clearing invoice
   * Completing shipment
   * Voiding invoice
2. Prevent double-click submission.
3. Show loading indicators.
4. Display error messages inline.

---

# 12. Legacy System Improvements

Compared to legacy system:

1. Invoice and shipment status synchronized automatically.
2. Clear separation between operational and financial data.
3. Structured timeline.
4. Stronger role-based isolation.
5. Cleaner layout hierarchy.

---

# 13. Accessibility Requirements

1. Buttons must have sufficient contrast.
2. Status badges must not rely solely on color (include label).
3. Forms must have labels.
4. Tables must have headers.

---

This completes UI/UX specification for core system screens.

---


