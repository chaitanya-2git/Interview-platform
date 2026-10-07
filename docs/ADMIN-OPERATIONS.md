# Administrator operations

## Calendar

The day view is the operational source of truth. It renders active resources dynamically, groups Tool resources before Normal resources, and places bookings on 15-minute rows. Card height represents the stored duration. Cancelled records never appear. A current-time line is rendered only for today, and elapsed bookings remain readable with reduced emphasis.

The week view uses a Monday-through-Sunday agenda. Both views query only their selected half-open UTC interval and join all display records in one query. No query is issued per resource.

Desktop and tablet show resource lanes. Screens up to 760px use an agenda with time, candidate, resource, and support. Selecting a booking opens an accessible native dialog as a right drawer on desktop or bottom sheet on mobile. Escape closes the dialog and focus moves to its close control when opened.

## Booking controls

Admins choose an existing candidate and enter the same authoritative interview details as candidate booking. Resources remain auto-assigned. An admin can create, reschedule, or cancel inside candidate notice/change cutoffs, but cannot bypass hours, inactive records, overlaps, or capacity. Admin rescheduling may change type, support person, technology, duration, date, and time. A failed replacement leaves the original row unchanged.

## Capacity safeguards

Resources and support people are never deleted. Deactivation is blocked when active future interviews reference the record. A resource type also cannot change until its future interviews are moved or cancelled. The directory provides an Inspect link pre-filtered to affected interviews.

## Technologies and candidate Tech Stack

Technologies form the shared skill directory. Candidate profiles can select up to 20 entries as their Tech Stack, while each interview continues to use one primary Interview Technology. Deactivation removes a technology from new profile and interview selections without deleting historical candidate or interview associations. Candidate filtering uses Tech Stack membership; reporting technology filters continue to use the interview technology.

## Settings safeguards

Scheduling settings include minimum candidate notice, candidate cancel/reschedule cutoff, and seven-day business hours. Proposed hours are evaluated against all future active bookings while holding the global scheduling mutex. Conflicting changes are rejected with affected interview identifiers; no partial settings update is committed.

## Audit actions

Phase 3 records `admin.booking.created`, `admin.booking.rescheduled`, `admin.booking.cancelled`, `admin.cutoff_override`, resource/support create or update actions, and `settings.scheduling.updated`. Metadata contains scheduling identifiers and before/after values, never passwords or authentication secrets.

## Reports, exports, and audit review

Reports default to This Month and interpret every date in Asia/Kolkata. Filters remain in the URL for internal bookmarks. Totals include cancelled bookings; scheduled minutes, support workload, peak times, and utilization include BOOKED bookings only. The report page states the calculation definitions and capacity-history limitation.

CSV export uses the same normalized filter scope, converts times to local business time, writes UTF-8, and streams rows. Cells beginning with optional whitespace followed by `=`, `+`, `-`, or `@` receive a leading apostrophe before standard CSV quoting. Authentication fields and stored photo names are excluded.

The Audit log is administrator-only and paginated. Its table shows readable labels; details show recursively redacted structured metadata. Cancellation source is attributed only when a matching audit action exists. Older or externally corrected cancellations remain “source unavailable.”
