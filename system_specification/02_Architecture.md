# 02_Architecture.md

---

# 02_Architecture.md

## 1. Architectural Overview

The **Anka Shipping & Logistics Management System (ASLMS)** will adopt a **modular monolithic architecture** built on Laravel, optimized for:

* Operational reliability
* Role-based access control
* Document-heavy workflows
* External API integrations
* Real-time communication handling
* Scalable future expansion

Given the current business size and expected concurrency (moderate internal users, limited external portal users), a modular monolith is strategically appropriate over microservices.

---

## 2. Architectural Style

### 2.1 Pattern

The system will follow:

* **MVC (Model–View–Controller)**
* Layered architecture separation
* Service-oriented domain logic
* Event-driven internal notifications

### 2.2 High-Level Layers

1. Presentation Layer (Livewire + Blade + Tailwind)
2. Application Layer (Controllers, Form Requests, Policies)
3. Domain Layer (Services, Actions, Business Logic)
4. Data Layer (Eloquent Models, Repositories)
5. Infrastructure Layer (External APIs, WhatsApp, File Storage)

---

## 3. System Components

### 3.1 Presentation Layer

Built with:

* Laravel Blade
* Livewire (Reactive components)
* TailwindCSS

Responsibilities:

* Dashboard UI
* Role-based menu rendering
* Shipment management interfaces
* WhatsApp communication panel
* Notification dropdown system
* Invoice builder interface
* Document preview and download views

Livewire will handle:

* Real-time updates
* Notification refresh
* Message polling
* Dynamic invoice item addition

---

### 3.2 Application Layer

This layer coordinates:

* HTTP Controllers
* Form validation
* Authorization checks (Policies / Gates)
* Request routing
* Session handling

Each core module has its own controller namespace:

* ShipmentController
* InvoiceController
* AgentController
* NotificationController
* DocumentController
* DriverController
* AdminController

This ensures separation of concerns.

---

### 3.3 Domain Layer (Business Logic)

All business rules are isolated inside Services.

Examples:

* ShipmentService
* InvoiceService
* DockReceiptGenerator
* WhatsAppService
* NotificationService
* AuctionImportService
* DriverAssignmentService

Important principle:

> Controllers must not contain business logic.

All state transitions (e.g., shipment lifecycle) are managed here.

---

### 3.4 Data Layer

Database: MySQL

ORM: Eloquent

Core Models:

* User
* Role
* Shipment
* Vehicle
* Invoice
* InvoiceItem (template)
* InvoiceLine (dynamic price entry)
* Document
* Notification
* Conversation
* Message
* Driver
* ActivityLog

Relationships will be strictly normalized.

---

## 4. Modular Structure

Although monolithic, the system will be logically modularized:

### 4.1 Core Modules

1. User & Role Management
2. Shipment Management
3. Vehicle & Auction Integration
4. Document Management
5. Invoice Management
6. WhatsApp Communication
7. Notification & Activity System
8. Driver Management

Each module:

* Has its own models
* Has its own service layer
* Emits internal events
* Has role-based policies

---

## 5. Shipment Lifecycle Architecture

Shipments will operate using a **state machine model**.

Example States:

* Draft
* Auction Linked
* Driver Assigned
* At Port
* Dock Receipt Generated
* Shipped
* Bill of Lading Uploaded
* Completed
* Cancelled

State transitions are controlled exclusively by ShipmentService.

Invalid transitions will be blocked at domain level.

---

## 6. Invoice Architecture

### 6.1 Invoice Item Template Model

Created by Admin.

Fields:

* name
* type
* description

### 6.2 Invoice Line Model

Created by Staff.

Fields:

* invoice_id
* invoice_item_id
* dynamic_price
* notes

This ensures:

* Template reuse
* Dynamic pricing flexibility
* Financial traceability

---

## 7. Document Generation Architecture

Documents supported:

* Dock Receipt (generated internally)
* Bill of Lading (uploaded)
* Vehicle Receipt (uploaded)

### 7.1 Generation Flow

1. Shipment validated
2. DockReceiptGenerator service invoked
3. PDF rendered using Blade template
4. Stored in secure storage
5. Linked to shipment record
6. Notification emitted

Documents are version-controlled and immutable once finalized.

---

## 8. WhatsApp Communication Architecture

**Two layers: Automation first, Agent on escalation.**

### 8.1 Integration

WhatsApp Business API integration layer. Webhook, message processor, **automation handler** (menu: get shipment info, create pre-alert, send documents), conversation manager. Shipper identified by phone (Shipper record).

### 8.2 Automation (First Layer)

Menu-driven self-service: get shipment information (VIN/ref to status and documents e.g. Dock Receipt via API); create pre-alert (VIN and receipt/bill of sale; vehicle API called, response stored, pre_alert created). No human agent until customer escalates.

### 8.3 Core Entities

* Conversation, Message, Agent, Customer (Shipper)

### 8.4 Escalation Mechanism (Second Layer)

When customer selects Talk to agent: conversation status = escalated; assigned to agent (e.g. round-robin). Agents can tag conversation, link shipment, add notes, respond via same WhatsApp channel. Escalation triggers internal notification events.

---

## 9. Internal Notification System

Event-driven architecture.

### 9.1 Event Types

* ShipmentCreated
* InvoiceUpdated
* DriverAssigned
* DockReceiptGenerated
* ConversationEscalated
* PaymentRecorded

### 9.2 Delivery

Notifications stored in database.

Displayed via:

* Dropdown live component
* Unread counter
* Activity log page

Supports:

* Real-time refresh (polling)
* Read/unread state
* Role-based visibility

---

## 10. Activity Logging

Every critical action triggers:

* ActivityLog record
* User ID
* Role
* Action type
* Entity reference
* Timestamp

Used for:

* Auditing
* Accountability
* Operational tracing

---

## 11. Security Architecture

### 11.1 Authentication

* Laravel authentication
* Password hashing (bcrypt)
* Session-based authentication

### 11.2 Authorization

Role-Based Access Control (RBAC):

* Super Admin
* Admin
* Staff (with staff type: Accountant, Booking Manager, Logistics Officer; permissions vary by type)
* Agent
* Driver
* Shipper

Enforced through:

* Policies
* Middleware
* Route guards

### 11.3 Data Isolation

* Shippers can only view their own shipments
* Drivers can only view assigned jobs
* Agents have restricted operational visibility

---

## 12. External Integrations

### 12.1 Auction API (RapidAPI)

Imports:

* Vehicle details
* Photos
* Sales history
* VIN
* Lot number

Handled by AuctionImportService.

### 12.2 WhatsApp Business API

Handles:

* Incoming messages
* Outgoing messages
* Webhook processing
* Media handling

---

## 13. File Storage Architecture

* Documents stored in secure storage (non-public directory)
* Access controlled via signed URLs
* Optional cloud storage upgrade (S3) in future phase

---

## 14. Scalability Strategy

Current deployment:

* Single Laravel application
* MySQL database
* Shared or VPS hosting (Hostinger)

Future-ready design:

* Queue system (Redis)
* Background jobs for:

  * PDF generation
  * Notification dispatch
  * API imports
* Horizontal scaling via load balancer (future phase)

---

## 15. Deployment Architecture

* GitHub repository
* Testing branch
* Main branch (production)
* Webhook-based deployment
* Environment-based configuration (.env)

---

## 16. Architectural Principles

1. Separation of concerns
2. Role isolation
3. Event-driven internal workflow
4. Immutable financial records
5. Centralized document control
6. Service-based business logic
7. Audit-first design

---
