# Application architecture through Phase 4

The web entry point is `public/index.php`. Routes map explicit HTTP methods to controllers. Authorization reloads the user from the database on every protected request; posted roles and candidate IDs are never used to authenticate. Views escape all dynamic values through `View::escape`. Controllers delegate account logic to services; repositories hold profile lookups. PDO uses real prepared statements.

`Config` accepts process environment variables first and a private root `.env` second. Environment parsing supports simple key/value pairs and enclosing quotes, without interpolation or inline comments. No Composer dependencies are needed in this phase. PHP 8.2 or later is required.

## Authentication and privacy

Candidates sign in using a canonical international phone number; Indian 10-digit mobile numbers gain +91. Administrators can use a username, email or phone (the provisioning command creates a username). Public registration always creates CANDIDATE. Passwords use password_hash / password_verify with PASSWORD_DEFAULT and a 72-byte input limit to prevent bcrypt truncation. Session IDs rotate on authentication and logout. Cookies are HttpOnly, SameSite=Lax, path scoped and Secure when APP_URL uses HTTPS. Idle timeout defaults to 30 minutes; authenticated sessions have a 12-hour absolute limit.

Candidate skills are normalized through `candidate_technologies`, with a unique `(candidate_id, technology_id)` pair and a reverse lookup index. A profile may have up to 20 skills. The older nullable `candidate_profiles.technology_id` column remains only as a migration and legacy-fixture bridge; application writes use the junction table. `interviews.technology_id` remains one independent primary technology for that interview, so scheduling and report dimensions keep their original meaning.

All POST routes verify a synchronizer CSRF token. Rate limits atomically increment InnoDB counters before authentication, keyed with HMAC rather than storing raw identifiers or IPs. Each identity permits 10 attempts / 15 minutes; each source IP permits 50 / 15 minutes. Registration permits 10 / hour per IP. Failed and successful attempts both count. REMOTE_ADDR is used directly: no untrusted forwarded headers. Adjust thresholds only after observing shared-office traffic.

Photos are optional so initials remain a supported profile presentation. JPEG/PNG uploads have MIME, upload-source, byte and dimension checks; GD crops and re-encodes to 256px JPEG, removing metadata and original bytes. Random filenames live outside the public directory. An authenticated, candidate-only route serves the current user's photo. No public directory browsing or arbitrary user-ID photo endpoint exists.

Logs include an opaque reference, exception class and source location; not raw exception messages, form values, passwords or SQL arguments. Administrative technology changes record before/after values within the same transaction as the change. Registration and CLI admin provisioning are audited.

## Database and business time

All database connections explicitly use UTC; DATETIME timestamps are UTC unless they are `business_hours` local wall-clock times. Business timezone is seeded as Asia/Kolkata. Scheduling converts candidate input from the configured business timezone to UTC and converts stored values back for display. Master records can be deactivated without deleting history. No candidates, interviews, or technologies are invented in production seeds. Technologies are added through the administrator screen.

The existing conflict indexes support candidate, support-person, resource, and calendar queries. MySQL does not provide a portable exclusion constraint for time intervals, so integrity comes from the transaction protocol and shared scheduling mutex described below.

## Scheduling domain

`BusinessHoursService`, `AvailabilityService`, `ResourceAssignmentService`, and `BookingService` form one scheduling path. `Clock` provides a real production clock and `FrozenClock` provides deterministic test time. All creation, rescheduling, and cancellation writes:

1. Begin an InnoDB transaction.
2. Lock scheduling_mutex row 1 using SELECT ... FOR UPDATE before other scheduling reads or writes.
3. Read current business settings and affected entities after acquiring the mutex.
4. Validate role/ownership, active entities, supported duration/type, local business day/hours, minimum advance time, candidate change cutoff, and candidate self-conflict.
5. Check BOOKED intervals with start_time < requested_end AND end_time > requested_start for resources, selected support person and candidate. Exclude the booking being rescheduled.
6. Choose the first free compatible active resource ordered by id. Tool bookings require both resource and support-person availability.
7. Persist the booking change and audit event, then commit; roll back on any failure.

The shared mutex intentionally serializes scheduling mutations, a portable strategy for this small shared-hosting workload. Informational availability reads without locking and never guarantees submission. Automated tests launch separate PHP processes against MariaDB to verify that competing requests cannot double-book the final resource or one support person. Future admin scheduling and configuration mutation paths must use the same mutex. Admin overrides may bypass candidate cutoffs, never integrity conflicts.

`ResourceAssignmentService` queries active resources dynamically. It removes conflicting resources, ranks the remainder by the number of booked interviews overlapping the local business date, then breaks ties by stable resource ID. Cancelled interviews do not contribute to conflicts or load.

All persisted interview timestamps are UTC. Input date/time values are interpreted using the configured `business_timezone`; displays convert UTC back to that timezone. Start times must be aligned to 15 minutes. The server derives end time from the allowed duration and never accepts a client end time.

Candidate detail and mutation queries include both interview ID and authenticated candidate ID. The availability API validates ownership before excluding an existing interview during rescheduling. Administrator repositories deliberately query across candidates.

## UI boundaries

Local Tailwind CSS is compiled and committed. Node is used only by developers to rebuild assets; the server has no Node dependency. Custom tokens and component classes provide buttons, fields, radios, badges, avatars, cards, metrics, dialogs, notifications, alerts, tables and empty states. SVG icons are local and consistent. Native selects and form validation preserve keyboard support. JS enhances password visibility, mobile navigation, notifications, double-submit protection and deactivation confirmation. Core forms work without JavaScript.

The admin command center presents live metrics, now/next, a bounded day/week calendar, operational directories, and configuration controls. Candidate navigation contains dashboard, booking, interview history, and profile destinations. The candidate booking workflow is progressively enhanced: without JavaScript all form sections remain usable; with JavaScript it becomes a four-step flow with advisory slot refresh and a review screen.

## Phase 3 operations read model

`AdminRepository` is the bounded read side for the command center. Day and week calendar calls select a UTC half-open interval and join candidate, technology, resource, and support data in one query. Dashboard now/next calculations operate on that same day result. Resource and support pages use aggregate queries, and candidate/interview directories use server-side pagination.

`AdminOperationsService` owns configuration mutations. It locks `scheduling_mutex` before changing resource state, support-person state, or business hours. The service re-fetches authoritative rows, checks future active interviews, writes the change, and records the audit entry in one transaction.

The browser calendar uses server-rendered 15-minute cells. Each resource is a dynamic column; booking cards occupy a height matching 30, 45, or 60 minutes. The geometry uses static CSS and document flow, so it remains compatible with the application CSP. On small screens the resource grid is hidden and the same bounded result is presented as an agenda.

## Phase 4 reporting and operational read models

`ReportService` converts inclusive Asia/Kolkata dates into half-open UTC boundaries. `ReportRepository` applies one filter scope to metrics, dimensions, workload, utilization bookings, cancellation attribution, and streamed CSV rows. Aggregates use bounded SQL `COUNT`, `SUM`, and `GROUP BY` queries. PHP fills empty trend buckets and business-hour capacity because those depend on the configured calendar.

Headline Total, Tool, Normal, candidate, technology, and classification figures include BOOKED and CANCELLED records whose scheduled start is in range. Scheduled minutes, support workload, peak times, and utilization use BOOKED records only. Cancellation sources come only from explicit candidate/admin cancellation audit actions.

Resource capacity starts at creation and sums configured open minutes. Hours and active-state history are not versioned, so current hours apply to historical ranges and an inactive resource has no exact deactivation timestamp. The UI states this limitation.

Global admin search has a two-character minimum, 80-character cap, six-result group limit, prepared statements, escaped LIKE metacharacters, and prefix/word-start matching. Name/company indexes support its main prefix path; expected internal scale makes the word-start fallback practical. Audit reads are paginated and use allowlisted categories. Structured detail recursively redacts secret-shaped keys.
