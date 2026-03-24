---
title: Documentation Update Plan — Auth & Session (March 2025)
tags:
  - docs
  - auth
  - session
  - signup
  - login
---

# Documentation Update Plan — Auth & Session

Plan to align documentation with the current implementation and decisions from the March 2025 auth/signup/login discussion.

**Related:** [[LOGIN_FLOW]], [[SIGNUP_FLOW]], [[MULTI_TENANT_API_SPEC]], [[ONBOARDING_IMPLEMENTATION_PLAN]], [[MULTI_TENANT_FEATURES]]

---

## Implementation status

| # | Task | Status |
|---|------|--------|
| 1 | Add platform auth endpoints to API spec | Done |
| 2 | Fix GET /api/session description in onboarding plan | Done |
| 3 | Add logged-in-on-signup edge case (proposed) | Done |
| 4 | Clarify workspace subdomain signup vs plan gating | Done |
| 5 | Document abandoned signup cleanup (proposed, future) | Done |
| 6 | Document onboarding fetch failure gap | Done |
| 7 | Add API simplification rationale | Done |

---

## Key decisions documented

- **GET /api/session** returns `{ user, workspace }` — merged from former `/api/my-workspace`
- **Platform auth** — `/api/session`, `/api/check-email` in PUBLIC_PATHS, no tenant required
- **Logged-in on /signup** — proposed: show "sign out first" error (product decision pending)
- **Abandoned signup cleanup** — future CRON for users with no `member` row
- **Onboarding fetch failure** — known gap, no try/catch
