
---

# 03_Roles_and_Permissions.md

---

# 3. Roles and Permissions

---

## 3.1 Purpose

This document defines the role-based access control (RBAC) model for the Anka Shipping Operations & Documentation Management System (ASODMS).

The objective is to:

* Protect sensitive export documentation
* Safeguard financial operations
* Ensure operational accountability
* Prevent unauthorized workflow manipulation
* Maintain compliance traceability

All system access shall be governed by explicit permissions assigned to defined roles.

---

## 3.2 Access Control Model

The system shall implement:

* Role-Based Access Control (RBAC)
* Permission-based authorization
* Policy enforcement at controller and service level
* Middleware protection for routes
* Database-level audit logging

No user shall perform any action without an assigned permission.

---

## 3.3 System Roles

The system shall support the following primary roles:

1. Super Administrator
2. Operations Administrator
3. Operations Staff
4. Invoice Staff
5. WhatsApp Agent
6. Driver
7. Shipper (Customer)

---

## 3.4 Role Definitions

---

### 3.4.1 Super Administrator

**Description:**
Highest-level system authority with full operational and financial control.

**Responsibilities:**

* Manage users and roles
* Configure invoice item templates
* Override workflow constraints (if necessary)
* View all shipments and documents
* View financial reports
* Access activity logs

**Risk Level:** Critical

---

### 3.4.2 Operations Administrator

**Description:**
Senior operational staff managing shipment workflows and port coordination.

**Responsibilities:**

* Create shipments
* Assign drivers
* Upload and verify titles
* Generate dock receipts
* Upload Bill of Lading
* Update shipment statuses
* View documents
* View activity logs

**Cannot:**

* Modify wallet balances directly
* Delete financial transactions

---

### 3.4.3 Operations Staff

**Description:**
Handles day-to-day shipment processing and coordination.

**Responsibilities:**

* Create pre-alerts
* Convert pre-alert to shipment
* Assign drivers
* Update shipment status
* Upload supporting documents
* View dock receipts
* View shipment details

**Cannot:**

* Issue invoices (unless granted invoice permission)
* Access financial reports
* Modify wallet transactions

---

### 3.4.4 Invoice Staff

**Description:**
Responsible for financial processing and invoicing.

**Responsibilities:**

* Create invoices
* Select invoice item templates
* Set dynamic pricing
* Issue invoices
* View wallet balances
* Process invoice payments
* View financial history

**Cannot:**

* Directly alter wallet balance outside system transactions
* Modify issued invoices
* Access system configuration

---

### 3.4.5 WhatsApp Agent

**Description:**
Handles client communication via WhatsApp.

**Responsibilities:**

* Receive inbound WhatsApp messages
* Respond within session window
* View shipment summaries
* Send document links
* Escalate to Operations or Invoice staff

**Cannot:**

* Modify shipment data
* Create invoices
* Adjust wallet balances
* Generate dock receipts

---

### 3.4.6 Driver

**Description:**
Responsible for vehicle transport to port.

**Responsibilities:**

* View assigned shipments
* Upload title document
* View dock receipt
* Confirm delivery at port

**Cannot:**

* View financial data
* Access other shipments
* Modify shipment details

---

### 3.4.7 Shipper (Customer)

**Description:**
External client user.

**Responsibilities:**

* Create pre-alert
* View shipment status
* View uploaded documents
* View invoices
* View wallet balance
* Fund wallet (if implemented)

**Cannot:**

* Modify shipment workflow
* Generate documents
* Edit invoice pricing

---

## 3.5 Permission Definitions

Permissions shall be granular and explicitly defined.

---

### 3.5.1 User Management Permissions

* manage_users
* assign_roles
* deactivate_user

---

### 3.5.2 Shipment Permissions

* create_pre_alert
* create_shipment
* update_shipment
* assign_driver
* change_shipment_status
* send_to_workshop
* mark_delivered
* view_all_shipments
* view_own_shipments

---

### 3.5.3 Document Permissions

* upload_auction_receipt
* upload_title
* generate_dock_receipt
* upload_bill_of_lading
* download_documents
* delete_document

---

### 3.5.4 Invoice Permissions

* create_invoice
* edit_invoice_draft
* issue_invoice
* void_invoice
* view_invoice
* manage_invoice_items
* set_dynamic_price

---

### 3.5.5 Wallet Permissions

* view_wallet
* process_wallet_payment
* credit_wallet
* view_wallet_transactions

---

### 3.5.6 Communication Permissions

* send_whatsapp_message
* view_whatsapp_thread
* send_email_notification
* view_notification_center

---

### 3.5.7 Audit Permissions

* view_activity_logs
* export_reports

---

## 3.6 Permission Matrix (Summary)

| Permission Category   | Super Admin | Ops Admin | Ops Staff | Invoice Staff | Agent | Driver | Shipper |
| --------------------- | ----------- | --------- | --------- | ------------- | ----- | ------ | ------- |
| Manage Users          | ✔           | ✖         | ✖         | ✖             | ✖     | ✖      | ✖       |
| Create Shipment       | ✔           | ✔         | ✔         | ✖             | ✖     | ✖      | ✖       |
| Assign Driver         | ✔           | ✔         | ✔         | ✖             | ✖     | ✖      | ✖       |
| Upload Title          | ✔           | ✔         | ✔         | ✖             | ✖     | ✔      | ✖       |
| Generate Dock Receipt | ✔           | ✔         | ✖         | ✖             | ✖     | ✖      | ✖       |
| Create Invoice        | ✔           | ✖         | ✖         | ✔             | ✖     | ✖      | ✖       |
| Set Dynamic Pricing   | ✔           | ✖         | ✖         | ✔             | ✖     | ✖      | ✖       |
| View Wallet           | ✔           | ✖         | ✖         | ✔             | ✖     | ✖      | ✔ (Own) |
| Send WhatsApp         | ✔           | ✖         | ✖         | ✖             | ✔     | ✖      | ✖       |
| View Activity Logs    | ✔           | ✔         | ✖         | ✖             | ✖     | ✖      | ✖       |

---

## 3.7 Role Assignment Rules

1. A user may have multiple roles.
2. Permissions are cumulative.
3. Critical financial permissions shall require admin approval.
4. Role assignment changes must be logged.

---

## 3.8 Segregation of Duties

To prevent fraud or operational risk:

* Invoice creation and wallet crediting should not be assigned to the same user without oversight.
* Dock receipt generation requires title verification.
* Invoice modification is prohibited after issuance.
* Wallet balances cannot be manually edited.

---

## 3.9 Audit & Monitoring

All role-based actions shall be recorded in the activity_logs table including:

* user_id
* action
* entity_type
* entity_id
* timestamp
* IP address

No log entries shall be deletable by non-super administrators.

---

## 3.10 Future Role Expansion

The system architecture allows future addition of:

* Compliance Officer
* Regional Manager
* Financial Auditor
* API Integration User

---

End of Document
`03_Roles_and_Permissions.md`

---



