# 01_System_Overview.md

## 1. Introduction

### 1.1 Purpose of the System

The **Anka Shipping & Logistics Management System (ASLMS)** is a web-based enterprise platform designed to digitize and streamline the operations of **Anka Shipping & Logistics Services**, a US-based logistics company serving clients primarily in Nigeria and West Africa.

The system will:

* Manage end-to-end vehicle shipment workflows
* Handle auction vehicle intake (e.g., Copart integration)
* Generate operational documents (Dock Receipt, Bill of Lading, Vehicle Receipt)
* Manage dynamic invoice items and pricing
* Enable WhatsApp-based customer communication (automation first for self-service; human agent only after escalation)
* Register new shippers (customers) from the system; once registered, shippers can create pre-alerts (web or WhatsApp)
* Send milestone emails and WhatsApp messages to shippers at each shipment event (using configurable templates and multiple sender addresses e.g. booking@, account@)
* Provide internal activity notifications
* Track drivers, shipments, and port processing
* Provide role-based access control for all actors

The system replaces fragmented manual workflows with a centralized, auditable, and scalable digital platform.

---

## 2. Business Context

Anka Shipping operates within the international vehicle logistics chain:

1. Vehicle purchased from US auction (e.g., Copart)
2. Vehicle picked up by driver
3. Vehicle transported to port
4. Dock Receipt generated
5. Ocean carrier issues Bill of Lading
6. Vehicle shipped to Nigeria or West Africa
7. Customer receives shipment
8. Invoice generated based on dynamic pricing

The system must support this operational lifecycle.

---

## 3. System Objectives

### 3.1 Operational Objectives

* Digitize shipment lifecycle management
* Centralize document generation and storage
* Provide structured invoice item management
* Enable real-time WhatsApp customer support
* Track internal staff activities
* Improve transparency and accountability

### 3.2 Technical Objectives

* Role-based access control (RBAC)
* Modular architecture (Laravel-based)
* Activity logging and notification system
* External API integration (RapidAPI / Auction data)
* Document generation engine (PDF)
* Scalable database structure

---

## 4. System Actors

The system currently supports five primary actors:

### 4.1 Super Admin (Admin)

**Highest privilege role.**

Responsibilities:

* Full system access
* Manage staff, agents, drivers
* Create invoice item types
* Define invoice item descriptions
* Manage system configuration
* View global reports
* Audit activities
* Override operational decisions if necessary

---

### 4.2 Staff

Operational personnel responsible for shipment and invoice processing. Staff are classified by **staff type**; permitted actions depend on that type (see Roles and Permissions).

**Staff types:**

* **Accountant** – invoices (create, lines, dynamic pricing, clear when authorised); view shipments and invoices. No shipment creation, driver assignment, or document upload.
* **Booking Manager** – convert pre-alert to shipment, create and edit shipments, assign driver (when authorised), generate Dock Receipt, upload BoL/Vehicle Receipt; create invoice and add pricing. No invoice clear.
* **Logistics Officer** – assign driver (when authorised), update logistics/clearance status, generate Dock Receipt, upload documents; view shipments and invoices. No shipment create/edit, no invoice create or clear.

Important rule:

> Staff cannot create invoice item templates.
> They can only assign dynamic pricing to existing invoice items created by Admin.
> Which Staff actions a user may perform is determined by their staff type (Accountant, Booking Manager, or Logistics Officer).

---

### 4.3 Agent (Customer Support – WhatsApp Role)

Agents operate the WhatsApp communication system.

Responsibilities:

* Respond to customer inquiries
* Handle shipment tracking questions
* Manage escalation to staff/admin
* View shipment data (read-only operational access)
* Attach shipment references to conversations
* Log communication activities

Agents operate through a **Dedicated Communication Interface** that includes:

* Conversation panel
* Customer profile
* Shipment summary
* Escalation controls
* Internal notes

---

### 4.4 Shipper (Customer)

The client shipping vehicles.

Capabilities (limited access portal):

* View shipment status
* View invoices
* Download Dock Receipt
* View Bill of Lading (if permitted)
* Track vehicle details

Shippers do not manage system data.

---

### 4.5 Driver

Responsible for vehicle transport to port.

Capabilities:

* View assigned pickup jobs
* Confirm vehicle pickup
* Upload proof (optional phase 2)
* Access Dock Receipt for port processing
* Update delivery status

Drivers operate with limited system privileges.

---

## 5. Core Modules

### 5.1 Shipment Management Module

Handles full shipment lifecycle:

* **Shipper registration:** New shippers (customers) are registered from the system (by Admin/Staff or self-registration as defined in configuration). Once registered, a shipper can create pre-alerts and use the portal or WhatsApp.
* **Pre-alert flow:** Shipper provides VIN (and optionally receipt/bill of sale). System calls the vehicle details API, **stores the full API response** in a dedicated table (to avoid paying for duplicate lookups), then creates the pre-alert linked to that stored response. Admin or authorised Staff convert pre-alert to shipment.
* Auction data intake (designated vehicle API; response stored for reuse)
* Vehicle record creation
* Shipment assignment and driver allocation
* Port status tracking
* Three status dimensions (logistics, invoice, payment/clearance)

---

### 5.2 Document Management Module

Supports generation and storage of:

* Dock Receipt (Generated by Anka)
* Bill of Lading (Uploaded from ocean carrier)
* Vehicle Receipt (From auction company)
* Other shipment-related documents

All documents are:

* Linked to shipment
* Versioned
* Downloadable
* Stored securely

---

### 5.3 Invoice Management Module

#### Invoice Item Structure

Invoice Items are template-based.

Created by:

* Admin only

Contain:

* Item Name
* Invoice Type
* Description

Staff Workflow:

* Select existing invoice item
* Add dynamic price
* Attach to invoice
* Generate final invoice

Pricing is not fixed in the template.
Pricing is determined per shipment.

---

### 5.4 WhatsApp Communication Module

**Two layers: Automation first, Agent on escalation.**

* **Automation (first):** Almost everything the shipper can do on the platform can be done via WhatsApp without a human. Menu-driven self-service: (1) **Get shipment information** – shipper sends VIN or reference number; system returns status and can send documents (e.g. Dock Receipt) via WhatsApp Business API. (2) **Create pre-alert** – shipper sends VIN and receipt/bill of sale (e.g. image/document); system calls vehicle API, stores response, creates pre-alert and confirms via WhatsApp; if the API fails, system returns an error message only (no manual override on WhatsApp). (3) Other options as defined in menu. Shipper identity by phone number (linked to Shipper record).
* **Agent (after escalation):** Only when the customer escalates (e.g. "Talk to agent") because automation was not sufficient does the conversation go to a human agent. Agent responds through the same WhatsApp channel via the Agent Interface.
* Dedicated Agent Interface: Inbox, conversation threads, shipment linking, internal notes, escalation handling, message logging. Multi-agent support, assignment logic, audit trail.

---

### 5.5 Internal Notification System

Dropdown-style activity notification system (for internal users). Plus **milestone communications to shippers:**

* For each shipment milestone (e.g. pre-alert received, shipment created, driver assigned, dock receipt ready, invoice ready, invoice cleared, shipment completed), the system sends: (1) **Email** to the shipper using a configurable template and the appropriate sender address (e.g. booking@ for booking-related, account@ for payment-related). (2) **WhatsApp** message to the shipper if the 24h session is still active.
* **System settings:** Multiple SMTP mailers (e.g. booking@, account@) and **email templates** per milestone (subject, body, placeholders). Super Admin manages mailer credentials; Admin (or Super Admin) manages templates.
* Internal notifications: real-time feed, unread count, clickable to shipment, role-based visibility. Examples: New shipment created, Driver assigned, Dock Receipt generated, Agent escalated conversation. Notifications persist when user is offline (subject to retention policy).

---

### 5.6 Activity Logging & Audit Trail

Every major system action logs:

* User
* Role
* Timestamp
* Action
* Entity affected

Used for:

* Accountability
* Operational tracing
* Compliance
* Internal monitoring

---

### 5.7 Auction Integration Module

Consumes RapidAPI auction response:

* Vehicle details
* VIN
* Photos
* Sales history
* Lot number
* Damage report

Stores structured vehicle data for shipment creation.

---

## 6. System Boundaries

### Included

* Shipment lifecycle management
* Document generation
* Invoice processing
* WhatsApp support
* Role-based management
* Notification system
* Auction data integration

### Excluded (Future Phase)

* Payment gateway integration
* Automated customs integration
* Mobile native application
* Advanced analytics dashboards
* Container tracking API

---

## 7. Technology Stack

* Backend: Laravel
* Frontend: Livewire + TailwindCSS
* Database: MySQL
* Hosting: Hostinger
* API Integration: RapidAPI (Auction)
* WhatsApp Integration: WhatsApp Business API
* Document Generation: PDF engine (Laravel compatible)

---

## 8. System Principles

* Role-segregated architecture
* Audit-first design
* Modular scalability
* Operational transparency
* Customer-centric communication
* Dynamic pricing flexibility

---

## 9. Strategic Value

This system will:

* Reduce operational errors
* Improve invoice accuracy
* Enhance customer communication
* Increase accountability
* Create structured internal workflow
* Position Anka competitively against established players

---

