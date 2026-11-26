# AI Course Information

## [1.1.0] - 2026-11-26

- **Bring your own AI key.** When the site has an LMS Labs BYOK licence and AI Connect (local_aiconfig 1.1.0 or later) sets Text to Moodle AI or an own key, Course Information is written on the site's own AI. The prompts for every framework and the HTML clean-up are a port of the LMS Labs service, generated from its source and checked word for word against it. No credits are used, the credit confirmation is replaced by a short "your own AI" confirmation, and no Client Portal usage is recorded. If the provider fails, the teacher sees the reason and nothing falls back to credits.

## [1.0.64] - 2026-11-24

- Added a test data generator, so the activity can be created by Moodle's automated tests and by demo and course-building tools. Tests cover creating, listing, deleting, backing up and restoring the activity.

## [1.0.63] - 2026-11-13

### Added
- Works with Client Portal 2.0.1 and later: each LMS Labs AI call made in a client's course, or by a client's people, is reported to the portal with the credits it used, so the site owner can see and invoice each client's usage. The service's own figure is used when its reply gives one; otherwise the plugin's published price. Calls that fail and polled jobs are never counted twice. Nothing changes on sites without Client Portal.

## [1.0.62] - 2026-11-12
- Slide Flow, Safety Hazards, Soft Skills, Branched Scenario and Anatomy are listed by their real names and times, not as "Other".

## [1.0.61] - 2026-11-10
- Course Builder requests carry a stable `idempotencyKey` and the `attempt` number, so a retry after a timeout returns the job already running instead of charging again.

## [1.0.60] - 2026-11-10

- A Course Builder generation that times out, or hits a busy or unavailable provider, is retried automatically instead of failing for good.
- Hidden activities (for example a failed Course Builder activity) are no longer described to learners.

## [1.0.59] - 2026-11-09
- Prepare Moodle activity structure during restore so complete Course Builder courses restore correctly.
- Keep generated course information grounded in the current unit's teacher-provided outcomes and actual course activities.

## [1.0.58] - 2026-11-07
- Send the current course unit's teacher-provided learning outcomes and teaching context to the native generator.
- Keep the established VET fields and generation path.

## 1.0.56
- Add native Course Builder generation through the existing LMS Labs route, with polling and storage in the module's own generated-content fields.
- Report the fixed 100-credit server tariff and avoid recharging when the generated content is already present.

## 1.0.55
- Add the AI Course Builder generator adapter.

## 1.0.54
- Add curriculum framework selection for VET, Australian Curriculum, Singapore MOE, university, workplace, and custom frameworks.
- Preserve the existing VET fields and use the existing VET generation path by default.
- Store non-VET details separately and use framework-specific course-information prompts.
- Add Moodle upgrade and backup/restore support for framework fields.
