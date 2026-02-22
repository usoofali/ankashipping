---

# 01_System_Overview.md

---

# 1. System Overview

## 1.1 System Name

**Anka Shipping Operations & Documentation Management System (ASODMS)**

---

## 1.2 Organization Context

This system is developed for **Anka Shipping & Logistic Services**, a United States-based vehicle export and freight forwarding company serving clients primarily in Nigeria and other West African countries.

The company facilitates:

* Purchase and export of vehicles from the United States
* Inland transportation to US ports
* RoRo (Roll-on/Roll-off) shipping
* Container shipping
* Documentation management
* Client communication and invoicing
* Delivery coordination to West Africa

The system is designed to digitalize, automate, and control the operational workflow of these activities.

---

## 1.3 Purpose of the System

The purpose of this system is to provide a centralized, compliance-aware platform for managing:

* Vehicle shipment lifecycle
* Export documentation
* Dock receipt generation
* Bill of Lading tracking
* Invoice management
* Wallet-based client payments
* Driver coordination
* WhatsApp and email communication
* Internal notifications and activity auditing

The system replaces fragmented manual processes with structured, traceable, and secure digital workflows.

---

## 1.4 Business Objectives

The system shall support the following business objectives:

1. Reduce documentation errors during export processing.
2. Prevent shipment rejection at US port terminals.
3. Ensure compliance with US export regulations.
4. Improve operational efficiency and coordination.
5. Provide financial transparency for clients.
6. Enable structured communication with West African clientele.
7. Maintain complete audit trails for operational and financial events.
8. Reduce dependency on manual WhatsApp coordination.

---

## 1.5 System Scope

### 1.5.1 In Scope

The system shall support:

* Pre-alert vehicle registration
* Shipment creation and lifecycle management
* Driver assignment and coordination
* Title document upload and validation
* Automated Dock Receipt generation
* Bill of Lading upload and tracking
* Invoice creation using dynamic pricing
* Wallet funding and deduction
* Email notifications
* Controlled WhatsApp communication
* Internal notification dropdown system
* Activity logging and auditing
* Role-based access control

---

### 1.5.2 Out of Scope

The following are not included in the initial release:

* GPS vehicle tracking
* Direct integration with US Customs AES filing system
* Real-time ocean vessel tracking
* Automated customs clearance in destination countries
* Payment gateway integration (unless defined in future phases)

---

## 1.6 Operational Geography

### 1.6.1 Origin (United States)

Primary operational ports may include:

* Baltimore, MD
* Houston, TX
* New Jersey

The system must support export compliance and port-specific operational requirements.

---

### 1.6.2 Destination (West Africa)

Primary markets include:

* Nigeria
* Ghana
* Benin
* Other West African countries

The system must support communication patterns and documentation expectations common in West African logistics operations.

---

## 1.7 Business Model Overview

Anka Shipping operates as:

* Freight forwarding intermediary
* Vehicle export coordinator
* Documentation processor
* Client financial handler
* Port delivery facilitator

The company does not operate ocean vessels but works with licensed ocean carriers to transport cargo internationally.

The system must therefore:

* Manage documentation before and after carrier issuance
* Track third-party documentation (e.g., Bill of Lading)
* Generate company-controlled documents (e.g., Dock Receipt, Invoice)

---

## 1.8 High-Level System Capabilities

The system shall provide:

### 1. Shipment Lifecycle Control

Structured workflow from:
Pre-alert → Shipment → Port Delivery → Vessel Loading → Arrival → Delivery

---

### 2. Compliance-Oriented Document Management

Support for:

* Auction Receipts
* Vehicle Titles
* Dock Receipts (System Generated)
* Bill of Lading uploads
* Invoice PDFs

Document sequencing rules must be enforced.

---

### 3. Financial Control Module

* Invoice item template creation (Admin)
* Dynamic pricing by authorized staff
* Wallet-based payment system
* Financial transaction logging

---

### 4. Communication Module

* Email notification engine
* WhatsApp session-aware messaging
* Internal notification dropdown system
* Activity logs for communication events

---

### 5. Role-Based Access & Audit

* Defined user roles
* Permission-based operations
* Immutable activity logs
* Action traceability

---

## 1.9 System Users

The system will be used by:

* Super Administrator
* Operations Staff
* Invoice Staff
* WhatsApp Agents
* Drivers
* Shippers (Customers)

Each role shall have defined permissions and restricted access.

---

## 1.10 Technology Stack Overview

The system will be developed using:

* Backend Framework: Laravel
* Frontend Stack: Livewire + Tailwind CSS
* Database: MySQL
* Queue System: Redis
* Email: SMTP
* WhatsApp: WhatsApp Business API
* PDF Generation: DomPDF or equivalent
* Storage: Secure private file storage

---

## 1.11 System Design Principles

The system shall be designed based on:

1. Compliance-first architecture
2. Event-driven automation
3. Role-based access control
4. Transaction-safe financial operations
5. Scalable queue processing
6. Secure document handling
7. Clear auditability

---

## 1.12 Strategic Positioning

This system is not merely a logistics tracking application.

It is a:

**Cross-Border Export Operations, Documentation, and Financial Control Platform**
for US-to-West Africa vehicle logistics.

It is designed to support current operations and scale with business growth into additional markets.

---
