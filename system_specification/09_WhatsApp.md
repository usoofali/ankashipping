# ANKA Shipping & Logistics

## WhatsApp Business Cloud API Support & Automation System

**Stack: Laravel + Livewire + Tailwind + MySQL**

---

# 1. Executive Overview

This system extends ANKA’s legacy shipping platform by integrating WhatsApp Business Cloud API to enable:

* Automated shipment tracking
* Document delivery (PDF, invoice, receipt)
* Status notifications
* Escalation to human support
* Agent-based conversation management
* Full audit and reporting

The system is composed of:

1. WhatsApp Cloud API Integration Layer
2. Laravel Backend (Business Logic)
3. MySQL Database
4. Livewire Agent Dashboard
5. Admin Control Panel
6. Media Storage Layer
7. Legacy System Integration Layer

---

# 2. High-Level Architecture

Meta Cloud API
↓
Webhook (Laravel Controller)
↓
Message Processing Service
↓
Database (MySQL)
↓
Livewire Agent/Admin Interface
↓
Outbound WhatsApp Service

---

# 3. Core System Modules

## 3.1 WhatsApp Integration Module

Responsible for:

* Receiving webhook events
* Downloading media (audio, PDF, image)
* Managing conversation sessions
* Sending outbound text, templates, and media
* Enforcing 24-hour session rules

### Core Services

App\Services\WhatsApp\MessageService

* sendText()
* sendTemplate()
* sendDocument()
* sendAudio()

App\Services\WhatsApp\WebhookProcessor

* handleIncomingMessage()
* handleMediaDownload()
* detectEscalation()
* updateSessionExpiry()

---

## 3.2 Conversation Management Engine

Handles:

* Conversation creation
* Escalation
* Agent assignment
* Session window validation
* Status transitions

Conversation statuses:

* active (automation running)
* escalated (awaiting agent)
* assigned (agent responding)
* closed

---

## 3.3 Agent Support System

Built entirely with Laravel + Livewire.

Features:

* Real-time chat (polling or websockets)
* Audio playback
* PDF preview links
* Shipment details panel
* Conversation assignment
* Session expiry detection
* Template-based replies (when session expired)

---

## 3.4 Admin Management System

Admin capabilities:

* Create / deactivate agents
* Monitor all conversations
* View SLA metrics
* View escalation logs
* View audit logs
* Manage WhatsApp templates
* Access reports

---

# 4. Database Architecture (MySQL)

## 4.1 users

id
name
email
password
role (admin / agent)
is_active
timestamps

---

## 4.2 customers

id
name
phone_number (unique, E.164)
email (nullable)
timestamps

---

## 4.3 conversations

id
customer_id
status (active / escalated / assigned / closed)
assigned_agent_id (nullable)
session_expires_at
last_message_at
timestamps

---

## 4.4 messages

id
conversation_id
direction (inbound / outbound)
type (text / image / document / audio)
body (nullable)
media_path (nullable)
whatsapp_message_id
timestamps

---

## 4.5 shipments

id
customer_id
vin
tracking_number
status
current_port
timestamps

---

## 4.6 documents

id
shipment_id
type (receipt / title / gate_pass / bol / invoice / paid_invoice)
file_path
uploaded_by
timestamps

---

# 5. Escalation System Design

## 5.1 Trigger

Customer selects:
"Talk to Support"

System updates:

conversation.status = escalated
automation paused

---

## 5.2 Queue Management

Escalated conversations appear in:

Unassigned Queue

Agent clicks:
Take Conversation

System sets:
assigned_agent_id = agent_id
status = assigned

---

## 5.3 Agent Chat Flow

Agent can:

* View conversation history
* Play voice notes
* View PDFs
* Send text
* Send media
* Use approved templates (if session expired)
* Close conversation

---

# 6. Audio & Media Handling

## 6.1 Incoming Audio

1. Webhook receives media ID
2. System calls Meta Graph API
3. Downloads audio file
4. Stores in storage/app/whatsapp/
5. Saves media_path in messages table

## 6.2 Agent Playback

Frontend renders:

HTML5 audio player referencing stored file.

Agents can respond with:

* Text
* Audio (optional enhancement)
* Document

---

# 7. Session Window Enforcement (24-Hour Rule)

Each inbound message updates:

session_expires_at = now() + 24 hours

Before sending free-text reply:

If now > session_expires_at:

* Disable text input
* Require template message

---

# 8. Livewire Interface Structure

## Agent Routes

/agent/dashboard
/agent/conversations
/agent/chat/{conversation}

---

## Admin Routes

/admin/dashboard
/admin/agents
/admin/reports
/admin/templates
/admin/audit

---

## Livewire Components

Agent:

* Dashboard
* ConversationList
* Chat

Admin:

* AgentManager
* ConversationMonitor
* ReportsPanel

---

# 9. Security Design

* Role-based middleware
* Agent can only access assigned conversations
* Webhook signature validation
* Media stored outside public directory
* Rate-limited webhook endpoint
* Full audit logging

---

# 10. Real-Time Strategy

Phase 1:

* Livewire polling (3–5 seconds)

Phase 2:

* Redis
* Laravel Echo
* WebSockets

---

# 11. Storage Strategy

Development:

* Local disk

Production:

* AWS S3 or DigitalOcean Spaces

Never expose raw storage paths publicly.

---

# 12. Legacy System Integration

Two approaches:

Option A – Direct Database Sync
WhatsApp system reads shipment data from legacy DB (read-only connection).

Option B – Internal API
Legacy system exposes secure API endpoints:

* Get shipment by VIN
* Get customer by phone
* Get documents
* Get shipment status

Recommended: API-based integration for clean separation.

---

# 13. Reporting & Metrics

Track:

* Total conversations
* Escalation rate
* Average response time
* Agent performance
* Session expiry rate
* Message volume (inbound/outbound)

---

# 14. Scalability Roadmap

Phase 1:
Single server
Polling
MySQL

Phase 2:
Redis queue
Background media processing
WebSockets

Phase 3:
Microservice separation:

* WhatsApp Service
* Support Service
* Shipment Service

---

# 15. Production Deployment Checklist

* HTTPS enforced
* Webhook verified
* Queue workers running
* Supervisor configured
* Storage linked
* Token secured in environment file
* Logging centralized
* Backup configured

---

# 16. Final System Capabilities

The ANKA WhatsApp System will:

* Automate shipment tracking
* Send shipping documents
* Handle media messages
* Support voice notes
* Escalate to human agents
* Provide admin oversight
* Maintain audit logs
* Scale with company growth

---
