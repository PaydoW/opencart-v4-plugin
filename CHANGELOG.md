# Changelog

## 2.0.0

Version is fixed until release; intermediate test builds retain 2.0.0.

- Display synchronized method logos in the standard OpenCart payment picker without changing stored method names.
- Added card callback transaction.id handling alongside existing transaction identifier formats.
- Prevented unverified callback failure states and late notifications from downgrading paid orders.
- Requested IPN redelivery when invoice verification is pending or temporarily unavailable.
- Fixed disabled-method filtering for flat API method responses.
- Added PayDo Hosted Page as an optional native checkout choice when payment-method selection is enabled.
- Added synchronization and allowlist validation for direct PayDo payment methods.
- Migrated invoice creation, invoice verification, and method synchronization to PayDo API v3.
- Added protected POST-only admin synchronization with explicit API error handling.
- Added server-side validation for Hosted Page and numeric method identifiers.
- Added duplicate-invoice protection and reuse of the stored invoice for repeated checkout actions.
- Expanded invoice storage with payment mode, transaction identifier, amount, currency, remote statuses, retry count, and modification time.
- Added idempotent order-status updates and paid-invoice handling when transaction state is still pending.
- Added bounded invoice-verification retries and manual-review history after repeated temporary failures.
- Added non-destructive setting and database upgrade logic for existing installations.
- Added English, Russian, and Ukrainian localization updates and behavior tests.

## 1.2

- Added initial synchronization and checkout support for separate PayDo payment methods.

## 1.1

- Hardened invoice callback validation and stored the PayDo invoice relation per order.
