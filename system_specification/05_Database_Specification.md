
# 05_Database_Specification.md

This document defines the relational database structure for the **Anka Shipping & Logistics Management System (ASLMS)**.

Database Engine: **MySQL 5+**
ORM: **Laravel Eloquent**
Charset: **utf8mb4**
Collation: **utf8mb4_unicode_ci**

---

# 1. Database Design Principles

1. Fully normalized (3NF minimum)
2. Foreign key constrained
3. Audit-first design
4. Soft deletes where operationally required
5. Immutable financial records once finalized
6. Separation of template vs transactional records

---

# 2. Core Identity & Access Tables

---

## 2.1 roles

Stores system roles.

| Field       | Type        | Notes                                |
| ----------- | ----------- | ------------------------------------ |
| id          | BIGINT PK   | Auto increment                       |
| name        | VARCHAR(50) | admin, staff, agent, driver, shipper |
| description | TEXT        | Nullable                             |
| created_at  | TIMESTAMP   |                                      |
| updated_at  | TIMESTAMP   |                                      |

---

## 2.2 users

Stores all authenticated users.

| Field      | Type                | Notes                                                                 |
| ---------- | ------------------- | --------------------------------------------------------------------- |
| id         | BIGINT PK           |                                                                       |
| role_id    | BIGINT FK           | → roles.id                                                            |
| staff_type | VARCHAR(50)         | Nullable. Required when role_id = Staff. Values: accountant, booking_manager, logistics_officer |
| name       | VARCHAR(150)        |                                                                       |
| email      | VARCHAR(150) UNIQUE |                                                                       |
| phone      | VARCHAR(20)         | Nullable                                                              |
| password   | VARCHAR(255)        | Hashed                                                                |
| is_active  | BOOLEAN             | Default true                                                          |
| created_at | TIMESTAMP           |                                                                       |
| updated_at | TIMESTAMP           |                                                                       |

Relationship:

* users.role_id → roles.id

**Staff type:** When `role_id` corresponds to Staff, `staff_type` must be one of: `accountant`, `booking_manager`, `logistics_officer`. Permissions for Staff are enforced by role + staff_type (see 03_Roles_and_Permissions §4.3).

---

# 3. Customer (Shipper) Tables

---

## 3.1 shippers

Represents customers.

| Field          | Type         | Notes                     |
| -------------- | ------------ | ------------------------- |
| id             | BIGINT PK    |                           |
| user_id        | BIGINT FK    | Nullable if portal access |
| company_name   | VARCHAR(150) |                           |
| contact_person | VARCHAR(150) |                           |
| phone          | VARCHAR(20)  |                           |
| email          | VARCHAR(150) |                           |
| address        | TEXT         |                           |
| created_at     | TIMESTAMP    |                           |
| updated_at     | TIMESTAMP    |                           |

Relationship:

* shippers.user_id → users.id (optional)

---

## 3.2 consignees

Recipient/consignee records created by Shipper.

| Field        | Type         | Notes          |
| ------------ | ------------ | -------------- |
| id           | BIGINT PK    |                |
| shipper_id   | BIGINT FK    | → shippers.id  |
| name         | VARCHAR(150)  |                |
| contact      | VARCHAR(150)  | Nullable       |
| phone        | VARCHAR(20)   | Nullable       |
| address      | TEXT          | Nullable       |
| created_at   | TIMESTAMP     |                |
| updated_at   | TIMESTAMP     |                |

---

## 3.3 vehicle_api_responses

Stores the full response from the vehicle-details API (e.g. VIN lookup) so that duplicate API calls are avoided (cost and rate limits). Referenced when creating a pre-alert and when converting to shipment.

| Field          | Type         | Notes                        |
| -------------- | ------------ | ---------------------------- |
| id             | BIGINT PK    |                              |
| vin            | VARCHAR(20)  |                              |
| raw_response   | JSON         | Full API response            |
| requested_at   | TIMESTAMP    | When the API was called      |
| created_at     | TIMESTAMP    |                              |

Index on vin (for lookup before calling API). Optional: pre_alert_id or first_use_shipment_id if tracking first use.

---

## 3.4 pre_alerts

Shipper-created shipping request before conversion to shipment. Can be created via web portal or WhatsApp. Links to stored API response when VIN was used.

| Field                   | Type         | Notes                          |
| ----------------------- | ------------ | ------------------------------ |
| id                      | BIGINT PK    |                                |
| shipper_id              | BIGINT FK    | → shippers.id                  |
| vin                     | VARCHAR(20)  | Nullable                       |
| vehicle_api_response_id | BIGINT FK    | Nullable → vehicle_api_responses.id |
| receipt_media_path     | TEXT         | Nullable; for WhatsApp bill of sale/receipt |
| status                  | VARCHAR(50)  | e.g. pending, converted        |
| reference               | VARCHAR(50)  | Nullable                       |
| notes                   | TEXT         | Nullable                       |
| converted_at            | DATETIME     | Nullable                       |
| shipment_id             | BIGINT FK    | Nullable → shipments.id after conversion |
| created_at              | TIMESTAMP    |                                |
| updated_at              | TIMESTAMP    |                                |

---

# 4. Shipment & Vehicle Tables

---

## 4.1 shipments

Core shipment entity.

| Field            | Type                                      | Notes           |
| ---------------- | ----------------------------------------- | --------------- |
| id               | BIGINT PK                                 |                 |
| reference_no     | VARCHAR(50) UNIQUE                        |                 |
| shipper_id       | BIGINT FK                                 | → shippers.id   |
| vin              | VARCHAR(20)                               |                 |
| make             | VARCHAR(100)                              |                 |
| model            | VARCHAR(100)                              |                 |
| year             | INT                                       |                 |
| color            | VARCHAR(50)                               |                 |
| purchase_price   | DECIMAL(12,2)                             | Nullable        |
| primary_damage   | VARCHAR(150)                              | Nullable        |
| secondary_damage | VARCHAR(150)                              | Nullable        |
| runner_status    | ENUM('runner','non_runner')               |                 |
| auction_name     | VARCHAR(100)                              |                 |
| lot_number       | VARCHAR(100)                              |                 |
| auction_location | VARCHAR(150)                              |                 |
| origin_port      | VARCHAR(150)                              |                 |
| destination_port | VARCHAR(150)                              |                 |
| shipping_company | VARCHAR(150)                              | Nullable        |
| ocean_carrier    | VARCHAR(150)                              | Nullable        |
| booking_number   | VARCHAR(100)                              | Nullable        |
| package_status   | ENUM('pending','in_progress','completed') | Default pending |
| invoice_status   | ENUM('pending','cleared','voided')        | Default pending |
| clearance_status | ENUM('pending','in_progress','completed') | Default pending |
| driver_id        | BIGINT FK                                 | Nullable        |
| created_by       | BIGINT FK                                 | → users.id      |
| created_at       | TIMESTAMP                                 |                 |
| updated_at       | TIMESTAMP                                 |                 |

Relationships:

* shipments.shipper_id → shippers.id
* shipments.driver_id → users.id
* shipments.created_by → users.id

---

## 4.2 vehicle_photos

| Field       | Type          |
| ----------- | ------------- |
| id          | BIGINT PK     |
| shipment_id | BIGINT FK     |
| photo_url   | TEXT          |
| local_path  | TEXT Nullable |
| created_at  | TIMESTAMP     |
| updated_at  | TIMESTAMP     |

FK:

* vehicle_photos.shipment_id → shipments.id

---

## 4.3 vehicle_sales_history

| Field          | Type          |
| -------------- | ------------- |
| id             | BIGINT PK     |
| shipment_id    | BIGINT FK     |
| purchase_price | DECIMAL(12,2) |
| sale_status    | VARCHAR(50)   |
| buyer_country  | VARCHAR(50)   |
| buyer_state    | VARCHAR(50)   |
| sale_date      | DATETIME      |
| created_at     | TIMESTAMP     |
| updated_at     | TIMESTAMP     |

---

# 5. Driver Module

Drivers are users with role=driver.

Optional extension table:

## 5.1 driver_assignments (if history needed)

| Field               | Type              |
| ------------------- | ----------------- |
| id                  | BIGINT PK         |
| shipment_id         | BIGINT FK         |
| driver_id           | BIGINT FK         |
| assigned_at         | DATETIME          |
| confirmed_pickup_at | DATETIME Nullable |
| delivered_at        | DATETIME Nullable |
| created_at          | TIMESTAMP         |
| updated_at          | TIMESTAMP         |

---

# 6. Document Management

---

## 6.1 documents

| Field       | Type                                                                      |
| ----------- | ------------------------------------------------------------------------- |
| id          | BIGINT PK                                                                 |
| shipment_id | BIGINT FK                                                                 |
| type        | ENUM('title','dock_receipt','bill_of_lading','vehicle_receipt','invoice') |
| file_path   | TEXT                                                                      |
| version     | INT Default 1                                                             |
| uploaded_by | BIGINT FK                                                                 |
| created_at  | TIMESTAMP                                                                 |
| updated_at  | TIMESTAMP                                                                 |

Relationships:

* documents.shipment_id → shipments.id
* documents.uploaded_by → users.id

---

# 7. Invoice System

---

## 7.1 invoice_item_templates

Created by Admin only.

| Field       | Type         |
| ----------- | ------------ |
| id          | BIGINT PK    |
| name        | VARCHAR(150) |
| type        | VARCHAR(100) |
| description | TEXT         |
| created_by  | BIGINT FK    |
| created_at  | TIMESTAMP    |
| updated_at  | TIMESTAMP    |

---

## 7.2 invoices

| Field          | Type                               |
| -------------- | ---------------------------------- |
| id             | BIGINT PK                          |
| shipment_id    | BIGINT FK                          |
| invoice_number | VARCHAR(50) UNIQUE                 |
| total_amount   | DECIMAL(14,2)                      |
| status         | ENUM('pending','cleared','voided') |
| created_by     | BIGINT FK                          |
| finalized_at   | DATETIME Nullable                  |
| created_at     | TIMESTAMP                          |
| updated_at     | TIMESTAMP                          |

---

## 7.3 invoice_lines

Transactional invoice rows.

| Field                    | Type          |
| ------------------------ | ------------- |
| id                       | BIGINT PK     |
| invoice_id               | BIGINT FK     |
| invoice_item_template_id | BIGINT FK     |
| description              | TEXT          |
| amount                   | DECIMAL(14,2) |
| created_at               | TIMESTAMP     |
| updated_at               | TIMESTAMP     |

Relationships:

* invoice_lines.invoice_id → invoices.id
* invoice_lines.invoice_item_template_id → invoice_item_templates.id

---

# 8. WhatsApp Communication Module

---

## 8.1 conversations

| Field              | Type                                           |
| ------------------ | ---------------------------------------------- |
| id                 | BIGINT PK                                      |
| shipper_id         | BIGINT FK Nullable                             |
| assigned_agent_id  | BIGINT FK Nullable                             |
| status             | ENUM('active','escalated','assigned','closed') |
| session_expires_at | DATETIME Nullable                              |
| created_at         | TIMESTAMP                                      |
| updated_at         | TIMESTAMP                                      |

---

## 8.2 messages

| Field               | Type                                    |
| ------------------- | --------------------------------------- |
| id                  | BIGINT PK                               |
| conversation_id     | BIGINT FK                               |
| direction           | ENUM('inbound','outbound')              |
| message_type        | ENUM('text','image','audio','document') |
| body                | TEXT Nullable                           |
| media_path          | TEXT Nullable                           |
| whatsapp_message_id | VARCHAR(150) Nullable                   |
| created_at          | TIMESTAMP                               |
| updated_at          | TIMESTAMP                               |

---

# 9. Notifications & Activity Logging

---

## 9.1 notifications

| Field       | Type                  |
| ----------- | --------------------- |
| id          | BIGINT PK             |
| user_id     | BIGINT FK             |
| title       | VARCHAR(150)          |
| message     | TEXT                  |
| entity_type | VARCHAR(50)           |
| entity_id   | BIGINT                |
| is_read     | BOOLEAN Default false |
| created_at  | TIMESTAMP             |
| updated_at  | TIMESTAMP             |

---

## 9.2 activity_logs

| Field         | Type          |
| ------------- | ------------- |
| id            | BIGINT PK     |
| user_id       | BIGINT FK     |
| role_snapshot | VARCHAR(50)   |
| action        | VARCHAR(150)  |
| entity_type   | VARCHAR(50)   |
| entity_id     | BIGINT        |
| old_values    | JSON Nullable |
| new_values    | JSON Nullable |
| created_at    | TIMESTAMP     |

---

# 10. Indexing Strategy

Add indexes on:

* shipments.reference_no
* shipments.vin
* vehicle_api_responses.vin
* pre_alerts.shipper_id
* pre_alerts.status
* invoices.invoice_number
* conversations.status
* messages.conversation_id
* notifications.user_id
* activity_logs.user_id

Foreign keys should be indexed automatically.

---

# 11. Soft Deletes Strategy

Apply soft deletes on:

* shipments
* invoices
* invoice_item_templates
* shippers

Avoid soft deletes on:

* invoice_lines
* activity_logs
* notifications

---

# 12. Data Integrity Rules

1. Invoice total must equal SUM(invoice_lines.amount).
2. Invoice cannot be modified after status = cleared.
3. Shipment cannot be deleted if invoice exists.
4. Document version increments on replacement.
5. Unique VIN per active shipment (optional constraint).

---

# 13. System Settings (Email and Communications)

## 13.1 mailers

Configurable SMTP mailers for different sender addresses (e.g. booking@, account@). Super Admin manages. Stored in DB or config; if DB, table below.

| Field           | Type         | Notes                    |
| --------------- | ------------ | ------------------------- |
| id              | BIGINT PK    |                           |
| name            | VARCHAR(50)  | e.g. booking, account     |
| from_address    | VARCHAR(150) |                           |
| from_name       | VARCHAR(100) | Nullable                  |
| smtp_host       | VARCHAR(150) |                           |
| smtp_port       | INT          |                           |
| smtp_username   | VARCHAR(150) | Nullable                  |
| smtp_password   | TEXT         | Encrypted                 |
| is_active       | BOOLEAN      | Default true              |
| created_at      | TIMESTAMP    |                           |
| updated_at      | TIMESTAMP    |                           |

---

## 13.2 email_templates

One record per milestone (or per email type). Admin (or Super Admin) edits. Used for milestone emails to shippers.

| Field        | Type         | Notes                                      |
| ------------ | ------------ | ------------------------------------------ |
| id           | BIGINT PK    |                                            |
| name         | VARCHAR(100) | e.g. pre_alert_received, shipment_created  |
| mailer_id    | BIGINT FK    | → mailers.id (which from-address)          |
| subject      | VARCHAR(255) | With placeholders                          |
| body_html    | TEXT         | With placeholders e.g. {{shipper_name}}    |
| is_active    | BOOLEAN      | Default true                               |
| created_at   | TIMESTAMP    |                                            |
| updated_at   | TIMESTAMP    |                                            |

---

## 13.3 sent_communications (optional)

Log of sent milestone emails/WhatsApp for support and auditing.

| Field          | Type          | Notes                |
| -------------- | ------------- | -------------------- |
| id             | BIGINT PK     |                      |
| channel        | ENUM('email','whatsapp') |              |
| recipient_type | VARCHAR(50)   | e.g. shipper         |
| recipient_id   | BIGINT        | e.g. shipper_id      |
| template_name  | VARCHAR(100)  | Nullable             |
| milestone      | VARCHAR(100)  | Nullable             |
| shipment_id    | BIGINT FK     | Nullable             |
| sent_at        | TIMESTAMP     |                      |

---

# 14. Future Expansion Ready

Schema supports:

* Multi-branch support
* Payment integration
* Multi-currency
* Accounting export
* Microservice migration

---

