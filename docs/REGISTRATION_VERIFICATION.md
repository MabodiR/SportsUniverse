# Registration verification and approval

Web and mobile password registration now share a database-backed workflow. Valid submitted registrations are stored before attempting delivery; this does not capture abandoned forms or invalid submissions. Pending data is encrypted at rest, passwords are hashed before storage, and the pending payload is erased when the account is created. Keep the Laravel application key stable to preserve decryptability.

Verification defaults to disabled. New accounts can register immediately without sending a verification message, and their email remains unverified. Super admins and system admins can enable verification in `/management/settings` → Registration, `/admin/registrations`, or the native dashboard's registration controls. Admins can review pending submissions across paginated lists and approve a member without falsely marking their email as verified. Approval records the actor and timestamp. Disabling verification does not automatically approve existing pending submissions.

Email verification continues to use signed links, valid for 60 minutes. WhatsApp uses six-digit codes valid for 10 minutes, with five attempts per code, throttled resend and verification endpoints, and single-use completion. A successful WhatsApp code verifies control of the phone for registration; it does not mark the email as verified. Failed mail/provider requests leave the submission pending with a failed delivery status. A sent status means the provider accepted the request, not that the message reached the recipient. Actual mailbox placement and WhatsApp delivery need provider-side checks.

## WhatsApp configuration

The implemented adapter targets Meta Cloud API authentication templates with a copy-code button. A phone number alone cannot activate this service. Configure these server secrets/configuration values before enabling WhatsApp:

- `WHATSAPP_ACCESS_TOKEN`: a credential permitted to send WhatsApp Business messages.
- `WHATSAPP_PHONE_NUMBER_ID`: Meta's sender identifier, not the telephone number.
- `WHATSAPP_AUTH_TEMPLATE`: approved authentication template name.
- `WHATSAPP_AUTH_LANGUAGE`: approved template language (default `en_US`).
- `WHATSAPP_GRAPH_VERSION`: supported Graph API version (default `v24.0`).

See [Meta's authentication template example](https://www.postman.com/meta/whatsapp-business-platform/request/6vkv46u/create-authentication-template-w-otp-copy-code-button). WhatsApp is disabled until configuration is available. Users choosing WhatsApp must supply their recipient number in international format, for example `+27768120088`. No real messages were sent during automated tests.

## API

`POST /api/v1/auth/register` accepts optional `verification_channel` (`email` or `whatsapp`). With verification disabled it returns the existing 201 account/token response. With verification enabled it returns 202 with `pending_registration: {id, channel}` and no access token. Clients must not treat 202 as authenticated.

`POST /api/v1/auth/register/resend` accepts `{id}`. `POST /api/v1/auth/register/verify` accepts `{id, otp}` for WhatsApp; after verification, sign in normally. Email links complete registration through the signed web route; native members return to the app to sign in.

Authenticated admin endpoints: `GET /api/v1/admin/system-settings` includes registration policy and pending submissions; `PATCH /api/v1/admin/system-settings/registration` accepts `verification_required` and `whatsapp_enabled` for system/super admins; `POST /api/v1/admin/registrations/{id}/approve` allows admin, system admin, and super admin.

## Release impact

Run the new registration-tables migration through the reviewed main/CI/GitHub deployment workflow. Review the default-off verification policy before release. Previously expired cache-only registrations cannot be recovered by this change; still-live legacy cache entries are imported when their signed link or resend action is used. There is no bulk historical backfill. Existing users remain unchanged. Rollback must account for pending data before dropping the new tables.

Web/API deployment and a runtime-compatible mobile EAS Update are needed. No native dependency/configuration changes or Google services were introduced; the same Expo code targets iOS, Android and Huawei Android. Live email/WhatsApp delivery and native device UX remain release acceptance checks. No production deployment is performed by this implementation.
