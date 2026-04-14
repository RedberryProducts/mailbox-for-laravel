# Changelog

All notable changes to `mailbox-for-laravel` will be documented in this file.

## [Unreleased]

### Added
- Added Testing Assertions API (`src/Testing/`) for verifying captured emails in test suites
  - `InteractsWithMailbox` trait — auto-clears mailbox between tests, provides `$this->mailbox()`
  - `MailboxAssertions` — collection-level: `assertSent()`, `assertNotSent()`, `assertNothingSent()`, `assertSentCount()`, `assertSentTo()`, `assertNotSentTo()`, `sent()`, `firstSent()`
  - `PendingMailboxMessageAssertion` — per-message fluent: `assertHasSubject()`, `assertSeeInHtml()`, `assertHasTo()`, `assertHasAttachment()`, and more
  - Facade support: `Mailbox::assertSent()`, `Mailbox::assertSentTo()`, etc.
  - Works with both Pest and PHPUnit
- Added "Clear Inbox" functionality to delete all messages via DELETE /mailbox/messages
- Added "Delete Single Message" functionality to delete individual messages via DELETE /mailbox/messages/{id}
- Added AlertDialog component suite using radix-vue for confirmation dialogs
- Added trash icon buttons with confirmation dialogs to prevent accidental deletion

### Changed
- Changed ClearMailboxController route from POST /mailbox/clear to DELETE /mailbox/messages for RESTful compliance
- Updated route names: `mailbox.clear` is now `mailbox.messages.clear`

### Breaking Changes
- The clear inbox endpoint has changed from POST /mailbox/clear to DELETE /mailbox/messages
- Route name changed from `mailbox.clear` to `mailbox.messages.clear`
