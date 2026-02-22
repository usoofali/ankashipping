# 03_Roles_and_Permissions.md

---

# 03_Roles_and_Permissions.md

## 1. Overview

This document defines the **Role-Based Access Control (RBAC)** model for the Anka Shipping & Logistics Management System (ASLMS).

The system enforces strict separation of responsibilities to ensure:

* Operational control
* Financial integrity
* Data security
* Accountability
* Audit traceability

All permissions are enforced using:

* Laravel Policies
* Middleware guards
* Role-based UI rendering
* Database-level constraints where necessary

---

## 2. Role Hierarchy

The system defines five primary roles:

1. Super Admin
2. Staff
3. Agent
4. Driver
5. Shipper (Customer)

Hierarchy (by privilege level):

Super Admin
→ Staff
→ Agent
→ Driver
→ Shipper

Privileges are not inherited automatically; they are explicitly assigned.

---

## 3. Super Admin

### 3.1 Description

The Super Admin is the highest authority within the system and has unrestricted system access.

This role is typically limited to company executives or system owners.

---

### 3.2 Permissions

#### User & Role Management

* Create staff accounts
* Create agent accounts
* Create driver accounts
* Assign roles
* Activate / deactivate users
* Reset passwords

#### Shipment Management

* View all shipments
* Edit any shipment
* Delete shipment (if policy allows)
* Override shipment state
* Assign or reassign drivers

#### Invoice Management

* Create invoice item templates
* Define invoice item types
* Define invoice descriptions
* View all invoices
* Edit invoices
* Void invoices (with audit log)

#### Document Management

* Generate Dock Receipt
* Upload Bill of Lading
* Upload Vehicle Receipt
* Replace documents (with versioning)

#### WhatsApp System

* View all conversations
* Reassign conversations
* Override escalations

#### Notification & Activity

* View full activity log
* View system-wide notifications

#### System Configuration

* Manage system settings
* Manage API credentials
* Manage environment parameters (if exposed)

---

## 4. Staff

### 4.1 Description

Staff members handle operational workflows such as shipment processing and invoice generation.

They do not control system configuration or invoice item templates.

---

### 4.2 Permissions

#### Shipment Management

* Create shipment
* Edit shipment (until locked state)
* Assign driver
* Update shipment status (within allowed transitions)
* Link auction vehicle
* View all shipments

#### Document Management

* Generate Dock Receipt
* Upload Bill of Lading
* Upload Vehicle Receipt
* Download shipment documents

#### Invoice Management

* Create invoice
* Add invoice lines (select existing invoice item templates)
* Set dynamic price per invoice line
* Edit invoice before finalization
* View all invoices

Important Restriction:

Staff CANNOT:

* Create invoice item templates
* Modify invoice item descriptions
* Change invoice item types

Only Admin defines invoice structure.

#### WhatsApp Visibility

* View escalated conversations
* Respond if reassigned
* View shipment linked to conversation

#### Notifications

* Receive shipment-related notifications
* Receive escalation alerts

---

## 5. Agent (WhatsApp Customer Support)

### 5.1 Description

Agents manage customer communication through the WhatsApp integration interface.

They do not perform shipment processing or financial configuration.

---

### 5.2 Permissions

#### Communication

* View assigned conversations
* Respond to messages
* Attach shipment reference to conversation
* Add internal notes
* Escalate conversation to Staff/Admin
* Tag conversations

#### Shipment Visibility

* View shipment summary (read-only)
* View invoice summary (read-only)
* View shipment status

Agents CANNOT:

* Create shipment
* Edit shipment
* Generate documents
* Create invoice
* Modify pricing

#### Notifications

* Receive new message alerts
* Receive conversation assignment alerts

---

## 6. Driver

### 6.1 Description

Drivers are responsible for transporting vehicles from auction locations to the port.

They operate under restricted access.

---

### 6.2 Permissions

* View assigned jobs
* View pickup details
* View Dock Receipt
* Confirm pickup
* Confirm delivery to port
* Upload proof (if enabled)

Drivers CANNOT:

* View financial data
* View other shipments
* Access invoices
* Access communication system

---

## 7. Shipper (Customer)

### 7.1 Description

The Shipper is the client who owns the shipment.

Access is strictly limited to their own data.

---

### 7.2 Permissions

* View own shipments
* View shipment status
* Download Dock Receipt (if allowed)
* View Bill of Lading
* View invoice
* Download invoice

Shippers CANNOT:

* Edit shipment
* Modify invoices
* View other customers' data
* Access internal logs

---

## 8. Permission Matrix (Summary)

| Feature                      | Admin | Staff | Agent         | Driver            | Shipper |
| ---------------------------- | ----- | ----- | ------------- | ----------------- | ------- |
| Create Shipment              | ✔     | ✔     | ✖             | ✖                 | ✖       |
| Edit Shipment                | ✔     | ✔     | ✖             | ✖                 | ✖       |
| Assign Driver                | ✔     | ✔     | ✖             | ✖                 | ✖       |
| Generate Dock Receipt        | ✔     | ✔     | ✖             | ✖                 | ✖       |
| Upload Bill of Lading        | ✔     | ✔     | ✖             | ✖                 | ✖       |
| Create Invoice Item Template | ✔     | ✖     | ✖             | ✖                 | ✖       |
| Add Dynamic Price to Invoice | ✔     | ✔     | ✖             | ✖                 | ✖       |
| View All Invoices            | ✔     | ✔     | ✖             | ✖                 | ✖       |
| Respond to WhatsApp          | ✔     | ✖     | ✔             | ✖                 | ✖       |
| Escalate Conversation        | ✔     | ✖     | ✔             | ✖                 | ✖       |
| View Assigned Jobs           | ✔     | ✔     | ✖             | ✔                 | ✖       |
| View Own Shipment            | ✔     | ✔     | ✔ (Read-only) | ✔ (Assigned only) | ✔       |

---

## 9. Authorization Implementation Strategy

### 9.1 Role Storage

Roles stored in:

roles table
role_user pivot (if multi-role allowed)

Or single-role system if simplified.

---

### 9.2 Enforcement Mechanisms

1. Route Middleware

   * role:admin
   * role:staff
   * role:agent

2. Laravel Policies

   * ShipmentPolicy
   * InvoicePolicy
   * ConversationPolicy
   * DocumentPolicy

3. Blade Directives

   * @can
   * @role

4. Livewire Component Guards

---

## 10. Segregation of Duties

The system enforces separation between:

* Template creation (Admin)
* Pricing application (Staff)
* Communication (Agent)
* Operational execution (Driver)

This reduces:

* Fraud risk
* Financial manipulation
* Unauthorized data modification

---

## 11. Audit Requirements

All sensitive operations must:

* Log user ID
* Log role
* Log timestamp
* Log entity ID
* Log before/after values (if applicable)

Sensitive operations include:

* Invoice modification
* Shipment status override
* Driver reassignment
* Document replacement
* Invoice voiding

---

## 12. Future Role Expansion (Phase 2)

Possible additional roles:

* Accountant
* Port Manager
* Compliance Officer
* Regional Manager

The RBAC design supports extension without architectural change.

---

