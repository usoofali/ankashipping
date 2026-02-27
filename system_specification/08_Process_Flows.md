
# 08_Process_Flows.md

**Anka Shipping & Logistics Management System (ASLMS)**

This document defines the end-to-end operational workflows of the system.
Each flow describes actors, triggers, steps, validations, and system responses.

These flows must align strictly with:

* 04_Functional_Requirements.md
* 05_Database_Specification.md
* 06_Business_Rules.md
* 07_Non_Functional_Requirements.md

---

# 1. Shipment Creation Flow

## Actors:

* Admin
* Staff

---

## Trigger:

User clicks “Create Shipment”.

---

## Flow:

1. User enters:

   * Shipper
   * VIN
   * Auction details
   * Origin port
   * Destination port

2. System validates:

   * Required fields present
   * VIN not duplicated among active shipments

3. Optional:

   * System calls VIN Lookup API
   * Auto-populates vehicle fields

4. User confirms data.

5. System:

   * Generates unique shipment reference number
   * Saves shipment record
   * Sets:

     * package_status = pending
     * invoice_status = pending
     * clearance_status = pending
   * Logs activity

6. Notification triggered:

   * “Shipment Created”

---

## Output:

Shipment record created in Draft/Pending state.

---

# 2. VIN Lookup Process Flow

## Actors:

* Admin
* Staff

---

## Trigger:

VIN entered in shipment form.

---

## Flow:

1. System calls external API.
2. API response validated.
3. System extracts:

   * Make
   * Model
   * Year
   * Damage
   * Lot number
   * Location
4. Photos stored in vehicle_photos table.
5. Sales history stored.
6. Data cached for reuse.

If API fails:

* Show error
* Allow manual entry

---

# 3. Driver Assignment Flow

## Actors:

* Admin
* Staff

---

## Trigger:

User selects “Assign Driver”.

---

## Flow:

1. System verifies:

   * Selected user has role = driver
2. Shipment.driver_id updated.
3. driver_assignments record created.
4. Notification sent to driver.
5. Activity logged.

---

## Driver Confirmation Flow

1. Driver logs in.
2. Views assigned shipment.
3. Clicks:

   * Confirm Pickup
   * Confirm Port Delivery
4. System updates timestamps.
5. Notification sent to Staff/Admin.
6. Activity logged.

---

# 4. Document Upload Flow

## Actors:

* Admin
* Staff

---

## Trigger:

User uploads document.

---

## Flow:

1. User selects document type:

   * Title
   * Dock Receipt
   * Bill of Lading
2. File validated (size/type).
3. Stored securely.
4. Version incremented if replacing.
5. Document record saved.
6. Activity logged.
7. Notification triggered.

---

# 5. Dock Receipt Generation Flow

## Actors:

* Admin
* Staff

---

## Trigger:

User clicks “Generate Dock Receipt”.

---

## Flow:

1. System validates shipment completeness.
2. DockReceiptGenerator service invoked.
3. PDF rendered using template.
4. Stored in documents table.
5. Shipment.package_status may update to in_progress.
6. Notification generated.
7. Activity logged.

---

# 6. Invoice Creation Flow

## Actors:

* Admin
* Staff

---

## Trigger:

User clicks “Create Invoice”.

---

## Flow:

1. System checks:

   * Shipment exists
   * No existing active invoice
2. User selects invoice item templates.
3. User enters dynamic price per line.
4. System validates:

   * At least one line
   * No negative values
5. System calculates total.
6. Invoice saved.
7. invoice_status remains pending.
8. Activity logged.

---

# 7. Invoice Clearance Flow

## Actors:

* Admin / Authorized Staff

---

## Trigger:

User marks invoice as cleared.

---

## Flow:

1. System validates:

   * Invoice status = pending
2. Update:

   * invoice.status = cleared
   * shipment.invoice_status = cleared
3. invoice.finalized_at set.
4. Activity logged.
5. Notification sent.

Invoice becomes immutable.

---

# 8. Shipment Completion Flow

## Actors:

* Admin
* Staff

---

## Trigger:

User marks shipment as completed.

---

## Validation:

System verifies:

* invoice_status = cleared
* Required documents exist
* Driver delivery confirmed

---

## Flow:

1. Update:

   * package_status = completed
   * clearance_status = completed
2. Activity logged.
3. Notification generated.

---

# 9. WhatsApp Message Flow

## Actors:

* Customer (External)
* Agent
* Admin

---

## Inbound Flow

1. WhatsApp webhook receives message.
2. System:

   * Finds or creates conversation.
   * Stores message.
3. Assigns to available agent.
4. Notification sent.

---

## Agent Response Flow

1. Agent opens conversation.
2. Views shipment summary (if linked).
3. Replies via system.
4. Message sent via WhatsApp API.
5. Outbound message stored.

---

## Escalation Flow

1. Agent clicks “Escalate”.
2. conversation.status = escalated
3. Notification sent to Admin.
4. Admin may reassign or resolve.

---

# 10. Notification Process Flow

## Trigger Events:

* Shipment created
* Driver assigned
* Document uploaded
* Invoice cleared
* Conversation escalated

---

## Flow:

1. Event dispatched.
2. Notification record created.
3. Stored in notifications table.
4. Displayed in dropdown.
5. Marked read when clicked.

---

# 11. Activity Logging Flow

For every critical action:

1. Action executed.
2. ActivityLog record created with:

   * user_id
   * role_snapshot
   * action
   * entity_type
   * entity_id
   * old_values (if applicable)
   * new_values (if applicable)

No activity logs may be deleted.

---

# 12. Shipment View Page Rendering Flow (Legacy Alignment)

When user opens shipment page:

System retrieves:

* Shipment record
* Vehicle details
* Document list
* Invoice summary
* Invoice breakdown
* Driver info
* Action history timeline

Page sections rendered in order:

1. Header (Invoice # / Reference)
2. Status indicators (Package / Invoice / Clearance)
3. Operational details
4. Vehicle block (VIN, damage, purchase price)
5. Financial breakdown
6. Documents
7. Timeline

All data must be consistent with database.

---

# 13. Error Handling Flow

If any process fails:

1. Transaction rolled back.
2. Error logged.
3. User shown safe error message.
4. No partial data committed.

---

# 14. End-to-End Shipment Lifecycle Summary

1. Shipment Created
2. VIN Linked
3. Driver Assigned
4. Vehicle Delivered to Port
5. Documents Uploaded
6. Invoice Created
7. Invoice Cleared
8. Shipment Completed

System must prevent skipping required steps.

---

This document defines operational choreography of the entire platform.

---


