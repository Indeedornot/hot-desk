# Requirements coverage

The project criteria in `REGULAMIN ZAJĘĆ` take precedence over conflicting
attendance/exam rules in `REGULAMIN_ZTPAI`. Both documents' compatible technology
and project requirements are retained.

| Requirement | Implementation |
| --- | --- |
| Backend and REST API | Symfony controllers under `src/Controller` |
| Separate persistent database | PostgreSQL service in `compose.yaml` |
| ORM and migrations | Doctrine entities and versioned SQL migration |
| Five related models | User, Office, Desk, Booking, SeatAssignment, AuditEvent |
| Main-resource CRUD | Offices, desks, accounts; booking edits and cancellation preserve history |
| HTTP methods and status codes | GET/POST/PUT/DELETE, 200/201 and JSON errors |
| Layers and DTOs | Controllers, request DTOs, application services, repository, response mapper |
| Validation/global errors | Symfony Validator, API exception subscriber |
| Business rules | Date horizon, booking conflicts, exclusive assignments, attendance states, final-admin protection |
| Authentication and two roles | Symfony session authentication, USER/ADMIN authorization |
| Pagination and search | Users, reservations, events; person/equipment search and status/date/type filters |
| API documentation | `docs/openapi.yaml`, authenticated `/api/openapi.json` |
| At least five unit and three integration tests | 12 policy tests, 7 API integration tests |
| Configuration and secrets | Ignored local environment files; generated local passwords |
| Framework frontend using API | Angular standalone components and typed HTTP services |
| Frontend login and CRUD/error handling | Member and administrative screens with API validation feedback |
| Asynchronous mechanism | Messenger Doctrine queue with a separate consumer process |
| Meaningful extension | Booking emails through Mailer and Mailpit |
| Responsive design | Desktop desk map and mobile layout |
| README and ERD | `README.md`, `docs/data-model.md` |
| Git history | Local commits after formatting, checks and review |
| Separate backend/database containers in Compose | Standalone `compose.yaml` with backend, PostgreSQL, worker and Mailpit |
| Repository URL and final commit/tag | Final submission step |
| Demo video | Recording pending; outline in `docs/demo.md` |

Technical coverage does not guarantee a grade. The recorded demonstration remains part of the final delivery.
