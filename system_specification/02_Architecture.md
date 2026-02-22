
---

# 02_Architecture.md

---

# 2. System Architecture

---

## 2.1 Architectural Overview

The Anka Shipping Operations & Documentation Management System (ASODMS) shall follow a **layered modular architecture** based on the MVC (Model–View–Controller) pattern, enhanced with service-layer abstraction and event-driven processing.

The architecture is designed to ensure:

* Separation of concerns
* Maintainability
* Scalability
* Compliance traceability
* Secure document handling
* Transaction-safe financial operations

---

## 2.2 Technology Stack

| Layer             | Technology                            |
| ----------------- | ------------------------------------- |
| Backend Framework | Laravel                               |
| Frontend          | Livewire + Tailwind CSS               |
| Database          | MySQL                                 |
| Queue System      | Redis                                 |
| File Storage      | Private Storage (Local/S3-ready)      |
| PDF Engine        | DomPDF (or equivalent)                |
| Email             | SMTP                                  |
| WhatsApp          | WhatsApp Business API                 |
| Authentication    | Laravel Auth + Role/Permission System |

---

## 2.3 Architectural Layers

The system shall be structured into the following logical layers:

---

### 2.3.1 Presentation Layer

**Purpose:**
Handle user interaction and UI rendering.

**Components:**

* Livewire components
* Blade templates
* Tailwind-based UI design
* Notification dropdown UI
* Dashboard panels
* Forms (Shipment, Invoice, Dock Receipt preview)

**Responsibilities:**

* Accept validated input
* Trigger application actions
* Display real-time state updates
* Display internal notifications
* Display shipment timeline

---

### 2.3.2 Application Layer

**Purpose:**
Coordinate business processes.

**Components:**

* Controllers
* Livewire handlers
* Form request validators
* Middleware
* Authorization gates

**Responsibilities:**

* Validate user permissions
* Route requests to services
* Trigger events
* Enforce workflow constraints

---

### 2.3.3 Domain Layer (Core Business Logic)

**Purpose:**
Encapsulate core logistics and financial rules.

This layer shall contain Service Classes such as:

* ShipmentService
* InvoiceService
* WalletService
* DocumentService
* NotificationService
* DriverService
* WhatsAppService
* ActivityLogService

**Responsibilities:**

* Execute business rules
* Manage workflow state transitions
* Ensure transactional integrity
* Coordinate document generation
* Trigger events

---

### 2.3.4 Infrastructure Layer

**Purpose:**
Handle external systems and technical services.

**Components:**

* MySQL Database
* Redis Queue Workers
* File Storage System
* SMTP Mail Server
* WhatsApp Business API
* PDF Generator
* External APIs (e.g., RapidAPI if used)

**Responsibilities:**

* Data persistence
* Background job processing
* Secure document storage
* Email delivery
* WhatsApp message dispatch

---

## 2.4 System Modules

The architecture is modular and divided into the following primary modules:

---

### 2.4.1 Shipment Management Module

Handles:

* Pre-alert creation
* Shipment creation
* Driver assignment
* Port assignment
* Status updates
* Workshop routing (if applicable)

Core Table Examples:

* shipments
* shipment_status_history
* drivers
* ports

---

### 2.4.2 Document Management Module

Handles:

* Auction receipt uploads
* Title uploads
* Dock receipt generation
* Bill of Lading uploads
* Invoice PDF generation

Core Table:

* shipment_documents

---

### 2.4.3 Invoice & Financial Module

Handles:

* Invoice item templates (Admin)
* Dynamic pricing by authorized staff
* Invoice issuance
* Wallet payments
* Transaction logging

Core Tables:

* invoice_items
* invoices
* invoice_line_items
* wallets
* wallet_transactions

---

### 2.4.4 Communication Module

Handles:

* Email notifications
* WhatsApp session-based messaging
* Event-triggered alerts

Core Tables:

* whatsapp_sessions
* outbound_messages
* inbound_messages

---

### 2.4.5 Internal Notification Module

Handles:

* Activity-based notification creation
* Unread count tracking
* Dropdown rendering
* Mark-as-read actions

Core Tables:

* notifications
* notification_reads

---

### 2.4.6 Activity Logging Module

Handles:

* Immutable logs of critical actions
* Financial changes
* Document generation
* Status updates

Core Table:

* activity_logs

---

## 2.5 Event-Driven Architecture

The system shall use Laravel Events and Listeners for automation.

Example Event Flow:

1. TitleUploaded
   → GenerateDockReceipt
   → LogActivity
   → SendEmailNotification

2. InvoiceIssued
   → SendInvoiceEmail
   → SendWhatsAppNotification
   → LogActivity

3. WalletDebited
   → MarkInvoicePaid
   → NotifyShipper
   → LogActivity

All external communications shall be queued.

---

## 2.6 Background Processing

Redis-backed queues shall handle:

* Email sending
* WhatsApp messaging
* PDF generation
* Heavy report generation

This prevents blocking user interaction and improves responsiveness.

---

## 2.7 Database Architecture

The database shall follow:

* Strict foreign key relationships
* Indexed frequently queried columns
* Soft deletes where appropriate
* Transaction wrapping for financial operations

Critical transactional operations:

* Wallet deduction
* Invoice payment processing
* Shipment state transitions

---

## 2.8 Security Architecture

The system shall implement:

### 2.8.1 Role-Based Access Control (RBAC)

* Permissions stored in database
* Middleware enforcement
* Policy-based authorization

---

### 2.8.2 File Security

* Documents stored in private directory
* Access via signed URLs
* Download logged in activity_logs

---

### 2.8.3 Financial Integrity

* Database transactions for wallet deduction
* Invoice locking after issuance
* No direct manual balance editing

---

### 2.8.4 Webhook Security

* WhatsApp webhook signature validation
* Rate limiting
* Request verification

---

## 2.9 Deployment Architecture

The system shall be deployable in the following configuration:

* Web Server (Nginx or Apache)
* PHP Runtime
* MySQL Server
* Redis Server
* Queue Worker Process
* SSL Certificate
* Automated Backup System

Environment separation:

* Development
* Staging
* Production

---

## 2.10 Scalability Considerations

The architecture allows:

* Horizontal scaling of queue workers
* Separation of database and application servers
* Future migration to containerized infrastructure
* API-first extension for mobile application (future phase)

---

## 2.11 Logging & Monitoring

The system shall log:

* Authentication events
* Document generation
* Financial transactions
* Shipment status changes
* Failed jobs

Future enhancement:

* Integration with monitoring tools
* Error reporting dashboards

---

## 2.12 Architectural Principles

The system shall adhere to:

1. Separation of Concerns
2. Single Responsibility Principle
3. Transaction Safety for Financial Logic
4. Event-Driven Automation
5. Compliance-Aware Workflow Enforcement
6. Auditability by Design

---

End of Document
`02_Architecture.md`

---

