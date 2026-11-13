# Project specification

## Scope

The app is a Nextcloud-first vocabulary trainer with private collections and a configurable study box system.

## User-facing language policy

- UI language: English and German
- Vocabulary languages: user-defined per collection
- storage format for vocabulary languages: BCP 47 tags such as `en`, `de`, `fr`, `es`, `ja`, `zh-Hans`, etc.

## Functional requirements

1. Collection management
   - create collection
   - rename collection
   - delete collection
   - assign source and target language when a collection is created

2. Entry management
   - add entry
   - edit entry
   - delete entry
   - keep entries scoped to the owning collection

3. Study mode
   - choose translation direction
   - choose study mode: multiple choice or typed answer
   - generate questions from entries in the collection
   - use a choice set from the same collection and target language for multiple-choice prompts
   - show answer feedback for a configurable delay, then advance automatically; allow pausing the countdown

4. Progress tracking
   - track progress per user, entry, and direction
   - correct answer: move one box up
   - wrong answer: reset to box 1
   - default box count: 5
   - box count should be configurable per collection
   - dashboard with progress and statistics

5. Privacy and ownership
   - collections are private by default
   - every collection operation is scoped to the current authenticated user
   - no collection is readable or editable by another user unless explicit shared access is added later
   - import/export of collections and/or sharing of collections within Nextcloud may be added later

## Non-functional requirements

- Nextcloud app with server-side validation and ownership checks
- versioned database migrations
- API structure compatible with a future Android client
- maintainable Vue frontend split from backend logic

## Architecture decisions

- Keep the Nextcloud app as the primary backend.
- Build a REST-like API with stable versioning to support Android later.
- Keep Android as a separate client, not an embedded part of the app.
- Treat progress and collection data as user-owned and server-validated.
