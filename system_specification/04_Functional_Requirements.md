
# 04_Functional_Requirements.md

## 1. Introduction

This document defines the functional requirements for the **Anka Shipping & Logistics Management System (ASLMS)**.

All requirements are written in structured, testable format using “shall” statements and are organized by module.

---

# 2. Shipment & Order Management

## 2.1 Shipment Creation

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

## 7.1 Message Handling

**FR-WA-01**
The system shall receive inbound WhatsApp messages via webhook.

**FR-WA-02**
The system shall create or update conversation thread per customer.

**FR-WA-03**
The system shall assign conversation to an agent.

---

## 7.2 Agent Capabilities

**FR-WA-04**
Agent shall view conversation history.

**FR-WA-05**
Agent shall reply via system interface.

**FR-WA-06**
Agent shall escalate conversation to Staff/Admin.

**FR-WA-07**
Agent shall link shipment to conversation.

---

## 7.3 Shipment Query Automation

**FR-WA-08**
The system shall allow automated shipment status response when VIN or reference number is detected.

---

# 8. Notification System

**FR-NOT-01**
The system shall generate notification for key events:

* Shipment created
* Driver assigned
* Document uploaded
* Invoice cleared
* Conversation escalated

**FR-NOT-02**
Notifications shall be displayed in dropdown.

**FR-NOT-03**
System shall track read/unread state.

**FR-NOT-04**
Notifications shall persist when user is offline.

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

