# Scheduling specification

## Time model

The database connection runs in UTC. `interviews.start_time` and `interviews.end_time` are UTC `DATETIME` values. Candidate input is parsed in the timezone stored at `app_settings.business_timezone` (currently `Asia/Kolkata`) with strict `Y-m-d H:i` parsing. The server calculates the end time from one of the allowed durations: 30, 45, or 60 minutes.

Start minutes must be divisible by 15. A start is valid only when it is on an open business day, starts at or after opening, finishes at or before closing, and is at least `advance_booking_minutes` after the injected clock. Equality at the advance boundary is allowed.

Examples with 10:00–19:00 hours:

- 18:00 + 60 minutes is valid.
- 18:15 + 45 minutes is valid.
- 18:15 + 60 minutes is invalid.
- 10:07 is invalid because it is outside the 15-minute grid.

## Conflict rule

Only `BOOKED` interviews participate. An interval conflicts when:

```text
existing.start_time < requested.end_time
AND existing.end_time > requested.start_time
```

This permits back-to-back bookings. The same predicate protects the candidate, the selected Tool support person, and each compatible physical resource. Normal interviews have no support person. Tool interviews require one active support person.

## Availability

`GET /candidate/availability` requires a candidate session and accepts `date`, `duration`, `interview_type`, and `support_person_id` for Tool interviews. During rescheduling it also accepts an ownership-checked `interview_id` so the current reservation can be excluded.

The endpoint builds 15-minute candidates from the configured business-hours row and applies the authoritative evaluator to every start. It returns available starts only. The browser refreshes these results when setup fields change, but they remain advisory; a later request may reserve the last capacity.

## Resource assignment

Candidates never choose a physical computer. The assignment query:

1. Selects active resources matching `TOOL` or `NORMAL`.
2. removes resources with an overlapping booked interview;
3. counts each remaining resource's booked interviews overlapping that local business date;
4. selects the smallest count, then the smallest resource ID.

This distributes work predictably while remaining deterministic.

## Atomic mutations

Creation, cancellation, and rescheduling use this sequence:

```text
BEGIN
SELECT id FROM scheduling_mutex WHERE id = 1 FOR UPDATE
reload and validate authoritative state
check candidate/support/resource conflicts
insert or update the interview
insert the audit event
COMMIT
```

Any exception rolls back. Every application path that can change scheduling capacity must acquire the same mutex. The lock is database-backed and works across independent PHP processes.

Creation reloads the active candidate, technology, support person, business hours, settings, and resources after locking. Rescheduling locks the ownership-scoped existing booking, checks the configured change cutoff, excludes that booking from conflicts, and updates it in place. A failed reschedule never cancels or alters the original reservation. Cancellation changes the status to `CANCELLED`; it does not delete the record. That immediately releases its resource and support person because conflict queries include only `BOOKED`.

## Candidate privacy

Candidate details, cancellation, and rescheduling query with both `interviews.id` and the authenticated `candidate_id`. Unknown and other-candidate IDs receive the same 404 response. Candidates never receive `resource_id` or resource names in booking availability or their interview pages. Administrators can see the internal assignment.

## Audit records

The lifecycle writes `booking.created`, `booking.rescheduled`, and `booking.cancelled` events. Metadata contains schedule and assignment identifiers needed for operations review; it omits passwords, session data, and HR phone numbers.

## Administrator operations

Administrator creation and rescheduling call `AvailabilityService::evaluate` with advance enforcement disabled. Every other validation remains active: 15-minute boundaries, configured business hours, allowed duration, active candidate/technology/resource/support records, candidate overlap, support overlap, and compatible resource capacity. Rescheduling locks the original interview and updates it only after the replacement assignment succeeds.

Administrator cancellation has no time cutoff, remains a soft cancellation, and releases capacity through the existing `status = 'BOOKED'` conflict predicates. Admin lifecycle actions write `admin.booking.*` audit records. When an operation falls inside a candidate cutoff, a separate `admin.cutoff_override` record identifies the operation and actor.

Resource deactivation and resource-type changes are rejected while the resource has future active interviews. Support-person deactivation is rejected while future Tool interviews reference that person. Both checks and writes occur under `scheduling_mutex`.

Business-hour updates validate all seven days, then compare every future active interview in business time with the proposed window. A closed day, later opening, or earlier closing is rejected when it would invalidate a booking. Notice/cutoff values and hours are committed and audited together.
