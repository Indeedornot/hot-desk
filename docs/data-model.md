# Data model

```mermaid
erDiagram
    APP_USER ||--o{ BOOKING : reserves
    OFFICE ||--o{ DESK : contains
    DESK ||--o{ BOOKING : booked_for
    APP_USER ||--o| SEAT_ASSIGNMENT : owns
    DESK ||--o| SEAT_ASSIGNMENT : assigned
    APP_USER ||--o{ SEAT_ASSIGNMENT : assigned_by
    APP_USER ||--o{ AUDIT_EVENT : performs

    APP_USER {
        int id PK
        string email UK
        string name
        string password
        string role
        boolean active
    }
    OFFICE {
        int id PK
        string name
        string address
        string timezone
        string opening_time
        string closing_time
        boolean active
    }
    DESK {
        int id PK
        int office_id FK
        string label
        string equipment
        int x
        int y
        boolean active
    }
    BOOKING {
        int id PK
        int user_id FK
        int desk_id FK
        date date
        timestamptz starts_at
        timestamptz ends_at
        string status
        timestamptz created_at
        timestamptz checked_in_at
        timestamptz checked_out_at
        timestamptz cancelled_at
    }
    SEAT_ASSIGNMENT {
        int id PK
        int user_id FK,UK
        int desk_id FK,UK
        int assigned_by_id FK
        timestamptz created_at
    }
    AUDIT_EVENT {
        int id PK
        int actor_id FK
        string event_name
        timestamptz event_date
        string event_type
        jsonb event_data
    }
```

`desk(office_id, label)` is unique. Partial indexes on `booking(desk_id, date)`
and `booking(user_id, date)` are unique while status is `BOOKED` or `CHECKED_IN`.
The reserved date is a calendar date in the office timezone. The start/end
instants capture that day's office hours for overlap checks and history.

`messenger_messages` stores pending/retry/failed notifications.
`notification_delivery` records event IDs already sent by the worker. These are
infrastructure tables, separate from the six core business entities.
