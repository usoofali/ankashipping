
# 04_Functional_Requirements.md

## 1. Introduction

This document defines the functional requirements for the **Anka Shipping & Logistics Management System (ASLMS)**.

All requirements are written in structured, testable format using “shall” statements and are organized by module.

**Staff types:** Staff users have a **staff type** (Accountant, Booking Manager, Logistics Officer). The system shall enforce that only the actions permitted for that staff type are available to each Staff user (see 03_Roles_and_Permissions §4.3). Where a requirement says “Admin and Staff” may perform an action, the system shall allow it only for Admin and for those Staff whose staff type is permitted (e.g. “create shipment” only for Staff type Booking Manager).

---

# 2. Shipper Registration and Pre-Alert

## 2.0 Shipper Registration

**FR-SH-00**
The system shall allow registration of new shippers (customers) from the system. Who may create shipper records (Admin, Staff, or self-registration) is configurable. Once a shipper is registered, they may create pre-alerts (via web portal or WhatsApp) and access the shipper portal if linked to a user account.

---

## 2.1 Pre-Alert Creation (Web or WhatsApp)

**FR-SH-00a**
The system shall allow Shipper to create a pre-alert by providing at least the **VIN** of the vehicle (and, for WhatsApp, the receipt/bill of sale as image or document).

**FR-SH-00b**
Before creating the pre-alert, the system shall call the designated vehicle-details API with the VIN, **store the full API response** in a dedicated table (e.g. vehicle_api_responses) to avoid duplicate paid requests, then create the pre-alert linked to that stored response. **Web:** If the API fails, the system shall allow manual entry or retry. **WhatsApp:** If the API fails during pre-alert creation via WhatsApp, the system shall return an error message to the user only (no manual override).

**FR-SH-00c**
Admin or authorised Staff (Booking Manager) shall convert a pre-alert into a formal shipment (acknowledgment of receipt). Conversion may use the stored API response so no second API call is required.

---

## 2.2 Shipment Creation

**FR-SH-01**
The system shall allow Admin and Staff to create a shipment.

**FR-SH-02**
The system shall require the following minimum fields before shipment creation:

* Customer (Shipper)
* VIN
* Auction source
* Origin location
* Destination port

**FR-SH-03**
The system shall generate a unique shipment reference number.

**FR-SH-04**
The system shall record the user who created the shipment.

---

## 2.2 Shipment Status Tracking

Each shipment shall have three independent operational statuses:

1. Package Status
2. Invoice Status
3. Clearance Status

**FR-SH-05**
The system shall display these three statuses on the order dashboard.

**FR-SH-06**
Each status shall support at minimum:

* Pending
* In Progress
* Completed

**FR-SH-07**
Status changes shall be logged in the activity timeline.

**FR-SH-08**
Only authorized roles may update specific status types.

---

## 2.3 Shipment Lifecycle Events

**FR-SH-09**
The system shall log the following events:

* Shipment created
* Driver assigned
* Shipment delivered
* Invoice cleared
* Document uploaded

**FR-SH-10**
Each event shall store:

* User ID
* Role
* Timestamp
* Description

**FR-SH-11**
The system shall display a chronological action history timeline on the order page.

---

# 3. Vehicle & Auction Integration

## 3.1 VIN Lookup

**FR-VIN-01**
The system shall allow VIN validation using external API.

**FR-VIN-02**
The system shall auto-populate:

* Make
* Model
* Year
* Color
* Engine
* Transmission
* Odometer
* Damage details
* Auction lot number
* Location

**FR-VIN-03**
The system shall store API response data for future reference.

**FR-VIN-04**
The system shall allow manual override of vehicle details by authorized staff.

---

## 3.2 Vehicle Condition Tracking

**FR-VIN-05**
The system shall store primary and secondary damage information.

**FR-VIN-06**
The system shall allow marking vehicle as:

* Runner
* Non-runner

**FR-VIN-07**
The system shall allow storing purchase price.

---

# 4. Driver & Transport Management

**FR-DRV-01**
The system shall allow Admin/Staff to assign driver to shipment.

**FR-DRV-02**
The system shall allow driver to view assigned shipments only.

**FR-DRV-03**
The system shall allow driver to confirm pickup.

**FR-DRV-04**
The system shall allow driver to confirm port delivery.

**FR-DRV-05**
Driver confirmation shall trigger notification to Staff/Admin.

---

# 5. Document Management

## 5.1 Supported Documents

The system shall support:

* Salvage Title / Certificate of Title
* Dock Receipt
* Bill of Lading
* Vehicle Receipt
* Invoice (generated)

---

## 5.2 Upload & Storage

**FR-DOC-01**
The system shall allow uploading documents to shipment.

**FR-DOC-02**
Each document shall store:

* Document type
* Uploaded by
* Upload date
* Version

**FR-DOC-03**
The system shall allow document download based on role permissions.

**FR-DOC-04**
The system shall log document uploads in action history.

---

## 5.3 Dock Receipt Generation

**FR-DOC-05**
The system shall generate Dock Receipt as PDF from shipment data.

**FR-DOC-06**
Dock Receipt shall be stored and linked to shipment.

---

# 6. Invoice Management

## 6.1 Invoice Item Templates

**FR-INV-01**
Admin shall create invoice item templates.

**FR-INV-02**
Invoice item templates shall include:

* Name
* Type
* Description

**FR-INV-03**
Staff shall not edit invoice item templates.

---

## 6.2 Invoice Creation

**FR-INV-04**
Admin and Staff shall create invoice linked to shipment.

**FR-INV-05**
Invoice lines shall be selected from predefined templates.

**FR-INV-06**
Staff shall set dynamic price for each invoice line.

**FR-INV-07**
The system shall calculate total automatically.

**FR-INV-08**
The system shall display invoice breakdown table:

* Line number
* Description
* Amount
* Total

**FR-INV-09**
The system shall support invoice status:

* Pending
* Cleared
* Voided

**FR-INV-10**
Invoice status updates shall be logged.

---

## 6.3 Invoice & Clearance Synchronization

**FR-INV-11**
The system shall ensure invoice dashboard status reflects latest internal status.

**FR-INV-12**
If internal clearance is marked complete, dashboard shall update accordingly.

---

# 7. WhatsApp Communication Module

WhatsApp has **two layers: Automation first, Agent on escalation.** Most shipper actions are handled by automation; a human agent is involved only after the customer escalates.

## 7.1 Message Handling and Automation

**FR-WA-01**
The system shall receive inbound WhatsApp messages via webhook. The shipper is identified by phone number (linked to the Shipper record).

**FR-WA-02**
The system shall create or update conversation thread per shipper (by phone).

**FR-WA-03**
**Automation (first):** The system shall provide menu-driven self-service so that almost everything the shipper can do on the platform can be done via WhatsApp without a human agent:
* **Get shipment information:** Shipper selects menu option and sends VIN or shipment reference number. The system shall look up the shipment (must belong to that shipper), return status, and allow sending documents (e.g. Dock Receipt) to the shipper via WhatsApp Business API (e.g. PDF).
* **Create pre-alert:** Shipper sends VIN and receipt/bill of sale (image or document). The system shall call the vehicle API, store the response, create the pre-alert, and confirm to the shipper via WhatsApp. If the vehicle API fails, the system shall send an error message to the shipper via WhatsApp only (no manual override on WhatsApp).
* Other menu options as defined (e.g. list my shipments, request document).

**FR-WA-04**
**Escalation:** When the customer chooses to talk to a human (e.g. "Talk to agent"), the conversation status shall become escalated and the conversation shall be assigned to an agent. Only then shall a human agent respond via the Agent Interface.

**FR-WA-05**
If a conversation remains unassigned after escalation for a specified duration, the system shall assign it via round-robin to an available agent.

---

## 7.2 Agent Capabilities (Post-Escalation Only)

**FR-WA-06**
After escalation, Agent shall view conversation history and reply via the system interface (same WhatsApp channel).

**FR-WA-07**
Agent shall link shipment to conversation when needed. Agent shall escalate or reassign to Staff/Admin as defined in roles.

---

# 8. Notification and Milestone Communications

## 8.1 Internal Notifications

**FR-NOT-01**
The system shall generate internal (in-app) notifications for key events (e.g. Shipment created, Driver assigned, Document uploaded, Invoice cleared, Conversation escalated). Notifications shall be displayed in dropdown, with read/unread state and retention as defined elsewhere.

## 8.2 Milestone Communications to Shipper (Email + WhatsApp)

**FR-NOT-02**
For each defined **shipment milestone** (e.g. pre-alert received, shipment created, driver assigned, dock receipt ready, invoice ready, invoice cleared, shipment completed), the system shall:
* Send an **email** to the shipper using a configurable **email template** (subject and body with placeholders) and the appropriate **sender address** (e.g. booking@ for booking-related milestones, account@ for payment-related such as invoice ready/cleared).
* If the shipper's WhatsApp 24h session is still active, also send a **WhatsApp message** with the same or a shortened version of the milestone information.

**FR-NOT-03**
**System settings for email:** The system shall support **multiple SMTP mailers** (e.g. booking@, account@), each with its own credentials and default "from" address. **Email templates** shall be configurable per milestone (name, subject, body, which mailer to use). Placeholders (e.g. shipper name, reference number, link to shipment) shall be supported. Super Admin shall manage mailer credentials; Admin (or Super Admin) shall manage templates.

---

# 9. Activity & Audit Logging

**FR-ACT-01**
The system shall log all critical operations.

**FR-ACT-02**
Activity log shall store:

* User
* Role
* Action
* Entity
* Timestamp

**FR-ACT-03**
Admin shall access full audit log.

---

# 10. Customer (Shipper) Portal

**FR-CUS-01**
Shipper shall view only their shipments.

**FR-CUS-02**
Shipper shall view shipment status.

**FR-CUS-03**
Shipper shall download invoice.

**FR-CUS-04**
Shipper shall view vehicle details.

---

# 11. Security Requirements

**FR-SEC-01**
The system shall enforce authentication.

**FR-SEC-02**
The system shall enforce role-based authorization.

**FR-SEC-03**
The system shall restrict document access based on role.

**FR-SEC-04**
All sensitive operations shall require authorization check.

---

# 12. Reporting & Monitoring

**FR-REP-01**
Admin shall view shipment statistics.

**FR-REP-02**
Admin shall view invoice summaries.

**FR-REP-03**
Admin shall view agent performance metrics.

---

# 13. MVP Scope

The MVP shall include:

* Shipment creation
* VIN lookup integration
* Driver assignment
* Document upload
* Dock Receipt generation
* Invoice creation with dynamic pricing
* WhatsApp agent system
* Notification dropdown
* Action history timeline

Deferred to Phase 2:

* Payment gateway integration
* Automated customs integration
* Advanced analytics
* Multi-branch support

---

