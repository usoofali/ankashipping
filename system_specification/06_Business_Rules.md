
# 06_Business_Rules.md

**Anka Shipping & Logistics Management System (ASLMS)**

This document defines the non-negotiable operational constraints, validations, and system-enforced policies governing system behavior. These rules ensure consistency, auditability, financial integrity, and operational control.

---

# 1. General System Rules

## 1.1 Role-Based Access Control (RBAC)

1. Every authenticated user must have exactly one role.
2. Permissions are strictly role-driven.
3. Only **Admin** can:

   * Create/edit invoice item templates
   * Void invoices
   * Modify system configuration
   * Reassign escalated WhatsApp conversations
4. Drivers cannot:

   * Access financial modules
   * Edit shipment financial data
5. Agents cannot:

   * Modify invoice templates
   * Delete shipments

---

## 1.2 Audit Enforcement

1. All create, update, and delete operations must be logged.
2. Financial modifications must store:

   * User ID
   * Role snapshot
   * Old values
   * New values
   * Timestamp
3. Audit logs are immutable.

---

# 2. Shipment Rules

---

## 2.1 Shipment Creation Rules

1. Shipment must have:

   * Shipper
   * VIN
   * Auction details
   * Origin port
   * Destination port
2. Reference number must be unique.
3. VIN must be unique among active shipments.
4. Shipment creator is automatically stored as `created_by`.

---

## 2.2 Shipment Status Logic

Shipment status flows:

`pending → in_progress → completed`

Rules:

1. A shipment cannot move to **completed** if:

   * Invoice status ≠ cleared
   * Required documents are missing
2. Shipment cannot be deleted if:

   * An invoice exists
   * Documents exist
   * It has financial records

Soft delete only allowed if:

* No invoice exists
* No financial activity exists

---

## 2.3 Driver Assignment Rules

1. Only users with role = driver can be assigned.
2. A shipment may have only one active driver.
3. Driver reassignment must log previous driver.
4. Driver can only update pickup/delivery confirmation fields.

---

# 3. Document Management Rules

---

## 3.1 Required Documents Before Completion

Before marking shipment as completed:

Mandatory documents:

* Vehicle receipt
* Bill of lading
* Invoice (system-generated)

Optional:

* Title
* Dock receipt

---

## 3.2 Version Control

1. Replacing a document increments version number.
2. Old document versions are never deleted.
3. Only Admin or assigned staff can replace documents.

---

# 4. Invoice & Financial Rules

---

## 4.1 Invoice Creation Rules

1. Invoice must belong to exactly one shipment.
2. Only one active invoice per shipment.
3. Invoice must have at least one invoice line.
4. Invoice total must equal sum(invoice_lines.amount).

System must validate total before saving.

---

## 4.2 Invoice Editing Rules

Invoice can be edited only if:

`status = pending`

Once:
`status = cleared`

Then:

* Invoice becomes immutable.
* Lines cannot be modified.
* Total cannot be modified.

---

## 4.3 Invoice Voiding Rules

Only Admin can void.

When voided:

* Status changes to "voided"
* Shipment invoice_status resets to "pending"
* Audit entry required

Voided invoices remain in database (never deleted).

---

## 4.4 Shipment–Invoice Synchronization

Rules to prevent legacy inconsistency:

1. When invoice.status = cleared:
   → shipment.invoice_status = cleared
2. When invoice.status = voided:
   → shipment.invoice_status = pending
3. Shipment cannot be marked completed if invoice_status ≠ cleared.

Synchronization must be automatic via transaction logic.

---

# 5. WhatsApp Communication Rules

---

## 5.1 Conversation Assignment Rules

1. Unassigned conversations default to status = active.
2. When assigned to agent:

   * status = assigned
3. If agent escalates:

   * status = escalated
   * Admin notification triggered
4. Only Admin can resolve escalated conversations.

---

## 5.2 Session Expiry Rules

1. If session_expires_at < current time:

   * Conversation cannot send outbound message.
2. Agent must request session reactivation template.

---

## 5.3 Message Logging Rules

1. All inbound and outbound messages must be stored.
2. WhatsApp message ID must be saved if provided.
3. Media files must store file path.

Messages cannot be deleted.

---

# 6. Notification Rules

---

1. Notifications created when:

   * Invoice cleared
   * Shipment completed
   * Conversation escalated
   * Driver assigned
2. Notifications default to unread.
3. Notifications cannot be deleted (only marked read).

---

# 7. Data Integrity Rules

---

## 7.1 Referential Integrity

1. Shipment cannot exist without shipper.
2. Invoice cannot exist without shipment.
3. Invoice lines cannot exist without invoice.
4. Messages cannot exist without conversation.

---

## 7.2 Transaction Integrity

All financial updates must use database transactions:

* Creating invoice + lines
* Clearing invoice
* Voiding invoice
* Shipment completion

---

## 7.3 Concurrency Rules

1. Optimistic locking recommended for invoices.
2. Prevent double-clearing of invoice.
3. Prevent simultaneous driver reassignment.

---

# 8. Deletion Rules

---

## 8.1 Hard Delete Allowed Only For:

* Notifications (optional cleanup)
* Temporary session records

---

## 8.2 Soft Delete Required For:

* Shipments
* Invoices
* Shippers
* Invoice templates

---

## 8.3 Never Delete:

* Invoice lines
* Activity logs
* Messages
* Documents (versioned instead)

---

# 9. Reporting Rules

---

1. Financial reports must exclude voided invoices unless explicitly filtered.
2. Shipment reports must show:

   * Invoice status
   * Clearance status
   * Completion status
3. Audit logs must be accessible to Admin only.

---

# 10. System Safeguards

---

1. System must prevent:

   * Negative invoice amounts
   * Duplicate invoice numbers
   * Duplicate VIN entries
2. Monetary fields must use DECIMAL, never FLOAT.
3. All timestamps stored in UTC.
4. File uploads must validate:

   * File type
   * File size
   * Virus scanning (if integrated)

---

# 11. Operational Constraints

---

1. No financial record may be edited after clearance.
2. Shipment lifecycle cannot skip stages.
3. Escalation cannot be reversed without Admin.
4. System must never allow orphan records.

---

# 12. Performance Constraints

---

1. VIN search must return < 1 second.
2. Shipment listing paginated.
3. Invoice generation must execute in single transaction.
4. WhatsApp messages paginated (latest 50 default).

---

# 13. Compliance & Traceability

---

1. Every shipment must be traceable by:

   * VIN
   * Reference number
   * Invoice number
2. System must support export of:

   * Shipment report
   * Financial report
   * Audit logs

---

This document formalizes operational discipline and prevents data drift, financial corruption, and workflow bypass.

---

