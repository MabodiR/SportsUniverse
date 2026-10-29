---
name: sportuniverse-delivery
description: Deliver SportsUniverse product changes consistently across the Laravel API, Vue web app, Expo React Native mobile app, tests, and GitHub production pipelines. Use for features, fixes, API changes, releases, or platform parity work in this repository.
---

# SportsUniverse delivery

Treat web and mobile as clients of one Laravel domain and API. Start by classifying a change as shared, cross-platform UI, or intentionally platform-specific.

## Delivery contract

For a shared feature:

1. Put business rules, validation, permissions, persistence, and counters in Laravel services/controllers rather than duplicating them in clients.
2. Provide a versioned `/api/v1` contract for mobile. Web may use Inertia, but equivalent user-visible data and actions must remain available to mobile.
3. Implement the experience in `resources/js/` and `mobile-app/`. Preserve native mobile interaction patterns rather than copying browser markup.
4. Keep the Expo implementation portable across iOS and Android. Avoid Google-only dependencies for core functionality; evaluate Huawei compatibility when adding notifications, maps, identity, payments, or device services.
5. Add backend feature tests and run web build plus mobile TypeScript checks. Add client tests when the repository has an applicable test harness.
6. Report platform coverage, verification, database/migration impact, and deployment status separately. Local completion is not production deployment.

If a change is genuinely web-only or mobile-only, document the reason in the PR and use the appropriate parity override marker described in `docs/DELIVERY_FRAMEWORK.md`.

## Release rules

- Feature branch or local work → pull request → CI → merge to `main`.
- A successful `CI` run on `main` triggers the xneelo web deployment.
- Mobile source changes trigger the Expo production update workflow after CI.
- JavaScript/assets can ship with EAS Update when runtime-compatible. Native configuration or dependency changes require new iOS and Android binaries.
- Huawei uses the Android artifact but requires AppGallery signing, testing, and submission outside EAS Submit.
- Never bypass production environment approvals, secrets, backups, migrations, or health checks.

Read [`docs/DELIVERY_FRAMEWORK.md`](../../docs/DELIVERY_FRAMEWORK.md) when planning a release, changing CI/CD, making a native dependency/configuration change, or handling a parity exception.
