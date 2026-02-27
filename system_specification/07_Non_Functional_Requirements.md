
# 07_Non_Functional_Requirements.md

**Anka Shipping & Logistics Management System (ASLMS)**

This document defines the quality attributes, operational constraints, and performance standards required for the system beyond core functionality.

Non-functional requirements ensure the system is reliable, secure, scalable, maintainable, and production-ready.

---

# 1. Performance Requirements

## 1.1 Response Time

1. Standard page loads shall complete within **≤ 2 seconds** under normal load.
2. Shipment listing queries shall return within **≤ 1.5 seconds** with pagination.
3. Invoice generation shall complete within **≤ 2 seconds**.
4. VIN lookup (external API dependent) shall return within **≤ 3 seconds**.
5. Notification dropdown refresh shall complete within **≤ 1 second**.

---

## 1.2 Concurrency

1. The system shall support at least:

   * 20 concurrent internal users
   * 50 concurrent customer sessions
2. Invoice operations must prevent race conditions.
3. Concurrent edits on the same invoice must be restricted (optimistic locking recommended).

---

## 1.3 Background Processing

Heavy operations shall be queued:

* PDF generation
* Auction API import
* Notification broadcasting
* Media processing

Queue system: Redis or database queue (initially).

---

# 2. Scalability Requirements

## 2.1 Horizontal Scalability

The architecture shall allow future migration to:

* Load-balanced multi-instance deployment
* Dedicated queue workers
* Cloud object storage (S3-compatible)

---

## 2.2 Database Scalability

1. Tables must be indexed appropriately.
2. Shipment listing must use pagination.
3. Reports must avoid full-table scans.

---

# 3. Security Requirements

## 3.1 Authentication

1. All system access requires authentication.
2. Passwords must be hashed using bcrypt or Argon2.
3. Session expiration must be enforced.
4. Optional: Two-Factor Authentication (future phase).

---

## 3.2 Authorization

1. Role-based access control must be enforced at:

   * Route level
   * Controller level
   * Policy level
2. Unauthorized access attempts must return 403.

---

## 3.3 Data Protection

1. Sensitive data must not be exposed in frontend source.
2. File storage must not expose direct public file paths.
3. Signed URLs required for protected document access.
4. API keys stored in environment variables (.env only).

---

## 3.4 Input Validation

1. All form inputs must be validated server-side.
2. File uploads must validate:

   * MIME type
   * File size
3. SQL injection protection via ORM.
4. XSS protection via automatic escaping.

---

# 4. Reliability & Availability

## 4.1 Uptime

Target uptime: **99% or higher** annually.

---

## 4.2 Fault Tolerance

1. External API failures must not crash shipment creation.
2. VIN lookup failures shall:

   * Return graceful error
   * Allow manual vehicle entry
3. WhatsApp webhook failures must log error.

---

## 4.3 Backup Strategy

1. Database backup:

   * Daily automated backups
2. File storage backup:

   * Weekly minimum
3. Backup retention:

   * Minimum 30 days

---

# 5. Maintainability Requirements

## 5.1 Code Structure

1. Controllers must not contain business logic.
2. Business logic must reside in service layer.
3. Code must follow PSR standards.
4. Naming conventions must be consistent.

---

## 5.2 Documentation

1. API endpoints must be documented.
2. Database schema must be version-controlled.
3. Business rules must be documented (see 06_Business_Rules.md).

---

## 5.3 Modularity

1. Each module must be logically separated.
2. Future modules must integrate without major refactoring.

---

# 6. Usability Requirements

## 6.1 User Interface

1. Dashboard must display:

   * Shipment status summary
   * Invoice status summary
   * Notification indicator
2. Order view page must clearly separate:

   * Operational data
   * Vehicle details
   * Financial breakdown
   * Action history

---

## 6.2 Role-Based UI Rendering

1. Users must only see menu items relevant to their role.
2. Buttons must be conditionally rendered based on permissions.

---

## 6.3 Accessibility

1. Interface must be usable on:

   * Desktop
   * Tablet
   * Mobile browser
2. Font sizes must remain readable.
3. Buttons must have clear visual states.

---

# 7. Data Integrity Requirements

## 7.1 Consistency

1. Invoice totals must equal sum of invoice lines.
2. Shipment.invoice_status must sync with invoice.status.
3. Document version numbers must increment sequentially.

---

## 7.2 Transaction Management

1. Financial operations must use database transactions.
2. Shipment completion must validate:

   * Invoice cleared
   * Required documents uploaded

---

# 8. Logging & Monitoring

## 8.1 Activity Logging

1. All critical operations must be logged.
2. Financial actions must log old and new values.

---

## 8.2 Error Logging

1. All unhandled exceptions must be logged.
2. API failures must log response payload.

---

## 8.3 Monitoring (Future Phase)

System should allow integration with:

* Log monitoring tools
* Server performance monitoring
* Alert notifications

---

# 9. Compliance Requirements

1. All timestamps stored in UTC.
2. Financial fields must use DECIMAL.
3. System must support exportable reports (CSV/PDF).
4. System must maintain historical integrity (no destructive edits).

---

# 10. Portability Requirements

1. System must run on:

   * Shared hosting (Hostinger initial)
   * VPS
   * Cloud VM
2. Environment configuration must be .env-based.
3. Storage path must be configurable.

---

# 11. Deployment Requirements

1. Git-based version control required.
2. Production must use:

   * APP_ENV=production
   * Debug disabled
3. Migrations must be reversible.
4. Testing branch required before production merge.

---

# 12. Future-Proofing Requirements

The system shall be extensible to support:

* Multi-branch operations
* Multi-currency invoices
* Online payment gateway
* Accounting system integration
* Mobile app integration
* Microservice separation (if scale increases)

---

# 13. Operational Constraints

1. System must not allow:

   * Negative invoice totals
   * Duplicate VIN entries
   * Duplicate invoice numbers
2. System must prevent:

   * Concurrent double invoice clearing
   * Unauthorized status changes

---

This completes the Non-Functional Requirements layer.

---

At this stage, your documentation stack now includes:

01_System_Overview
02_Architecture
03_Roles_and_Permissions
04_Functional_Requirements
05_Database_Specification
06_Business_Rules
07_Non_Functional_Requirements


