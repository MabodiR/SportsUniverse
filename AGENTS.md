# SportsUniverse delivery rules

For any product feature, user-facing change, API contract change, authentication change, promotion, notification, navigation change, or release, read and follow [`agent-skills/sportuniverse-delivery/SKILL.md`](agent-skills/sportuniverse-delivery/SKILL.md).

The application has three coordinated surfaces:

- Laravel API and shared domain logic in `app/`, `routes/`, and `database/`.
- Web UI in `resources/js/`.
- Expo React Native mobile UI in `mobile-app/`, targeting iOS and Android from one codebase. Huawei uses the Android build, with AppGallery handled as a distribution channel.

Do not call a cross-platform feature complete until its web, mobile, API, tests, and release impact have been evaluated. Platform-specific work must be explicitly identified as such.

Never deploy directly from an unverified working tree. Production releases originate from a reviewed commit on `main`, pass CI, and run through the GitHub deployment workflows.
