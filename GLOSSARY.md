#  Glossary

## Movie Booking Distributed System

> **Purpose**: Technical terms and definitions for the Movie Booking Distributed System.  
> **Audience**: All team members, stakeholders, and new developers.

---

## How to Use This Glossary

This glossary helps you understand technical terms used throughout the project documentation.

### Quick Navigation

| Letter  | Key Terms                                            |
| ------- | ---------------------------------------------------- |
| **A**   | ACL, ACID, API Gateway, Argon2id                     |
| **B-C** | Blue-Green, Circuit Breaker, Correlation ID          |
| **D-E** | Distributed Lock, Event-Driven, Eventual Consistency |
| **F-H** | FastAPI, Health Check, HPA                           |
| **I-J** | Idempotency, JWT, JWKS                               |
| **K-M** | Kubernetes, Microservices, Message Broker            |
| **N-P** | Network Policy, PostgreSQL, Prometheus               |
| **R-S** | RabbitMQ, Redis, Saga Pattern                        |
| **T-W** | TLS, UUID, WAF                                       |

### Most Important Terms for Beginners

| Term                | Plain English Explanation                                                    |
| ------------------- | ---------------------------------------------------------------------------- |
| **Microservices**   | Instead of one big program, we have 5 small programs that talk to each other |
| **API Gateway**     | The "front door" that all requests go through                                |
| **JWT**             | A secure "ticket" that proves who you are                                    |
| **Redis**           | Super-fast temporary storage (like a whiteboard vs. a filing cabinet)        |
| **Circuit Breaker** | A safety switch that prevents one failing service from breaking everything   |
| **Saga Pattern**    | A way to coordinate actions across multiple services (like a relay race)     |

---

## A

### ACL (Access Control List)

A list of permissions attached to a resource that specifies which users or services can perform which operations. In Redis, ACL controls which commands clients can execute.

### ACID

**Atomicity, Consistency, Isolation, Durability** - Properties that guarantee database transactions are processed reliably. In our system, individual service databases maintain ACID while cross-service consistency uses eventual consistency.

### API Gateway

A server that acts as the single entry point for all client requests. In our system, NGINX serves as the API gateway, handling routing, rate limiting, and TLS termination.

### Argon2id

A password hashing algorithm that won the Password Hashing Competition. It's resistant to GPU-based attacks and side-channel attacks. We use it for storing user passwords.

---

## B

### Blue-Green Deployment

A deployment strategy that maintains two identical production environments (blue and green). Traffic is switched from one to the other for zero-downtime deployments.

### Booking Service

The microservice responsible for managing movie seat reservations. It handles seat locking, booking creation, and cancellation.

---

## C

### Canary Deployment

A deployment strategy where new code is gradually rolled out to a small subset of users before full deployment, allowing early detection of issues.

### Circuit Breaker

A design pattern that prevents cascading failures by detecting failures and preventing the system from repeatedly trying to execute an operation that's likely to fail.

```
CLOSED → (failures exceed threshold) → OPEN
OPEN → (timeout expires) → HALF-OPEN
HALF-OPEN → (success) → CLOSED
HALF-OPEN → (failure) → OPEN
```

### CQRS (Command Query Responsibility Segregation)

A pattern that separates read and write operations for a data store. Not fully implemented in our system but recommended for the movie catalog.

### Correlation ID

A unique identifier passed through all services for a single request, enabling request tracing across the distributed system.

---

## D

### Database Per Service

A microservices pattern where each service has its own dedicated database. In our target architecture:

- Auth Service → `auth_db`
- Movie Service → `movies_db`
- Booking Service → `bookings_db`
- Payment Service → `payments_db`
- Notification Service → `notifications_db`

### Dead Letter Queue (DLQ)

A queue that stores messages that couldn't be processed successfully after multiple retry attempts. Used for investigating failed message processing.

### Distributed Lock

A lock that works across multiple processes or machines. We use Redis for distributed locking to prevent double-booking of seats.

### Distributed Transaction

A transaction that spans multiple services or databases. We use the Saga pattern instead of traditional distributed transactions.

---

## E

### Event

A notification of something that has happened in the system. Events are immutable facts. Example: `BookingCreated`, `PaymentCompleted`.

### Event-Driven Architecture

An architecture pattern where services communicate through events rather than direct calls. Services publish events to a message broker, and interested services subscribe.

### Event Sourcing

A pattern where state changes are stored as a sequence of events. Not implemented in our current system but considered for audit trail.

### Eventual Consistency

A consistency model where the system guarantees that, given enough time without new updates, all replicas will converge to the same state. Used across our microservices.

---

## F

### FastAPI

A modern, high-performance Python web framework used for building our microservices. Based on Python type hints and async/await.

### Failover

The process of switching to a redundant system when the primary system fails. Redis Sentinel provides automatic failover for Redis.

---

## G

### Gateway

See [API Gateway](#api-gateway).

### Grafana

An open-source analytics and monitoring platform used for visualizing metrics from Prometheus.

---

## H

### Health Check

An endpoint that reports the operational status of a service. We implement:

- **Liveness**: Is the service running?
- **Readiness**: Can the service handle requests?

### Helm

A package manager for Kubernetes that helps define, install, and upgrade applications.

### HPA (Horizontal Pod Autoscaler)

A Kubernetes resource that automatically scales the number of pod replicas based on CPU/memory utilization or custom metrics.

---

## I

### Idempotency

A property where an operation produces the same result regardless of how many times it's executed. Critical for payment processing to prevent duplicate charges.

### Idempotency Key

A unique identifier sent with a request to ensure the operation is only performed once, even if the request is retried.

### Ingress

A Kubernetes resource that manages external access to services, typically HTTP/HTTPS routing.

---

## J

### Jaeger

An open-source distributed tracing system used for monitoring and troubleshooting microservices-based architectures.

### JWT (JSON Web Token)

A compact, URL-safe token format for securely transmitting information between parties. We use JWT with RS256 algorithm for authentication.

### JWKS (JSON Web Key Set)

A set of public keys used to verify JWTs. Our auth service exposes a JWKS endpoint for other services to validate tokens locally.

---

## K

### Kubernetes (K8s)

An open-source container orchestration platform that automates deployment, scaling, and management of containerized applications.

---

## L

### Load Balancing

Distributing network traffic across multiple servers. NGINX and Kubernetes Services provide load balancing in our system.

### Lock Extension

The process of extending a distributed lock's TTL before it expires, allowing long-running operations to complete without losing the lock.

### Loki

A log aggregation system from Grafana Labs, designed to store and query logs from all applications.

---

## M

### Message Broker

A system that translates messages between formal messaging protocols. RabbitMQ serves as our message broker for event-driven communication.

### Microservices

An architectural style where an application is composed of small, independent services that communicate over well-defined APIs.

### Movie Service

The microservice responsible for managing the movie catalog, including movies, theaters, showtimes, and seat layouts.

---

## N

### Network Policy

A Kubernetes resource that specifies how groups of pods can communicate with each other and other network endpoints.

### Notification Service

The microservice responsible for sending notifications (email, SMS, push) to users about bookings, confirmations, and reminders.

---

## O

### Observability

The ability to understand the internal state of a system by examining its outputs (logs, metrics, traces). Our stack includes Prometheus, Grafana, Jaeger, and Loki.

### OpenTelemetry

A collection of tools, APIs, and SDKs for instrumenting, generating, collecting, and exporting telemetry data (metrics, logs, traces).

### Outbox Pattern

A pattern for reliable event publishing where events are stored in an outbox table within the same transaction as the business data, then published asynchronously.

---

## P

### Payment Service

The microservice responsible for processing payments, refunds, and maintaining payment history.

### PDB (Pod Disruption Budget)

A Kubernetes resource that limits the number of pods that can be voluntarily disrupted at once, ensuring high availability during updates.

### PostgreSQL

An open-source relational database used as the primary data store for all our services.

### Prometheus

An open-source monitoring and alerting toolkit used for collecting and querying metrics.

---

## Q

### Queue

A data structure that holds messages for asynchronous processing. RabbitMQ manages our event queues.

---

## R

### RabbitMQ

An open-source message broker that implements AMQP (Advanced Message Queuing Protocol). Used for service-to-service event communication.

### Rate Limiting

Controlling the number of requests a user or service can make in a given time period. Implemented at the API gateway level.

### Redis

An in-memory data store used for caching, distributed locking, and session management.

### Redis Sentinel

A system for managing Redis high availability, providing monitoring, notification, and automatic failover.

### Replica

A copy of a database or service instance for redundancy and load distribution.

### Retry with Exponential Backoff

A strategy where failed operations are retried with increasing delays between attempts:

```
delay = base_delay * (2 ^ attempt) + jitter
```

### RBAC (Role-Based Access Control)

A method of regulating access based on the roles of individual users. Our system defines roles: admin, manager, staff, customer.

### RS256

RSA Signature with SHA-256, an asymmetric algorithm used for signing JWTs. The private key signs, and the public key verifies.

---

## S

### Saga Pattern

A pattern for managing distributed transactions across microservices. Each service performs its transaction and publishes an event; if any step fails, compensating transactions are executed.

```
Choreography Saga (our implementation):
Booking → (BookingCreated) → Payment → (PaymentCompleted) → Notification
                             ↓ failure
                         (PaymentFailed) → Booking cancels reservation
```

### Scaling

- **Horizontal Scaling**: Adding more instances (pods) of a service
- **Vertical Scaling**: Adding more resources (CPU/memory) to existing instances

### Service Mesh

A dedicated infrastructure layer for handling service-to-service communication. Not implemented but recommended for production (Istio/Linkerd).

### Showtime

A specific screening of a movie at a particular theater and time. Contains available seats and pricing.

### SPOF (Single Point of Failure)

A component that, if it fails, will cause the entire system to fail. Our architecture eliminates SPOFs through redundancy.

---

## T

### TDE (Transparent Data Encryption)

Database feature that encrypts data at rest without requiring application changes.

### TLS (Transport Layer Security)

A cryptographic protocol for secure communication. We use TLS 1.3 for all external and internal communications.

### Topic Exchange

A RabbitMQ exchange type that routes messages based on pattern matching between the routing key and the binding pattern.

### Trace

A complete path of a request through the distributed system, consisting of multiple spans.

### Transaction Outbox

See [Outbox Pattern](#outbox-pattern).

---

## U

### UUID (Universally Unique Identifier)

A 128-bit identifier used for uniquely identifying resources across the distributed system. Format: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`

---

## V

### Vault (HashiCorp Vault)

A secrets management tool for securely storing and accessing sensitive data like API keys, passwords, and certificates.

---

## W

### WAF (Web Application Firewall)

A firewall that monitors and filters HTTP traffic to protect web applications from attacks like SQL injection and XSS.

---

## Acronyms Quick Reference

| Acronym | Full Form                                      |
| ------- | ---------------------------------------------- |
| ACL     | Access Control List                            |
| ACID    | Atomicity, Consistency, Isolation, Durability  |
| AMQP    | Advanced Message Queuing Protocol              |
| API     | Application Programming Interface              |
| CI/CD   | Continuous Integration / Continuous Deployment |
| CORS    | Cross-Origin Resource Sharing                  |
| CQRS    | Command Query Responsibility Segregation       |
| DLQ     | Dead Letter Queue                              |
| DNS     | Domain Name System                             |
| GDPR    | General Data Protection Regulation             |
| HA      | High Availability                              |
| HPA     | Horizontal Pod Autoscaler                      |
| HTTP    | HyperText Transfer Protocol                    |
| JWT     | JSON Web Token                                 |
| JWKS    | JSON Web Key Set                               |
| K8s     | Kubernetes                                     |
| LB      | Load Balancer                                  |
| mTLS    | Mutual TLS                                     |
| OIDC    | OpenID Connect                                 |
| ORM     | Object-Relational Mapping                      |
| PCI-DSS | Payment Card Industry Data Security Standard   |
| PDB     | Pod Disruption Budget                          |
| PII     | Personally Identifiable Information            |
| RBAC    | Role-Based Access Control                      |
| REST    | Representational State Transfer                |
| RPC     | Remote Procedure Call                          |
| SIEM    | Security Information and Event Management      |
| SLA     | Service Level Agreement                        |
| SOC     | Service Organization Control                   |
| SPOF    | Single Point of Failure                        |
| SQL     | Structured Query Language                      |
| SRE     | Site Reliability Engineering                   |
| SSO     | Single Sign-On                                 |
| TDE     | Transparent Data Encryption                    |
| TLS     | Transport Layer Security                       |
| TTL     | Time To Live                                   |
| UUID    | Universally Unique Identifier                  |
| WAF     | Web Application Firewall                       |
| XSS     | Cross-Site Scripting                           |

---

_Glossary v2.0_  
_Movie Booking Distributed System - CSE702063_  
_January 2026_
