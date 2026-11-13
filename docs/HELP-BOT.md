# BluJay Help assistant

A floating **Help** button sits on every page: sign-in, register and forgot-password pages, plus the candidate, consultancy and admin areas.

## How it answers
- **Ready questions** (buttons under the chat) answer instantly from `App\Services\HelpBotService::faqs()`. Each audience (guest, candidate, consultancy, admin) gets its own list. Each answer can carry page buttons ("Forgot password →").
- **Typed questions** go to `POST /help/ask`. The server sends Gemini these facts, plus live booking notice, cutoff and business hours from Settings. Gemini replies in the user's language (English, Telugu, or Telugu in English letters).
  - Links are limited to pages that visitor can open.
  - Admin knowledge is only sent for admins.
- If Gemini is unavailable, the server falls back to keyword matching against the ready answers.

## Safety and limits
- Phone numbers, OTPs and other long digit runs are replaced with `[number]` before anything is sent to Gemini. The bot never asks for passwords or OTPs.
- Rate limits: 30 questions per session per hour, and 120 per IP per hour.
- Gemini free tier may use inputs to improve Google products. Use a paid key in production.

## Editing answers
Update the `faqs()` arrays in `app/Services/HelpBotService.php`. Typed answers use the same text, so one edit updates both the ready answers and the AI's knowledge.

## Settings (`.env`)
- `GEMINI_API_KEY` (required for typed questions).
- `HELP_BOT_MODEL` (optional; defaults to `GEMINI_FALLBACK_MODEL`, the fast lite model).
- `HELP_BOT_THINKING` (default `minimal`).
