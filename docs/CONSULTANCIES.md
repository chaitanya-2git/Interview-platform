# Partner consultancies

Consultancies book interview support for their own students. Each booking carries the student's name, so the BluJay team never sees a consultancy booking as one repeated person.

## Accounts

- **Sign-up:** "Continue with Google" → first-time accounts choose *I'm a candidate* or *I'm a consultancy* (`/register/google`). Consultancies enter name, contact person, phone, optional city and website (`/register/consultancy`).
- **One login per consultancy:** a `users` row with role `CONSULTANCY` (Google sign-in only) owning one `consultancies` row.
- **Approval:** new consultancies are `PENDING` and see a waiting page. Admins approve, reject, suspend or reactivate at **Admin → Consultancies** (`/admin/consultancies`), optionally with a note the consultancy sees. Only `APPROVED` consultancies can add students or book.

## Students

- A student is a normal candidate record (`users` + `candidate_profiles`) with `candidate_profiles.consultancy_id` set, so booking rules, conflict checks, the calendar, search (including "Gen AI 2+") and reports all apply unchanged.
- Added at `/consultancy/students/new`: resume required (Gemini auto-fill works here), photo optional, total experience and skills with years.
- **Phone numbers are unique.** A number already registered with BluJay (as a candidate or another consultancy's student) is rejected.
- Students cannot sign in themselves: password login and "Forgot password" tell them their profile is managed by their consultancy.

## Bookings

- `/consultancy/book` → choose the student → the standard four-step booking flow. Reschedule and cancel follow the same cutoffs as candidates. There is no booking limit; free slots are the only constraint.
- `interviews.consultancy_id` and `interviews.booked_by_user_id` record who booked. Audit actions: `consultancy.booking.created|rescheduled|cancelled`, `consultancy.student.added`, `admin.consultancy.approve|reject|suspend|reactivate`.
- Admin views show a **"via <consultancy>"** badge on interviews, the calendar, the dashboard and the candidates list. `/admin/interviews?consultancy_id=<id>` filters by consultancy.

## Reports

- Consultancy: `/consultancy/reports` with a date range: calls booked, completed, upcoming, cancelled, students with calls, Tool/Normal split, support time, and breakdowns by month, student, company and technology. The dashboard shows students, upcoming, calls this month, completed and cancelled.
- Admin: each consultancy's page shows the same totals plus calls per student for any date range.
